<?php
// Test Teams connection API endpoint
session_start();
require_once '../config.php';

// Clear any output buffer and send clean JSON
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID not found in session']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }
    
    $client_id = trim($input['client_id'] ?? '');
    $client_secret = trim($input['client_secret'] ?? '');
    $tenant_id = trim($input['tenant_id'] ?? '');
    
    // Validate required fields
    if (empty($client_id) || empty($client_secret) || empty($tenant_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing required credentials']);
        exit;
    }
    
    // Test the connection by getting an access token
    $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
    
    $token_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials'
    ];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo json_encode(['success' => false, 'error' => 'Connection error: ' . $curl_error]);
        exit;
    }
    
    if ($http_code !== 200) {
        $error_response = json_decode($response, true);
        $error_message = $error_response['error_description'] ?? 'Authentication failed';
        echo json_encode(['success' => false, 'error' => $error_message]);
        exit;
    }
    
    $token_response = json_decode($response, true);
    
    if (!$token_response || !isset($token_response['access_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid token response']);
        exit;
    }
    
    // Test a basic Graph API call to verify permissions
    $graph_url = 'https://graph.microsoft.com/v1.0/organization';
    
    $ch = curl_init($graph_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token_response['access_token'],
        'Content-Type: application/json'
    ]);
    
    $graph_response = curl_exec($ch);
    $graph_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $graph_curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($graph_curl_error) {
        echo json_encode(['success' => false, 'error' => 'Graph API connection error: ' . $graph_curl_error]);
        exit;
    }
    
    if ($graph_http_code !== 200) {
        $graph_error = json_decode($graph_response, true);
        $graph_error_message = $graph_error['error']['message'] ?? 'Graph API access failed';
        echo json_encode(['success' => false, 'error' => 'Graph API error: ' . $graph_error_message]);
        exit;
    }
    
    // If we get here, the connection is successful
    $org_data = json_decode($graph_response, true);
    $org_name = '';
    
    if (isset($org_data['value']) && is_array($org_data['value']) && count($org_data['value']) > 0) {
        $org_name = $org_data['value'][0]['displayName'] ?? 'Unknown Organization';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Connection successful',
        'organization' => $org_name,
        'tenant_id' => $tenant_id
    ]);
    
} catch (Exception $e) {
    error_log("Teams connection test error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
}
?>