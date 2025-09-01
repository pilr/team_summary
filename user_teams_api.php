<?php
require_once 'teams_config.php';
require_once 'database_helper.php';

/**
 * User-specific Teams API Helper that uses OAuth tokens stored in database
 */
class UserTeamsAPIHelper {
    private $user_id;
    private $db;
    private $access_token;
    
    public function __construct($user_id) {
        $this->user_id = $user_id;
        $this->db = new DatabaseHelper();
    }
    
    /**
     * Get valid access token for the user
     */
    private function getValidAccessToken() {
        if ($this->access_token && $this->isCurrentTokenValid()) {
            return $this->access_token;
        }
        
        error_log("UserTeamsAPI: Getting token for user " . $this->user_id);
        $token_info = $this->db->getOAuthToken($this->user_id, 'microsoft');
        if (!$token_info) {
            error_log("UserTeamsAPI: No token found for user " . $this->user_id);
            return false;
        }
        
        error_log("UserTeamsAPI: Token found, expires at: " . $token_info['expires_at']);
        
        // Check if token is expired
        $expires_at = new DateTime($token_info['expires_at']);
        $now = new DateTime();
        
        if ($now >= $expires_at) {
            error_log("UserTeamsAPI: Token expired, attempting refresh");
            // Try to refresh token
            if (!empty($token_info['refresh_token'])) {
                $refreshed = $this->refreshAccessToken($token_info['refresh_token']);
                if ($refreshed) {
                    $token_info = $this->db->getOAuthToken($this->user_id, 'microsoft');
                    $this->access_token = $token_info['access_token'];
                    error_log("UserTeamsAPI: Token refreshed successfully");
                    return $this->access_token;
                }
            }
            error_log("UserTeamsAPI: Token refresh failed");
            return false;
        }
        
        $this->access_token = $token_info['access_token'];
        error_log("UserTeamsAPI: Using valid token");
        return $this->access_token;
    }
    
