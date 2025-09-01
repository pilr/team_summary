<?php
session_start();
require_once 'database_helper.php';
require_once 'user_teams_api.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Unknown User';

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// Run diagnostics
$userTeamsAPI = new UserTeamsAPIHelper($user_id);
$is_connected = $userTeamsAPI->isConnected();

global $db;
$token_info = null;
$teams = [];
$api_working = false;

if ($is_connected) {
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    
    // Test basic API access (User.Read scope)
    $profile_working = ($userTeamsAPI->getUserProfile() !== false);
    
    // Test Teams API access (Team.ReadBasic.All scope) 
    $teams = $userTeamsAPI->getUserTeams();
    $teams_api_working = !empty($teams) || !$profile_working; // If profile doesn't work, teams won't either
    
    // Check for permissions error by testing a simple Teams API call
    $has_teams_permission = true;
    if ($profile_working && empty($teams)) {
        // Profile works but no teams - could be permissions issue
        // Make a raw API call to detect 403 errors
        $test_response = testTeamsAPIPermission($token_info['access_token']);
        $has_teams_permission = !isset($test_response['forbidden']);
    }
    
    $api_working = $profile_working;
}

/**
 * Test Teams API permission by making a raw API call
 */
function testTeamsAPIPermission($access_token) {
    $url = 'https://graph.microsoft.com/v1.0/me/joinedTeams';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 403) {
        $error_data = json_decode($response, true);
        return [
            'forbidden' => true,
            'error' => $error_data['error'] ?? null
        ];
    }
    
    return ['forbidden' => false, 'http_code' => $http_code];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microsoft Teams Diagnostics - Team Summary Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .content {
            padding: 2rem;
        }

        .status-card {
            border: 2px solid;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .status-success {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .status-warning {
            border-color: #f59e0b;
            background: #fffbeb;
        }

        .status-error {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .status-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }

        .diagnostic-item {
            display: flex;
            align-items: center;
            margin: 0.75rem 0;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .diagnostic-item i {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin: 0.5rem 0.5rem 0.5rem 0;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #4338ca;
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .solution-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .solution-box h4 {
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .solution-box ol {
            padding-left: 1.2rem;
        }

        .solution-box li {
            margin: 0.5rem 0;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fab fa-microsoft"></i> Microsoft Teams Diagnostics</h1>
            <p>Connection Status for <?php echo htmlspecialchars($user_name); ?></p>
        </div>

        <div class="content">
            <?php if (!$is_connected): ?>
            <!-- Not Connected -->
            <div class="status-card status-error">
                <div class="status-icon error text-center">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 class="text-center error">Microsoft Account Not Connected</h3>
                <p class="text-center">You need to connect your Microsoft account to access Teams data.</p>
                
                <div class="solution-box">
                    <h4><i class="fas fa-lightbulb"></i> How to Fix:</h4>
                    <ol>
                        <li>Go to <strong>Account Settings</strong></li>
                        <li>Click <strong>"Connect to Microsoft Teams"</strong></li>
                        <li>Sign in with your Microsoft work or school account</li>
                        <li>Grant the required permissions</li>
                    </ol>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <a href="account.php" class="btn">
                        <i class="fas fa-link"></i> Connect Microsoft Account
                    </a>
                </div>
            </div>

            <?php elseif (!$api_working): ?>
            <!-- Connected but API not working -->
            <div class="status-card status-error">
                <div class="status-icon error text-center">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-center error">API Connection Issue</h3>
                <p class="text-center">Your account is connected but the API is not responding properly.</p>
                
                <div class="solution-box">
                    <h4><i class="fas fa-tools"></i> Troubleshooting Steps:</h4>
                    <ol>
                        <li>Your token may have expired - try reconnecting</li>
                        <li>Check if you have the correct permissions</li>
                        <li>Contact your IT administrator</li>
                    </ol>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <a href="account.php" class="btn">
                        <i class="fas fa-refresh"></i> Reconnect Account
                    </a>
                </div>
            </div>

            <?php elseif ($profile_working && empty($teams) && !$has_teams_permission): ?>
            <!-- Connected but Teams API permissions issue -->
            <div class="status-card status-error">
                <div class="status-icon error text-center">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="text-center error">Teams API Permissions Issue</h3>
                <p class="text-center">Your Microsoft account is connected, but the app doesn't have permission to access Teams data.</p>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div>
                    <strong>Microsoft Account Connected</strong>
                    <div style="font-size: 0.9em; color: #6b7280;">
                        Token expires: <?php echo $token_info['expires_at'] ?? 'Unknown'; ?>
                    </div>
                </div>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div><strong>Basic API Access Working</strong></div>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-times error"></i>
                <div><strong>Teams API Access: 403 Forbidden</strong></div>
            </div>

            <div class="solution-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Admin Consent Required</h4>
                <p><strong>This is a permissions issue at the Azure app registration level.</strong></p>
                <ol>
                    <li><strong>Contact IT Administrator:</strong> The app needs admin consent for Teams permissions</li>
                    <li><strong>Required permissions:</strong> Team.ReadBasic.All, Channel.ReadBasic.All, ChannelMessage.Read.All</li>
                    <li><strong>External users:</strong> Additional restrictions may apply for external users</li>
                    <li><strong>Organization policy:</strong> Your organization may not allow Teams API access for external apps</li>
                </ol>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                    <p style="margin: 0; color: #dc2626; font-weight: 500;">
                        <i class="fas fa-info-circle"></i> <strong>For External Users:</strong>
                    </p>
                    <p style="margin: 0.5rem 0 0 0; color: #7f1d1d;">
                        External users often face additional restrictions when accessing Teams API, even with proper permissions. This requires Azure admin configuration of cross-tenant access policies.
                    </p>
                </div>
            </div>

            <?php elseif (empty($teams)): ?>
            <!-- Connected and API working, but no teams -->
            <div class="status-card status-warning">
                <div class="status-icon warning text-center">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h3 class="text-center warning">No Microsoft Teams Found</h3>
                <p class="text-center">Your Microsoft account is connected and working, but you're not a member of any Teams.</p>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div>
                    <strong>Microsoft Account Connected</strong>
                    <div style="font-size: 0.9em; color: #6b7280;">
                        Token expires: <?php echo $token_info['expires_at'] ?? 'Unknown'; ?>
                    </div>
                </div>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div><strong>API Connection Working</strong></div>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-times error"></i>
                <div><strong>Teams Found: 0</strong></div>
            </div>

            <div class="solution-box">
                <h4><i class="fas fa-users"></i> How to Join Microsoft Teams:</h4>
                <ol>
                    <li><strong>Ask your organization:</strong> Contact your IT admin or team lead to be added to relevant Teams</li>
                    <li><strong>Join via Teams app:</strong> Open Microsoft Teams app and look for "Join or create team"</li>
                    <li><strong>Team invitation:</strong> Ask colleagues to send you a team invitation link</li>
                    <li><strong>Create a team:</strong> If you have permissions, create your own team</li>
                </ol>
                <p style="margin-top: 1rem; color: #6b7280; font-style: italic;">
                    <i class="fas fa-info-circle"></i> Note: You must be a team <strong>member</strong>, not just a guest, to appear in the API results.
                </p>
            </div>

            <?php else: ?>
            <!-- Everything working -->
            <div class="status-card status-success">
                <div class="status-icon success text-center">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="text-center success">Microsoft Teams Connected Successfully!</h3>
                <p class="text-center">Found <?php echo count($teams); ?> team(s). Your Teams integration is working correctly.</p>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div><strong>Microsoft Account Connected</strong></div>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div><strong>API Connection Working</strong></div>
            </div>

            <div class="diagnostic-item">
                <i class="fas fa-check success"></i>
                <div><strong>Teams Found: <?php echo count($teams); ?></strong></div>
            </div>

            <div style="margin-top: 1.5rem;">
                <h4>Your Teams:</h4>
                <?php foreach ($teams as $team): ?>
                <div class="diagnostic-item">
                    <i class="fas fa-users" style="color: #4f46e5;"></i>
                    <div><strong><?php echo htmlspecialchars($team['displayName']); ?></strong></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
                <a href="summaries.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="debug_permissions.php" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-code"></i> Technical Details
                </a>
            </div>
        </div>
    </div>
</body>
</html>