<?php
// Debug login flow to trace authentication process
require_once 'database_helper.php';
require_once 'error_logger.php';

echo "=== Login Flow Debug ===\n\n";

// Test authentication for both users
$test_emails = [
    'pil.rollano@seriousweb.ch',
    'staedeli@gmail.com'
];

try {
    global $db;
    
    echo "1. Testing User Authentication:\n";
    foreach ($test_emails as $email) {
        echo "\n   Testing: $email\n";
        
        // Check if user exists in database
        $stmt = $db->getPDO()->prepare("SELECT id, email, display_name, password_hash, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "   ✓ User exists in database:\n";
            echo "     - ID: {$user['id']}\n";
            echo "     - Email: {$user['email']}\n";
            echo "     - Display Name: {$user['display_name']}\n";
            echo "     - Status: {$user['status']}\n";
            echo "     - Has Password Hash: " . (!empty($user['password_hash']) ? 'Yes' : 'No') . "\n";
            
            // Test authentication method
            try {
                // Note: We won't actually test the password, just check the method
                echo "     - Authentication method available: Yes\n";
            } catch (Exception $authEx) {
                echo "     - Authentication error: " . $authEx->getMessage() . "\n";
            }
            
        } else {
            echo "   ✗ User NOT FOUND in database\n";
        }
    }
    
    // Check for duplicate users or similar emails
    echo "\n2. Checking for Duplicate/Similar Users:\n";
    $stmt = $db->getPDO()->query("SELECT id, email, display_name, status FROM users ORDER BY email");
    $all_users = $stmt->fetchAll();
    
    echo "   All users in database:\n";
    foreach ($all_users as $user) {
        echo "     - ID: {$user['id']}, Email: {$user['email']}, Name: {$user['display_name']}, Status: {$user['status']}\n";
    }
    
    // Look for potential email conflicts
    $email_conflicts = [];
    $seen_emails = [];
    foreach ($all_users as $user) {
        $email = strtolower(trim($user['email']));
        if (isset($seen_emails[$email])) {
            $email_conflicts[] = $email;
        }
        $seen_emails[$email] = $user['id'];
    }
    
    if (!empty($email_conflicts)) {
        echo "\n   ⚠ DUPLICATE EMAIL CONFLICTS FOUND:\n";
        foreach ($email_conflicts as $conflict_email) {
            echo "     - Duplicate email: $conflict_email\n";
        }
    } else {
        echo "   ✓ No duplicate emails found\n";
    }
    
    // Check session handling in authenticateUser method
    echo "\n3. Session Handling Analysis:\n";
    echo "   - Current session_id: " . session_id() . "\n";
    echo "   - Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
    
    // Simulate what happens during login
    echo "\n4. Simulated Login Process Analysis:\n";
    echo "   When logging in as pil.rollano@seriousweb.ch:\n";
    
    $stmt = $db->getPDO()->prepare("SELECT id, email, display_name FROM users WHERE email = ?");
    $stmt->execute(['pil.rollano@seriousweb.ch']);
    $pil_user = $stmt->fetch();
    
    if ($pil_user) {
        echo "     - Database would return User ID: {$pil_user['id']}\n";
        echo "     - Session should be set to:\n";
        echo "       * user_id: {$pil_user['id']}\n";
        echo "       * user_email: {$pil_user['email']}\n";
        echo "       * user_name: {$pil_user['display_name']}\n";
    }
    
    echo "\n   When logging in as staedeli@gmail.com:\n";
    
    $stmt->execute(['staedeli@gmail.com']);
    $staedeli_user = $stmt->fetch();
    
    if ($staedeli_user) {
        echo "     - Database would return User ID: {$staedeli_user['id']}\n";
        echo "     - Session should be set to:\n";
        echo "       * user_id: {$staedeli_user['id']}\n";
        echo "       * user_email: {$staedeli_user['email']}\n";
        echo "       * user_name: {$staedeli_user['display_name']}\n";
    } else {
        echo "     - ✗ staedeli@gmail.com user not found - this could be the issue!\n";
    }
    
    // Check recent error logs for login issues
    echo "\n5. Checking Recent Error Logs:\n";
    if (file_exists('errors.txt')) {
        $error_lines = file('errors.txt');
        $recent_errors = array_slice($error_lines, -20); // Last 20 lines
        
        echo "   Recent error log entries (last 20 lines):\n";
        foreach ($recent_errors as $line) {
            if (stripos($line, 'login') !== false || stripos($line, 'session') !== false || stripos($line, 'auth') !== false) {
                echo "     " . trim($line) . "\n";
            }
        }
    } else {
        echo "   No error log file found\n";
    }
    
} catch (Exception $e) {
    echo "Error during debug: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Analysis Summary ===\n";
echo "Possible causes of session mismatch:\n";
echo "1. staedeli@gmail.com user was created but pil.rollano session is persisting\n";
echo "2. Browser cache/cookie issues\n";
echo "3. Session not being properly cleared between logins\n";
echo "4. User ID mapping issue in database\n";
echo "5. Multiple sessions or session fixation\n";
echo "\nTo fix: Check debug_current_session.php for live session data\n";
?>