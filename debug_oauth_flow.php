<?php
/**
 * Debug OAuth Flow - Step by step tracing
 * Simulates the complete Teams connection process
 */
session_start();
require_once 'database_helper.php';
require_once 'teams_config.php';

// Security check
$secret = $_GET['secret'] ?? '';
if ($secret !== 'debug2024') {
    http_response_code(403);
    die('Access denied');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>OAuth Flow Debug</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; margin: 20px; }
        .success { color: #00ff00; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        .info { color: #44aaff; }
        .step { margin: 20px 0; padding: 15px; border: 1px solid #333; background: #2a2a2a; }
        pre { background: #1a1a1a; padding: 10px; border: 1px solid #333; overflow-x: auto; }
        .highlight { background: #333; padding: 5px; }
    </style>
</head>
<body>
<h1>🔍 OAuth Flow Debug Trace</h1>
<p>Time: <?= date('Y-m-d H:i:s T') ?></p>

<?php
try {
    echo "<div class='step'>";
    echo "<h2>Step 1: Session Check</h2>";
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        echo "<div class='success'>✅ User is logged in</div>";
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['user_name'] ?? 'Unknown';
        echo "<div class='info'>User ID: $userId</div>";
        echo "<div class='info'>User Name: $userName</div>";
        
        if (!$userId) {
            echo "<div class='error'>❌ CRITICAL: No user_id in session!</div>";
            echo "<div class='warning'>This will cause oauth_callback.php to fail</div>";
        }
    } else {
        echo "<div class='warning'>⚠️ No user logged in - simulating with test data</div>";
        $userId = 999998; // Test user ID
        $userName = 'Debug User';
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>Step 2: Database Connection Test</h2>";
    
    $db = new DatabaseHelper();
    $pdo = $db->getPDO();
    
    if ($pdo) {
        echo "<div class='success'>✅ Database connected</div>";
        
        // Check table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->rowCount();
        if ($tableCheck > 0) {
            echo "<div class='success'>✅ oauth_tokens table exists</div>";
        } else {
            echo "<div class='error'>❌ oauth_tokens table missing</div>";
        }
    } else {
        echo "<div class='error'>❌ Database connection failed</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>Step 3: Simulate Token Save (like oauth_callback.php)</h2>";
    
    // Simulate what oauth_callback.php does
    $testAccessToken = 'test_access_token_' . time();
    $testRefreshToken = 'test_refresh_token_' . time();
    $expiresIn = 3600;
    $expiresAt = new DateTime('now', new DateTimeZone('UTC'));
    $expiresAt->add(new DateInterval("PT{$expiresIn}S"));
    
    echo "<div class='info'>Simulating token save for user: $userId</div>";
    echo "<div class='info'>Token expires: " . $expiresAt->format('Y-m-d H:i:s') . "</div>";
    
    $tokenSaved = $db->saveOAuthToken(
        $userId,
        'microsoft',
        $testAccessToken,
        $testRefreshToken,
        'Bearer',
        $expiresAt->format('Y-m-d H:i:s'),
        'test_scope'
    );
    
    if ($tokenSaved) {
        echo "<div class='success'>✅ Token save successful</div>";
        
        // Test retrieval
        $retrieved = $db->getOAuthToken($userId, 'microsoft');
        if ($retrieved) {
            echo "<div class='success'>✅ Token retrieval successful</div>";
            echo "<div class='info'>Retrieved expires_at: {$retrieved['expires_at']}</div>";
        } else {
            echo "<div class='error'>❌ Token retrieval failed</div>";
        }
        
        // Clean up
        $pdo->exec("DELETE FROM oauth_tokens WHERE user_id = $userId AND access_token = '$testAccessToken'");
        echo "<div class='success'>✅ Test data cleaned up</div>";
    } else {
        echo "<div class='error'>❌ Token save failed</div>";
        echo "<div class='warning'>This is the likely cause of your connection failure</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>Step 4: Test Persistent Service Integration</h2>";
    
    require_once 'persistent_teams_service.php';
    global $persistentTeamsService;
    
    // Test with another token
    $testAccessToken2 = 'persistent_test_' . time();
    $testRefreshToken2 = 'persistent_refresh_' . time();
    
    $saveResult2 = $db->saveOAuthToken(
        $userId,
        'microsoft',
        $testAccessToken2,
        $testRefreshToken2,
        'Bearer',
        date('Y-m-d H:i:s', time() + 3600),
        'test_scope'
    );
    
    if ($saveResult2) {
        echo "<div class='success'>✅ Token saved for persistent service test</div>";
        
        // Test persistent service methods
        $status = $persistentTeamsService->getUserTeamsStatus($userId);
        echo "<div class='info'>Persistent service status: " . json_encode($status) . "</div>";
        
        if ($status['status'] === 'connected') {
            echo "<div class='success'>✅ Persistent service recognizes connection</div>";
        } else {
            echo "<div class='warning'>⚠️ Persistent service status: {$status['status']}</div>";
        }
        
        // Clean up
        $pdo->exec("DELETE FROM oauth_tokens WHERE user_id = $userId AND access_token = '$testAccessToken2'");
    } else {
        echo "<div class='error'>❌ Persistent service token save failed</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>Step 5: Check Real User Token (if exists)</h2>";
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && $_SESSION['user_id']) {
        $realUserId = $_SESSION['user_id'];
        $realUserToken = $db->getOAuthToken($realUserId, 'microsoft');
        
        if ($realUserToken) {
            echo "<div class='success'>✅ Real user has existing token</div>";
            echo "<div class='info'>Expires: {$realUserToken['expires_at']}</div>";
            
            $expires = new DateTime($realUserToken['expires_at']);
            $now = new DateTime();
            
            if ($expires > $now) {
                echo "<div class='success'>✅ Token is still valid</div>";
                $timeDiff = $expires->diff($now);
                echo "<div class='info'>Valid for: {$timeDiff->h}h {$timeDiff->i}m</div>";
            } else {
                echo "<div class='warning'>⚠️ Token is expired</div>";
                echo "<div class='info'>Expired: " . $expires->format('Y-m-d H:i:s') . "</div>";
                echo "<div class='info'>Current: " . $now->format('Y-m-d H:i:s') . "</div>";
            }
        } else {
            echo "<div class='info'>ℹ️ Real user has no existing token</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ No real user session to check</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>Step 6: Test Connection Status API</h2>";
    
    echo "<div class='info'>Testing check_teams_connection.php logic...</div>";
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && $_SESSION['user_id']) {
        require_once 'persistent_teams_service.php';
        global $persistentTeamsService;
        
        $status = $persistentTeamsService->getUserTeamsStatus($_SESSION['user_id']);
        echo "<div class='info'>API would return: " . json_encode($status) . "</div>";
        
        if ($status['status'] === 'connected') {
            echo "<div class='success'>✅ Connection status API shows connected</div>";
        } else {
            echo "<div class='warning'>⚠️ Connection status API shows: {$status['status']}</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ No session to test connection API</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>🎯 Diagnosis Summary</h2>";
    
    $issues = [];
    $recommendations = [];
    
    if (!$pdo) {
        $issues[] = "Database connection failed";
        $recommendations[] = "Check database configuration in config.php";
    }
    
    if (!$userId) {
        $issues[] = "No user_id in session during oauth_callback";
        $recommendations[] = "Check login.php and session management";
    }
    
    if ($tableCheck <= 0) {
        $issues[] = "oauth_tokens table missing";
        $recommendations[] = "Run emergency database fix";
    }
    
    if (!$tokenSaved) {
        $issues[] = "Token saving to database failed";
        $recommendations[] = "Check database permissions and table structure";
    }
    
    if (empty($issues)) {
        echo "<div class='success'>🎉 No critical issues found!</div>";
        echo "<div class='info'>The OAuth flow should work correctly.</div>";
        echo "<div class='info'>Try connecting to Microsoft Teams again.</div>";
    } else {
        echo "<div class='error'>Issues found:</div>";
        foreach ($issues as $issue) {
            echo "<div class='error'>❌ $issue</div>";
        }
        echo "<br><div class='warning'>Recommendations:</div>";
        foreach ($recommendations as $rec) {
            echo "<div class='warning'>🔧 $rec</div>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='step'>";
    echo "<div class='error'>❌ Debug Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'>File: " . $e->getFile() . ":" . $e->getLine() . "</div>";
    echo "</div>";
}
?>

<div class="step">
    <h2>🔧 Next Steps</h2>
    <p><a href="account.php" style="color: #44aaff;">Try Teams Connection Again</a></p>
    <p><a href="debug_panel.php" style="color: #44aaff;">Back to Debug Panel</a></p>
</div>

</body>
</html>