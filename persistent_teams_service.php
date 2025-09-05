<?php
/**
 * Persistent Teams Service
 * Maintains Teams connections independently of user sessions
 */
require_once 'teams_config.php';
require_once 'database_helper.php';
require_once 'error_logger.php';

class PersistentTeamsService {
    private $db;
    
    public function __construct() {
        $this->db = new DatabaseHelper();
    }
    
    /**
     * Check and refresh all active Teams connections
     */
    public function maintainConnections() {
        try {
            $pdo = $this->db->getPDO();
            
            // Get all users with Teams tokens that need refresh or are about to expire
            $stmt = $pdo->prepare("
                SELECT user_id, access_token, refresh_token, expires_at 
                FROM oauth_tokens 
                WHERE provider = 'microsoft' 
                AND refresh_token IS NOT NULL
                AND (expires_at <= DATE_ADD(NOW(), INTERVAL 30 MINUTE) OR expires_at <= NOW())
            ");
            $stmt->execute();
            $tokens = $stmt->fetchAll();
            
            foreach ($tokens as $token) {
                $this->refreshUserToken($token['user_id'], $token['refresh_token']);
            }
            
            error_log("Maintained " . count($tokens) . " Teams connections");
            return true;
        } catch (Exception $e) {
            error_log("Error maintaining Teams connections: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Refresh a specific user's token
     */
    public function refreshUserToken($user_id, $refresh_token) {
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
                error_log("Token refresh failed for user $user_id: HTTP $http_code, Error: $curl_error");
                return false;
            }

            $token_response = json_decode($response, true);
            if (!$token_response || !isset($token_response['access_token'])) {
                error_log("Invalid refresh token response for user $user_id");
                return false;
            }

            // Update token in database
            $expires_in = $token_response['expires_in'] ?? 3600;
            $expires_at = new DateTime('now', new DateTimeZone('UTC'));
            $expires_at->add(new DateInterval("PT{$expires_in}S"));

            $success = $this->db->updateOAuthToken(
                $user_id,
                'microsoft',
                $token_response['access_token'],
                $token_response['refresh_token'] ?? $refresh_token,
                $expires_at->format('Y-m-d H:i:s')
            );

            if ($success) {
                error_log("Token refreshed successfully for user $user_id");
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Token refresh exception for user $user_id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get Teams connection status for a user (without requiring session)
     */
    public function getUserTeamsStatus($user_id) {
        try {
            $token_info = $this->db->getOAuthToken($user_id, 'microsoft');
            
            if (!$token_info) {
                return ['status' => 'disconnected'];
            }
            
            $expires_at = new DateTime($token_info['expires_at']);
            $now = new DateTime();
            
            if ($now >= $expires_at) {
                // Try to refresh if refresh token exists
                if (!empty($token_info['refresh_token'])) {
                    $refreshed = $this->refreshUserToken($user_id, $token_info['refresh_token']);
                    if ($refreshed) {
                        return ['status' => 'connected'];
                    }
                }
                return ['status' => 'expired'];
            }
            
            return ['status' => 'connected', 'expires_at' => $token_info['expires_at']];
        } catch (Exception $e) {
            error_log("Error checking user Teams status: " . $e->getMessage());
            return ['status' => 'error'];
        }
    }
    
    /**
     * Test Teams API access for a user
     */
    public function testUserTeamsAccess($user_id) {
        try {
            $token_info = $this->db->getOAuthToken($user_id, 'microsoft');
            
            if (!$token_info) {
                return false;
            }
            
            // Ensure token is fresh
            $status = $this->getUserTeamsStatus($user_id);
            if ($status['status'] !== 'connected') {
                return false;
            }
            
            // Get fresh token info after potential refresh
            $token_info = $this->db->getOAuthToken($user_id, 'microsoft');
            
            $ch = curl_init('https://graph.microsoft.com/v1.0/me');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token_info['access_token'],
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $http_code === 200;
        } catch (Exception $e) {
            error_log("Error testing user Teams access: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize global service instance
$persistentTeamsService = new PersistentTeamsService();
?>