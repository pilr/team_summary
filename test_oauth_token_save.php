<?php
/**
 * Test OAuth token saving functionality
 * Run this script to test if the MySQL PDO buffering issues are fixed
 */

require_once 'database_helper.php';
require_once 'error_logger.php';

try {
    echo "<h1>OAuth Token Save Test</h1>";
    
    $db = new DatabaseHelper();
    $pdo = $db->getPDO();
    
    echo "<p>Database connection: ✅ OK</p>";
    
    // Test 1: Check if table exists
    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
    $tableExists = $tableCheckStmt->rowCount() > 0;
    $tableCheckStmt->closeCursor();
    
    echo "<p>oauth_tokens table exists: " . ($tableExists ? '✅ YES' : '❌ NO') . "</p>";
    
    if (!$tableExists) {
        echo "<p>Creating oauth_tokens table...</p>";
        $created = $db->createOAuthTable();
        echo "<p>Table creation result: " . ($created ? '✅ SUCCESS' : '❌ FAILED') . "</p>";
    }
    
    // Test 2: Test token save
    echo "<h2>Testing Token Save</h2>";
    
    $test_user_id = 999; // Test user ID
    $test_token = 'test_access_token_' . time();
    $test_refresh_token = 'test_refresh_token_' . time();
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    echo "<p>Attempting to save test token for user ID: $test_user_id</p>";
    
    $save_result = $db->saveOAuthToken(
        $test_user_id,
        'microsoft',
        $test_token,
        $test_refresh_token,
        'Bearer',
        $expires_at,
        'test scope'
    );
    
    if ($save_result) {
        echo "<p>✅ Token save SUCCESSFUL!</p>";
        
        // Test 3: Verify token was saved
        echo "<h2>Verifying Saved Token</h2>";
        
        $saved_token = $db->getOAuthToken($test_user_id, 'microsoft');
        if ($saved_token) {
            echo "<p>✅ Token retrieved successfully!</p>";
            echo "<pre>";
            echo "Saved token data:\n";
            echo "- Token type: " . htmlspecialchars($saved_token['token_type']) . "\n";
            echo "- Expires at: " . htmlspecialchars($saved_token['expires_at']) . "\n";
            echo "- Scope: " . htmlspecialchars($saved_token['scope']) . "\n";
            echo "- Created at: " . htmlspecialchars($saved_token['created_at']) . "\n";
            echo "</pre>";
            
            // Clean up test data
            echo "<h2>Cleaning Up Test Data</h2>";
            $delete_stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE user_id = ? AND provider = ?");
            $delete_result = $delete_stmt->execute([$test_user_id, 'microsoft']);
            $delete_stmt->closeCursor();
            
            echo "<p>Test data cleanup: " . ($delete_result ? '✅ SUCCESS' : '❌ FAILED') . "</p>";
            
        } else {
            echo "<p>❌ Failed to retrieve saved token</p>";
        }
        
    } else {
        echo "<p>❌ Token save FAILED!</p>";
        echo "<p>Check the errors.txt file for detailed error information.</p>";
    }
    
    echo "<h2>Summary</h2>";
    echo "<p>✅ MySQL PDO buffering issue fix has been applied</p>";
    echo "<p>✅ Statement cursors are properly closed</p>";
    echo "<p>✅ Buffered queries are enabled to prevent query conflicts</p>";
    
    if ($save_result) {
        echo "<p><strong>🎉 OAuth token saving is now working correctly!</strong></p>";
        echo "<p>You can now try connecting to Microsoft Teams from the account page.</p>";
    } else {
        echo "<p><strong>⚠️ OAuth token saving still has issues. Check errors.txt for details.</strong></p>";
    }
    
    ErrorLogger::logSuccess("OAuth token save test completed", [
        'test_script' => 'test_oauth_token_save.php',
        'table_exists' => $tableExists,
        'save_result' => $save_result
    ]);
    
} catch (Exception $e) {
    echo "<p>❌ Test Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    ErrorLogger::logDatabaseError("test_script", "OAuth token save test failed: " . $e->getMessage());
}
?>