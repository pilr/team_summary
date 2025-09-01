<?php
session_start();
require_once 'database_helper.php';
require_once 'user_teams_api.php';

// Debug the exact same flow as summaries.php
echo "=== Debug Summaries Flow ===\n";

// Check if user is logged in (same as summaries.php)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "❌ User not logged in\n";
    exit();
}

// Get user information from session (same as summaries.php)
$user_name = $_SESSION['user_name'] ?? 'Unknown User';
$user_email = $_SESSION['user_email'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

echo "✓ User logged in:\n";
echo "  - User ID: $user_id\n";
echo "  - User Name: $user_name\n";
echo "  - User Email: $user_email\n\n";

// If user_id is missing, redirect to login (same as summaries.php)
if (!$user_id) {
    echo "❌ Missing user_id in session\n";
    exit();
}

echo "=== Initialize Teams API (same as summaries.php) ===\n";

// Initialize Teams API - check if user has connected their Microsoft account
$userTeamsAPI = new UserTeamsAPIHelper($user_id);
echo "✓ UserTeamsAPIHelper created for user ID: $user_id\n";

// Debug: Log user connection check
echo "Checking connection for user_id: $user_id\n";

// Try user-specific API first, fallback to app-only API
$user_is_connected = $userTeamsAPI->isConnected();
echo "UserTeamsAPI->isConnected() = " . ($user_is_connected ? 'true' : 'false') . "\n";

if ($user_is_connected) {
    $teamsAPI = $userTeamsAPI;
    $is_user_connected = true;
    echo "✓ Using UserTeamsAPI for user $user_id\n";
} else {
    // Fallback to app-only API (original behavior)
    require_once 'teams_api.php';
    $teamsAPI = new TeamsAPIHelper();
    $is_user_connected = false;
    echo "❌ Using app-only TeamsAPI (user not connected)\n";
}

echo "\n=== Direct Database Check ===\n";
global $db;
$token_info = $db->getOAuthToken($user_id, 'microsoft');
if ($token_info) {
    echo "✓ Token found in database:\n";
    echo "  - Expires At: " . $token_info['expires_at'] . "\n";
    echo "  - Created At: " . $token_info['created_at'] . "\n";
} else {
    echo "❌ No token found in database for user $user_id\n";
}

$isValid = $db->isTokenValid($user_id, 'microsoft');
echo "Database isTokenValid() = " . ($isValid ? 'true' : 'false') . "\n";

echo "\n=== Test API Calls ===\n";
if ($user_is_connected) {
    echo "Testing getUserTeams()...\n";
    $teams = $userTeamsAPI->getUserTeams();
    echo "Teams found: " . count($teams) . "\n";
    
    if (!empty($teams)) {
        foreach ($teams as $team) {
            echo "  - " . $team['displayName'] . " (ID: " . $team['id'] . ")\n";
        }
    }
    
    echo "\nTesting getAllChannels()...\n";
    $channels = $userTeamsAPI->getAllChannels();
    echo "Channels found: " . count($channels) . "\n";
    
    if (!empty($channels)) {
        foreach (array_slice($channels, 0, 3) as $channel) {
            echo "  - " . $channel['displayName'] . " (" . $channel['teamName'] . ")\n";
        }
        if (count($channels) > 3) {
            echo "  ... and " . (count($channels) - 3) . " more\n";
        }
    }
} else {
    echo "User not connected - skipping API calls\n";
}

echo "\nDone.\n";
?>