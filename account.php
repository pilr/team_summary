<?php
// Enable output buffering and compression for better performance
ob_start();
if (extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}

session_start();
require_once 'database_helper.php';
require_once 'teams_config.php';
require_once 'cache_helper.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get user information from session (all from database)
$user_name = $_SESSION['user_name'] ?? 'Unknown User';
$user_email = $_SESSION['user_email'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$login_method = $_SESSION['login_method'] ?? 'database';

// If user_id is missing, redirect to login (database authentication required)
if (!$user_id) {
    error_log("Missing user_id in session, redirecting to login");
    header('Location: login.php');
    exit();
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle OAuth callback results
if (isset($_GET['success']) && $_GET['success'] === 'teams_connected') {
    $success_message = 'Microsoft Teams connected successfully! You can now access your Teams data.';
} elseif (isset($_GET['warning']) && $_GET['warning'] === 'teams_connected_limited') {
    $error_message = 'Teams connected but with limited access. Some features may not work properly.';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'oauth_failed':
            $error_message = 'OAuth authentication failed: ' . (htmlspecialchars($_GET['message'] ?? 'Unknown error'));
            break;
        case 'no_auth_code':
            $error_message = 'No authorization code received from Microsoft. Please try connecting again.';
            break;
        case 'connection_failed':
            $error_message = 'Connection failed: ' . (htmlspecialchars($_GET['message'] ?? 'Unknown error'));
            break;
        case 'invalid_state':
            $error_message = 'Invalid state parameter. Please try connecting again.';
            break;
        case 'code_already_used':
            $error_message = 'This authorization code has already been used. Please try connecting again.';
            break;
        case 'session_expired':
            $error_message = 'Your session has expired. Please log in again.';
            break;
        case 'invalid_session':
            $error_message = 'Invalid session. Please log in again.';
            break;
        default:
            $error_message = 'An error occurred: ' . htmlspecialchars($_GET['error']);
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $new_name = trim($_POST['display_name'] ?? '');
                
                if (empty($new_name)) {
                    $error_message = 'Display name cannot be empty.';
                } else {
                    try {
                        global $db;
                        $db->updateUserProfile($user_id, $new_name);
                        $_SESSION['user_name'] = $new_name;
                        $user_name = $new_name;
                        $success_message = 'Profile updated successfully.';
                    } catch (Exception $e) {
                        $error_message = 'Failed to update profile. Please try again.';
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'New password must be at least 6 characters long.';
                } else {
                    try {
                        global $db;
                        if ($db->verifyPassword($user_id, $current_password)) {
                            $db->updatePassword($user_id, $new_password);
                            $success_message = 'Password changed successfully.';
                        } else {
                            $error_message = 'Current password is incorrect.';
                        }
                    } catch (Exception $e) {
                        $error_message = 'Failed to change password. Please try again.';
                    }
                }
                break;
                
            case 'delete_account':
                try {
                    global $db;
                    $db->deleteUser($user_id);
                    session_destroy();
                    header('Location: login.php?message=account_deleted');
                    exit();
                } catch (Exception $e) {
                    $error_message = 'Failed to delete account. Please try again.';
                }
                break;
        }
    }
}

// Get user activity stats with caching
try {
    global $db, $cache;
    
    // Cache activity stats for 5 minutes
    $activity_stats = $cache->remember("user_activity_stats_$user_id", function() use ($db, $user_id) {
        return $db->getUserActivityStats($user_id);
    }, 300);
    
    // Cache recent activity for 2 minutes
    $recent_activity = $cache->remember("user_recent_activity_$user_id", function() use ($db, $user_id) {
        return $db->getUserRecentActivity($user_id, 10);
    }, 120);
    
} catch (Exception $e) {
    // Mock data fallback if database unavailable
    $activity_stats = [
        'total_logins' => rand(25, 50),
        'messages_read' => rand(1500, 3000),
        'channels_followed' => rand(8, 15),
        'account_created' => '2024-01-15'
    ];
    
    $recent_activity = [
        ['action' => 'login', 'timestamp' => date('Y-m-d H:i:s'), 'details' => 'Logged in via demo'],
        ['action' => 'view_dashboard', 'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')), 'details' => 'Viewed dashboard'],
        ['action' => 'read_messages', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'details' => 'Read 5 messages in General channel'],
        ['action' => 'login', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')), 'details' => 'Logged in via demo'],
        ['action' => 'view_summaries', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')), 'details' => 'Viewed daily summaries']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Teams Activity Dashboard</title>
    
    <!-- Performance optimizations -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://ui-avatars.com">
    
    <!-- Critical CSS inline -->
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1e40af, #1d4ed8); color: white; position: fixed; height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; background: #f8fafc; min-height: 100vh; }
        .loading { opacity: 0.7; pointer-events: none; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
    
    <!-- Load non-critical CSS asynchronously -->
    <link rel="preload" href="styles.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="styles.css"></noscript>
</head>
<body>
    <div class="app-container">
        <!-- Navigation Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-comments"></i>
                    <span>TeamsSummary</span>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="summaries.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span>Summaries</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="account.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span>Account</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="logout.php" class="nav-link logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Account</h1>
                </div>
                <div class="top-bar-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <button class="settings-btn">
                        <i class="fas fa-cog"></i>
                    </button>
                    <div class="user-profile">
                        <img data-src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=6366f1&color=fff&format=svg" alt="User Avatar" class="user-avatar" loading="lazy">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </header>

            <!-- Account Content -->
            <div class="dashboard-content">
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php endif; ?>

                <!-- Profile Section -->
                <section class="account-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-user-circle"></i> Profile Information</h2>
                        </div>
                        <div class="card-content">
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <img data-src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=6366f1&color=fff&size=120&format=svg" alt="Profile Avatar" class="user-avatar" loading="lazy">
                                    <button class="avatar-upload-btn">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                </div>
                                <div class="profile-info">
                                    <h3><?php echo htmlspecialchars($user_name); ?></h3>
                                    <p><?php echo htmlspecialchars($user_email); ?></p>
                                    <span class="account-type"><?php echo ucfirst($login_method); ?> Account</span>
                                </div>
                            </div>

                            <form method="POST" class="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-group">
                                    <label for="display_name">Display Name</label>
                                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                                    <small>Email cannot be changed</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Microsoft Teams Integration -->
                <section class="account-section">
                    <div class="card">
                        <div class="card-header teams-header">
                            <div class="header-content">
                                <div class="header-icon">
                                    <i class="fab fa-microsoft"></i>
                                </div>
                                <div class="header-text">
                                    <h2>Microsoft Teams Integration</h2>
                                    <p>Connect your personal Teams account for enhanced features</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="integration-status" id="teamsIntegrationStatus">
                                <div class="status-indicator">
                                    <i class="fas fa-circle status-icon" id="statusIcon"></i>
                                    <span class="status-text" id="statusText">Checking connection...</span>
                                </div>
                                <p class="status-description" id="statusDescription">
                                    Connect your Microsoft account to access your personal Teams data and get personalized summaries tailored to your teams and channels.
                                </p>
                            </div>
                            
                            <div class="integration-actions">
                                <button id="connectTeamsBtn" class="btn btn-microsoft" onclick="connectToMicrosoft()">
                                    <i class="fab fa-microsoft"></i>
                                    <span>Connect to Microsoft Teams</span>
                                </button>
                                <button id="disconnectTeamsBtn" class="btn btn-danger" onclick="disconnectMicrosoft()" style="display: none;">
                                    <i class="fas fa-unlink"></i>
                                    <span>Disconnect Microsoft Account</span>
                                </button>
                                <button id="refreshTeamsBtn" class="btn btn-secondary" onclick="refreshConnection()" style="display: none;">
                                    <i class="fas fa-sync"></i>
                                    <span>Refresh Connection</span>
                                </button>
                            </div>
                            
                            <div class="integration-permissions" id="permissionsInfo" style="display: none;">
                                <h4>Connected Permissions</h4>
                                <ul class="permissions-list">
                                    <li><i class="fas fa-check-circle"></i> Read your Teams</li>
                                    <li><i class="fas fa-check-circle"></i> Read channel information</li>
                                    <li><i class="fas fa-check-circle"></i> Read messages</li>
                                    <li><i class="fas fa-check-circle"></i> Read user profile</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Security Section -->
                <section class="account-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-shield-alt"></i> Security</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="security-form">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Activity Stats -->
                <section class="account-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-bar"></i> Account Statistics</h2>
                        </div>
                        <div class="card-content">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $activity_stats['total_logins']; ?></div>
                                        <div class="stat-label">Total Logins</div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo number_format($activity_stats['messages_read']); ?></div>
                                        <div class="stat-label">Messages Read</div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-hashtag"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $activity_stats['channels_followed']; ?></div>
                                        <div class="stat-label">Channels Followed</div>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo date('M Y', strtotime($activity_stats['account_created'])); ?></div>
                                        <div class="stat-label">Member Since</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Recent Activity -->
                <section class="account-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-history"></i> Recent Activity</h2>
                        </div>
                        <div class="card-content">
                            <div class="activity-list">
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas <?php echo $activity['action'] === 'login' ? 'fa-sign-in-alt' : ($activity['action'] === 'view_dashboard' ? 'fa-tachometer-alt' : 'fa-eye'); ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-description"><?php echo htmlspecialchars($activity['details']); ?></div>
                                        <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Danger Zone -->
                <section class="account-section danger-zone">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                        </div>
                        <div class="card-content">
                            <div class="danger-content">
                                <h3>Delete Account</h3>
                                <p>Once you delete your account, there is no going back. Please be certain.</p>
                                <form method="POST" class="delete-form" onsubmit="return confirmDelete()">
                                    <input type="hidden" name="action" value="delete_account">
                                    <button type="submit" class="btn btn-danger">Delete Account</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Mobile Navigation Overlay -->
    <div class="mobile-nav-overlay"></div>

    <!-- Load JavaScript optimally -->
    <script src="js/loader.js" defer></script>
    <script>
        // Load non-critical resources after page load
        window.addEventListener('load', function() {
            if (window.LoaderManager) {
                LoaderManager.loadParallel([
                    { type: 'css', src: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', media: 'all' },
                    { type: 'js', src: 'js/image-optimizer.js', defer: true },
                    { type: 'js', src: 'script.js', defer: true }
                ]).then(() => {
                    // Initialize image optimization
                    if (window.ImageOptimizer) {
                        window.ImageOptimizer.init();
                    }
                });
            }
        });
    </script>
    <script>
        // PHP data for JavaScript
        window.phpData = {
            userName: '<?php echo addslashes($user_name); ?>',
            userEmail: '<?php echo addslashes($user_email); ?>',
            currentPage: 'account.php'
        };

        function confirmDelete() {
            return confirm('Are you sure you want to delete your account? This action cannot be undone.');
        }

        // Microsoft Teams Integration Functions
        async function connectToMicrosoft() {
            try {
                const state = Math.random().toString(36).substring(2, 15);
                sessionStorage.setItem('oauth_state', state);
                
                const authUrl = new URL('https://login.microsoftonline.com/<?php echo TEAMS_TENANT_ID; ?>/oauth2/v2.0/authorize');
                authUrl.searchParams.append('client_id', '<?php echo TEAMS_CLIENT_ID; ?>');
                authUrl.searchParams.append('response_type', 'code');
                authUrl.searchParams.append('redirect_uri', '<?php echo TEAMS_REDIRECT_URI; ?>');
                authUrl.searchParams.append('scope', 'https://graph.microsoft.com/User.Read https://graph.microsoft.com/Team.ReadBasic.All https://graph.microsoft.com/Channel.ReadBasic.All https://graph.microsoft.com/ChannelMessage.Read.All');
                authUrl.searchParams.append('state', state);
                authUrl.searchParams.append('prompt', 'select_account');
                
                console.log('Redirecting to:', authUrl.toString());
                window.location.href = authUrl.toString();
            } catch (error) {
                console.error('Error connecting to Microsoft:', error);
                showAlert('Failed to initiate connection. Please try again.', 'error');
            }
        }

        async function disconnectMicrosoft() {
            if (!confirm('Are you sure you want to disconnect your Microsoft account?')) {
                return;
            }
            
            try {
                const response = await fetch('api/disconnect_microsoft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const result = await response.json();
                if (result.success) {
                    updateConnectionStatus('disconnected');
                    showAlert('Microsoft account disconnected successfully.', 'success');
                } else {
                    showAlert('Failed to disconnect Microsoft account.', 'error');
                }
            } catch (error) {
                console.error('Error disconnecting:', error);
                showAlert('An error occurred while disconnecting.', 'error');
            }
        }

        async function refreshConnection() {
            updateConnectionStatus('checking');
            
            try {
                const response = await fetch('api/refresh_teams_token.php', {
                    method: 'POST'
                });
                
                const result = await response.json();
                if (result.success) {
                    updateConnectionStatus('connected');
                    showAlert('Connection refreshed successfully.', 'success');
                } else {
                    updateConnectionStatus('disconnected');
                    showAlert('Failed to refresh connection. Please reconnect.', 'error');
                }
            } catch (error) {
                console.error('Error refreshing:', error);
                updateConnectionStatus('error');
                showAlert('An error occurred while refreshing.', 'error');
            }
        }

        function updateConnectionStatus(status) {
            const statusIcon = document.getElementById('statusIcon');
            const statusText = document.getElementById('statusText');
            const statusDescription = document.getElementById('statusDescription');
            const connectBtn = document.getElementById('connectTeamsBtn');
            const disconnectBtn = document.getElementById('disconnectTeamsBtn');
            const refreshBtn = document.getElementById('refreshTeamsBtn');
            const permissionsInfo = document.getElementById('permissionsInfo');

            switch (status) {
                case 'connected':
                    statusIcon.className = 'fas fa-circle status-icon connected';
                    statusText.textContent = 'Connected';
                    statusDescription.textContent = 'Your Microsoft Teams account is connected and ready to use.';
                    connectBtn.style.display = 'none';
                    disconnectBtn.style.display = 'inline-flex';
                    refreshBtn.style.display = 'inline-flex';
                    permissionsInfo.style.display = 'block';
                    break;
                case 'disconnected':
                    statusIcon.className = 'fas fa-circle status-icon disconnected';
                    statusText.textContent = 'Not Connected';
                    statusDescription.textContent = 'Connect your Microsoft account to access your Teams data and get personalized summaries.';
                    connectBtn.style.display = 'inline-flex';
                    disconnectBtn.style.display = 'none';
                    refreshBtn.style.display = 'none';
                    permissionsInfo.style.display = 'none';
                    break;
                case 'error':
                    statusIcon.className = 'fas fa-circle status-icon error';
                    statusText.textContent = 'Connection Error';
                    statusDescription.textContent = 'There was an error with your Microsoft Teams connection. Please try reconnecting.';
                    connectBtn.style.display = 'inline-flex';
                    disconnectBtn.style.display = 'none';
                    refreshBtn.style.display = 'none';
                    permissionsInfo.style.display = 'none';
                    break;
                case 'checking':
                    statusIcon.className = 'fas fa-spinner fa-spin status-icon';
                    statusText.textContent = 'Checking connection...';
                    statusDescription.textContent = 'Please wait while we check your connection status.';
                    connectBtn.style.display = 'none';
                    disconnectBtn.style.display = 'none';
                    refreshBtn.style.display = 'none';
                    break;
            }
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            const container = document.querySelector('.dashboard-content');
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }

        // Check connection status on page load
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('Page loaded, checking Teams connection...');
            try {
                const response = await fetch('api/check_teams_connection.php');
                console.log('Connection check response:', response.status);
                const result = await response.json();
                console.log('Connection status result:', result);
                updateConnectionStatus(result.status || 'disconnected');
            } catch (error) {
                console.error('Error checking connection:', error);
                updateConnectionStatus('error');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>

    <style>
        .account-section {
            margin-bottom: 2rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-avatar {
            position: relative;
        }

        .profile-avatar img {
            border-radius: 50%;
            width: 120px;
            height: 120px;
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        .profile-info p {
            margin: 0 0 0.5rem 0;
            color: var(--text-secondary);
        }

        .account-type {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .profile-form, .security-form {
            display: grid;
            gap: 1.5rem;
            max-width: 500px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group input:disabled {
            background: var(--surface-hover);
            color: var(--text-secondary);
        }

        .form-group small {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--surface-hover);
            border-radius: 12px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--surface-hover);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .activity-description {
            font-weight: 500;
            color: var(--text-primary);
        }

        .activity-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .danger-zone .card {
            border-color: #ef4444;
        }

        .danger-zone .card-header {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .danger-content h3 {
            color: #ef4444;
            margin: 0 0 0.5rem 0;
        }

        .danger-content p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border: 2px solid transparent;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            transition: opacity 0.3s ease;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Microsoft Teams Integration Styles */
        .integration-status {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(168, 85, 247, 0.05) 100%);
            border-radius: 12px;
            border: 1px solid rgba(99, 102, 241, 0.1);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .status-icon {
            font-size: 1rem;
            animation: pulse 2s infinite;
        }

        .status-icon.connected {
            color: #16a34a;
            animation: none;
        }

        .status-icon.disconnected {
            color: #6b7280;
            animation: none;
        }

        .status-icon.error {
            color: #ef4444;
            animation: none;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .status-text {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .status-description {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .integration-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn-microsoft {
            background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 110, 190, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-microsoft:hover {
            background: linear-gradient(135deg, #106ebe 0%, #005a9e 100%);
            box-shadow: 0 6px 20px rgba(16, 110, 190, 0.4);
            transform: translateY(-1px);
        }

        .btn-microsoft:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(16, 110, 190, 0.3);
        }

        .btn-secondary {
            background: var(--surface-hover);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .integration-permissions {
            padding: 1.5rem;
            background: var(--surface-hover);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .integration-permissions::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #16a34a, #10b981, #16a34a);
            border-radius: 12px 12px 0 0;
        }

        .integration-permissions h4 {
            margin: 0 0 1.25rem 0;
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .permissions-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.75rem;
        }

        .permissions-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .permissions-list li:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(4px);
        }

        .permissions-list li i {
            color: #16a34a;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        /* Teams Header Styles */
        .teams-header {
            background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .header-text h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .header-text p {
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>