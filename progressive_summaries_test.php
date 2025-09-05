<?php
// Progressive test of summaries.php sections to identify HTTP 500 cause
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
    // Test basic API functionality
    echo "Testing basic API functionality...\n";
    
    // This is likely where the error occurs - let's test it step by step
    echo "Calling getUserChannels...\n";
    $channels = $teamsAPI->getUserChannels();
    echo "Channels retrieved: " . (is_array($channels) ? count($channels) : 'null') . "\n";
    
    echo "Calling getTeamsActivity...\n";
    $activities = $teamsAPI->getTeamsActivity($date_range);
    echo "Activities retrieved: " . (is_array($activities) ? count($activities) : 'null') . "\n";
    
} catch (Exception $e) {
    echo "❌ API CALL FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "This is likely the cause of the HTTP 500 error!\n";
}

echo "\n=== Progressive test completed ===\n";
?>

<h1>Progressive Summaries Test</h1>
<p>Check the output above to see where the error occurs.</p>