<?php
// Quick test to verify new credentials are loaded correctly
require_once 'teams_config.php';

echo "<h2>Teams Credentials Test</h2>";
echo "<h3>Current Configuration:</h3>";
echo "<ul>";
echo "<li><strong>Client ID:</strong> " . (TEAMS_CLIENT_ID ? '✅ Loaded' : '❌ Missing') . "</li>";
echo "<li><strong>Client Secret:</strong> " . (TEAMS_CLIENT_SECRET ? '✅ Loaded' : '❌ Missing') . "</li>";
echo "<li><strong>Secret ID:</strong> " . (TEAMS_SECRET_ID ? '✅ Loaded' : '❌ Missing') . "</li>";
echo "<li><strong>Tenant ID:</strong> " . (TEAMS_TENANT_ID ? '✅ Loaded' : '❌ Missing') . "</li>";
echo "</ul>";

echo "<h3>File Status:</h3>";
echo "<ul>";
echo "<li><strong>team_summary_ke.txt:</strong> " . (file_exists(__DIR__ . '/team_summary_ke.txt') ? '✅ Found' : '❌ Missing') . "</li>";
echo "</ul>";

if (file_exists(__DIR__ . '/team_summary_ke.txt')) {
    echo "<h3>New Credentials Preview:</h3>";
    echo "<ul>";
    if (TEAMS_CLIENT_ID && TEAMS_CLIENT_ID !== 'YOUR_CLIENT_ID_HERE') {
        echo "<li><strong>Client ID:</strong> " . substr(TEAMS_CLIENT_ID, 0, 8) . "..." . substr(TEAMS_CLIENT_ID, -8) . "</li>";
    }
    if (TEAMS_TENANT_ID && TEAMS_TENANT_ID !== 'YOUR_TENANT_ID_HERE') {
        echo "<li><strong>Tenant ID:</strong> " . substr(TEAMS_TENANT_ID, 0, 8) . "..." . substr(TEAMS_TENANT_ID, -8) . "</li>";
    }
    echo "</ul>";
    
    echo "<div style='color: green; font-weight: bold; margin: 20px 0;'>";
    echo "✅ SUCCESS: New credentials from team_summary_ke.txt are loaded successfully!";
    echo "</div>";
} else {
    echo "<div style='color: red; font-weight: bold; margin: 20px 0;'>";
    echo "❌ ERROR: team_summary_ke.txt file not found. Please ensure the file exists.";
    echo "</div>";
}
?>