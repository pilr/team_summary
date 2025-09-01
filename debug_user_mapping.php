<?php
session_start();
require_once 'database_helper.php';

echo "=== Debug User Mapping ===\n";

// Check current dashboard login session
echo "Current Dashboard Session:\n";
echo "  - logged_in: " . (isset($_SESSION['logged_in']) ? ($_SESSION['logged_in'] ? 'true' : 'false') : 'not set') . "\n";
echo "  - user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";
echo "  - user_name: " . ($_SESSION['user_name'] ?? 'not set') . "\n";
echo "  - user_email: " . ($_SESSION['user_email'] ?? 'not set') . "\n";
echo "  - login_method: " . ($_SESSION['login_method'] ?? 'not set') . "\n";

$dashboard_user_id = $_SESSION['user_id'] ?? null;

if (!$dashboard_user_id) {
    echo "\n❌ No dashboard user logged in\n";
    exit();
}

echo "\n=== OAuth Tokens Table ===\n";
try {
    global $db;
    $pdo = $db->getPDO();
    
    // Check all oauth tokens
    $stmt = $pdo->query("SELECT user_id, provider, expires_at, created_at FROM oauth_tokens ORDER BY created_at DESC");
    $tokens = $stmt->fetchAll();
    
    echo "All OAuth tokens in database:\n";
    if (empty($tokens)) {
        echo "  (No tokens found)\n";
    } else {
        foreach ($tokens as $token) {
            echo "  - User ID: {$token['user_id']}, Provider: {$token['provider']}, Expires: {$token['expires_at']}, Created: {$token['created_at']}\n";
        }
    }
    
    // Check token for current dashboard user
    echo "\nToken for current dashboard user (ID: $dashboard_user_id):\n";
    $token_info = $db->getOAuthToken($dashboard_user_id, 'microsoft');
    
    if ($token_info) {
        echo "  ✓ Microsoft token found for dashboard user $dashboard_user_id\n";
        echo "    - Token Type: " . $token_info['token_type'] . "\n";
        echo "    - Expires At: " . $token_info['expires_at'] . "\n";
        echo "    - Created At: " . $token_info['created_at'] . "\n";
        echo "    - Has Refresh Token: " . (!empty($token_info['refresh_token']) ? 'Yes' : 'No') . "\n";
        
        // Check if valid
        $isValid = $db->isTokenValid($dashboard_user_id, 'microsoft');
        echo "    - Is Valid: " . ($isValid ? 'Yes' : 'No') . "\n";
        
    } else {
        echo "  ❌ No Microsoft token found for dashboard user $dashboard_user_id\n";
        echo "  This means the Microsoft OAuth flow saved the token with a different user_id\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Users Table Check ===\n";
try {
    // Check users table to see user info
    $stmt = $pdo->prepare("SELECT id, email, display_name FROM users WHERE id = ?");
    $stmt->execute([$dashboard_user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "Dashboard user details:\n";
        echo "  - ID: {$user['id']}\n";
        echo "  - Email: {$user['email']}\n";
        echo "  - Display Name: {$user['display_name']}\n";
    } else {
        echo "❌ Dashboard user ID $dashboard_user_id not found in users table\n";
    }
} catch (Exception $e) {
    echo "Users table error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>