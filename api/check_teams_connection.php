<?php
header('Content-Type: application/json');
session_start();
require_once '../database_helper.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit();
}

try {
    global $db;
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    
    if (!$token_info) {
        echo json_encode(['status' => 'disconnected']);
        exit();
    }
    
    // Check if token is expired
    $expires_at = new DateTime($token_info['expires_at']);
    $now = new DateTime();
    
    if ($now >= $expires_at) {
        // Try to refresh token if refresh_token exists
        if (!empty($token_info['refresh_token'])) {
            $refreshed = refreshAccessToken($token_info['refresh_token'], $user_id);
            if ($refreshed) {
                echo json_encode(['status' => 'connected']);
            } else {
                echo json_encode(['status' => 'disconnected']);
            }
        } else {
            echo json_encode(['status' => 'disconnected']);
        }
        exit();
    }
    
    // Test the token with Graph API
    $test_result = testGraphAPIAccess($token_info['access_token']);
    
    if ($test_result) {
        echo json_encode(['status' => 'connected']);
    } else {
        echo json_encode(['status' => 'error']);
    }

} catch (Exception $e) {
    error_log("Check Teams connection error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Test Graph API access
 */
function testGraphAPIAccess($access_token) {
    try {
        $ch = curl_init('https://graph.microsoft.com/v1.0/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("CURL error in testGraphAPIAccess: $curl_error");
            return false;
        }

        return $http_code === 200;
    } catch (Exception $e) {
        error_log("Graph API test error: " . $e->getMessage());
        return false;
    }
}

/**
 * Refresh access token using refresh token
 */
function refreshAccessToken($refresh_token, $user_id) {
    require_once '../teams_config.php';
    
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