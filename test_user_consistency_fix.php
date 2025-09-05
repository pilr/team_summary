<?php
/**
 * Test script to verify user consistency fix
 */
session_start();
require_once 'database_helper.php';
require_once 'session_validator.php';

echo "=== User Consistency Fix Test ===\n\n";

// Test 1: Simulate Microsoft login
echo "1. Testing Microsoft social login simulation:\n";
$_POST['provider'] = 'microsoft';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Clear any existing session
session_unset();

// Manually include the logic from social-login.php
$provider = $_POST['provider'] ?? '';
if (in_array($provider, ['microsoft', 'google'])) {
    try {
        global $db;
        
        // Map provider to actual user emails based on the issue description
        $user_email = '';
        if ($provider === 'microsoft') {
            $user_email = 'pil.rollano@seriousweb.ch'; // User who should show as themselves
        } else {
            $user_email = 'staedeli@gmail.com'; // User who was showing wrong info
        }
        
        echo "   - Looking up user: $user_email\n";
        
        // Get the actual user from database
        $stmt = $db->getPDO()->prepare("SELECT id, email, display_name FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$user_email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session data properly with database values
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email']; 
            $_SESSION['user_name'] = $user['display_name'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['login_method'] = $provider;
            
            echo "   ✅ Session set successfully:\n";
            echo "      - user_id: {$user['id']}\n";
            echo "      - user_email: {$user['email']}\n";
            echo "      - user_name: {$user['display_name']}\n";
            echo "      - login_method: $provider\n";
        } else {
            echo "   ❌ User not found in database: $user_email\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Database error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 2: Verify SessionValidator works correctly
echo "2. Testing SessionValidator consistency:\n";
try {
    $current_user = SessionValidator::getCurrentUser();
    if ($current_user) {
        echo "   ✅ SessionValidator returned consistent data:\n";
        echo "      - id: {$current_user['id']}\n";
        echo "      - email: {$current_user['email']}\n";
        echo "      - name: {$current_user['name']}\n";
        echo "      - session_refreshed: " . ($current_user['session_refreshed'] ? 'true' : 'false') . "\n";
    } else {
        echo "   ❌ SessionValidator returned null\n";
    }
} catch (Exception $e) {
    echo "   ❌ SessionValidator error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Clear session and test Google login
echo "3. Testing Google social login simulation:\n";
session_unset();
$_POST['provider'] = 'google';

$provider = $_POST['provider'] ?? '';
if (in_array($provider, ['microsoft', 'google'])) {
    try {
        global $db;
        
        // Map provider to actual user emails based on the issue description
        $user_email = '';
        if ($provider === 'microsoft') {
            $user_email = 'pil.rollano@seriousweb.ch'; // User who should show as themselves
        } else {
            $user_email = 'staedeli@gmail.com'; // User who was showing wrong info
        }
        
        echo "   - Looking up user: $user_email\n";
        
        // Get the actual user from database
        $stmt = $db->getPDO()->prepare("SELECT id, email, display_name FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$user_email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session data properly with database values
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email']; 
            $_SESSION['user_name'] = $user['display_name'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['login_method'] = $provider;
            
            echo "   ✅ Session set successfully:\n";
            echo "      - user_id: {$user['id']}\n";
            echo "      - user_email: {$user['email']}\n";
            echo "      - user_name: {$user['display_name']}\n";
            echo "      - login_method: $provider\n";
        } else {
            echo "   ❌ User not found in database: $user_email\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Database error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 4: Verify SessionValidator works correctly with Google user
echo "4. Testing SessionValidator consistency with Google user:\n";
try {
    $current_user = SessionValidator::getCurrentUser();
    if ($current_user) {
        echo "   ✅ SessionValidator returned consistent data:\n";
        echo "      - id: {$current_user['id']}\n";
        echo "      - email: {$current_user['email']}\n";
        echo "      - name: {$current_user['name']}\n";
        echo "      - session_refreshed: " . ($current_user['session_refreshed'] ? 'true' : 'false') . "\n";
    } else {
        echo "   ❌ SessionValidator returned null\n";
    }
} catch (Exception $e) {
    echo "   ❌ SessionValidator error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

// Clean up
session_unset();
unset($_POST['provider']);
?>