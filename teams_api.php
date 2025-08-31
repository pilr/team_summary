<?php
require_once 'teams_config.php';

class TeamsAPIHelper {
    private $accessToken;
    private $cacheDir;
    
    public function __construct() {
        $this->cacheDir = TEAMS_CACHE_DIR;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get access token using client credentials flow (for app-only access)
     */
    public function getAccessToken() {
        if ($this->accessToken && $this->isTokenValid()) {
            return $this->accessToken;
        }
        
        // Check cache first
        $cacheFile = $this->cacheDir . '/access_token.json';
        if (file_exists($cacheFile)) {
            $tokenData = json_decode(file_get_contents($cacheFile), true);
            if ($tokenData && time() < $tokenData['expires_at']) {
                $this->accessToken = $tokenData['access_token'];
                return $this->accessToken;
            }
        }
        
        // Request new token
        $tokenData = [
            'client_id' => TEAMS_CLIENT_ID,
            'client_secret' => TEAMS_CLIENT_SECRET,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        ];
        
        $ch = curl_init(TEAMS_TOKEN_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $tokenResponse = json_decode($response, true);
            if ($tokenResponse && isset($tokenResponse['access_token'])) {
                $this->accessToken = $tokenResponse['access_token'];
                
                // Cache the token
                $cacheData = [
                    'access_token' => $this->accessToken,
                    'expires_at' => time() + ($tokenResponse['expires_in'] - 60) // 1 minute buffer
                ];
                file_put_contents($cacheFile, json_encode($cacheData));
                
                return $this->accessToken;
            }
        }
        
        error_log("Failed to get Teams access token. HTTP Code: $httpCode, Response: $response");
        return false;
    }
    
    /**
     * Get all teams the app has access to (real-time, no cache)
     */
    public function getTeams() {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }
        
        $ch = curl_init(TEAMS_GRAPH_URL . '/teams');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['value'])) {
                return $data['value'];
            }
        }
        
        error_log("Failed to get Teams. HTTP Code: $httpCode, Response: $response");
        return [];
    }
    
    /**
     * Get channels for a specific team (real-time, no cache)
     */
    public function getTeamChannels($teamId) {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }
        
        $ch = curl_init(TEAMS_GRAPH_URL . "/teams/{$teamId}/channels");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['value'])) {
                return $data['value'];
            }
        }
        
        error_log("Failed to get Team channels. HTTP Code: $httpCode, Response: $response");
        return [];
    }
    
    /**
     * Get all channels across all teams (no cache, real-time)
     */
    public function getAllChannels($useCache = false) {
        $teams = $this->getTeams();
        $allChannels = [];
        
        foreach ($teams as $team) {
            $channels = $this->getTeamChannels($team['id']);
            foreach ($channels as $channel) {
                // Get member count for this channel
                $memberCount = $this->getChannelMemberCount($team['id'], $channel['id']);
                
                $allChannels[] = [
                    'id' => $channel['id'],
                    'displayName' => $channel['displayName'],
                    'description' => $channel['description'] ?? '',
                    'teamId' => $team['id'],
                    'teamName' => $team['displayName'],
                    'memberCount' => $memberCount,
                    'webUrl' => $channel['webUrl'] ?? '',
                    'membershipType' => $channel['membershipType'] ?? 'standard'
                ];
            }
        }
        
        return !empty($allChannels) ? $allChannels : [];
    }
    
    /**
     * Get channel members
     */
    public function getChannelMembers($teamId, $channelId) {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }
        
        // For most channels, members are the team members
        $ch = curl_init(TEAMS_GRAPH_URL . "/teams/{$teamId}/members");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['value'])) {
                return $data['value'];
            }
        }
        
        error_log("Failed to get channel members. HTTP Code: $httpCode, Response: $response");
        return [];
    }
    
    /**
     * Get channel member count
     */
    public function getChannelMemberCount($teamId, $channelId) {
        $members = $this->getChannelMembers($teamId, $channelId);
        return count($members);
    }
    
    /**
     * Get messages from a specific channel
     */
    public function getChannelMessages($teamId, $channelId, $limit = 50) {
        $token = $this->getAccessToken();
        if (!$token) {
            return $this->getFallbackMessages();
        }
        
        $ch = curl_init(TEAMS_GRAPH_URL . "/teams/{$teamId}/channels/{$channelId}/messages?\$top={$limit}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['value'])) {
                return $data['value'];
            }
        }
        
        error_log("Failed to get channel messages. HTTP Code: $httpCode, Response: $response");
        return $this->getFallbackMessages();
    }
    
    /**
     * Check if current access token is valid
     */
    private function isTokenValid() {
        $cacheFile = $this->cacheDir . '/access_token.json';
        if (file_exists($cacheFile)) {
            $tokenData = json_decode(file_get_contents($cacheFile), true);
            return $tokenData && time() < $tokenData['expires_at'];
        }
        return false;
    }
    
    /**
     * Fallback teams data when API is unavailable
     */
    private function getFallbackTeams() {
        return [
            [
                'id' => 'team-1',
                'displayName' => 'Development Team',
                'description' => 'Main development team'
            ],
            [
                'id' => 'team-2', 
                'displayName' => 'Marketing Team',
                'description' => 'Marketing and communications'
            ],
            [
                'id' => 'team-3',
                'displayName' => 'Design Team', 
                'description' => 'Product design and UX'
            ]
        ];
    }
    
    /**
     * Fallback channels data when API is unavailable
     */
    private function getFallbackChannels() {
        return [
            [
                'id' => 'general',
                'displayName' => 'General',
                'description' => 'General discussions',
                'teamId' => 'team-1',
                'teamName' => 'Development Team'
            ],
            [
                'id' => 'development', 
                'displayName' => 'Development',
                'description' => 'Development discussions',
                'teamId' => 'team-1',
                'teamName' => 'Development Team'
            ],
            [
                'id' => 'marketing',
                'displayName' => 'Marketing',
                'description' => 'Marketing discussions', 
                'teamId' => 'team-2',
                'teamName' => 'Marketing Team'
            ],
            [
                'id' => 'design',
                'displayName' => 'Design',
                'description' => 'Design discussions',
                'teamId' => 'team-3', 
                'teamName' => 'Design Team'
            ]
        ];
    }
    
    /**
     * Fallback messages data when API is unavailable
     */
    private function getFallbackMessages() {
        return [
            [
                'id' => '1',
                'createdDateTime' => date('c', strtotime('-2 hours')),
                'body' => [
                    'content' => 'Production deployment successful!'
                ],
                'from' => [
                    'user' => [
                        'displayName' => 'DevOps Team'
                    ]
                ]
            ],
            [
                'id' => '2',
                'createdDateTime' => date('c', strtotime('-1 hour')),
                'body' => [
                    'content' => 'Great work on the new features!'
                ],
                'from' => [
                    'user' => [
                        'displayName' => 'Project Manager'
                    ]
                ]
            ]
        ];
    }
}
?>