<?php
session_start();
require_once 'database_helper.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get user information from session
$user_name = $_SESSION['user_name'] ?? 'John Doe';
$user_email = $_SESSION['user_email'] ?? 'john.doe@company.com';
$user_id = $_SESSION['user_id'] ?? null;
$login_method = $_SESSION['login_method'] ?? 'demo';

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $new_name = trim($_POST['display_name'] ?? '');
                
                if (empty($new_name)) {
                    $error_message = 'Display name cannot be empty.';
                } else {
                    try {
                        if ($login_method === 'database' && $user_id) {
                            global $db;
                            $db->updateUserProfile($user_id, $new_name);
                            $_SESSION['user_name'] = $new_name;
                            $user_name = $new_name;
                            $success_message = 'Profile updated successfully.';
                        } else {
                            $_SESSION['user_name'] = $new_name;
                            $user_name = $new_name;
                            $success_message = 'Profile updated successfully (demo mode).';
                        }
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
                    if ($login_method === 'database' && $user_id) {
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
                    } else {
                        $success_message = 'Password change simulated (demo mode).';
                    }
                }
                break;
                
            case 'delete_account':
                if ($login_method === 'database' && $user_id) {
                    try {
                        global $db;
                        $db->deleteUser($user_id);
                        session_destroy();
                        header('Location: login.php?message=account_deleted');
                        exit();
                    } catch (Exception $e) {
                        $error_message = 'Failed to delete account. Please try again.';
                    }
                } else {
                    $error_message = 'Account deletion not available in demo mode.';
                }
                break;
        }
    }
}

// Get user activity stats
try {
    if ($login_method === 'database' && $user_id) {
        global $db;
        $activity_stats = $db->getUserActivityStats($user_id);
        $recent_activity = $db->getUserRecentActivity($user_id, 10);
    } else {
        throw new Exception("Demo mode");
    }
} catch (Exception $e) {
    // Mock data for demo users
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Navigation Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-comments"></i>
                    <span>TeamSummary</span>
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
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=6366f1&color=fff" alt="User Avatar" class="user-avatar">
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
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=6366f1&color=fff&size=120" alt="Profile Avatar">
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
                <?php if ($login_method === 'database'): ?>
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
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Mobile Navigation Overlay -->
    <div class="mobile-nav-overlay"></div>

    <script src="script.js"></script>
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
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
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