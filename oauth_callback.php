<?php
session_start();
require_once 'teams_config.php';
require_once 'database_helper.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?error=session_expired');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Unknown';
error_log("OAuth Callback: Processing for dashboard user_id=$user_id, user_name=$user_name");

if (!$user_id) {
    error_log("OAuth Callback: No user_id in session, redirecting to login");
    header('Location: login.php?error=invalid_session');
    exit();
}

// Handle OAuth callback
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $error_description = $_GET['error_description'] ?? 'Unknown error occurred';
    error_log("OAuth error: $error - $error_description");
    header('Location: account.php?error=oauth_failed&message=' . urlencode($error_description));
    exit();
}

if (!isset($_GET['code'])) {
    header('Location: account.php?error=no_auth_code');
    exit();
}

$auth_code = $_GET['code'];
$state = $_GET['state'] ?? '';

// Verify state parameter (if stored in session)
// Note: In production, you should implement proper state verification

try {
    // Exchange authorization code for access token
    $token_data = [
        'client_id' => TEAMS_CLIENT_ID,
        'client_secret' => TEAMS_CLIENT_SECRET,
        'code' => $auth_code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/oauth_callback.php'
    ];

    $ch = curl_init(TEAMS_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("CURL Error: $curl_error");
    }

    if ($http_code !== 200) {
        throw new Exception("Token request failed with HTTP code: $http_code. Response: $response");
    }

    $token_response = json_decode($response, true);
    if (!$token_response || !isset($token_response['access_token'])) {
        throw new Exception("Invalid token response: $response");
    }

    // Calculate expiration time in UTC
    $expires_in = $token_response['expires_in'] ?? 3600;
    $expires_at = new DateTime('now', new DateTimeZone('UTC'));
    $expires_at->add(new DateInterval("PT{$expires_in}S"));

    // Save token to database
    global $db;
    
    // Log token details for debugging (without sensitive data)
    error_log("OAuth Callback - User ID: $user_id, Token Type: " . ($token_response['token_type'] ?? 'Bearer') . ", Expires: " . $expires_at->format('Y-m-d H:i:s'));
    
    $token_saved = $db->saveOAuthToken(
        $user_id,
        'microsoft',
        $token_response['access_token'],
        $token_response['refresh_token'] ?? null,
        $token_response['token_type'] ?? 'Bearer',
        $expires_at->format('Y-m-d H:i:s'),
        $token_response['scope'] ?? ''
    );

    if (!$token_saved) {
        error_log("Failed to save OAuth token to database for user $user_id");
        throw new Exception("Failed to save token to database");
    }
    
    error_log("OAuth token saved successfully for user $user_id");

    // Test the token by making a Graph API call
    $test_success = testGraphAPIAccess($token_response['access_token']);
    
    if ($test_success) {
        // Redirect to account page with success message
        header('Location: account.php?success=teams_connected');
    } else {
        // Token saved but API test failed
        header('Location: account.php?warning=teams_connected_limited');
    }
    exit();

} catch (Exception $e) {
    error_log("OAuth callback error: " . $e->getMessage());
    header('Location: account.php?error=connection_failed&message=' . urlencode($e->getMessage()));
    exit();
}

/**
 * Test Graph API access with the obtained token
 */
function testGraphAPIAccess($access_token) {
    try {
        // Test with a simple Graph API call
        $ch = curl_init('https://graph.microsoft.com/v1.0/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    } catch (Exception $e) {
        error_log("Graph API test failed: " . $e->getMessage());
        return false;
    }
}
?>