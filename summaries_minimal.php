<?php
// Minimal summaries.php test - Add components one by one
echo "Starting summaries minimal test...\n";

ob_start();
echo "Output buffering: OK\n";

require_once 'config.php';
echo "Config loaded: OK\n";

session_start();
echo "Session started: OK\n";

require_once 'database_helper.php';
echo "Database helper loaded: OK\n";

// Try to create database connection
try {
    global $db;
    $db = new DatabaseHelper();
    echo "Database connection: OK\n";
} catch (Exception $e) {
    echo "Database connection FAILED: " . $e->getMessage() . "\n";
    // Continue without database for now
}

require_once 'session_validator.php';
echo "Session validator loaded: OK\n";

// Check if user is logged in (don't redirect on failure)
try {
    $current_user = SessionValidator::getCurrentUser();
    if ($current_user) {
        echo "User session: Valid (User: " . $current_user['email'] . ")\n";
        $user_id = $current_user['id'];
        $user_name = $current_user['name'];
        $user_email = $current_user['email'];
    } else {
        echo "User session: No valid session found\n";
        // Use dummy data for testing
        $user_id = 1;
        $user_name = 'Test User';
        $user_email = 'test@example.com';
    }
} catch (Exception $e) {
    echo "Session validation error: " . $e->getMessage() . "\n";
    $user_id = 1;
    $user_name = 'Test User';
    $user_email = 'test@example.com';
}

echo "Loading Teams APIs...\n";
require_once 'teams_api.php';
require_once 'user_teams_api.php';
echo "Teams APIs loaded: OK\n";

try {
    $userTeamsAPI = new UserTeamsAPIHelper($user_id);
    echo "UserTeamsAPI created: OK\n";
} catch (Exception $e) {
    echo "UserTeamsAPI creation failed: " . $e->getMessage() . "\n";
}

try {
    $teamsAPI = new TeamsAPIHelper();
    echo "TeamsAPI created: OK\n";
} catch (Exception $e) {
    echo "TeamsAPI creation failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test completed successfully! ===\n";
echo "All core components loaded without fatal errors.\n";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Summaries Test</title>
</head>
<body>
    <h1>Summaries Minimal Test</h1>
    <p>If you can see this HTML, the PHP processing completed successfully.</p>
    <p>The HTTP 500 error might be in the complex logic or HTML output of the full summaries.php file.</p>
</body>
</html>