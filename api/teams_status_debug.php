<?php
/**
 * Debug endpoint for Teams connection status (administrative use only)
 */
header('Content-Type: application/json');

// Basic security - check if accessed from localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

require_once '../error_logger.php';
require_once '../persistent_teams_service.php';
require_once '../database_helper.php';

try {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['error' => 'user_id parameter required']);
        exit();
    }
    
    ErrorLogger::log("Debug Teams status check", ['user_id' => $user_id], 'DEBUG');
    
    $db = new DatabaseHelper();
    global $persistentTeamsService;
    
    // Get user info
    $user = $db->getUser($user_id);
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Get token info
    $token = $db->getOAuthToken($user_id, 'microsoft');
    
    // Get connection status
    $status = $persistentTeamsService->getUserTeamsStatus($user_id);
    
    // Test API access
    $api_test = false;
    if ($status['status'] === 'connected') {
        $api_test = $persistentTeamsService->testUserTeamsAccess($user_id);
    }
    
    $result = [
        'user_id' => $user_id,
        'user_email' => $user['email'],
        'user_name' => $user['display_name'],
        'token_exists' => $token !== null,
        'token_expires' => $token['expires_at'] ?? null,
        'connection_status' => $status,
        'api_test_passed' => $api_test,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    ErrorLogger::logSuccess("Debug status check completed", $result);
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ErrorLogger::logTeamsError("debug_status", $e->getMessage(), [
        'user_id' => $user_id ?? 'unknown',
        'exception_trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>