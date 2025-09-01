<?php
header('Content-Type: application/json');
session_start();
require_once '../database_helper.php';
require_once '../teams_config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit();
}

try {
    global $db;
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    
    if (!$token_info || empty($token_info['refresh_token'])) {
        echo json_encode(['success' => false, 'error' => 'No refresh token available']);
        exit();
    }
    
    $refresh_success = refreshAccessToken($token_info['refresh_token'], $user_id);
    
    if ($refresh_success) {
        echo json_encode(['success' => true, 'message' => 'Token refreshed successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to refresh token']);
    }
    
} catch (Exception $e) {
    error_log("Refresh Teams token error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while refreshing token']);
}

/**
 * Refresh access token using refresh token
 */
function refreshAccessToken($refresh_token, $user_id) {
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
            error_log("Token refresh failed: HTTP $http_code, Error: $curl_error, Response: $response");
            return false;
        }

        $token_response = json_decode($response, true);
        if (!$token_response || !isset($token_response['access_token'])) {
            error_log("Invalid refresh token response: $response");
            return false;
        }

        // Update token in database
        $expires_in = $token_response['expires_in'] ?? 3600;
        $expires_at = new DateTime();
        $expires_at->add(new DateInterval("PT{$expires_in}S"));

        global $db;
        return $db->updateOAuthToken(
            $user_id,
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
?>