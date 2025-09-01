<?php
session_start();
require_once 'database_helper.php';

echo "=== Debug Microsoft Graph API Calls ===\n";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "❌ No user logged in\n";
    exit();
}

echo "User ID: $user_id\n\n";

global $db;
$token_info = $db->getOAuthToken($user_id, 'microsoft');
if (!$token_info) {
    echo "❌ No token found\n";
    exit();
}

$access_token = $token_info['access_token'];
echo "✓ Token found, expires at: " . $token_info['expires_at'] . "\n";

// Test 1: Get user profile
echo "\n=== Test 1: Get User Profile ===\n";
$url = 'https://graph.microsoft.com/v1.0/me';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($curl_error) {
    echo "CURL Error: $curl_error\n";
}

if ($http_code === 200) {
    $profile = json_decode($response, true);
    echo "✓ Profile retrieved successfully:\n";
    echo "  - Display Name: " . ($profile['displayName'] ?? 'N/A') . "\n";
    echo "  - Email: " . ($profile['mail'] ?? $profile['userPrincipalName'] ?? 'N/A') . "\n";
    echo "  - ID: " . ($profile['id'] ?? 'N/A') . "\n";
} else {
    echo "❌ Profile request failed\n";
    echo "Response: $response\n";
}

// Test 2: Get joined teams
echo "\n=== Test 2: Get Joined Teams ===\n";
$url = 'https://graph.microsoft.com/v1.0/me/joinedTeams';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($curl_error) {
    echo "CURL Error: $curl_error\n";
}

if ($http_code === 200) {
    $teams_response = json_decode($response, true);
    $teams = $teams_response['value'] ?? [];
    echo "✓ Teams request successful, found " . count($teams) . " teams\n";
    
    if (empty($teams)) {
        echo "  (No teams found - user may not be a member of any teams)\n";
    } else {
        foreach ($teams as $team) {
            echo "  - " . $team['displayName'] . " (ID: " . $team['id'] . ")\n";
            echo "    Description: " . ($team['description'] ?? 'N/A') . "\n";
            echo "    Visibility: " . ($team['visibility'] ?? 'N/A') . "\n\n";
        }
    }
} else {
    echo "❌ Teams request failed\n";
    echo "Response: $response\n";
    
    // Try to decode error response
    $error = json_decode($response, true);
    if ($error && isset($error['error'])) {
        echo "Error Code: " . $error['error']['code'] . "\n";
        echo "Error Message: " . $error['error']['message'] . "\n";
    }
}

// Test 3: Check token scopes (if teams request failed)
if ($http_code !== 200) {
    echo "\n=== Test 3: Check Token Scopes ===\n";
    echo "Token scopes from database: " . ($token_info['scope'] ?? 'N/A') . "\n";
    
    // Decode the access token to see scopes (if it's a JWT)
    $token_parts = explode('.', $access_token);
    if (count($token_parts) === 3) {
        try {
            $payload = json_decode(base64_decode($token_parts[1]), true);
            if ($payload && isset($payload['scp'])) {
                echo "Token scopes from JWT: " . $payload['scp'] . "\n";
            }
        } catch (Exception $e) {
            echo "Could not decode token payload\n";
        }
    }
}

// Test 4: Alternative - Try to get teams via different endpoint
echo "\n=== Test 4: Alternative Teams Endpoint ===\n";
$url = 'https://graph.microsoft.com/v1.0/me/teamwork/associatedTeams';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Alternative teams endpoint HTTP Code: $http_code\n";
if ($http_code === 200) {
    $teams_response = json_decode($response, true);
    $teams = $teams_response['value'] ?? [];
    echo "✓ Alternative endpoint found " . count($teams) . " teams\n";
} else {
    echo "❌ Alternative endpoint also failed\n";
    if ($response) {
        $error = json_decode($response, true);
        if ($error && isset($error['error'])) {
            echo "Error: " . $error['error']['message'] . "\n";
        }
    }
}

echo "\nDone.\n";
?>