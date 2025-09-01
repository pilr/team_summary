<?php
session_start();
require_once 'database_helper.php';
require_once 'user_teams_api.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "❌ Not logged in\n";
    exit();
}

echo "=== Testing Alternative Teams API Approach ===\n";
echo "User ID: $user_id\n\n";

$userTeamsAPI = new UserTeamsAPIHelper($user_id);

// Test if user is connected
if (!$userTeamsAPI->isConnected()) {
    echo "❌ User not connected\n";
    exit();
}

echo "✅ User connected\n\n";

// Test the updated getUserTeams method (which now includes fallback)
echo "=== Testing getUserTeams (with fallback) ===\n";
$teams = $userTeamsAPI->getUserTeams();
echo "Found " . count($teams) . " teams\n";

if (!empty($teams)) {
    foreach ($teams as $team) {
        echo "- " . $team['displayName'] . " (ID: " . $team['id'] . ")\n";
    }
    
    // Test getting channels for the first team
    if (count($teams) > 0) {
        $firstTeam = $teams[0];
        echo "\n=== Testing channels for first team ===\n";
        $channels = $userTeamsAPI->getTeamChannels($firstTeam['id']);
        echo "Found " . count($channels) . " channels in " . $firstTeam['displayName'] . "\n";
        
        foreach (array_slice($channels, 0, 3) as $channel) {
            echo "- " . $channel['displayName'] . "\n";
        }
    }
} else {
    echo "\nNo teams found. Let's test raw API calls:\n\n";
    
    // Test raw API calls
    global $db;
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    if ($token_info) {
        $access_token = $token_info['access_token'];
        
        echo "=== Raw API Test: /me/joinedTeams ===\n";
        $result1 = testRawAPI('/me/joinedTeams', $access_token);
        echo "HTTP " . $result1['http_code'] . ": " . ($result1['http_code'] === 200 ? "SUCCESS" : "FAILED") . "\n";
        
        if ($result1['http_code'] !== 200) {
            $error = json_decode($result1['response'], true);
            if ($error && isset($error['error']['code'])) {
                echo "Error: " . $error['error']['code'] . "\n";
            }
        }
        
        echo "\n=== Raw API Test: /me/memberOf ===\n";
        $result2 = testRawAPI('/me/memberOf', $access_token);
        echo "HTTP " . $result2['http_code'] . ": " . ($result2['http_code'] === 200 ? "SUCCESS" : "FAILED") . "\n";
        
        if ($result2['http_code'] === 200) {
            $data = json_decode($result2['response'], true);
            $groups = $data['value'] ?? [];
            echo "Found " . count($groups) . " groups total\n";
            
            $teamGroups = 0;
            foreach ($groups as $group) {
                if (isset($group['resourceProvisioningOptions']) && 
                    in_array('Team', $group['resourceProvisioningOptions'] ?? [])) {
                    $teamGroups++;
                    echo "- Team: " . $group['displayName'] . "\n";
                }
            }
            echo "Teams via memberOf: $teamGroups\n";
        }
    }
}

function testRawAPI($endpoint, $access_token) {
    $url = 'https://graph.microsoft.com/v1.0' . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'http_code' => $http_code];
}

echo "\nDone.\n";
?>