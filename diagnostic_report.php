<?php
/**
 * Comprehensive Diagnostic Report
 * Checks database, tables, permissions, and configuration
 */
session_start();
require_once 'database_helper.php';
require_once 'teams_config.php';

// Security check - only allow when logged in or with secret parameter
$secret = $_GET['secret'] ?? '';
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$hasSecret = $secret === 'diagnostic2024'; // Change this secret as needed

if (!$isLoggedIn && !$hasSecret) {
    http_response_code(403);
    die('Access denied. Please log in or use the diagnostic secret.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teams Summary - Diagnostic Report</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1a1a1a; color: #00ff00; }
        .success { color: #00ff00; }
        .error { color: #ff4444; }
        .warning { color: #ffaa00; }
        .info { color: #44aaff; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #333; background: #2a2a2a; }
        pre { background: #1a1a1a; padding: 10px; border: 1px solid #333; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #333; }
    </style>
</head>
<body>
<h1>🔍 Teams Summary Diagnostic Report</h1>
<p>Generated: <?= date('Y-m-d H:i:s') ?> UTC</p>

<?php
try {
    echo "<div class='section'>";
    echo "<h2>1. 🗄️ Database Connection Test</h2>";
    
    $db = new DatabaseHelper();
    $pdo = $db->getPDO();
    
    if ($pdo) {
        echo "<div class='success'>✅ Database connection: SUCCESS</div>";
        
        // Get database info
        $dbInfo = $pdo->query("SELECT DATABASE() as db_name, VERSION() as version, NOW() as current_time")->fetch();
        echo "<div class='info'>📊 Database: {$dbInfo['db_name']}</div>";
        echo "<div class='info'>📊 MySQL Version: {$dbInfo['version']}</div>";
        echo "<div class='info'>📊 Server Time: {$dbInfo['current_time']}</div>";
    } else {
        echo "<div class='error'>❌ Database connection: FAILED</div>";
        throw new Exception("No database connection");
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>2. 📋 Table Structure Check</h2>";
    
    // Check if oauth_tokens table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
    if ($tableCheck->rowCount() > 0) {
        echo "<div class='success'>✅ oauth_tokens table: EXISTS</div>";
        
        // Show table structure
        $structure = $pdo->query("DESCRIBE oauth_tokens")->fetchAll();
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for foreign key constraints
        $constraints = $pdo->query("
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'oauth_tokens' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll();
        
        if (count($constraints) > 0) {
            echo "<div class='warning'>⚠️ Foreign key constraints found:</div>";
            foreach ($constraints as $constraint) {
                echo "<div class='warning'>   - {$constraint['CONSTRAINT_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}</div>";
            }
        } else {
            echo "<div class='success'>✅ No foreign key constraints</div>";
        }
        
        // Count existing tokens
        $tokenCount = $pdo->query("SELECT COUNT(*) as count FROM oauth_tokens")->fetch()['count'];
        echo "<div class='info'>📊 Existing tokens: $tokenCount</div>";
        
    } else {
        echo "<div class='error'>❌ oauth_tokens table: MISSING</div>";
        
        // Try to create it
        echo "<div class='info'>🔧 Attempting to create table...</div>";
        try {
            $db->createOAuthTable();
            echo "<div class='success'>✅ Table created successfully</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Table creation failed: {$e->getMessage()}</div>";
        }
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>3. 🧪 Token Save Test</h2>";
    
    // Test token save
    $testUserId = 99999; // Use a high test ID
    $testToken = 'diagnostic_test_' . time();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    echo "<div class='info'>🔬 Testing token save for user ID: $testUserId</div>";
    
    $saveResult = $db->saveOAuthToken(
        $testUserId,
        'microsoft',
        $testToken,
        'diagnostic_refresh_token',
        'Bearer',
        $expiresAt,
        'test_scope'
    );
    
    if ($saveResult) {
        echo "<div class='success'>✅ Token save: SUCCESS</div>";
        
        // Test retrieval
        $retrieved = $db->getOAuthToken($testUserId, 'microsoft');
        if ($retrieved && $retrieved['access_token'] === $testToken) {
            echo "<div class='success'>✅ Token retrieve: SUCCESS</div>";
            
            // Clean up
            $pdo->exec("DELETE FROM oauth_tokens WHERE user_id = $testUserId");
            echo "<div class='success'>✅ Cleanup: SUCCESS</div>";
        } else {
            echo "<div class='error'>❌ Token retrieve: FAILED</div>";
        }
    } else {
        echo "<div class='error'>❌ Token save: FAILED</div>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>4. 🔑 Teams Configuration Check</h2>";
    
    echo "<div class='info'>📊 Client ID: " . (TEAMS_CLIENT_ID !== 'YOUR_CLIENT_ID_HERE' ? '✅ Configured' : '❌ Not configured') . "</div>";
    echo "<div class='info'>📊 Client Secret: " . (TEAMS_CLIENT_SECRET !== 'YOUR_CLIENT_SECRET_HERE' ? '✅ Configured' : '❌ Not configured') . "</div>";
    echo "<div class='info'>📊 Tenant ID: " . (TEAMS_TENANT_ID !== 'YOUR_TENANT_ID_HERE' ? '✅ Configured' : '❌ Not configured') . "</div>";
    echo "<div class='info'>📊 Auth URL: " . TEAMS_AUTH_URL . "</div>";
    echo "<div class='info'>📊 Token URL: " . TEAMS_TOKEN_URL . "</div>";
    
    // Test Graph API connectivity
    echo "<div class='info'>🌐 Testing Microsoft Graph API connectivity...</div>";
    
    $ch = curl_init('https://login.microsoftonline.com/' . TEAMS_TENANT_ID . '/v2.0/.well-known/openid_configuration');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "<div class='error'>❌ CURL Error: $curlError</div>";
    } elseif ($httpCode === 200) {
        echo "<div class='success'>✅ Microsoft API: Reachable</div>";
        $config = json_decode($response, true);
        if ($config) {
            echo "<div class='info'>📊 Token endpoint: {$config['token_endpoint']}</div>";
        }
    } else {
        echo "<div class='error'>❌ Microsoft API: HTTP $httpCode</div>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>5. 🔍 Current Session Check</h2>";
    
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        $userId = $_SESSION['user_id'] ?? 'Not set';
        $userName = $_SESSION['user_name'] ?? 'Not set';
        echo "<div class='success'>✅ User logged in</div>";
        echo "<div class='info'>👤 User ID: $userId</div>";
        echo "<div class='info'>👤 User Name: $userName</div>";
        
        // Check if user has existing tokens
        if (is_numeric($userId)) {
            $userToken = $db->getOAuthToken($userId, 'microsoft');
            if ($userToken) {
                echo "<div class='success'>✅ User has existing token</div>";
                echo "<div class='info'>📅 Expires: {$userToken['expires_at']}</div>";
                
                $expiresAt = new DateTime($userToken['expires_at']);
                $now = new DateTime();
                if ($now >= $expiresAt) {
                    echo "<div class='warning'>⚠️ Token is EXPIRED</div>";
                } else {
                    echo "<div class='success'>✅ Token is VALID</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ User has no existing token</div>";
            }
        }
    } else {
        echo "<div class='warning'>⚠️ No user logged in</div>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>6. 🔧 Error Log Check</h2>";
    
    // Get recent PHP error logs if accessible
    $errorLogPath = ini_get('error_log');
    if ($errorLogPath && file_exists($errorLogPath) && is_readable($errorLogPath)) {
        echo "<div class='info'>📋 Error log: $errorLogPath</div>";
        $lines = array_slice(file($errorLogPath), -20); // Last 20 lines
        echo "<pre>";
        foreach ($lines as $line) {
            if (stripos($line, 'oauth') !== false || stripos($line, 'token') !== false || stripos($line, 'teams') !== false) {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
    } else {
        echo "<div class='warning'>⚠️ Error log not accessible</div>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>7. 🎯 Recommended Actions</h2>";
    
    $recommendations = [];
    
    if (!$pdo) {
        $recommendations[] = "❌ Fix database connection configuration";
    }
    
    if (TEAMS_CLIENT_ID === 'YOUR_CLIENT_ID_HERE') {
        $recommendations[] = "❌ Configure Microsoft Teams client credentials";
    }
    
    $tableExists = $pdo && $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->rowCount() > 0;
    if (!$tableExists) {
        $recommendations[] = "❌ Create oauth_tokens table";
    }
    
    if (empty($recommendations)) {
        echo "<div class='success'>✅ No critical issues found!</div>";
        echo "<div class='info'>💡 Try the Teams connection again</div>";
    } else {
        foreach ($recommendations as $rec) {
            echo "<div class='error'>$rec</div>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<div class='error'>❌ Diagnostic Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'>📍 File: " . $e->getFile() . ":" . $e->getLine() . "</div>";
    echo "</div>";
}
?>

<div class="section">
    <h2>🚀 Quick Actions</h2>
    <p><a href="fix_oauth_tokens_table.php?secret=diagnostic2024" style="color: #44aaff;">🔧 Run Table Fix Script</a></p>
    <p><a href="test_token_save.php?secret=diagnostic2024" style="color: #44aaff;">🧪 Run Token Test</a></p>
    <p><a href="account.php" style="color: #44aaff;">👤 Back to Account</a></p>
</div>

<script>
// Auto-refresh every 30 seconds if ?auto=1
if (window.location.search.includes('auto=1')) {
    setTimeout(() => location.reload(), 30000);
}
</script>
</body>
</html>
<?php
// Log this diagnostic run
error_log("Diagnostic report accessed by " . ($_SESSION['user_name'] ?? 'anonymous') . " at " . date('Y-m-d H:i:s'));
?>