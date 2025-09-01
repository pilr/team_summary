<?php
require_once 'database_helper.php';

echo "Creating OAuth tokens table...\n";

try {
    global $db;
    $pdo = $db->getPDO();
    
    // Create oauth_tokens table
    $sql = "
    CREATE TABLE IF NOT EXISTS oauth_tokens (
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
    )";
    
    $pdo->exec($sql);
    echo "OAuth tokens table created successfully!\n";
    
    // Check if table was created
    $result = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
    if ($result->rowCount() > 0) {
        echo "✓ Table exists in database\n";
        
        // Show table structure
        $result = $pdo->query("DESCRIBE oauth_tokens");
        echo "\nTable structure:\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "  {$row['Field']} ({$row['Type']})\n";
        }
    } else {
        echo "✗ Table was not created\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>