<?php
// Progressive test of summaries.php sections - Version 2 (Cache busting)
ob_start();
require_once 'config.php';
session_start();

// Performance optimizations
ini_set('memory_limit', '256M');
set_time_limit(30);

require_once 'teams_api.php';
require_once 'session_validator.php';

// Use unified session validation  
try {
    $current_user = SessionValidator::getCurrentUser();
} catch (Exception $e) {
    echo "Session validation failed: " . $e->getMessage() . "\n";
    $current_user = null;
}

if (!$current_user) {
    echo "No valid session - using test user\n";
    $user_id = 10;
    $user_name = "Test User";
    $user_email = "test@example.com";
} else {
    $user_id = $current_user['id'];
    $user_name = $current_user['name'];
    $user_email = $current_user['email'];
}

echo "Core loading complete. User: $user_email\n";

// Initialize Teams API - check if user has connected their Microsoft account
require_once 'user_teams_api.php';
$userTeamsAPI = new UserTeamsAPIHelper($user_id);

echo "Teams API helper loaded\n";

// Debug: Log user connection check
error_log("Summaries Page: Checking connection for user_id: $user_id");

// Try user-specific API first, fallback to app-only API
try {
    $user_is_connected = $userTeamsAPI->isConnected();
    echo "Connection check: " . ($user_is_connected ? 'Connected' : 'Not connected') . "\n";
} catch (Exception $e) {
    echo "Connection check failed: " . $e->getMessage() . "\n";
    $user_is_connected = false;
}

if ($user_is_connected) {
    $teamsAPI = $userTeamsAPI;
    $is_user_connected = true;
    echo "Using UserTeamsAPI\n";
} else {
    // Fallback to app-only API (original behavior)
    require_once 'teams_api.php';
    $teamsAPI = new TeamsAPIHelper();
    $is_user_connected = false;
    echo "Using app-only TeamsAPI\n";
}

// Handle date range and filters
$date_range = $_GET['range'] ?? 'today';
$channel_filter = $_GET['channel'] ?? 'all';

echo "Date range: $date_range, Channel filter: $channel_filter\n";

try {
    // Test basic API functionality that summaries.php actually uses
    echo "Testing basic API functionality...\n";
    
    // FIXED: Test getAllChannels() - this is what summaries.php uses
    echo "Calling getAllChannels...\n";
    $channels = $teamsAPI->getAllChannels();
    echo "Channels retrieved: " . (is_array($channels) ? count($channels) : 'null') . "\n";
    
    // Test getChannelMessages() if we have channels
    if (is_array($channels) && count($channels) > 0) {
        echo "Testing getChannelMessages with first channel...\n";
        $firstChannel = $channels[0];
        if (isset($firstChannel['teamId']) && isset($firstChannel['id'])) {
            $messages = $teamsAPI->getChannelMessages($firstChannel['teamId'], $firstChannel['id'], 10);
            echo "Messages retrieved: " . (is_array($messages) ? count($messages) : 'null') . "\n";
        } else {
            echo "First channel missing teamId or id - skipping message test\n";
        }
    } else {
        echo "No channels found - skipping message test\n";
    }
    
} catch (Exception $e) {
    echo "❌ API CALL FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    echo "This is likely the cause of the HTTP 500 error!\n";
}

echo "\n=== Progressive test V2 completed ===\n";
echo "This version should call getAllChannels() correctly!\n";
?>

<h1>Progressive Summaries Test V2</h1>
<p>Check the output above to see where the error occurs.</p>
<p>This version uses the correct getAllChannels() method.</p>