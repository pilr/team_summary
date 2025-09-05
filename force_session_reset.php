<?php
session_start();
require_once 'database_helper.php';

echo "=== Force Session Reset ===\n\n";

// 1. Show current session before reset
echo "1. Current Session Before Reset:\n";
if (!empty($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        echo "   - $key: " . (is_string($value) ? "'$value'" : json_encode($value)) . "\n";
    }
} else {
    echo "   (Session is already empty)\n";
}

// 2. Force complete session destruction
echo "\n2. Destroying Session...\n";
echo "   - Session ID before: " . session_id() . "\n";

// Unset all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session
session_start();
session_regenerate_id(true);

echo "   - Session ID after: " . session_id() . "\n";
echo "   ✓ Session completely reset\n";

// 3. Clear any remember me cookies
echo "\n3. Clearing Remember Me Cookies...\n";
if (isset($_COOKIE['remember_email'])) {
    setcookie('remember_email', '', time() - 3600, '/');
    echo "   ✓ Remember email cookie cleared\n";
} else {
    echo "   - No remember email cookie found\n";
}

// 4. Verify session is clean
echo "\n4. Verifying Clean Session:\n";
if (empty($_SESSION)) {
    echo "   ✓ Session is completely empty\n";
} else {
    echo "   ⚠ Session still contains data:\n";
    foreach ($_SESSION as $key => $value) {
        echo "     - $key: $value\n";
    }
}

// 5. Test fresh login capability
echo "\n5. Testing Fresh Login Capability:\n";
try {
    global $db;
    
    // Test if we can query users without session interference
    $stmt = $db->getPDO()->prepare("SELECT id, email, display_name FROM users WHERE email = ?");
    $stmt->execute(['pil.rollano@seriousweb.ch']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "   ✓ Can query pil.rollano@seriousweb.ch - ID: {$user['id']}\n";
    } else {
        echo "   ✗ Cannot find pil.rollano@seriousweb.ch user\n";
    }
    
    $stmt->execute(['staedeli@gmail.com']);
    $user2 = $stmt->fetch();
    
    if ($user2) {
        echo "   ✓ Can query staedeli@gmail.com - ID: {$user2['id']}\n";
    } else {
        echo "   ⚠ Cannot find staedeli@gmail.com user (may need to be created)\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Database test failed: " . $e->getMessage() . "\n";
}

echo "\n=== Session Reset Complete ===\n";
echo "Next steps:\n";
echo "1. Navigate to login.php\n";
echo "2. Login with pil.rollano@seriousweb.ch\n";
echo "3. Verify session shows correct user\n";
echo "4. Check debug_current_session.php to confirm\n";

// Create a simple redirect
echo "\n<p><a href='login.php'>← Go to Login Page</a></p>";
echo "<p><a href='debug_current_session.php'>Debug Current Session</a></p>";
?>