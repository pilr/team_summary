<?php
session_start();
require_once 'database_helper.php';
require_once 'user_teams_api.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "❌ Not logged in\n";
    exit();
}

echo "=== Simple Teams API Debug ===\n";
echo "User ID: $user_id\n\n";

$userTeamsAPI = new UserTeamsAPIHelper($user_id);

// Test connection
echo "1. Testing isConnected()...\n";
$connected = $userTeamsAPI->isConnected();
echo "   Result: " . ($connected ? 'true' : 'false') . "\n\n";

if (!$connected) {
    echo "❌ User not connected - stopping here\n";
    exit();
}

// Test getUserTeams directly
echo "2. Testing getUserTeams()...\n";
$teams = $userTeamsAPI->getUserTeams();
echo "   Found " . count($teams) . " teams\n";

if (empty($teams)) {
    echo "   ❌ No teams returned\n";
    
    // Let's check the raw token and try a manual API call
    global $db;
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    if ($token_info) {
        echo "   Token expires at: " . $token_info['expires_at'] . "\n";
        echo "   Token scopes: " . ($token_info['scope'] ?? 'N/A') . "\n";
        
        // Manual API call to see raw response
        echo "\n3. Manual API call to /me/joinedTeams...\n";
        $url = 'https://graph.microsoft.com/v1.0/me/joinedTeams';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token_info['access_token'],
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "   HTTP Code: $http_code\n";
        if ($curl_error) {
            echo "   CURL Error: $curl_error\n";
        }
        
        if ($http_code === 200) {
            echo "   ✓ API call successful\n";
            $data = json_decode($response, true);
            if ($data) {
                echo "   Raw response keys: " . implode(', ', array_keys($data)) . "\n";
                if (isset($data['value'])) {
                    echo "   Teams in response: " . count($data['value']) . "\n";
                    if (empty($data['value'])) {
                        echo "   ⚠️  API returns empty teams array - user may not be member of any Teams\n";
                    }
                }
            }
        } else {
            echo "   ❌ API call failed\n";
            echo "   Response: $response\n";
            
            // Try to parse error
            $error = json_decode($response, true);
            if ($error && isset($error['error'])) {
                echo "   Error code: " . $error['error']['code'] . "\n";
                echo "   Error message: " . $error['error']['message'] . "\n";
                
                if (strpos($error['error']['message'], 'Insufficient privileges') !== false) {
                    echo "   🔍 This is a permissions issue - token doesn't have required scopes\n";
                }
            }
        }
    }
} else {
    echo "   ✓ Found teams:\n";
    foreach ($teams as $team) {
        echo "   - " . $team['displayName'] . " (ID: " . $team['id'] . ")\n";
    }
    
    echo "\n3. Testing getAllChannels()...\n";
    $channels = $userTeamsAPI->getAllChannels();
    echo "   Found " . count($channels) . " total channels\n";
}

echo "\nDone.\n";
?>