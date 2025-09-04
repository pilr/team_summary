<?php
/**
 * Emergency Database Fix
 * Creates oauth_tokens table without foreign key constraints
 */
require_once 'config.php';

// Security check
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emergency2024') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Emergency Database Fix ===\n";

try {
    // Direct PDO connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    echo "✅ Database connected\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n\n";
    
    // Check if table exists
    $result = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->fetch();
    if ($result) {
        echo "⚠️  oauth_tokens table exists, dropping...\n";
        $pdo->exec("DROP TABLE oauth_tokens");
        echo "✅ Table dropped\n";
    }
    
    // Create table with minimal structure
    echo "🔨 Creating oauth_tokens table...\n";
    
    $sql = "
    CREATE TABLE oauth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(50) NOT NULL DEFAULT 'microsoft',
        access_token TEXT NOT NULL,
        refresh_token TEXT,
        token_type VARCHAR(20) DEFAULT 'Bearer',
        expires_at DATETIME NOT NULL,
        scope TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_user_provider (user_id, provider),
        INDEX idx_user_provider (user_id, provider),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Table created successfully\n";
    
    // Test insert
    echo "🧪 Testing token insert...\n";
    $testId = 999999;
    $testToken = 'emergency_test_' . time();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    $stmt = $pdo->prepare("
        INSERT INTO oauth_tokens (user_id, provider, access_token, refresh_token, expires_at, scope)
        VALUES (?, 'microsoft', ?, 'test_refresh', ?, 'test_scope')
    ");
    
    $result = $stmt->execute([$testId, $testToken, $expiresAt]);
    
    if ($result) {
        echo "✅ Test insert successful\n";
        
        // Test select
        $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE user_id = ?");
        $stmt->execute([$testId]);
        $row = $stmt->fetch();
        
        if ($row) {
            echo "✅ Test select successful\n";
            echo "   Token: " . substr($row['access_token'], 0, 20) . "...\n";
            echo "   Expires: " . $row['expires_at'] . "\n";
        }
        
        // Clean up
        $pdo->exec("DELETE FROM oauth_tokens WHERE user_id = $testId");
        echo "✅ Test cleanup complete\n";
    } else {
        echo "❌ Test insert failed\n";
    }
    
    echo "\n=== Fix Complete ===\n";
    echo "oauth_tokens table is ready for use\n";
    echo "Try connecting to Microsoft Teams again\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>