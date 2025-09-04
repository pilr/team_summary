<?php
/**
 * Force Create OAuth Tokens Table
 * More aggressive table creation with permission checks
 */
require_once 'config.php';

// Security check
$secret = $_GET['secret'] ?? '';
if ($secret !== 'force2024') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Force Create OAuth Tokens Table ===\n";
echo "Time: " . date('Y-m-d H:i:s T') . "\n\n";

try {
    // Direct PDO connection with more options
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false, // Don't use persistent for this operation
        ]
    );
    
    echo "✅ Database connected successfully\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    echo "Host: " . DB_HOST . "\n\n";
    
    // Check current database and permissions
    $currentDb = $pdo->query("SELECT DATABASE() as db")->fetch()['db'];
    echo "Current database: $currentDb\n";
    
    // Check user privileges
    echo "🔐 Checking user privileges...\n";
    try {
        $privileges = $pdo->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll();
        foreach ($privileges as $privilege) {
            echo "   " . $privilege['Grants for ' . DB_USER . '@%'] . "\n";
        }
    } catch (Exception $e) {
        echo "   Could not check privileges: " . $e->getMessage() . "\n";
    }
    
    // List all tables first
    echo "\n📋 Current tables in database:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll();
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "   - $tableName\n";
    }
    
    // Check if oauth_tokens exists
    echo "\n🔍 Checking for oauth_tokens table...\n";
    $result = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->fetch();
    if ($result) {
        echo "⚠️  Table exists, getting details...\n";
        
        // Show table structure
        $structure = $pdo->query("DESCRIBE oauth_tokens")->fetchAll();
        echo "Current structure:\n";
        foreach ($structure as $column) {
            echo "   {$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']}\n";
        }
        
        // Check row count
        $count = $pdo->query("SELECT COUNT(*) as count FROM oauth_tokens")->fetch()['count'];
        echo "Current row count: $count\n";
        
        echo "\n🗑️  Dropping existing table...\n";
        $pdo->exec("DROP TABLE IF EXISTS oauth_tokens");
        echo "✅ Table dropped\n";
    } else {
        echo "ℹ️  Table does not exist\n";
    }
    
    // Create table with explicit engine and charset
    echo "\n🔨 Creating oauth_tokens table...\n";
    
    $sql = "
    CREATE TABLE oauth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL DEFAULT 'microsoft',
        access_token TEXT NOT NULL,
        refresh_token TEXT NULL,
        token_type VARCHAR(20) DEFAULT 'Bearer',
        expires_at DATETIME NOT NULL,
        scope TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_user_provider (user_id, provider),
        INDEX idx_user_provider (user_id, provider),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB 
      DEFAULT CHARSET=utf8mb4 
      COLLATE=utf8mb4_unicode_ci
      COMMENT='OAuth tokens for external service integrations'";
    
    echo "Executing SQL:\n";
    echo $sql . "\n\n";
    
    $pdo->exec($sql);
    echo "✅ Table created successfully\n";
    
    // Verify table was created
    echo "\n✅ Verifying table creation...\n";
    $verifyResult = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->fetch();
    if ($verifyResult) {
        echo "✅ Table verification: SUCCESS\n";
        
        // Show final structure
        $finalStructure = $pdo->query("DESCRIBE oauth_tokens")->fetchAll();
        echo "Final structure:\n";
        foreach ($finalStructure as $column) {
            echo "   {$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']}\n";
        }
    } else {
        throw new Exception("Table verification failed - table not found after creation");
    }
    
    // Test insert/update/delete operations
    echo "\n🧪 Testing CRUD operations...\n";
    
    $testUserId = 999999;
    $testToken = 'force_test_' . time();
    $testRefresh = 'force_refresh_' . time();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    // Test INSERT
    echo "Testing INSERT...\n";
    $insertStmt = $pdo->prepare("
        INSERT INTO oauth_tokens (user_id, provider, access_token, refresh_token, expires_at, scope)
        VALUES (?, 'microsoft', ?, ?, ?, 'test_scope')
    ");
    $insertResult = $insertStmt->execute([$testUserId, $testToken, $testRefresh, $expiresAt]);
    
    if ($insertResult) {
        echo "✅ INSERT: SUCCESS\n";
        
        // Test SELECT
        echo "Testing SELECT...\n";
        $selectStmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE user_id = ?");
        $selectStmt->execute([$testUserId]);
        $row = $selectStmt->fetch();
        
        if ($row) {
            echo "✅ SELECT: SUCCESS\n";
            echo "   ID: {$row['id']}\n";
            echo "   User ID: {$row['user_id']}\n";
            echo "   Provider: {$row['provider']}\n";
            echo "   Token: " . substr($row['access_token'], 0, 20) . "...\n";
            echo "   Expires: {$row['expires_at']}\n";
            
            // Test UPDATE
            echo "Testing UPDATE...\n";
            $newToken = 'updated_' . time();
            $updateStmt = $pdo->prepare("
                UPDATE oauth_tokens 
                SET access_token = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = ? AND provider = 'microsoft'
            ");
            $updateResult = $updateStmt->execute([$newToken, $testUserId]);
            
            if ($updateResult) {
                echo "✅ UPDATE: SUCCESS\n";
            } else {
                echo "❌ UPDATE: FAILED\n";
            }
            
            // Test DELETE
            echo "Testing DELETE...\n";
            $deleteStmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE user_id = ?");
            $deleteResult = $deleteStmt->execute([$testUserId]);
            
            if ($deleteResult) {
                echo "✅ DELETE: SUCCESS\n";
            } else {
                echo "❌ DELETE: FAILED\n";
            }
            
        } else {
            echo "❌ SELECT: FAILED\n";
        }
    } else {
        echo "❌ INSERT: FAILED\n";
        $errorInfo = $insertStmt->errorInfo();
        echo "Error: " . json_encode($errorInfo) . "\n";
    }
    
    // Final table verification
    echo "\n📊 Final table status:\n";
    $finalCount = $pdo->query("SELECT COUNT(*) as count FROM oauth_tokens")->fetch()['count'];
    echo "Row count: $finalCount\n";
    
    if ($finalCount == 0) {
        echo "✅ Table is clean and ready for use\n";
    }
    
    echo "\n🎉 SUCCESS: oauth_tokens table is fully functional!\n";
    echo "You can now try connecting to Microsoft Teams.\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "SQL State: " . $e->errorInfo[0] . "\n";
} catch (Exception $e) {
    echo "❌ General Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";
?>