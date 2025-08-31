<?php
ob_start();
session_start();

echo "<h1>Session Test</h1>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session save path: " . session_save_path() . "</p>";
echo "<p>Session status: " . session_status() . " (2 = active)</p>";

// Test setting and getting session variables
$_SESSION['test'] = 'Session is working!';
echo "<p>Test session variable: " . ($_SESSION['test'] ?? 'NOT SET') . "</p>";

// Show all session variables
echo "<h2>All Session Variables:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test redirect capability
echo "<h2>Headers sent test:</h2>";
if (headers_sent()) {
    echo "<p style='color: red;'>Headers already sent - cannot redirect</p>";
} else {
    echo "<p style='color: green;'>Headers not sent yet - can redirect</p>";
}

echo "<p><a href='login.php'>Back to Login</a></p>";
?>