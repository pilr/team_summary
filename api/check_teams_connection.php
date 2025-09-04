<?php
header('Content-Type: application/json');
session_start();
require_once '../persistent_teams_service.php';

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
    global $persistentTeamsService;
    
    // Use persistent service to check connection status
    $status = $persistentTeamsService->getUserTeamsStatus($user_id);
    
    if ($status['status'] === 'connected') {
        // Test actual API access
        $api_test = $persistentTeamsService->testUserTeamsAccess($user_id);
        if ($api_test) {
            echo json_encode(['status' => 'connected', 'expires_at' => $status['expires_at'] ?? null]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'API access test failed']);
        }
    } else {
        echo json_encode(['status' => $status['status']]);
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