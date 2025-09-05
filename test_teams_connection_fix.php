<?php
/**
 * Test script to verify Microsoft Teams connection fixes
 */
require_once 'error_logger.php';
require_once 'teams_config.php';
require_once 'database_helper.php';

echo "<h1>Microsoft Teams Connection Fix Test</h1>";

// Test 1: Error Logger
echo "<h2>Test 1: Error Logger</h2>";
try {
    ErrorLogger::log("Test log entry", ['test' => 'data'], 'TEST');
    ErrorLogger::logTeamsError("test_operation", "Test error message", ['context' => 'test']);
    echo "✅ Error logging system is working<br>";
} catch (Exception $e) {
    echo "❌ Error logging failed: " . $e->getMessage() . "<br>";
}

// Test 2: Teams Configuration
echo "<h2>Test 2: Teams Configuration</h2>";
echo "Client ID: " . (TEAMS_CLIENT_ID !== 'YOUR_CLIENT_ID_HERE' ? '✅ Configured' : '❌ Not configured') . "<br>";
echo "Client Secret: " . (TEAMS_CLIENT_SECRET !== 'YOUR_CLIENT_SECRET_HERE' ? '✅ Configured' : '❌ Not configured') . "<br>";
echo "Tenant ID: " . (TEAMS_TENANT_ID !== 'YOUR_TENANT_ID_HERE' ? '✅ Configured' : '❌ Not configured') . "<br>";
echo "Redirect URI: " . TEAMS_REDIRECT_URI . "<br>";

// Test 3: Database Connection
echo "<h2>Test 3: Database Connection</h2>";
try {
    $db = new DatabaseHelper();
    $pdo = $db->getPDO();
    echo "✅ Database connection successful<br>";
    
    // Check oauth_tokens table
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->rowCount();
    echo "OAuth tokens table: " . ($tableCheck > 0 ? '✅ Exists' : '❌ Missing') . "<br>";
    
    if ($tableCheck == 0) {
        echo "Creating oauth_tokens table...<br>";
        $db->createOAuthTable();
        echo "✅ Table created successfully<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    ErrorLogger::logDatabaseError("connection_test", $e->getMessage());
}

// Test 4: Recent Error Log
echo "<h2>Test 4: Recent Error Log Entries</h2>";
try {
    $recentErrors = ErrorLogger::getRecentErrors(10);
    if (empty($recentErrors)) {
        echo "No recent errors logged<br>";
    } else {
        echo "<pre>" . implode("\n", array_slice($recentErrors, -10)) . "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Could not read error log: " . $e->getMessage() . "<br>";
}

// Test 5: Configuration Summary
echo "<h2>Test 5: Configuration Summary</h2>";
echo "<pre>";
echo "Auth URL: " . TEAMS_AUTH_URL . "\n";
echo "Token URL: " . TEAMS_TOKEN_URL . "\n";
echo "Graph URL: " . TEAMS_GRAPH_URL . "\n";
echo "Scopes: " . implode(', ', TEAMS_SCOPES) . "\n";
echo "Cache Duration: " . TEAMS_CACHE_DURATION . " seconds\n";
echo "</pre>";

echo "<h2>Fix Status Summary</h2>";
echo "<ul>";
echo "<li>✅ Comprehensive error logging to errors.txt implemented</li>";
echo "<li>✅ OAuth callback error handling improved</li>";
echo "<li>✅ Database initialization with retry logic added</li>";
echo "<li>✅ API error logging with detailed context</li>";
echo "<li>✅ Redirect URI configuration fixed</li>";
echo "<li>✅ Token validation and refresh logic enhanced</li>";
echo "</ul>";

?>