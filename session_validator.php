<?php
/**
 * Unified Session Validation System
 * Ensures all pages get consistent user data
 */

class SessionValidator {
    private static $db = null;
    
    /**
     * Validate and get current user data with database verification
     */
    public static function getCurrentUser($force_refresh = false) {
        global $db;
        
        // Ensure database connection exists
        if (!$db) {
            require_once 'database_helper.php';
            $db = new DatabaseHelper();
        }
        self::$db = $db;
        
        // Start session if not already started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return null;
        }
        
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            return null;
        }
        
        try {
            // Always verify against database to ensure consistency
            $stmt = self::$db->getPDO()->prepare("
                SELECT id, email, display_name, status, last_login 
                FROM users 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$user_id]);
            $db_user = $stmt->fetch();
            
            if (!$db_user) {
                // User not found or inactive - clear session
                self::clearSession();
                return null;
            }
            
            // Get session data
            $session_email = $_SESSION['user_email'] ?? '';
            $session_name = $_SESSION['user_name'] ?? '';
            
            // Check for session-database mismatch
            $needs_refresh = false;
            
            if ($db_user['email'] !== $session_email) {
                error_log("SessionValidator: Email mismatch - Session: '$session_email', DB: '{$db_user['email']}'");
                $needs_refresh = true;
            }
            
            if ($db_user['display_name'] !== $session_name) {
                error_log("SessionValidator: Name mismatch - Session: '$session_name', DB: '{$db_user['display_name']}'");
                $needs_refresh = true;
            }
            
            // Refresh session data if there's a mismatch or forced refresh
            if ($needs_refresh || $force_refresh) {
                $_SESSION['user_email'] = $db_user['email'];
                $_SESSION['user_name'] = $db_user['display_name'];
                $_SESSION['last_validated'] = time();
                
                error_log("SessionValidator: Refreshed session data for user {$db_user['id']} - {$db_user['email']}");
            }
            
            return [
                'id' => $db_user['id'],
                'email' => $db_user['email'],
                'name' => $db_user['display_name'],
                'status' => $db_user['status'],
                'last_login' => $db_user['last_login'],
                'session_refreshed' => $needs_refresh || $force_refresh
            ];
            
        } catch (Exception $e) {
            error_log("SessionValidator error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Require authentication - redirect to login if not authenticated
     */
    public static function requireAuth($redirect_to_login = true) {
        $user = self::getCurrentUser();
        
        if (!$user) {
            if ($redirect_to_login) {
                header('Location: login.php?error=session_expired');
                exit();
            }
            return null;
        }
        
        return $user;
    }
    
    /**
     * Clear session completely
     */
    public static function clearSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            
            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
    }
    
    /**
     * Get user data in the format expected by existing pages
     */
    public static function getLegacyUserVars() {
        $user = self::getCurrentUser();
        
        if (!$user) {
            return [
                'user_id' => null,
                'user_email' => '',
                'user_name' => 'Unknown User'
            ];
        }
        
        return [
            'user_id' => $user['id'],
            'user_email' => $user['email'], 
            'user_name' => $user['name']
        ];
    }
    
    /**
     * Debug current session state
     */
    public static function debugSession() {
        $user = self::getCurrentUser();
        
        return [
            'session_id' => session_id(),
            'session_active' => session_status() === PHP_SESSION_ACTIVE,
            'raw_session' => $_SESSION,
            'validated_user' => $user,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
?>