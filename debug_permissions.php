<?php
session_start();
require_once 'database_helper.php';
require_once 'teams_config.php';

echo "=== Microsoft Teams API Permissions Debug ===\n";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "❌ Not logged in\n";
    exit();
}

echo "User ID: $user_id\n\n";

global $db;
$token_info = $db->getOAuthToken($user_id, 'microsoft');
if (!$token_info) {
    echo "❌ No token found for user\n";
    exit();
}

echo "=== Token Information ===\n";
echo "Token Type: " . ($token_info['token_type'] ?? 'Bearer') . "\n";
echo "Expires At: " . $token_info['expires_at'] . "\n";
echo "Created At: " . $token_info['created_at'] . "\n";
echo "Scopes in DB: " . ($token_info['scope'] ?? 'N/A') . "\n\n";

// Check if token is expired
$expires_at = new DateTime($token_info['expires_at']);
$now = new DateTime();
echo "Token Status: " . ($now < $expires_at ? "Valid" : "Expired") . "\n";

if ($now >= $expires_at) {
    echo "⚠️  Token is expired - this may be the issue\n\n";
}

// Test each API endpoint individually
$access_token = $token_info['access_token'];

echo "=== API Endpoint Tests ===\n";

// Test 1: User profile (should always work with User.Read)
echo "1. Testing /me (User.Read scope)...\n";
$result = makeGraphAPICall('/me', $access_token);
if ($result['success']) {
    echo "   ✓ SUCCESS - User profile retrieved\n";
    echo "   User: " . ($result['data']['displayName'] ?? 'N/A') . " (" . ($result['data']['userPrincipalName'] ?? 'N/A') . ")\n";
} else {
    echo "   ❌ FAILED - " . $result['error'] . "\n";
    if ($result['http_code'] === 401) {
        echo "   🔍 401 Unauthorized - Token may be invalid or expired\n";
    }
}

echo "\n2. Testing /me/joinedTeams (Team.ReadBasic.All scope)...\n";
$result = makeGraphAPICall('/me/joinedTeams', $access_token);
if ($result['success']) {
    $teams = $result['data']['value'] ?? [];
    echo "   ✓ SUCCESS - Found " . count($teams) . " teams\n";
    
    if (empty($teams)) {
        echo "   ℹ️  User is not a member of any Microsoft Teams\n";
        echo "   This is the root cause - user needs to:\n";
        echo "   - Join at least one Microsoft Team\n";
        echo "   - Be added as a member (not just guest) to a team\n";
    } else {
        echo "   Teams found:\n";
        foreach ($teams as $team) {
            echo "   - " . $team['displayName'] . "\n";
        }
    }
} else {
    echo "   ❌ FAILED - " . $result['error'] . "\n";
    analyzeAPIError($result);
}

echo "\n3. Testing alternative endpoint /me/teamwork/associatedTeams...\n";
$result = makeGraphAPICall('/me/teamwork/associatedTeams', $access_token);
if ($result['success']) {
    $teams = $result['data']['value'] ?? [];
    echo "   ✓ SUCCESS - Alternative endpoint found " . count($teams) . " teams\n";
} else {
    echo "   ❌ FAILED - " . $result['error'] . "\n";
}

echo "\n=== Required vs Actual Scopes ===\n";
echo "Required scopes:\n";
foreach (TEAMS_SCOPES as $scope) {
    echo "- $scope\n";
}

echo "\nActual scopes from token:\n";
$actualScopes = explode(' ', $token_info['scope'] ?? '');
foreach ($actualScopes as $scope) {
    if (trim($scope)) {
        echo "- " . trim($scope) . "\n";
    }
}

// Check if all required scopes are present
echo "\nScope verification:\n";
$tokenScopes = array_map('trim', explode(' ', $token_info['scope'] ?? ''));
foreach (TEAMS_SCOPES as $requiredScope) {
    $hasScope = in_array($requiredScope, $tokenScopes);
    echo ($hasScope ? "✓" : "❌") . " $requiredScope\n";
}

echo "\n=== Recommendations ===\n";
if (empty($teams ?? [])) {
    echo "🔍 ROOT CAUSE: User is not a member of any Microsoft Teams\n\n";
    echo "SOLUTIONS:\n";
    echo "1. Join a Microsoft Team through the Teams app or web interface\n";
    echo "2. Have a team admin add you as a member (not guest) to an existing team\n";
    echo "3. Create a new team if you have permissions\n\n";
    echo "Note: The API connection is working correctly, but there's no data to retrieve.\n";
} else {
    echo "Teams API is working - check channel-level permissions\n";
}

function makeGraphAPICall($endpoint, $access_token) {
    $url = 'https://graph.microsoft.com/v1.0' . $endpoint;
    
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
    
    if ($curl_error) {
        return ['success' => false, 'error' => "CURL Error: $curl_error", 'http_code' => 0];
    }
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return ['success' => true, 'data' => $data, 'http_code' => $http_code];
    }
    
    return [
        'success' => false, 
        'error' => $response, 
        'http_code' => $http_code,
        'parsed_error' => json_decode($response, true)
    ];
}

function analyzeAPIError($result) {
    $http_code = $result['http_code'];
    
    switch ($http_code) {
        case 401:
            echo "   🔍 401 Unauthorized - Token invalid, expired, or insufficient permissions\n";
            break;
        case 403:
            echo "   🔍 403 Forbidden - Permission denied. Possible causes:\n";
            echo "     - Missing required API permissions in app registration\n";
            echo "     - Admin consent required but not granted\n";
            echo "     - User doesn't have access to the requested resource\n";
            break;
        case 404:
            echo "   🔍 404 Not Found - Resource doesn't exist or user has no access\n";
            break;
        default:
            echo "   🔍 HTTP $http_code - " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
    if (isset($result['parsed_error']['error'])) {
        $error = $result['parsed_error']['error'];
        echo "   Error Code: " . ($error['code'] ?? 'N/A') . "\n";
        echo "   Error Message: " . ($error['message'] ?? 'N/A') . "\n";
        
        // Specific error analysis
        $errorCode = $error['code'] ?? '';
        if (strpos($errorCode, 'Forbidden') !== false || strpos($errorCode, 'InsufficientPermissions') !== false) {
            echo "   💡 This is definitely a permissions issue. Check app registration permissions.\n";
        }
    }
}

echo "\nDone.\n";
?>