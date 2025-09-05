<?php
/**
 * Initialize OAuth tokens table
 * Run this script once to ensure the oauth_tokens table exists
 */

require_once 'database_helper.php';
require_once 'error_logger.php';

try {
    echo "<h1>OAuth Table Initialization</h1>";
    
    $db = new DatabaseHelper();
    $pdo = $db->getPDO();
    
    echo "<p>Database connection: ✅ OK</p>";
    
    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->rowCount();
    echo "<p>oauth_tokens table exists: " . ($tableCheck > 0 ? '✅ YES' : '❌ NO') . "</p>";
    
    if ($tableCheck == 0) {
        echo "<p>Creating oauth_tokens table...</p>";
        $created = $db->createOAuthTable();
        
        if ($created) {
            echo "<p>✅ Table created successfully!</p>";
            ErrorLogger::logSuccess("OAuth table initialized manually", [
                'script' => 'init_oauth_table.php'
            ]);
        } else {
            echo "<p>❌ Table creation failed!</p>";
            ErrorLogger::logDatabaseError("manual_init", "Table creation failed in init script");
        }
    } else {
        echo "<p>✅ Table already exists - no action needed</p>";
    }
    
    // Verify table structure
    echo "<h2>Table Structure</h2>";
    $structure = $pdo->query("DESCRIBE oauth_tokens")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($structure as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Summary</h2>";
    echo "<p>✅ OAuth tokens table is ready for use</p>";
    echo "<p>You can now try connecting to Microsoft Teams from the account page.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    ErrorLogger::logDatabaseError("manual_init", "Init script failed: " . $e->getMessage());
}
?>