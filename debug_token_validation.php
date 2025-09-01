<?php
session_start();
require_once 'database_helper.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "Not logged in\n";
    exit();
}

echo "=== Debug Token Validation ===\n";
echo "User ID: $user_id\n\n";

try {
    global $db;
    $pdo = $db->getPDO();
    
    // Get the exact query that isTokenValid uses
    $stmt = $pdo->prepare("
        SELECT expires_at, NOW() as db_now, 
               (expires_at > NOW()) as is_valid
        FROM oauth_tokens 
        WHERE user_id = ? AND provider = ?
    ");
    $stmt->execute([$user_id, 'microsoft']);
    $result = $stmt->fetch();
    
    if (!$result) {
        echo "No token found\n";
        exit();
    }
    
    echo "Database Query Results:\n";
    echo "  - expires_at: " . $result['expires_at'] . "\n";
    echo "  - NOW(): " . $result['db_now'] . "\n";
    echo "  - is_valid (expires_at > NOW()): " . ($result['is_valid'] ? 'true' : 'false') . "\n";
    echo "  - is_valid raw value: " . var_export($result['is_valid'], true) . "\n";
    
    // Test the exact logic from isTokenValid()
    echo "\nTesting isTokenValid() logic:\n";
    $bool_result = (bool)$result['is_valid'];
    echo "  - (bool)is_valid: " . ($bool_result ? 'true' : 'false') . "\n";
    
    // Call the actual method
    echo "\nActual isTokenValid() result:\n";
    $actual_result = $db->isTokenValid($user_id, 'microsoft');
    echo "  - Method result: " . ($actual_result ? 'true' : 'false') . "\n";
    
    // Check data types
    echo "\nData type analysis:\n";
    echo "  - is_valid type: " . gettype($result['is_valid']) . "\n";
    echo "  - is_valid value: " . json_encode($result['is_valid']) . "\n";
    
    // Manual time comparison
    echo "\nManual time comparison:\n";
    $expires = new DateTime($result['expires_at']);
    $now = new DateTime($result['db_now']);
    $manual_valid = $now < $expires;
    echo "  - Manual PHP comparison: " . ($manual_valid ? 'true' : 'false') . "\n";
    echo "  - Expires: " . $expires->format('Y-m-d H:i:s T') . "\n";
    echo "  - Now: " . $now->format('Y-m-d H:i:s T') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>