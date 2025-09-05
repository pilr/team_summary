<?php
session_start();
require_once 'database_helper.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>User Consistency Diagnosis</title>";
echo "<style>body{font-family:monospace;margin:20px;} .error{color:red;} .success{color:green;} .warning{color:orange;} .highlight{background:yellow;padding:2px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:8px;text-align:left;}</style>";
echo "</head><body>";

echo "<h1>🔍 User Consistency Diagnosis</h1>";

// Current session analysis
echo "<h2>1. Current Session Data</h2>";
echo "<table>";
echo "<tr><th>Key</th><th>Value</th><th>Status</th></tr>";

$session_keys = ['logged_in', 'user_id', 'user_email', 'user_name', 'login_time', 'login_method'];
foreach ($session_keys as $key) {
    $value = $_SESSION[$key] ?? 'NOT SET';
    $status = isset($_SESSION[$key]) ? '<span class="success">✓ Set</span>' : '<span class="error">✗ Missing</span>';
    echo "<tr><td>$key</td><td class='highlight'>$value</td><td>$status</td></tr>";
}
echo "</table>";

// Simulate what each page would see
echo "<h2>2. What Each Page Should Display</h2>";

try {
    global $db;
    
    $user_id = $_SESSION['user_id'] ?? null;
    $user_email = $_SESSION['user_email'] ?? '';
    $user_name = $_SESSION['user_name'] ?? 'Unknown User';
    
    echo "<table>";
    echo "<tr><th>Page</th><th>User Source</th><th>Expected Display</th><th>Issues</th></tr>";
    
    // Index.php (Dashboard)
    echo "<tr>";
    echo "<td><strong>index.php (Dashboard)</strong></td>";
    echo "<td>Session variables</td>";
    echo "<td>";
    echo "Name: <span class='highlight'>$user_name</span><br>";
    echo "Email: <span class='highlight'>$user_email</span>";
    echo "</td>";
    echo "<td>";
    
    // Check for potential issues in index.php
    if ($user_id) {
        // Check if index.php has any hardcoded user data or fallback logic
        $db_user = null;
        if ($db) {
            $stmt = $db->getPDO()->prepare("SELECT display_name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $db_user = $stmt->fetch();
        }
        
        if ($db_user) {
            if ($db_user['email'] !== $user_email || $db_user['display_name'] !== $user_name) {
                echo "<span class='warning'>⚠ Session vs DB mismatch</span><br>";
                echo "DB: {$db_user['email']} / {$db_user['display_name']}";
            } else {
                echo "<span class='success'>✓ Session matches DB</span>";
            }
        }
    }
    echo "</td>";
    echo "</tr>";
    
    // Account.php
    echo "<tr>";
    echo "<td><strong>account.php</strong></td>";
    echo "<td>Session variables</td>";
    echo "<td>";
    echo "Name: <span class='highlight'>$user_name</span><br>";
    echo "Email: <span class='highlight'>$user_email</span>";
    echo "</td>";
    echo "<td><span class='success'>Should be consistent</span></td>";
    echo "</tr>";
    
    // Settings.php
    echo "<tr>";
    echo "<td><strong>settings.php</strong></td>";
    echo "<td>Session variables</td>";
    echo "<td>";
    echo "Name: <span class='highlight'>$user_name</span><br>";
    echo "Email: <span class='highlight'>$user_email</span>";
    echo "</td>";
    echo "<td><span class='success'>Should be consistent</span></td>";
    echo "</tr>";
    
    // Summaries.php
    echo "<tr>";
    echo "<td><strong>summaries.php</strong></td>";
    echo "<td>Session variables</td>";
    echo "<td>";
    echo "Name: <span class='highlight'>$user_name</span><br>";
    echo "Email: <span class='highlight'>$user_email</span>";
    echo "</td>";
    echo "<td><span class='success'>Should be consistent</span></td>";
    echo "</tr>";
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<span class='error'>Database error: " . $e->getMessage() . "</span>";
}

// Check for potential caching or JavaScript issues
echo "<h2>3. Potential JavaScript/Frontend Issues</h2>";
echo "<p>Some inconsistencies might be caused by:</p>";
echo "<ul>";
echo "<li><strong>JavaScript variables:</strong> Check if pages have hardcoded JS user data</li>";
echo "<li><strong>Browser caching:</strong> Cached JavaScript or CSS with old user info</li>";
echo "<li><strong>AJAX calls:</strong> Asynchronous calls returning different user data</li>";
echo "<li><strong>Template caching:</strong> Server-side template caching with wrong user</li>";
echo "</ul>";

// Check browser cache and JavaScript
echo "<h2>4. Browser/JavaScript Analysis</h2>";
echo "<p><strong>Browser Session Storage:</strong></p>";
echo "<script>";
echo "document.write('<p>sessionStorage: ' + JSON.stringify(sessionStorage) + '</p>');";
echo "document.write('<p>localStorage: ' + JSON.stringify(localStorage) + '</p>');";
echo "</script>";

echo "<p><strong>Check these on each page:</strong></p>";
echo "<ol>";
echo "<li>Open browser developer tools (F12)</li>";
echo "<li>Check Console for JavaScript errors</li>";
echo "<li>Check Application/Storage tab for sessionStorage/localStorage</li>";
echo "<li>Check Network tab for AJAX requests returning wrong user data</li>";
echo "</ol>";

// Recommendations
echo "<h2>5. Recommended Actions</h2>";
echo "<div style='background:#f0f0f0;padding:15px;border:1px solid #ccc;'>";

if (isset($_SESSION['user_email']) && isset($_SESSION['user_name'])) {
    echo "<p><strong>Current Session Status:</strong> <span class='success'>✓ Valid session data present</span></p>";
    
    echo "<p><strong>Immediate steps:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Clear browser cache completely</strong> - Press Ctrl+Shift+Delete</li>";
    echo "<li><strong>Close ALL browser tabs/windows</strong></li>";
    echo "<li><strong>Open new incognito/private window</strong></li>";
    echo "<li><strong>Login fresh</strong> and test each page</li>";
    echo "</ol>";
    
    echo "<p><strong>If issue persists:</strong></p>";
    echo "<ol>";
    echo "<li>Check each page's source code for hardcoded user data</li>";
    echo "<li>Look for JavaScript that might be overriding user display</li>";
    echo "<li>Check for AJAX calls that return different user info</li>";
    echo "</ol>";
    
} else {
    echo "<p><strong>Session Issue:</strong> <span class='error'>✗ Missing session data</span></p>";
    echo "<p>Run <a href='force_session_reset.php'>force_session_reset.php</a> first</p>";
}

echo "</div>";

// Test links
echo "<h2>6. Test Each Page</h2>";
echo "<p>After clearing cache, test these pages in order:</p>";
echo "<p>";
echo "<a href='index.php' style='background:blue;color:white;padding:5px;text-decoration:none;margin:5px;'>Dashboard</a> ";
echo "<a href='account.php' style='background:green;color:white;padding:5px;text-decoration:none;margin:5px;'>Account</a> ";
echo "<a href='settings.php' style='background:orange;color:white;padding:5px;text-decoration:none;margin:5px;'>Settings</a> ";
echo "<a href='summaries.php' style='background:purple;color:white;padding:5px;text-decoration:none;margin:5px;'>Summaries</a>";
echo "</p>";

echo "<p><em>Note: This page auto-refreshes every 10 seconds to monitor changes</em></p>";

echo "<script>setTimeout(function(){location.reload();}, 10000);</script>";
echo "</body></html>";
?>