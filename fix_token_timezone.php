<?php
session_start();
require_once 'database_helper.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "Not logged in\n";
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "No user_id in session\n";
    exit();
}

echo "=== Fix Token Timezone ===\n";
echo "User ID: $user_id\n\n";

try {
    global $db;
    $pdo = $db->getPDO();
    
    // Get current token
    $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE user_id = ? AND provider = 'microsoft'");
    $stmt->execute([$user_id]);
    $token = $stmt->fetch();
    
    if (!$token) {
        echo "No token found for user $user_id\n";
        exit();
    }
    
    echo "Current token:\n";
    echo "  - Expires At: " . $token['expires_at'] . "\n";
    echo "  - Created At: " . $token['created_at'] . "\n";
    
    // Parse the current expiration time and assume it was stored in UTC
    $current_expires = new DateTime($token['expires_at'], new DateTimeZone('UTC'));
    echo "  - Current Expires UTC: " . $current_expires->format('Y-m-d H:i:s T') . "\n";
    
    // Check if token is still valid in UTC
    $now_utc = new DateTime('now', new DateTimeZone('UTC'));
    $is_valid_utc = $now_utc < $current_expires;
    
    echo "  - Now UTC: " . $now_utc->format('Y-m-d H:i:s T') . "\n";
    echo "  - Valid in UTC: " . ($is_valid_utc ? 'Yes' : 'No') . "\n";
    
    if ($is_valid_utc) {
        echo "\n✓ Token is valid when properly interpreted as UTC time\n";
        echo "No fix needed - the issue is in validation logic, not token storage.\n";
    } else {
        echo "\n✗ Token is expired even in UTC\n";
        echo "You need to reconnect your Microsoft account.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>