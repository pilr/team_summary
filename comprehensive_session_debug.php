<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .section { border: 1px solid #ccc; margin: 10px 0; padding: 15px; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .highlight { background: yellow; padding: 2px; }
    </style>
</head>
<body>

<h1>🔍 Comprehensive Session Debug</h1>

<?php
session_start();
require_once 'database_helper.php';
require_once 'error_logger.php';

echo "<div class='section'>";
echo "<h2>1. Current Session State</h2>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '<span class="success">Active</span>' : '<span class="error">Inactive</span>') . "<br><br>";

if (!empty($_SESSION)) {
    echo "<strong>Session Data:</strong><br>";
    foreach ($_SESSION as $key => $value) {
        $display_value = is_array($value) ? json_encode($value) : htmlspecialchars($value);
        if ($key === 'user_email') {
            echo "<span class='highlight'>$key: $display_value</span><br>";
        } else {
            echo "$key: $display_value<br>";
        }
    }
} else {
    echo "<span class='warning'>Session is empty</span>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>2. Expected vs Actual</h2>";
echo "<strong>You logged in as:</strong> <span class='highlight'>pil.rollano@seriousweb.ch</span><br>";
echo "<strong>Session shows:</strong> <span class='highlight'>" . ($_SESSION['user_email'] ?? 'Not set') . "</span><br>";

if (isset($_SESSION['user_email'])) {
    if ($_SESSION['user_email'] === 'pil.rollano@seriousweb.ch') {
        echo "<span class='success'>✓ Session email is CORRECT</span><br>";
    } else {
        echo "<span class='error'>✗ SESSION MISMATCH DETECTED!</span><br>";
    }
} else {
    echo "<span class='warning'>⚠ No user_email in session</span><br>";
}
echo "</div>";

try {
    global $db;
    
    echo "<div class='section'>";
    echo "<h2>3. Database User Verification</h2>";
    
    // Check session user in database
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $db->getPDO()->prepare("SELECT id, email, display_name, status, last_login FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $session_user = $stmt->fetch();
        
        if ($session_user) {
            echo "<strong>Database User for Session ID {$user_id}:</strong><br>";
            echo "Email: <span class='highlight'>{$session_user['email']}</span><br>";
            echo "Name: {$session_user['display_name']}<br>";
            echo "Status: {$session_user['status']}<br>";
            echo "Last Login: {$session_user['last_login']}<br>";
        } else {
            echo "<span class='error'>✗ Session user ID {$user_id} not found in database!</span><br>";
        }
    }
    
    // Check both specific users
    $users_to_check = ['pil.rollano@seriousweb.ch', 'staedeli@gmail.com'];
    foreach ($users_to_check as $email) {
        $stmt = $db->getPDO()->prepare("SELECT id, email, display_name, status, last_login FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        echo "<br><strong>User: {$email}</strong><br>";
        if ($user) {
            echo "ID: {$user['id']}<br>";
            echo "Display Name: {$user['display_name']}<br>";
            echo "Status: {$user['status']}<br>";
            echo "Last Login: " . ($user['last_login'] ?: 'Never') . "<br>";
            
            // Check if this matches current session
            $current_session_id = $_SESSION['user_id'] ?? null;
            if ($current_session_id == $user['id']) {
                echo "<span class='success'>>>> THIS IS THE CURRENT SESSION USER <<<<</span><br>";
            }
        } else {
            echo "<span class='error'>Not found in database</span><br>";
        }
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>4. All Users in Database</h2>";
    $stmt = $db->getPDO()->query("SELECT id, email, display_name, status, last_login FROM users ORDER BY last_login DESC");
    $all_users = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Email</th><th>Display Name</th><th>Status</th><th>Last Login</th><th>Current Session?</th></tr>";
    
    $current_session_id = $_SESSION['user_id'] ?? null;
    foreach ($all_users as $user) {
        $is_current = ($current_session_id == $user['id']) ? '<span class="success">YES</span>' : '';
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['display_name']) . "</td>";
        echo "<td>{$user['status']}</td>";
        echo "<td>" . ($user['last_login'] ?: 'Never') . "</td>";
        echo "<td>{$is_current}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2>Database Error</h2>";
    echo "<span class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>5. Cookies</h2>";
echo "<strong>Session Cookie:</strong> " . (isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : 'Not set') . "<br>";
echo "<strong>Remember Email:</strong> " . (isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : 'Not set') . "<br>";
echo "<strong>All Cookies:</strong><br>";
if (!empty($_COOKIE)) {
    foreach ($_COOKIE as $name => $value) {
        echo htmlspecialchars($name) . " = " . htmlspecialchars($value) . "<br>";
    }
} else {
    echo "No cookies found<br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>6. Possible Causes & Solutions</h2>";
echo "<strong>If you see a session mismatch:</strong><br>";
echo "• The session wasn't properly cleared between logins<br>";
echo "• Browser cached old session data<br>";
echo "• Multiple tabs/windows with different sessions<br>";
echo "• Session fixation or persistence issue<br><br>";

echo "<strong>Solutions to try:</strong><br>";
echo "1. <a href='force_session_reset.php'>Force complete session reset</a><br>";
echo "2. Clear all browser cookies and cache<br>";
echo "3. Use incognito/private browsing window<br>";
echo "4. Check if you have multiple tabs open with different logins<br>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>7. Quick Actions</h2>";
echo "<a href='force_session_reset.php' style='background: red; color: white; padding: 5px; text-decoration: none;'>🔄 Force Session Reset</a> ";
echo "<a href='login.php' style='background: blue; color: white; padding: 5px; text-decoration: none;'>🔑 Go to Login</a> ";
echo "<a href='index.php' style='background: green; color: white; padding: 5px; text-decoration: none;'>🏠 Go to Dashboard</a>";
echo "</div>";

?>

<script>
// Auto-refresh every 30 seconds to monitor session changes
setTimeout(function() {
    location.reload();
}, 30000);
</script>

</body>
</html>