    /**
     * Check if current cached token is still valid
     */
    private function isCurrentTokenValid() {
        if (!$this->access_token) {
            return false;
        }
        
        return $this->db->isTokenValid($this->user_id, 'microsoft');
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refreshAccessToken($refresh_token) {
        try {
            $token_data = [
                'client_id' => TEAMS_CLIENT_ID,
                'client_secret' => TEAMS_CLIENT_SECRET,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            ];

            $ch = curl_init(TEAMS_TOKEN_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error || $http_code !== 200) {
                error_log("Token refresh failed: HTTP $http_code, Error: $curl_error");
                return false;
            }

            $token_response = json_decode($response, true);
            if (!$token_response || !isset($token_response['access_token'])) {
                error_log("Invalid refresh token response");
                return false;
            }

            // Update token in database - use UTC
            $expires_in = $token_response['expires_in'] ?? 3600;
            $expires_at = new DateTime('now', new DateTimeZone('UTC'));
            $expires_at->add(new DateInterval("PT{$expires_in}S"));

            return $this->db->updateOAuthToken(
                $this->user_id,
                'microsoft',
                $token_response['access_token'],
                $token_response['refresh_token'] ?? $refresh_token,
                $expires_at->format('Y-m-d H:i:s')
            );

        } catch (Exception $e) {
            error_log("Token refresh exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Make authenticated Graph API request
     */
    private function makeGraphAPIRequest($endpoint, $method = 'GET', $data = null) {
        $token = $this->getValidAccessToken();
        if (!$token) {
            return false;
        }
        
        $url = TEAMS_GRAPH_URL . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("Graph API request failed: $curl_error");
            return false;
        }
        
        if ($http_code !== 200) {
            error_log("Graph API request failed: HTTP $http_code, Response: $response");
            return false;
        }
        
        $data = json_decode($response, true);
        return $data;
    }
    
    /**
     * Get user's joined teams
     */
    public function getUserTeams() {
        $result = $this->makeGraphAPIRequest('/me/joinedTeams');
        return $result ? ($result['value'] ?? []) : [];
    }
    
    /**
     * Get channels for a specific team
     */
    public function getTeamChannels($team_id) {
        $result = $this->makeGraphAPIRequest("/teams/{$team_id}/channels");
        return $result ? ($result['value'] ?? []) : [];
    }
    
    /**
     * Get all channels across user's teams
     */
    public function getAllChannels() {
        $teams = $this->getUserTeams();
        $all_channels = [];
        
        error_log("UserTeamsAPI: Found " . count($teams) . " teams for user " . $this->user_id);
        
        foreach ($teams as $team) {
            $channels = $this->getTeamChannels($team['id']);
            error_log("UserTeamsAPI: Team " . $team['displayName'] . " has " . count($channels) . " channels");
            
            foreach ($channels as $channel) {
                $all_channels[] = [
                    'id' => $channel['id'],
                    'displayName' => $channel['displayName'],
                    'description' => $channel['description'] ?? '',
                    'teamId' => $team['id'],
                    'teamName' => $team['displayName'],
                    'memberCount' => $this->getChannelMemberCount($team['id'], $channel['id']),
                    'webUrl' => $channel['webUrl'] ?? '',
                    'membershipType' => $channel['membershipType'] ?? 'standard'
                ];
            }
        }
        
        error_log("UserTeamsAPI: Total channels found: " . count($all_channels));
        return $all_channels;
    }
    
    /**
     * Get channel member count
     */
    public function getChannelMemberCount($team_id, $channel_id) {
        $members = $this->getChannelMembers($team_id, $channel_id);
        return count($members);
    }
    
    /**
     * Get messages from a specific channel
     */
    public function getChannelMessages($team_id, $channel_id, $limit = 50) {
        $result = $this->makeGraphAPIRequest("/teams/{$team_id}/channels/{$channel_id}/messages?\$top={$limit}");
        return $result ? ($result['value'] ?? []) : [];
    }
    
    /**
     * Get channel members
     */
    public function getChannelMembers($team_id, $channel_id) {
        // For most channels, members are the team members
        $result = $this->makeGraphAPIRequest("/teams/{$team_id}/members");
        return $result ? ($result['value'] ?? []) : [];
    }
    
    /**
     * Get user profile information
     */
    public function getUserProfile() {
        return $this->makeGraphAPIRequest('/me');
    }
    
    /**
     * Test if the user has a valid connection
     */
    public function testConnection() {
        $profile = $this->getUserProfile();
        return $profile !== false;
    }
    
    /**
     * Check if user has connected their Microsoft account
     */
    public function isConnected() {
        // First check if any token exists (even if expired)
        $token_info = $this->db->getOAuthToken($this->user_id, 'microsoft');
        if (!$token_info) {
            error_log("UserTeamsAPI: No token found for user " . $this->user_id);
            return false;
        }
        
        // Check if token is valid (not expired)
        $isValid = $this->db->isTokenValid($this->user_id, 'microsoft');
        error_log("UserTeamsAPI: isConnected for user " . $this->user_id . " = " . ($isValid ? 'true' : 'false'));
        
        // If token is expired, try to refresh it
        if (!$isValid && !empty($token_info['refresh_token'])) {
            error_log("UserTeamsAPI: Token expired, attempting refresh in isConnected()");
            $refreshed = $this->refreshAccessToken($token_info['refresh_token']);
            if ($refreshed) {
                error_log("UserTeamsAPI: Token refreshed successfully in isConnected()");
                return true;
            }
        }
        
        return $isValid;
    }
    
    /**
     * Get connection status with details
     */
    public function getConnectionStatus() {
        if (!$this->isConnected()) {
            return ['status' => 'disconnected', 'message' => 'Not connected to Microsoft'];
        }
        
        if ($this->testConnection()) {
            return ['status' => 'connected', 'message' => 'Connected and working'];
        }
        
        return ['status' => 'error', 'message' => 'Connected but API calls failing'];
    }
}
?>