<?php
session_start();
require_once 'database_helper.php';
require_once 'error_logger.php';

echo "=== Current Session Debug ===\n\n";

// 1. Display all session data
echo "1. Current Session Data:\n";
echo "   Session ID: " . session_id() . "\n";
echo "   Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
echo "   Full Session Array:\n";
if (!empty($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        if (is_array($value)) {
            echo "   - $key: " . json_encode($value) . "\n";
        } else {
            echo "   - $key: " . (is_string($value) ? "'$value'" : $value) . "\n";
        }
    }
} else {
    echo "   (Session is empty)\n";
}

// 2. Check cookies
echo "\n2. Session Cookies:\n";
echo "   Session Name: " . session_name() . "\n";
echo "   Session Cookie: " . (isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : 'Not set') . "\n";
echo "   All Cookies:\n";
if (!empty($_COOKIE)) {
    foreach ($_COOKIE as $name => $value) {
        echo "   - $name: $value\n";
    }
} else {
    echo "   (No cookies found)\n";
}

// 3. Database verification
echo "\n3. Database User Verification:\n";
try {
    global $db;
    $pdo = $db->getPDO();
    
    // Check if session user exists in database
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        echo "   Session User ID: $user_id\n";
        
        $stmt = $pdo->prepare("SELECT id, email, display_name, status, created_at, last_login FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "   ✓ User found in database:\n";
            echo "     - ID: {$user['id']}\n";
            echo "     - Email: {$user['email']}\n";
            echo "     - Display Name: {$user['display_name']}\n";
            echo "     - Status: {$user['status']}\n";
            echo "     - Created: {$user['created_at']}\n";
            echo "     - Last Login: {$user['last_login']}\n";
            
            // Compare with session data
            $session_email = $_SESSION['user_email'] ?? 'not set';
            if ($user['email'] === $session_email) {
                echo "   ✓ Session email matches database\n";
            } else {
                echo "   ✗ SESSION MISMATCH!\n";
                echo "     - Session email: '$session_email'\n";
                echo "     - Database email: '{$user['email']}'\n";
            }
        } else {
            echo "   ✗ User ID $user_id NOT FOUND in database!\n";
        }
    } else {
        echo "   No user_id in session\n";
    }
    
    // 4. Check for the specific emails mentioned
    echo "\n4. Checking Specific User Accounts:\n";
    $emails_to_check = ['pil.rollano@seriousweb.ch', 'staedeli@gmail.com'];
    
    foreach ($emails_to_check as $email) {
        $stmt = $pdo->prepare("SELECT id, email, display_name, status, created_at, last_login FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "   ✓ {$email}:\n";
            echo "     - User ID: {$user['id']}\n";
            echo "     - Display Name: {$user['display_name']}\n";
            echo "     - Status: {$user['status']}\n";
            echo "     - Last Login: {$user['last_login']}\n";
            
            // Check if this is the current session user
            $current_session_id = $_SESSION['user_id'] ?? null;
            if ($current_session_id == $user['id']) {
                echo "     >>> THIS IS THE CURRENT SESSION USER <<<\n";
            }
        } else {
            echo "   ✗ {$email}: Not found in database\n";
        }
    }
    
    // 5. Check recent login activity
    echo "\n5. Recent Login Activity:\n";
    $stmt = $pdo->query("SELECT u.email, u.display_name, u.last_login FROM users u WHERE u.last_login IS NOT NULL ORDER BY u.last_login DESC LIMIT 5");
    $recent_logins = $stmt->fetchAll();
    
    if (!empty($recent_logins)) {
        foreach ($recent_logins as $login) {
            echo "   - {$login['email']} ({$login['display_name']}) - Last login: {$login['last_login']}\n";
        }
    } else {
        echo "   No recent login records found\n";
    }
    
} catch (Exception $e) {
    echo "   Database error: " . $e->getMessage() . "\n";
}

// 6. Server environment check
echo "\n6. Server Environment:\n";
echo "   PHP Session Save Path: " . session_save_path() . "\n";
echo "   PHP Session GC Maxlifetime: " . ini_get('session.gc_maxlifetime') . " seconds\n";
echo "   Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "   Server: " . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . "\n";

// 7. Check if this is browser session issue
echo "\n7. Browser/Request Info:\n";
echo "   User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') . "\n";
echo "   Remote Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Not set') . "\n";
echo "   Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
echo "   HTTP Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'Not set') . "\n";

echo "\n=== Debug Complete ===\n";
echo "If there's a session mismatch, this indicates the login flow may be:\n";
echo "1. Setting the wrong user_id in the session\n";
echo "2. Using cached/old session data\n";
echo "3. Browser cookie issues\n";
echo "4. Multiple users sharing the same session somehow\n";
?>