<?php
echo "=== Summaries.php HTTP 500 Debug - Step by Step ===\n";

// Step 1: Basic PHP test
echo "1. PHP is working ✓\n";

try {
    // Step 2: Test output buffering
    ob_start();
    echo "2. Output buffering started ✓\n";
    
    // Step 3: Test config loading
    require_once 'config.php';
    echo "3. Config loaded ✓\n";
    
    // Step 4: Test session start
    session_start();
    echo "4. Session started ✓\n";
    
    // Step 5: Test database helper
    require_once 'database_helper.php';
    echo "5. Database helper loaded ✓\n";
    
    // Step 6: Test database connection
    global $db;
    $db = new DatabaseHelper();
    echo "6. DatabaseHelper instantiated ✓\n";
    
    // Step 7: Test session validator
    require_once 'session_validator.php';
    echo "7. Session validator loaded ✓\n";
    
    // Step 8: Test session validation (this might fail and that's OK for now)
    try {
        $current_user = SessionValidator::getCurrentUser();
        if ($current_user) {
            echo "8. Session validation passed ✓ (User: " . $current_user['email'] . ")\n";
        } else {
            echo "8. Session validation returned null (user not logged in)\n";
        }
    } catch (Exception $e) {
        echo "8. Session validation failed: " . $e->getMessage() . "\n";
    }
    
    // Step 9: Test Teams API loading
    require_once 'teams_api.php';
    echo "9. Teams API loaded ✓\n";
    
    // Step 10: Test UserTeams API loading
    require_once 'user_teams_api.php';
    echo "10. User Teams API loaded ✓\n";
    
    // Step 11: Test Teams API instantiation
    $teamsAPI = new TeamsAPIHelper();
    echo "11. TeamsAPIHelper instantiated ✓\n";
    
    echo "\n=== All major components loaded successfully! ===\n";
    echo "If you see this message, the issue might be later in summaries.php\n";
    echo "Check the HTML output or JavaScript sections.\n";
    
} catch (Error $e) {
    echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>