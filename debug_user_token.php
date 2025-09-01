<?php
session_start();
require_once 'database_helper.php';
require_once 'user_teams_api.php';

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

echo "=== Debug User Token ===\n";
echo "User ID: $user_id\n";
echo "User Name: " . ($_SESSION['user_name'] ?? 'Unknown') . "\n\n";

// Check database directly
try {
    global $db;
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    
    if ($token_info) {
        echo "✓ Token found in database\n";
        echo "  - Token Type: " . $token_info['token_type'] . "\n";
        echo "  - Expires At: " . $token_info['expires_at'] . "\n";
        echo "  - Created At: " . $token_info['created_at'] . "\n";
        echo "  - Has Refresh Token: " . (!empty($token_info['refresh_token']) ? 'Yes' : 'No') . "\n";
        
        // Check if expired with detailed timezone info
        $expires_at = new DateTime($token_info['expires_at']);
        $now = new DateTime();
        $is_expired = $now >= $expires_at;
        echo "  - Is Expired: " . ($is_expired ? 'Yes' : 'No') . "\n";
        echo "  - Current Time: " . $now->format('Y-m-d H:i:s T') . "\n";
        echo "  - Expires Time: " . $expires_at->format('Y-m-d H:i:s T') . "\n";
        echo "  - Server Timezone: " . date_default_timezone_get() . "\n";
        
        if ($is_expired) {
            $time_diff = $now->diff($expires_at);
            echo "  - Expired " . $time_diff->format('%h hours %i minutes ago') . "\n";
        } else {
            $time_diff = $expires_at->diff($now);
            echo "  - Expires in " . $time_diff->format('%h hours %i minutes') . "\n";
        }
        
    } else {
        echo "✗ No token found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error checking token: " . $e->getMessage() . "\n";
}

// Test UserTeamsAPI
echo "\n=== UserTeamsAPI Test ===\n";
try {
    $userAPI = new UserTeamsAPIHelper($user_id);
    
    $isConnected = $userAPI->isConnected();
    echo "isConnected(): " . ($isConnected ? 'true' : 'false') . "\n";
    
    if ($isConnected) {
        echo "Testing API calls...\n";
        
        $teams = $userAPI->getUserTeams();
        echo "Teams found: " . count($teams) . "\n";
        
        $channels = $userAPI->getAllChannels();
        echo "Channels found: " . count($channels) . "\n";
        
        if (!empty($channels)) {
            echo "First few channels:\n";
            foreach (array_slice($channels, 0, 3) as $channel) {
                echo "  - " . $channel['displayName'] . " (" . $channel['teamName'] . ")\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error with UserTeamsAPI: " . $e->getMessage() . "\n";
}

echo "\n=== Database Token Validation ===\n";
$isValid = $db->isTokenValid($user_id, 'microsoft');
echo "isTokenValid(): " . ($isValid ? 'true' : 'false') . "\n";

echo "\n=== Direct Database Query ===\n";
try {
    $pdo = $db->getPDO();
    $stmt = $pdo->prepare("SELECT expires_at, NOW() as db_now, expires_at > NOW() as is_valid FROM oauth_tokens WHERE user_id = ? AND provider = ?");
    $stmt->execute([$user_id, 'microsoft']);
    $result = $stmt->fetch();
    
    if ($result) {
        echo "Database expires_at: " . $result['expires_at'] . "\n";
        echo "Database NOW(): " . $result['db_now'] . "\n";
        echo "Database comparison (expires_at > NOW()): " . ($result['is_valid'] ? 'true' : 'false') . "\n";
    }
} catch (Exception $e) {
    echo "Database query error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>