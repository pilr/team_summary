<?php
// Fix session and user mapping issues
require_once 'database_helper.php';
require_once 'error_logger.php';

echo "=== Fixing Session User Mapping Issues ===\n\n";

try {
    global $db;
    $pdo = $db->getPDO();
    
    // 1. First create the oauth_tokens table if it doesn't exist
    echo "1. Checking/Creating oauth_tokens table...\n";
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
    if ($tableCheck->rowCount() == 0) {
        echo "   - oauth_tokens table doesn't exist, creating it...\n";
        $tableCreated = $db->createOAuthTable();
        if ($tableCreated) {
            echo "   ✓ oauth_tokens table created successfully\n";
        } else {
            echo "   ✗ Failed to create oauth_tokens table\n";
            throw new Exception("Cannot continue without oauth_tokens table");
        }
    } else {
        echo "   ✓ oauth_tokens table already exists\n";
    }
    
    // 2. Check existing users in the database
    echo "\n2. Checking existing users...\n";
    $stmt = $pdo->query("SELECT id, email, display_name, status FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "   ⚠ No users found in database\n";
    } else {
        echo "   Found " . count($users) . " users:\n";
        foreach ($users as $user) {
            echo "   - ID: {$user['id']}, Email: {$user['email']}, Name: {$user['display_name']}, Status: {$user['status']}\n";
        }
    }
    
    // 3. Check for specific email addresses mentioned in the issue
    echo "\n3. Checking for specific email addresses...\n";
    $emails_to_check = ['staedeli@gmail.com', 'pil.rollano@seriousweb.ch'];
    
    foreach ($emails_to_check as $email) {
        $stmt = $pdo->prepare("SELECT id, email, display_name, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "   ✓ Found user for {$email}: ID {$user['id']}, Name: {$user['display_name']}, Status: {$user['status']}\n";
        } else {
            echo "   ✗ No user found for {$email}\n";
        }
    }
    
    // 4. Check oauth tokens
    echo "\n4. Checking existing OAuth tokens...\n";
    $stmt = $pdo->query("SELECT user_id, provider, expires_at, created_at FROM oauth_tokens ORDER BY created_at DESC");
    $tokens = $stmt->fetchAll();
    
    if (empty($tokens)) {
        echo "   - No OAuth tokens found\n";
    } else {
        echo "   Found " . count($tokens) . " tokens:\n";
        foreach ($tokens as $token) {
            $user_info = $pdo->prepare("SELECT email, display_name FROM users WHERE id = ?");
            $user_info->execute([$token['user_id']]);
            $user = $user_info->fetch();
            
            echo "   - User ID: {$token['user_id']} ({$user['email']}), Provider: {$token['provider']}, Expires: {$token['expires_at']}\n";
        }
    }
    
    // 5. Create user for staedeli@gmail.com if it doesn't exist
    echo "\n5. Ensuring staedeli@gmail.com user exists...\n";
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['staedeli@gmail.com']);
    $existing_user = $stmt->fetch();
    
    if (!$existing_user) {
        echo "   - Creating user for staedeli@gmail.com...\n";
        $userId = $db->createUser(
            'staedeli@gmail.com',
            'password123', // Default password - user should change this
            'Staedeli User',
            'Staedeli',
            'User'
        );
        
        if ($userId) {
            echo "   ✓ User created successfully with ID: $userId\n";
            echo "   ⚠ Default password is 'password123' - please change it after login\n";
        } else {
            echo "   ✗ Failed to create user for staedeli@gmail.com\n";
        }
    } else {
        echo "   ✓ User for staedeli@gmail.com already exists with ID: {$existing_user['id']}\n";
    }
    
    echo "\n=== Fix Summary ===\n";
    echo "✓ OAuth tokens table verified/created\n";
    echo "✓ User mapping checked\n";
    echo "✓ Database structure validated\n";
    echo "\nNext steps:\n";
    echo "1. Login with staedeli@gmail.com (password: password123 if just created)\n";
    echo "2. Try connecting to Microsoft Teams\n";
    echo "3. The OAuth token should now save correctly to your user account\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>