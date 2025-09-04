<?php
/**
 * Debug Panel - Quick access to diagnostic and fix scripts
 */
session_start();

// Simple security check
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
if (!$isLoggedIn) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teams Summary - Debug Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #1a1a1a; 
            color: #ffffff; 
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #4CAF50; text-align: center; }
        .panel { 
            background: #2d2d2d; 
            margin: 20px 0; 
            padding: 20px; 
            border-radius: 8px; 
            border: 1px solid #444; 
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { background: #45a049; }
        .btn-danger { background: #f44336; }
        .btn-danger:hover { background: #da190b; }
        .btn-warning { background: #ff9800; }
        .btn-warning:hover { background: #e68900; }
        .btn-info { background: #2196F3; }
        .btn-info:hover { background: #1976D2; }
        .status { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px; 
            font-family: monospace; 
        }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ef5350; }
        .success { background: #e8f5e8; color: #2e7d32; border: 1px solid #4caf50; }
        .warning { background: #fff3e0; color: #ef6c00; border: 1px solid #ff9800; }
        .info { background: #e3f2fd; color: #1565c0; border: 1px solid #2196f3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Teams Debug Panel</h1>
        
        <div class="panel">
            <h2>🔍 Current Status</h2>
            <p><strong>User:</strong> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?></p>
            <p><strong>User ID:</strong> <?= htmlspecialchars($_SESSION['user_id'] ?? 'Not set') ?></p>
            <p><strong>Time:</strong> <?= date('Y-m-d H:i:s T') ?></p>
            
            <?php
            // Quick connection test
            try {
                require_once 'database_helper.php';
                $db = new DatabaseHelper();
                $pdo = $db->getPDO();
                if ($pdo) {
                    echo '<div class="status success">✅ Database: Connected</div>';
                    
                    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'")->rowCount();
                    if ($tableCheck > 0) {
                        echo '<div class="status success">✅ oauth_tokens table: Exists</div>';
                        
                        // Check user's token status
                        $userId = $_SESSION['user_id'] ?? null;
                        if ($userId) {
                            $userToken = $db->getOAuthToken($userId, 'microsoft');
                            if ($userToken) {
                                $expires = new DateTime($userToken['expires_at']);
                                $now = new DateTime();
                                if ($expires > $now) {
                                    echo '<div class="status success">✅ Your Teams token: Valid until ' . $expires->format('Y-m-d H:i:s') . '</div>';
                                } else {
                                    echo '<div class="status warning">⚠️ Your Teams token: Expired (' . $expires->format('Y-m-d H:i:s') . ')</div>';
                                }
                            } else {
                                echo '<div class="status info">ℹ️ Your Teams token: Not connected</div>';
                            }
                        }
                    } else {
                        echo '<div class="status error">❌ oauth_tokens table: Missing</div>';
                    }
                } else {
                    echo '<div class="status error">❌ Database: Connection failed</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>
        
        <div class="panel">
            <h2>🔍 Diagnostic Tools</h2>
            <p>Comprehensive system diagnostics and status checks</p>
            <a href="diagnostic_report.php?secret=diagnostic2024" class="btn btn-info" target="_blank">
                📊 Full Diagnostic Report
            </a>
            <a href="diagnostic_report.php?secret=diagnostic2024&auto=1" class="btn btn-info" target="_blank">
                📊 Auto-Refresh Report
            </a>
        </div>
        
        <div class="panel">
            <h2>🔧 Database Fixes</h2>
            <p>Fix database table and connection issues</p>
            <a href="emergency_db_fix.php?secret=emergency2024" class="btn btn-danger" target="_blank">
                🚨 Emergency DB Fix
            </a>
            <a href="fix_oauth_tokens_table.php?secret=diagnostic2024" class="btn btn-warning" target="_blank">
                🔧 Standard Table Fix
            </a>
            <a href="test_token_save.php?secret=diagnostic2024" class="btn btn-info" target="_blank">
                🧪 Test Token Save
            </a>
        </div>
        
        <div class="panel">
            <h2>🔗 Connection Tests</h2>
            <p>Test Microsoft Teams connection and authentication</p>
            <a href="api/check_teams_connection.php" class="btn btn-info" target="_blank">
                📡 Check Connection Status
            </a>
            <a href="oauth_callback.php?test=1" class="btn btn-warning" onclick="return confirm('This may redirect you. Continue?')">
                🔑 Test OAuth Flow
            </a>
        </div>
        
        <div class="panel">
            <h2>📋 Quick Actions</h2>
            <a href="account.php" class="btn">👤 Back to Account</a>
            <a href="summaries.php" class="btn">📊 View Summaries</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
        
        <div class="panel">
            <h2>📖 Instructions</h2>
            <ol>
                <li><strong>Run Diagnostic Report</strong> first to identify issues</li>
                <li>If database issues are found, run <strong>Emergency DB Fix</strong></li>
                <li>Test token functionality with <strong>Test Token Save</strong></li>
                <li>Try connecting to Teams again from the Account page</li>
                <li>Use <strong>Check Connection Status</strong> to verify the connection</li>
            </ol>
        </div>
    </div>
    
    <script>
        // Auto-reload status every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>