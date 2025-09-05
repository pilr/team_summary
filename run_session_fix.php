<?php
// Run the session fix script
echo "<!DOCTYPE html><html><head><title>Session Fix</title></head><body>";
echo "<h2>Running Session Fix...</h2>";
echo "<pre>";

// Start output buffering to capture all output
ob_start();

// Include and run the fix
require_once 'fix_session_user_mapping.php';

// Get the output
$output = ob_get_clean();

// Display it
echo htmlspecialchars($output);

echo "</pre>";
echo "<p><a href='login.php'>← Back to Login</a></p>";
echo "</body></html>";
?>