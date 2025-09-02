<?php
// Minimal test for AI summary API
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();

ob_clean();
header('Content-Type: application/json');

// Simple test response
try {
    // Test OpenAI key loading
    $openai_file = '../openai.txt';
    if (!file_exists($openai_file)) {
        throw new Exception('OpenAI key file not found');
    }
    
    $openai_key = trim(file_get_contents($openai_file));
    
    if (empty($openai_key)) {
        throw new Exception('OpenAI key is empty');
    }
    
    // Test basic cURL functionality
    $ch = curl_init();
    if (!$ch) {
        throw new Exception('cURL initialization failed');
    }
    curl_close($ch);
    
    // Test Teams API integration if session is active
    $teamsAPI_test = null;
    $channels_test = null;
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
        try {
            require_once '../user_teams_api.php';
            $userTeamsAPI = new UserTeamsAPIHelper($_SESSION['user_id']);
            if ($userTeamsAPI->isConnected()) {
                $teamsAPI_test = 'Connected';
                $channels = $userTeamsAPI->getAllChannels();
                $channels_test = count($channels) . ' channels found';
            } else {
                $teamsAPI_test = 'Not connected';
            }
        } catch (Exception $e) {
            $teamsAPI_test = 'Error: ' . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'AI Summary API test successful',
        'openai_key_loaded' => !empty($openai_key),
        'openai_key_length' => strlen($openai_key),
        'curl_available' => function_exists('curl_init'),
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'teams_api_test' => $teamsAPI_test,
        'channels_test' => $channels_test,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

if (ob_get_level()) {
    ob_end_flush();
}
?>