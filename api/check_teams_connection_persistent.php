<?php
/**
 * Check Teams Connection Status (Session-independent)
 * This endpoint can check connection status without requiring an active session
 * Useful for background processes and maintenance
 */
header('Content-Type: application/json');
require_once '../persistent_teams_service.php';

// Check if user_id is provided via POST or GET
$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id parameter required']);
    exit();
}

// Basic API key authentication for security (optional)
$api_key = $_POST['api_key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
$expected_api_key = getenv('TEAMS_API_KEY') ?: 'your-api-key-here';

if ($api_key && $api_key !== $expected_api_key) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit();
}

try {
    global $persistentTeamsService;
    
    // Check connection status using persistent service
    $status = $persistentTeamsService->getUserTeamsStatus($user_id);
    
    if ($status['status'] === 'connected') {
        // Optionally test actual API access
        $test_api = $_POST['test_api'] ?? $_GET['test_api'] ?? false;
        if ($test_api) {
            $api_test = $persistentTeamsService->testUserTeamsAccess($user_id);
            $status['api_accessible'] = $api_test;
        }
    }
    
    echo json_encode($status);

} catch (Exception $e) {
    error_log("Check persistent Teams connection error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>