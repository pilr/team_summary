<?php
require_once 'teams_config.php';
require_once 'teams_api.php';

echo "=== Teams API Configuration Test ===\n";
echo "Client ID: " . TEAMS_CLIENT_ID . "\n";
echo "Client Secret: " . (TEAMS_CLIENT_SECRET ? 'SET (length: ' . strlen(TEAMS_CLIENT_SECRET) . ')' : 'NOT SET') . "\n";  
echo "Tenant ID: " . TEAMS_TENANT_ID . "\n";
echo "Token URL: " . TEAMS_TOKEN_URL . "\n\n";

echo "=== Testing API Connection ===\n";
$teamsAPI = new TeamsAPIHelper();

// Test getting access token
echo "Getting access token...\n";
$token = $teamsAPI->getAccessToken();
if ($token) {
    echo "✓ Access token obtained successfully (length: " . strlen($token) . ")\n";
    
    // Test getting teams
    echo "Getting teams...\n";
    $teams = $teamsAPI->getTeams();
    echo "Teams found: " . count($teams) . "\n";
    
    if (!empty($teams)) {
        foreach ($teams as $team) {
            echo "  - " . $team['displayName'] . " (ID: " . $team['id'] . ")\n";
        }
    }
    
    // Test getting all channels
    echo "Getting all channels...\n";
    $channels = $teamsAPI->getAllChannels();
    echo "Channels found: " . count($channels) . "\n";
    
    if (!empty($channels)) {
        foreach (array_slice($channels, 0, 5) as $channel) {
            echo "  - " . $channel['displayName'] . " (" . $channel['teamName'] . ")\n";
        }
        if (count($channels) > 5) {
            echo "  ... and " . (count($channels) - 5) . " more\n";
        }
    }
} else {
    echo "✗ Failed to obtain access token\n";
    
    // Check error logs
    $errorLogFile = 'php_errors.log';
    if (file_exists($errorLogFile)) {
        echo "\nRecent errors:\n";
        $errors = file_get_contents($errorLogFile);
        echo $errors;
    }
}
?>