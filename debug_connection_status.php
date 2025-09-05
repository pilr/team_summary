<?php
/**
 * Debug Teams connection status detection
 */

session_start();
require_once 'database_helper.php';
require_once 'persistent_teams_service.php';
require_once 'error_logger.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "<p>❌ User not logged in. Please log in first.</p>";
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<p>❌ No user_id in session.</p>";
    exit();
}

echo "<h1>Teams Connection Status Debug</h1>";
echo "<p>User ID: $user_id</p>";
echo "<p>User Name: " . ($_SESSION['user_name'] ?? 'Unknown') . "</p>";

try {
    // Test 1: Direct database check
    echo "<h2>Test 1: Direct Database Token Check</h2>";
    $db = new DatabaseHelper();
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    
    if ($token_info) {
        echo "<p>✅ Token found in database</p>";
        echo "<p>Token Type: " . htmlspecialchars($token_info['token_type']) . "</p>";
        echo "<p>Expires At: " . htmlspecialchars($token_info['expires_at']) . "</p>";
        echo "<p>Created At: " . htmlspecialchars($token_info['created_at']) . "</p>";
        echo "<p>Updated At: " . htmlspecialchars($token_info['updated_at']) . "</p>";
        echo "<p>Scope: " . htmlspecialchars($token_info['scope']) . "</p>";
        
        // Check if token is expired
        $expires_at = new DateTime($token_info['expires_at']);
        $now = new DateTime();
        $is_expired = $now >= $expires_at;
        echo "<p>Token Status: " . ($is_expired ? "❌ EXPIRED" : "✅ VALID") . "</p>";
        echo "<p>Current Time: " . $now->format('Y-m-d H:i:s') . "</p>";
        echo "<p>Token Expires: " . $expires_at->format('Y-m-d H:i:s') . "</p>";
        
    } else {
        echo "<p>❌ No token found in database</p>";
    }
    
    // Test 2: Persistent service check
    echo "<h2>Test 2: Persistent Service Status Check</h2>";
    global $persistentTeamsService;
    $status = $persistentTeamsService->getUserTeamsStatus($user_id);
    
    echo "<p>Connection Status: " . htmlspecialchars($status['status']) . "</p>";
    if (isset($status['expires_at'])) {
        echo "<p>Expires At: " . htmlspecialchars($status['expires_at']) . "</p>";
    }
    
    // Test 3: API connection test
    if ($status['status'] === 'connected') {
        echo "<h2>Test 3: API Access Test</h2>";
        $api_test = $persistentTeamsService->testUserTeamsAccess($user_id);
        echo "<p>API Test Result: " . ($api_test ? "✅ SUCCESS" : "❌ FAILED") . "</p>";
    }
    
    // Test 4: Simulate the account page AJAX call
    echo "<h2>Test 4: Account Page AJAX Call Simulation</h2>";
    
    // Simulate what the JavaScript does
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/api/check_teams_connection.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>HTTP Code: $http_code</p>";
    if ($response) {
        $json_response = json_decode($response, true);
        echo "<p>API Response: </p>";
        echo "<pre>" . htmlspecialchars(json_encode($json_response, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<p>❌ No response from API</p>";
    }
    
    // Test 5: Show all tokens for this user
    echo "<h2>Test 5: All OAuth Tokens for User</h2>";
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $all_tokens = $stmt->fetchAll();
    $stmt->closeCursor();
    
    if ($all_tokens) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Provider</th><th>Token Type</th><th>Expires At</th><th>Created At</th><th>Updated At</th></tr>";
        foreach ($all_tokens as $token) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($token['id']) . "</td>";
            echo "<td>" . htmlspecialchars($token['provider']) . "</td>";
            echo "<td>" . htmlspecialchars($token['token_type']) . "</td>";
            echo "<td>" . htmlspecialchars($token['expires_at']) . "</td>";
            echo "<td>" . htmlspecialchars($token['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($token['updated_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ No tokens found for this user</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    ErrorLogger::logTeamsError("debug_connection", $e->getMessage(), [
        'user_id' => $user_id,
        'exception_trace' => $e->getTraceAsString()
    ]);
}
?>

<style>
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
</style>