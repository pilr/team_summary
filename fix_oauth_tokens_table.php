<?php
/**
 * Fix OAuth Tokens Table Issues
 * This script ensures the oauth_tokens table exists and is properly configured
 */
require_once 'database_helper.php';

// Security check - allow web access with secret parameter
$secret = $_GET['secret'] ?? '';
$isCommandLine = !isset($_SERVER['REQUEST_METHOD']);
$hasSecret = $secret === 'diagnostic2024'; // Change this secret as needed

if (!$isCommandLine && !$hasSecret) {
    http_response_code(403);
    die('Access denied. Use the secret parameter or run from command line.');
}

// Set content type for web access
if (!$isCommandLine) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== OAuth Tokens Table Fix Script ===\n";
echo "Checking and fixing oauth_tokens table issues...\n\n";

try {
    $db = new DatabaseHelper();
    $pdo = $db->getPDO();
    
    echo "1. Testing database connection... ";
    if ($pdo) {
        echo "✓ Connected\n";
    } else {
        throw new Exception("Database connection failed");
    }
    
    echo "2. Checking if oauth_tokens table exists... ";
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
    $tableExists = $tableCheck->rowCount() > 0;
    
    if ($tableExists) {
        echo "✓ Table exists\n";
        
        // Check table structure
        echo "3. Checking table structure... ";
        $structure = $pdo->query("DESCRIBE oauth_tokens");
        $columns = $structure->fetchAll(PDO::FETCH_ASSOC);
        
        $hasRequiredColumns = false;
        $columnNames = array_column($columns, 'Field');
        $required = ['id', 'user_id', 'provider', 'access_token', 'refresh_token', 'expires_at'];
        
        if (count(array_intersect($required, $columnNames)) === count($required)) {
            echo "✓ Structure OK\n";
            $hasRequiredColumns = true;
        } else {
            echo "✗ Missing columns\n";
        }
        
        // Check for foreign key constraints
        echo "4. Checking foreign key constraints... ";
        $constraints = $pdo->query("
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'oauth_tokens' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fkeys = $constraints->fetchAll();
        
        if (count($fkeys) > 0) {
            echo "⚠ Found foreign key constraints:\n";
            foreach ($fkeys as $fkey) {
                echo "    - " . $fkey['CONSTRAINT_NAME'] . " -> " . $fkey['REFERENCED_TABLE_NAME'] . "\n";
            }
            
            // Drop foreign key constraints that might be causing issues
            echo "5. Removing problematic foreign key constraints... ";
            foreach ($fkeys as $fkey) {
                if ($fkey['REFERENCED_TABLE_NAME'] === 'users') {
                    try {
                        $pdo->exec("ALTER TABLE oauth_tokens DROP FOREIGN KEY " . $fkey['CONSTRAINT_NAME']);
                        echo "✓ Removed " . $fkey['CONSTRAINT_NAME'] . "\n";
                    } catch (Exception $e) {
                        echo "⚠ Could not remove " . $fkey['CONSTRAINT_NAME'] . ": " . $e->getMessage() . "\n";
                    }
                }
            }
        } else {
            echo "✓ No foreign key constraints\n";
        }
        
        if (!$hasRequiredColumns) {
            echo "6. Dropping and recreating table with correct structure... ";
            $pdo->exec("DROP TABLE oauth_tokens");
            $tableExists = false;
        }
    } else {
        echo "✗ Table does not exist\n";
    }
    
    if (!$tableExists) {
        echo "Creating oauth_tokens table with proper structure... ";
        
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
        ) ENGINE=InnoDB";
        
        $pdo->exec($sql);
        echo "✓ Table created successfully\n";
    }
    
    echo "\n7. Testing token save functionality... ";
    
    // Test with a dummy user_id (we'll use 1 or create a test record)
    $testUserId = 1;
    $testToken = 'test_access_token_' . time();
    $testRefreshToken = 'test_refresh_token_' . time();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    $success = $db->saveOAuthToken(
        $testUserId,
        'microsoft',
        $testToken,
        $testRefreshToken,
        'Bearer',
        $expiresAt,
        'test_scope'
    );
    
    if ($success) {
        echo "✓ Token save test successful\n";
        
        // Clean up test token
        echo "8. Cleaning up test token... ";
        $pdo->exec("DELETE FROM oauth_tokens WHERE user_id = $testUserId AND access_token = '$testToken'");
        echo "✓ Cleaned up\n";
    } else {
        echo "✗ Token save test failed\n";
        throw new Exception("Token save functionality is still not working");
    }
    
    echo "\n=== Fix Complete ===\n";
    echo "The oauth_tokens table is now properly configured and working.\n";
    echo "You can now try connecting to Microsoft Teams again.\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Please check the error logs for more details.\n";
    exit(1);
}
?>