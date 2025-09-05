<?php
session_start();
require_once 'database_helper.php';
require_once 'session_validator.php';

// Use unified session validation
$current_user = SessionValidator::requireAuth();

// Get user information with guaranteed consistency
$user_id = $current_user['id'];
$user_name = $current_user['name'];
$user_email = $current_user['email'];
$login_method = $_SESSION['login_method'] ?? 'database';

// Log session refresh if it occurred
if ($current_user['session_refreshed']) {
    error_log("Settings.php: Session data refreshed for user {$user_id} - {$user_email}");
}

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
    'auto_mark_read' => false,
    'ai_summary_prompt' => 'Please analyze these Microsoft Teams messages and provide a comprehensive summary including:

1. **Key Discussion Topics**: What are the main subjects being discussed?
2. **Important Decisions**: Any decisions made or conclusions reached?
3. **Action Items**: Tasks or follow-ups mentioned?
4. **Team Activity**: Overall communication patterns and engagement?
5. **Notable Mentions**: Important announcements or highlights?

Format the response in clear sections with bullet points where appropriate.'
];

// Load user settings
try {
    global $db;
    $user_settings = $db->getUserSettings($user_id);
    $settings = array_merge($default_settings, $user_settings);
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
                    global $db;
                    $db->updateUserSettings($user_id, $new_settings);
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
                    global $db;
                    $db->updateUserSettings($user_id, $new_settings);
                    $settings = array_merge($settings, $new_settings);
                    $success_message = 'Appearance settings updated successfully.';
                } catch (Exception $e) {
                    $error_message = 'Failed to update appearance settings.';
                }
                break;
                
            case 'update_ai_prompt':
                $ai_summary_prompt = trim($_POST['ai_summary_prompt'] ?? '');
                
                if (empty($ai_summary_prompt)) {
                    $error_message = 'AI summary prompt cannot be empty.';
                } else {
                    $new_settings = [
                        'ai_summary_prompt' => $ai_summary_prompt
                    ];
                    
                    try {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                        $settings = array_merge($settings, $new_settings);
                        $success_message = 'AI summary prompt updated successfully.';
                    } catch (Exception $e) {
                        $error_message = 'Failed to update AI summary prompt.';
                    }
                }
                break;
                
            case 'update_general':
                $new_settings = [
                    'timezone' => $_POST['timezone'] ?? 'America/New_York',
                    'language' => $_POST['language'] ?? 'en',
                    'auto_mark_read' => isset($_POST['auto_mark_read'])
                ];
                
                try {
                    global $db;
                    $db->updateUserSettings($user_id, $new_settings);
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
                
                try {
                    global $db;
                    $export_data['activity_stats'] = $db->getUserActivityStats($user_id);
                    $export_data['recent_activity'] = $db->getUserRecentActivity($user_id, 50);
                } catch (Exception $e) {
                    // Continue with basic export
                }
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="teamsummary-data-' . date('Y-m-d') . '.json"');
                echo json_encode($export_data, JSON_PRETTY_PRINT);
                exit();
                break;
                
            case 'api_keys':
                $new_settings = [];
                
                // Handle OpenAI API key
                if (isset($_POST['openai_api_key'])) {
                    $openai_key = trim($_POST['openai_api_key']);
                    if (!empty($openai_key)) {
                        // Basic validation for OpenAI key format
                        if (!preg_match('/^sk-[a-zA-Z0-9\-_]+$/', $openai_key)) {
                            $error_message = 'Invalid OpenAI API key format. Keys should start with "sk-".';
                        } else {
                            $new_settings['openai_api_key'] = $openai_key;
                        }
                    } else {
                        $new_settings['openai_api_key'] = ''; // Clear the key
                    }
                }
                
                if (!$error_message) {
                    try {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                        $settings = array_merge($settings, $new_settings);
                        $success_message = 'API configuration saved successfully.';
                    } catch (Exception $e) {
                        $error_message = 'Failed to save API configuration.';
                    }
                }
                break;
                
            case 'teams_config':
                $new_settings = [
                    'teams_client_id' => trim($_POST['teams_client_id'] ?? ''),
                    'teams_client_secret' => trim($_POST['teams_client_secret'] ?? ''),
                    'teams_tenant_id' => trim($_POST['teams_tenant_id'] ?? ''),
                    'teams_secret_id' => trim($_POST['teams_secret_id'] ?? '')
                ];
                
                // Basic validation for required fields
                $required_fields = ['teams_client_id', 'teams_client_secret', 'teams_tenant_id'];
                $missing_fields = [];
                
                foreach ($required_fields as $field) {
                    if (empty($new_settings[$field])) {
                        $missing_fields[] = str_replace('teams_', '', str_replace('_', ' ', $field));
                    }
                }
                
                if (!empty($missing_fields) && !empty(array_filter($new_settings))) {
                    $error_message = 'Missing required fields: ' . implode(', ', $missing_fields);
                } else {
                    try {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                        $settings = array_merge($settings, $new_settings);
                        $success_message = 'Teams configuration saved successfully.';
                    } catch (Exception $e) {
                        $error_message = 'Failed to save Teams configuration.';
                    }
                }
                break;
        }
    } else if (isset($_POST['settings_type'])) {
        // Handle new form structure
        $settings_type = $_POST['settings_type'];
        
        if ($settings_type === 'api_keys') {
            $_POST['action'] = 'api_keys';
        } elseif ($settings_type === 'teams_config') {
            $_POST['action'] = 'teams_config';
        }
        
        // Reprocess with the action set
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            if ($action === 'api_keys') {
                $new_settings = [];
                
                // Handle OpenAI API key
                if (isset($_POST['openai_api_key'])) {
                    $openai_key = trim($_POST['openai_api_key']);
                    if (!empty($openai_key)) {
                        // Basic validation for OpenAI key format
                        if (!preg_match('/^sk-[a-zA-Z0-9\-_]+$/', $openai_key)) {
                            $error_message = 'Invalid OpenAI API key format. Keys should start with "sk-".';
                        } else {
                            $new_settings['openai_api_key'] = $openai_key;
                        }
                    } else {
                        $new_settings['openai_api_key'] = ''; // Clear the key
                    }
                }
                
                if (!$error_message) {
                    try {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                        $settings = array_merge($settings, $new_settings);
                        $success_message = 'API configuration saved successfully.';
                    } catch (Exception $e) {
                        $error_message = 'Failed to save API configuration.';
                    }
                }
            } elseif ($action === 'teams_config') {
                $new_settings = [
                    'teams_client_id' => trim($_POST['teams_client_id'] ?? ''),
                    'teams_client_secret' => trim($_POST['teams_client_secret'] ?? ''),
                    'teams_tenant_id' => trim($_POST['teams_tenant_id'] ?? ''),
                    'teams_secret_id' => trim($_POST['teams_secret_id'] ?? '')
                ];
                
                // Basic validation for required fields
                $required_fields = ['teams_client_id', 'teams_client_secret', 'teams_tenant_id'];
                $missing_fields = [];
                
                foreach ($required_fields as $field) {
                    if (empty($new_settings[$field])) {
                        $missing_fields[] = str_replace('teams_', '', str_replace('_', ' ', $field));
                    }
                }
                
                if (!empty($missing_fields) && !empty(array_filter($new_settings))) {
                    $error_message = 'Missing required fields: ' . implode(', ', $missing_fields);
                } else {
                    try {
                        global $db;
                        $db->updateUserSettings($user_id, $new_settings);
                        $settings = array_merge($settings, $new_settings);
                        $success_message = 'Teams configuration saved successfully.';
                    } catch (Exception $e) {
                        $error_message = 'Failed to save Teams configuration.';
                    }
                }
            }
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
                    <a href="settings.php" class="settings-btn">
                        <i class="fas fa-cog"></i>
                    </a>
                    <a href="account.php" class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=6366f1&color=fff" alt="User Avatar" class="user-avatar">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </a>
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
                    <button class="settings-tab" data-tab="notifications">
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
                    <button class="settings-tab active" data-tab="ai-summary">
                        <i class="fas fa-brain"></i>
                        AI Summary
                    </button>
                    <button class="settings-tab" data-tab="api-keys">
                        <i class="fas fa-key"></i>
                        API Keys
                    </button>
                    <button class="settings-tab" data-tab="teams-config">
                        <i class="fas fa-microsoft"></i>
                        Teams Config
                    </button>
                    <button class="settings-tab" data-tab="privacy">
                        <i class="fas fa-shield-alt"></i>
                        Privacy & Data
                    </button>
                </div>

                <!-- Notifications Settings -->
                <section id="notifications-settings" class="settings-section">
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

                <!-- AI Summary Settings -->
                <section id="ai-summary-settings" class="settings-section active">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-brain"></i> AI Summary Settings</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="action" value="update_ai_prompt">
                                
                                <div class="setting-group">
                                    <h3>Custom AI Summary Prompt</h3>
                                    <p class="setting-description">
                                        Customize the prompt that will be sent to the AI when generating summaries of your Teams conversations.
                                        This allows you to tailor the AI's analysis to focus on specific aspects that matter most to you.
                                    </p>
                                    
                                    <div class="setting-item">
                                        <label for="ai_summary_prompt" class="setting-title">AI Summary Prompt</label>
                                        <div class="setting-description">
                                            Use this prompt to guide the AI's analysis. You can ask it to focus on specific topics, 
                                            formatting preferences, or particular insights you want highlighted.
                                        </div>
                                        <textarea 
                                            id="ai_summary_prompt" 
                                            name="ai_summary_prompt" 
                                            class="ai-prompt-textarea" 
                                            rows="12" 
                                            placeholder="Enter your custom AI prompt..."
                                        ><?php echo htmlspecialchars($settings['ai_summary_prompt']); ?></textarea>
                                        
                                        <div class="setting-hint">
                                            <strong>Tips for effective prompts:</strong>
                                            <ul>
                                                <li>Be specific about what information you want highlighted</li>
                                                <li>Request specific formatting (bullet points, sections, etc.)</li>
                                                <li>Ask for action items, decisions, or key topics to be identified</li>
                                                <li>Specify the tone and detail level you prefer</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="setting-group">
                                    <h3>Prompt Examples</h3>
                                    <div class="prompt-examples">
                                        <div class="example-prompt" data-prompt="Please analyze these Microsoft Teams messages and provide a brief summary focusing on:&#10;&#10;1. Key decisions made&#10;2. Action items assigned&#10;3. Important deadlines mentioned&#10;4. Overall team progress&#10;&#10;Keep the response concise and actionable.">
                                            <h4>📊 Executive Summary Style</h4>
                                            <p>Focus on decisions, action items, and deadlines</p>
                                            <button type="button" class="btn-link use-example">Use This Example</button>
                                        </div>
                                        
                                        <div class="example-prompt" data-prompt="Please create a detailed analysis of these Teams messages including:&#10;&#10;1. **Discussion Topics**: What subjects were covered?&#10;2. **Key Participants**: Who were the main contributors?&#10;3. **Technical Details**: Any technical discussions or solutions?&#10;4. **Follow-ups**: What needs to happen next?&#10;5. **Sentiment**: Overall team mood and engagement&#10;&#10;Provide specific examples where relevant.">
                                            <h4>🔍 Detailed Analysis Style</h4>
                                            <p>Comprehensive breakdown with technical details</p>
                                            <button type="button" class="btn-link use-example">Use This Example</button>
                                        </div>
                                        
                                        <div class="example-prompt" data-prompt="Summarize these Teams messages in simple bullet points:&#10;&#10;• Main topics discussed&#10;• Important announcements&#10;• Tasks mentioned&#10;• Questions asked&#10;• Next steps&#10;&#10;Keep it short and easy to scan.">
                                            <h4>📝 Bullet Point Style</h4>
                                            <p>Simple, scannable format</p>
                                            <button type="button" class="btn-link use-example">Use This Example</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="setting-actions">
                                    <button type="submit" class="btn btn-primary" id="saveAIPromptBtn">
                                        <i class="fas fa-save"></i>
                                        <span class="btn-text">Save AI Prompt</span>
                                        <i class="fas fa-spinner fa-spin loading-spinner" style="display: none;"></i>
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetToDefault()">
                                        <i class="fas fa-undo"></i>
                                        Reset to Default
                                    </button>
                                </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- API Keys Settings -->
                <section id="api-keys-settings" class="settings-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-key"></i> API Keys Configuration</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="settings_type" value="api_keys">
                                
                                <div class="setting-group">
                                    <h3>OpenAI API Configuration</h3>
                                    <p class="setting-description">Configure your OpenAI API key for AI-powered summary generation. You can get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.</p>
                                    
                                    <div class="setting-item">
                                        <label for="openai_api_key" class="setting-title">OpenAI API Key</label>
                                        <div class="setting-description">Your personal OpenAI API key (starts with sk-)</div>
                                        <input type="password" 
                                               id="openai_api_key" 
                                               name="openai_api_key" 
                                               class="form-control"
                                               placeholder="sk-..." 
                                               value="<?php echo htmlspecialchars($user_settings['openai_api_key'] ?? ''); ?>">
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Your API key is stored securely and encrypted. Leave blank to disable AI summaries.
                                        </small>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" id="saveApiKeysBtn">
                                        <i class="fas fa-save"></i> 
                                        <span class="btn-text">Save API Configuration</span>
                                        <i class="fas fa-spinner fa-spin loading-spinner" style="display: none;"></i>
                                    </button>
                                    <div class="save-status" id="apiKeysSaveStatus" style="display: none;"></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Teams Configuration Settings -->
                <section id="teams-config-settings" class="settings-section">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-microsoft"></i> Microsoft Teams Configuration</h2>
                        </div>
                        <div class="card-content">
                            <form method="POST" class="settings-form">
                                <input type="hidden" name="settings_type" value="teams_config">
                                
                                <div class="setting-group">
                                    <h3>Azure Application Registration</h3>
                                    <p class="setting-description">Configure your Microsoft Azure application credentials for Teams integration. You can create an app registration in the <a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Azure Portal</a>.</p>
                                    
                                    <div class="setting-item">
                                        <label for="teams_client_id" class="setting-title">Client ID (Application ID)</label>
                                        <div class="setting-description">The Application (client) ID from your Azure app registration</div>
                                        <input type="text" 
                                               id="teams_client_id" 
                                               name="teams_client_id" 
                                               class="form-control"
                                               placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" 
                                               value="<?php echo htmlspecialchars($user_settings['teams_client_id'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="setting-item">
                                        <label for="teams_client_secret" class="setting-title">Client Secret</label>
                                        <div class="setting-description">The client secret value from your Azure app registration</div>
                                        <input type="password" 
                                               id="teams_client_secret" 
                                               name="teams_client_secret" 
                                               class="form-control"
                                               placeholder="Enter client secret..." 
                                               value="<?php echo htmlspecialchars($user_settings['teams_client_secret'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="setting-item">
                                        <label for="teams_tenant_id" class="setting-title">Tenant ID (Directory ID)</label>
                                        <div class="setting-description">Your Azure AD tenant (directory) ID</div>
                                        <input type="text" 
                                               id="teams_tenant_id" 
                                               name="teams_tenant_id" 
                                               class="form-control"
                                               placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" 
                                               value="<?php echo htmlspecialchars($user_settings['teams_tenant_id'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="setting-item">
                                        <label for="teams_secret_id" class="setting-title">Secret ID (Optional)</label>
                                        <div class="setting-description">The ID of the client secret (if applicable)</div>
                                        <input type="text" 
                                               id="teams_secret_id" 
                                               name="teams_secret_id" 
                                               class="form-control"
                                               placeholder="Enter secret ID..." 
                                               value="<?php echo htmlspecialchars($user_settings['teams_secret_id'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="setting-group">
                                    <h3>Application Permissions</h3>
                                    <p class="setting-description">Ensure your Azure app registration has the following Microsoft Graph permissions:</p>
                                    <ul class="permission-list">
                                        <li><code>Channel.ReadBasic.All</code> - Read basic channel properties</li>
                                        <li><code>ChannelMessage.Read.All</code> - Read all channel messages</li>
                                        <li><code>Chat.Read</code> - Read user chat messages</li>
                                        <li><code>Chat.ReadBasic</code> - Read basic chat properties</li>
                                        <li><code>Team.ReadBasic.All</code> - Read basic team properties</li>
                                        <li><code>User.Read</code> - Read user profile</li>
                                    </ul>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary" id="saveTeamsConfigBtn">
                                        <i class="fas fa-save"></i> 
                                        <span class="btn-text">Save Teams Configuration</span>
                                        <i class="fas fa-spinner fa-spin loading-spinner" style="display: none;"></i>
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="testTeamsConnection()">
                                        <i class="fas fa-plug"></i> Test Connection
                                    </button>
                                    <div class="save-status" id="teamsConfigSaveStatus" style="display: none;"></div>
                                </div>
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
            
            // Ensure AI Summary section is visible on page load
            const aiSummarySection = document.getElementById('ai-summary-settings');
            if (aiSummarySection) {
                aiSummarySection.style.display = 'block';
                console.log('AI Summary section made visible on load');
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;
                    console.log('Tab clicked:', targetTab); // Debug log

                    // Remove active class from all tabs and sections
                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => {
                        s.classList.remove('active');
                        s.style.display = 'none';
                    });

                    // Add active class to clicked tab and corresponding section
                    this.classList.add('active');
                    const targetSection = document.getElementById(targetTab + '-settings');
                    console.log('Target section:', targetSection); // Debug log
                    if (targetSection) {
                        targetSection.style.display = 'block';
                        targetSection.classList.add('active');
                        console.log('Section displayed'); // Debug log
                    } else {
                        console.error('Target section not found:', targetTab + '-settings');
                    }
                });
            });

            // AI Prompt functionality
            document.querySelectorAll('.use-example').forEach(button => {
                button.addEventListener('click', function() {
                    const promptData = this.closest('.example-prompt').getAttribute('data-prompt');
                    const decodedPrompt = promptData.replace(/&#10;/g, '\n');
                    document.querySelector('.ai-prompt-textarea').value = decodedPrompt;
                    
                    // Visual feedback
                    this.textContent = 'Applied!';
                    this.style.color = '#10b981';
                    setTimeout(() => {
                        this.textContent = 'Use This Example';
                        this.style.color = '';
                    }, 2000);
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

            // AI Prompt save button loading state
            const aiPromptForm = document.querySelector('form[action=""][method="POST"]');
            const saveBtn = document.getElementById('saveAIPromptBtn');
            
            if (aiPromptForm && saveBtn) {
                aiPromptForm.addEventListener('submit', function(e) {
                    // Show loading state
                    const btnText = saveBtn.querySelector('.btn-text');
                    const spinner = saveBtn.querySelector('.loading-spinner');
                    
                    if (btnText && spinner) {
                        btnText.style.display = 'none';
                        spinner.style.display = 'inline-block';
                        saveBtn.disabled = true;
                    }
                });
            }

            // Show save confirmation when prompt is changed
            const promptTextarea = document.getElementById('ai_summary_prompt');
            if (promptTextarea) {
                let originalValue = promptTextarea.value;
                
                promptTextarea.addEventListener('input', function() {
                    if (this.value !== originalValue) {
                        saveBtn.classList.add('btn-warning');
                        saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span class="btn-text">Save Changes</span>';
                    } else {
                        saveBtn.classList.remove('btn-warning');
                        saveBtn.innerHTML = '<i class="fas fa-save"></i> <span class="btn-text">Save AI Prompt</span>';
                    }
                });
            }
        });
        
        // Function to reset AI prompt to default
        function resetToDefault() {
            const defaultPrompt = `<?php echo addslashes($default_settings['ai_summary_prompt']); ?>`;
            const textarea = document.getElementById('ai_summary_prompt');
            
            if (confirm('Are you sure you want to reset the AI prompt to the default? This will overwrite your current custom prompt.')) {
                textarea.value = defaultPrompt;
                // Auto-resize textarea if needed
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }
        }

        // Enhanced save button functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add form submission handling for API Keys
            const apiKeysForm = document.querySelector('form[data-form="api-keys"]') || 
                               document.querySelector('#api-keys-settings form');
            if (apiKeysForm) {
                apiKeysForm.addEventListener('submit', function(e) {
                    handleFormSubmission(e, 'saveApiKeysBtn', 'apiKeysSaveStatus', 'API configuration');
                });
            }
            
            // Add form submission handling for Teams Config
            const teamsConfigForm = document.querySelector('form[data-form="teams-config"]') || 
                                   document.querySelector('#teams-config-settings form');
            if (teamsConfigForm) {
                teamsConfigForm.addEventListener('submit', function(e) {
                    handleFormSubmission(e, 'saveTeamsConfigBtn', 'teamsConfigSaveStatus', 'Teams configuration');
                });
            }
        });

        function handleFormSubmission(event, buttonId, statusId, configType) {
            const button = document.getElementById(buttonId);
            const status = document.getElementById(statusId);
            
            if (button && status) {
                const btnText = button.querySelector('.btn-text');
                const spinner = button.querySelector('.loading-spinner');
                const icon = button.querySelector('i:first-child');
                
                // Show loading state
                button.disabled = true;
                if (btnText) btnText.textContent = 'Saving...';
                if (spinner) spinner.style.display = 'inline';
                if (icon) icon.style.display = 'none';
                
                status.style.display = 'none';
                
                // The form will submit normally, but we'll show feedback after page reload
                // Store the action in sessionStorage for feedback after redirect
                sessionStorage.setItem('pendingSave', JSON.stringify({
                    type: configType,
                    timestamp: Date.now()
                }));
            }
        }

        // Show feedback after page reload
        window.addEventListener('load', function() {
            const pendingSave = sessionStorage.getItem('pendingSave');
            if (pendingSave) {
                try {
                    const saveData = JSON.parse(pendingSave);
                    // Only show if it's recent (within 5 seconds)
                    if (Date.now() - saveData.timestamp < 5000) {
                        // Check for success/error messages from PHP
                        const successMessage = document.querySelector('.alert.alert-success');
                        const errorMessage = document.querySelector('.alert.alert-error');
                        
                        if (successMessage || errorMessage) {
                            // Add a small delay to ensure the message is visible
                            setTimeout(() => {
                                const message = successMessage || errorMessage;
                                message.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }, 100);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing pending save data:', e);
                }
                sessionStorage.removeItem('pendingSave');
            }
        });

        // Test Teams connection
        function testTeamsConnection() {
            const clientId = document.getElementById('teams_client_id').value;
            const clientSecret = document.getElementById('teams_client_secret').value;
            const tenantId = document.getElementById('teams_tenant_id').value;
            
            if (!clientId || !clientSecret || !tenantId) {
                alert('Please fill in all required fields before testing the connection.');
                return;
            }
            
            const button = document.querySelector('button[onclick="testTeamsConnection()"]');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing Connection...';
            button.disabled = true;
            
            // Make an AJAX request to test the connection
            fetch('api/test_teams_connection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    client_id: clientId,
                    client_secret: clientSecret,
                    tenant_id: tenantId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Connection successful! Your Teams credentials are working properly.');
                } else {
                    alert('❌ Connection failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Test connection error:', error);
                alert('❌ Connection test failed: ' + error.message);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
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

        /* AI Prompt Settings Styles */
        .ai-prompt-textarea {
            width: 100%;
            min-height: 200px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
            background: var(--surface-color);
            transition: border-color 0.2s ease;
        }

        .ai-prompt-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            background: white;
        }

        .setting-item.full-width {
            grid-column: 1 / -1;
        }

        .prompt-examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .example-prompt {
            background: var(--surface-hover);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .example-prompt:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }

        .example-prompt h4 {
            margin: 0 0 8px 0;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
        }

        .example-prompt p {
            margin: 0 0 12px 0;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.4;
        }

        .btn-link {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
            padding: 4px 0;
            transition: color 0.2s ease;
        }

        .btn-link:hover {
            color: var(--primary-hover);
        }

        .setting-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .btn-secondary {
            background: var(--surface-hover);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-1px);
        }

        .btn-warning {
            background: #f59e0b !important;
            border-color: #f59e0b !important;
            color: white !important;
        }

        .btn-warning:hover {
            background: #d97706 !important;
            border-color: #d97706 !important;
        }

        .loading-spinner {
            margin-left: 8px;
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Mobile responsive for AI settings */
        @media (max-width: 768px) {
            .prompt-examples {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .example-prompt {
                padding: 16px;
            }

            .setting-actions {
                flex-direction: column;
            }

            .ai-prompt-textarea {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }

        /* API Keys and Teams Config specific styles */
        .permission-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .permission-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .permission-list li:last-child {
            border-bottom: none;
        }

        .permission-list code {
            background: var(--background-secondary);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: var(--accent-primary);
            font-weight: 500;
        }

        .form-text.text-muted {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-text.text-muted i {
            color: var(--accent-secondary);
        }

        .btn.btn-secondary {
            background: var(--background-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-light);
        }

        .btn.btn-secondary:hover {
            background: var(--background-tertiary);
            border-color: var(--accent-primary);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }

        #teams-config-settings .setting-item input[type="text"],
        #teams-config-settings .setting-item input[type="password"],
        #api-keys-settings .setting-item input[type="password"] {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }

        /* Save button loading states */
        .btn .loading-spinner {
            margin-left: 0.5rem;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .save-status {
            margin-left: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .save-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .save-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Alert message styling */
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert.alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert.alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert i {
            margin-right: 0.5rem;
        }
    </style>
</body>
</html>