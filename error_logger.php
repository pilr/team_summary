<?php
/**
 * Comprehensive Error Logger for Microsoft Teams Integration
 * Logs all errors to errors.txt with detailed context
 */

class ErrorLogger {
    private static $logFile = __DIR__ . '/errors.txt';
    
    /**
     * Log error to errors.txt
     */
    public static function log($message, $context = [], $level = 'ERROR') {
        $timestamp = date('Y-m-d H:i:s');
        $sessionId = session_id() ?: 'no-session';
        $userId = $_SESSION['user_id'] ?? 'no-user';
        
        // Build log entry
        $logEntry = sprintf(
            "[%s] [%s] [User: %s] [Session: %s] %s\n",
            $timestamp,
            $level,
            $userId,
            $sessionId,
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $logEntry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
        
        $logEntry .= "---\n";
        
        // Write to file with error handling
        try {
            file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
            // Also log to PHP error log for redundancy
            error_log($message);
        } catch (Exception $e) {
            error_log("Failed to write to errors.txt: " . $e->getMessage());
        }
    }
    
    /**
     * Log Microsoft Teams specific errors
     */
    public static function logTeamsError($operation, $error, $context = []) {
        $message = "Microsoft Teams $operation Error: $error";
        self::log($message, array_merge($context, ['operation' => $operation]), 'TEAMS_ERROR');
    }
    
    /**
     * Log OAuth errors
     */
    public static function logOAuthError($step, $error, $context = []) {
        $message = "OAuth $step Error: $error";
        self::log($message, array_merge($context, ['oauth_step' => $step]), 'OAUTH_ERROR');
    }
    
    /**
     * Log database errors
     */
    public static function logDatabaseError($operation, $error, $context = []) {
        $message = "Database $operation Error: $error";
        self::log($message, array_merge($context, ['db_operation' => $operation]), 'DB_ERROR');
    }
    
    /**
     * Log API errors
     */
    public static function logAPIError($api, $endpoint, $httpCode, $error, $context = []) {
        $message = "$api API Error: $endpoint returned HTTP $httpCode - $error";
        self::log($message, array_merge($context, [
            'api' => $api,
            'endpoint' => $endpoint,
            'http_code' => $httpCode
        ]), 'API_ERROR');
    }
    
    /**
     * Log success events (for debugging)
     */
    public static function logSuccess($operation, $context = []) {
        $message = "$operation completed successfully";
        self::log($message, $context, 'SUCCESS');
    }
    
    /**
     * Clear error log (for maintenance)
     */
    public static function clearLog() {
        try {
            file_put_contents(self::$logFile, '');
            self::log("Error log cleared", [], 'INFO');
        } catch (Exception $e) {
            error_log("Failed to clear errors.txt: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent errors
     */
    public static function getRecentErrors($lines = 50) {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$logFile);
        $allLines = explode("\n", $content);
        
        return array_slice($allLines, -$lines);
    }
}

// Initialize error logger
if (!file_exists(__DIR__ . '/errors.txt')) {
    ErrorLogger::log("Error logging system initialized", [], 'INFO');
}
?>