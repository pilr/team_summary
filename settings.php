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

// Default settings
$default_settings = [
    'email_notifications' => true,
    'urgent_alerts' => true,
    'mention_notifications' => true,
    'daily_digest' => true,
    'weekly_summary' => true,
    'notification_frequency' => 'immediate',
    'digest_time' => '08:00',
    'theme' => 'light',
    'timezone' => 'America/New_York',
    'language' => 'en',
    'compact_view' => false,
    'show_read_messages' => true,
    'auto_mark_read' => false
];

// Load user settings
try {
    if ($login_method === 'database' && $user_id) {
        global $db;
        $user_settings = $db->getUserSettings($user_id);
        $settings = array_merge($default_settings, $user_settings);
    } else {
        $settings = $default_settings;
    }
} catch (Exception $e) {
    $settings = $default_settings;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_notifications':
                $new_settings = [
                    'email_notifications' => isset($_POST['email_notifications']),
                    'urgent_alerts' => isset($_POST['urgent_alerts']),
                    'mention_notifications' => isset($_POST['mention_notifications']),
                    'daily_digest' => isset($_POST['daily_digest']),
                    'weekly_summary' => isset($_POST['weekly_summary']),
                    'notification_frequency' => $_POST['notification_frequency'] ?? 'immediate',
                    'digest_time' => $_POST['digest_time'] ?? '08:00'
                ];
                
                try {
                    if ($login_method === 'database' && $user_id) {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                    }
                    $settings = array_merge($settings, $new_settings);
                    $success_message = 'Notification settings updated successfully.';
                } catch (Exception $e) {
                    $error_message = 'Failed to update notification settings.';
                }
                break;
                
            case 'update_appearance':
                $new_settings = [
                    'theme' => $_POST['theme'] ?? 'light',
                    'compact_view' => isset($_POST['compact_view']),
                    'show_read_messages' => isset($_POST['show_read_messages'])
                ];
                
                try {
                    if ($login_method === 'database' && $user_id) {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                    }
                    $settings = array_merge($settings, $new_settings);
                    $success_message = 'Appearance settings updated successfully.';
                } catch (Exception $e) {
                    $error_message = 'Failed to update appearance settings.';
                }
                break;
                
            case 'update_general':
                $new_settings = [
                    'timezone' => $_POST['timezone'] ?? 'America/New_York',
                    'language' => $_POST['language'] ?? 'en',
                    'auto_mark_read' => isset($_POST['auto_mark_read'])
                ];
                
                try {
                    if ($login_method === 'database' && $user_id) {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                    }
                    $settings = array_merge($settings, $new_settings);
                    $success_message = 'General settings updated successfully.';
                } catch (Exception $e) {
                    $error_message = 'Failed to update general settings.';
                }
                break;
                
            case 'export_data':
                // Generate data export
                $export_data = [
                    'user_info' => [
                        'name' => $user_name,
                        'email' => $user_email,
                        'export_date' => date('Y-m-d H:i:s')
                    ],
                    'settings' => $settings
                ];
                
                if ($login_method === 'database' && $user_id) {
                    try {
                        global $db;
                        $export_data['activity_stats'] = $db->getUserActivityStats($user_id);
                        $export_data['recent_activity'] = $db->getUserRecentActivity($user_id, 50);
                    } catch (Exception $e) {
                        // Continue with basic export
                    }
                }
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="teamsummary-data-' . date('Y-m-d') . '.json"');
                echo json_encode($export_data, JSON_PRETTY_PRINT);
                exit();
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Teams Activity Dashboard</title>
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
                <li class="nav-item active">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
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
                    <h1>Settings</h1>
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

            <!-- Settings Content -->
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

                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <button class="settings-tab active" data-tab="notifications">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </button>
                    <button class="settings-tab" data-tab="appearance">
                        <i class="fas fa-palette"></i>
                        Appearance
                    </button>
                    <button class="settings-tab" data-tab="general">
                        <i class="fas fa-cog"></i>
                        General
                    </button>
                    <button class="settings-tab" data-tab="privacy">
                        <i class="fas fa-shield-alt"></i>
                        Privacy & Data
                    </button>
                </div>

                <!-- Notifications Settings -->
                <section id="notifications-settings" class="settings-section active">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <div class="setting-group">
                                    <h3>Email Notifications</h3>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Enable email notifications</span>
                                            <span class="setting-description">Receive notifications via email</span>
                                        </label>
                                    </div>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="urgent_alerts" <?php echo $settings['urgent_alerts'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Urgent message alerts</span>
                                            <span class="setting-description">Immediate alerts for urgent messages</span>
                                        </label>
                                    </div>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="mention_notifications" <?php echo $settings['mention_notifications'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Mention notifications</span>
                                            <span class="setting-description">Get notified when you're mentioned</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="setting-group">
                                    <h3>Digest Settings</h3>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="daily_digest" <?php echo $settings['daily_digest'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Daily digest</span>
                                            <span class="setting-description">Receive daily summary emails</span>
                                        </label>
                                    </div>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="weekly_summary" <?php echo $settings['weekly_summary'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Weekly summary</span>
                                            <span class="setting-description">Receive weekly activity summaries</span>
                                        </label>
                                    </div>
                                    <div class="setting-item">
                                        <label class="setting-title">Daily digest time</label>
                                        <input type="time" name="digest_time" value="<?php echo $settings['digest_time']; ?>" class="setting-input">
                                        <span class="setting-description">Time to send daily digest</span>
                                    </div>
                                </div>

                                <div class="setting-group">
                                    <h3>Notification Frequency</h3>
                                    <div class="setting-item">
                                        <label class="setting-title">Notification frequency</label>
                                        <select name="notification_frequency" class="setting-select">
                                            <option value="immediate" <?php echo $settings['notification_frequency'] === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                            <option value="hourly" <?php echo $settings['notification_frequency'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                            <option value="daily" <?php echo $settings['notification_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        </select>
                                        <span class="setting-description">How often to receive notifications</span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Notification Settings</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Appearance Settings -->
                <section id="appearance-settings" class="settings-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-palette"></i> Appearance</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="action" value="update_appearance">
                                
                                <div class="setting-group">
                                    <h3>Theme</h3>
                                    <div class="setting-item">
                                        <label class="setting-title">Color theme</label>
                                        <div class="theme-options">
                                            <label class="theme-option">
                                                <input type="radio" name="theme" value="light" <?php echo $settings['theme'] === 'light' ? 'checked' : ''; ?>>
                                                <div class="theme-preview light">
                                                    <div class="theme-color"></div>
                                                    <span>Light</span>
                                                </div>
                                            </label>
                                            <label class="theme-option">
                                                <input type="radio" name="theme" value="dark" <?php echo $settings['theme'] === 'dark' ? 'checked' : ''; ?>>
                                                <div class="theme-preview dark">
                                                    <div class="theme-color"></div>
                                                    <span>Dark</span>
                                                </div>
                                            </label>
                                            <label class="theme-option">
                                                <input type="radio" name="theme" value="auto" <?php echo $settings['theme'] === 'auto' ? 'checked' : ''; ?>>
                                                <div class="theme-preview auto">
                                                    <div class="theme-color"></div>
                                                    <span>Auto</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="setting-group">
                                    <h3>Layout</h3>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="compact_view" <?php echo $settings['compact_view'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Compact view</span>
                                            <span class="setting-description">Show more content in less space</span>
                                        </label>
                                    </div>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="show_read_messages" <?php echo $settings['show_read_messages'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Show read messages</span>
                                            <span class="setting-description">Display messages you've already read</span>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Appearance Settings</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- General Settings -->
                <section id="general-settings" class="settings-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-cog"></i> General Settings</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="action" value="update_general">
                                
                                <div class="setting-group">
                                    <h3>Localization</h3>
                                    <div class="setting-item">
                                        <label class="setting-title">Timezone</label>
                                        <select name="timezone" class="setting-select">
                                            <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (ET)</option>
                                            <option value="America/Chicago" <?php echo $settings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time (CT)</option>
                                            <option value="America/Denver" <?php echo $settings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time (MT)</option>
                                            <option value="America/Los_Angeles" <?php echo $settings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (PT)</option>
                                            <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        </select>
                                        <span class="setting-description">Your local timezone</span>
                                    </div>
                                    <div class="setting-item">
                                        <label class="setting-title">Language</label>
                                        <select name="language" class="setting-select">
                                            <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="es" <?php echo $settings['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                                            <option value="fr" <?php echo $settings['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                                            <option value="de" <?php echo $settings['language'] === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                        </select>
                                        <span class="setting-description">Interface language</span>
                                    </div>
                                </div>

                                <div class="setting-group">
                                    <h3>Behavior</h3>
                                    <div class="setting-item">
                                        <label class="setting-label">
                                            <input type="checkbox" name="auto_mark_read" <?php echo $settings['auto_mark_read'] ? 'checked' : ''; ?>>
                                            <span class="setting-title">Auto-mark messages as read</span>
                                            <span class="setting-description">Automatically mark messages as read when viewed</span>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save General Settings</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Privacy & Data Settings -->
                <section id="privacy-settings" class="settings-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-shield-alt"></i> Privacy & Data</h2>
                        </div>
                        <div class="card-content">
                            <div class="setting-group">
                                <h3>Data Management</h3>
                                <div class="setting-item">
                                    <div class="setting-title">Export your data</div>
                                    <div class="setting-description">Download all your account data and settings</div>
                                    <form method="POST" style="margin-top: 1rem;">
                                        <input type="hidden" name="action" value="export_data">
                                        <button type="submit" class="btn btn-secondary">
                                            <i class="fas fa-download"></i>
                                            Export Data
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="setting-group">
                                <h3>Privacy</h3>
                                <div class="privacy-info">
                                    <p>Your privacy is important to us. We collect and process data to provide you with the best experience.</p>
                                    <ul>
                                        <li>Message content is processed to generate summaries</li>
                                        <li>Activity data is stored to improve recommendations</li>
                                        <li>Settings and preferences are saved for your convenience</li>
                                    </ul>
                                    <p>For more information, please review our <a href="#" class="link">Privacy Policy</a>.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
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
            currentPage: 'settings.php'
        };

        // Settings tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.settings-tab');
            const sections = document.querySelectorAll('.settings-section');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;

                    // Remove active class from all tabs and sections
                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));

                    // Add active class to clicked tab and corresponding section
                    this.classList.add('active');
                    document.getElementById(targetTab + '-settings').classList.add('active');
                });
            });

            // Auto-hide alerts after 5 seconds
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
        .settings-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .settings-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .settings-tab:hover {
            color: var(--text-primary);
            background: var(--surface-hover);
        }

        .settings-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .setting-group {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .setting-group h3 {
            margin: 0;
            font-size: 1.125rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }

        .setting-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .setting-label {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            cursor: pointer;
        }

        .setting-label input[type="checkbox"] {
            margin-top: 0.25rem;
        }

        .setting-title {
            font-weight: 500;
            color: var(--text-primary);
        }

        .setting-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .setting-input, .setting-select {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            max-width: 300px;
        }

        .theme-options {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .theme-option {
            cursor: pointer;
        }

        .theme-option input[type="radio"] {
            display: none;
        }

        .theme-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .theme-option input[type="radio"]:checked + .theme-preview {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
        }

        .theme-color {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
        }

        .theme-preview.light .theme-color {
            background: linear-gradient(45deg, #ffffff 50%, #f3f4f6 50%);
        }

        .theme-preview.dark .theme-color {
            background: linear-gradient(45deg, #1f2937 50%, #374151 50%);
        }

        .theme-preview.auto .theme-color {
            background: linear-gradient(90deg, #ffffff 0%, #1f2937 100%);
        }

        .privacy-info {
            background: var(--surface-hover);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .privacy-info ul {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        .privacy-info li {
            margin-bottom: 0.5rem;
        }

        .link {
            color: var(--primary-color);
            text-decoration: none;
        }

        .link:hover {
            text-decoration: underline;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: var(--surface-hover);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
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
            .settings-nav {
                gap: 0;
            }

            .settings-tab {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }

            .theme-options {
                flex-direction: column;
                gap: 0.75rem;
            }

            .theme-preview {
                flex-direction: row;
                padding: 0.75rem;
            }
        }
    </style>
</body>
</html>