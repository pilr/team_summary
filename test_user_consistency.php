<!DOCTYPE html>
<html>
<head>
    <title>User Consistency Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { border: 1px solid #ccc; margin: 10px 0; padding: 15px; background: #f9f9f9; }
        .consistent { color: green; font-weight: bold; }
        .inconsistent { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        iframe { width: 100%; height: 400px; border: 1px solid #ccc; margin: 10px 0; }
    </style>
</head>
<body>

<h1>🔍 User Consistency Test</h1>

<?php
session_start();
require_once 'session_validator.php';

// Get current user from session validator
$current_user = SessionValidator::getCurrentUser();

if (!$current_user) {
    echo "<div class='section'>";
    echo "<h2>❌ No Valid Session</h2>";
    echo "<p>Please <a href='login.php'>login</a> first to test user consistency.</p>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

echo "<div class='section'>";
echo "<h2>✅ Current Session (SessionValidator)</h2>";
echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>User ID</td><td>{$current_user['id']}</td></tr>";
echo "<tr><td>Email</td><td>{$current_user['email']}</td></tr>";
echo "<tr><td>Display Name</td><td>{$current_user['name']}</td></tr>";
echo "<tr><td>Status</td><td>{$current_user['status']}</td></tr>";
echo "<tr><td>Session Refreshed</td><td>" . ($current_user['session_refreshed'] ? 'Yes' : 'No') . "</td></tr>";
echo "</table>";
echo "</div>";

// Test what each page would show
$test_pages = [
    'index.php' => 'Dashboard',
    'account.php' => 'Account', 
    'settings.php' => 'Settings',
    'summaries.php' => 'Summaries'
];

echo "<div class='section'>";
echo "<h2>📊 Page Consistency Check</h2>";
echo "<p>This shows what each page should display with the unified SessionValidator:</p>";
echo "<table>";
echo "<tr><th>Page</th><th>Expected User ID</th><th>Expected Email</th><th>Expected Name</th><th>Status</th></tr>";

foreach ($test_pages as $page => $title) {
    echo "<tr>";
    echo "<td><strong>{$title} ({$page})</strong></td>";
    echo "<td>{$current_user['id']}</td>";
    echo "<td>{$current_user['email']}</td>";
    echo "<td>{$current_user['name']}</td>";
    echo "<td class='consistent'>✅ Should be consistent</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔄 Live Page Test</h2>";
echo "<p>Test each page to verify they show the correct user information:</p>";
echo "<p>";
foreach ($test_pages as $page => $title) {
    echo "<a href='{$page}' target='_blank' style='background: #007cba; color: white; padding: 8px 12px; text-decoration: none; margin: 5px; display: inline-block; border-radius: 3px;'>{$title}</a> ";
}
echo "</p>";
echo "<p><em>Open each page in a new tab and verify they all show the same user information.</em></p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🧪 Debug Information</h2>";
$debug = SessionValidator::debugSession();
echo "<h3>Session Debug Data:</h3>";
echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; overflow: auto;'>";
print_r($debug);
echo "</pre>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🚨 Expected Behavior</h2>";
echo "<p><strong>After the fix:</strong></p>";
echo "<ul>";
echo "<li><span class='consistent'>✅ ALL pages should show: {$current_user['email']}</span></li>";
echo "<li><span class='consistent'>✅ Microsoft Teams connection should use: {$current_user['email']}</span></li>";
echo "<li><span class='consistent'>✅ No more user switching between pages</span></li>";
echo "<li><span class='consistent'>✅ Consistent user display across Dashboard, Account, Settings, Summaries</span></li>";
echo "</ul>";

echo "<p><strong>If you still see inconsistencies:</strong></p>";
echo "<ul>";
echo "<li>Clear browser cache completely (Ctrl+Shift+Delete)</li>";
echo "<li>Close all browser tabs and windows</li>";
echo "<li>Login again in a fresh browser session</li>";
echo "<li>Check browser developer tools console for JavaScript errors</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 Quick Actions</h2>";
echo "<a href='force_session_reset.php' style='background: red; color: white; padding: 8px 12px; text-decoration: none; margin: 5px;'>🔄 Reset Session</a> ";
echo "<a href='comprehensive_session_debug.php' style='background: blue; color: white; padding: 8px 12px; text-decoration: none; margin: 5px;'>🔍 Debug Session</a> ";
echo "<a href='login.php' style='background: green; color: white; padding: 8px 12px; text-decoration: none; margin: 5px;'>🔑 Login Page</a>";
echo "</div>";

?>

<script>
// Auto-refresh every 30 seconds to monitor changes
setTimeout(function() {
    location.reload();
}, 30000);

// Show current time
document.body.insertAdjacentHTML('beforeend', '<p style="text-align: center; color: #666; margin-top: 20px;"><em>Last updated: ' + new Date().toLocaleString() + ' (auto-refresh in 30s)</em></p>');
</script>

</body>
</html>