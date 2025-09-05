<?php
ob_start(); // Start output buffering
session_start();
require_once 'database_helper.php';
require_once 'session_validator.php';

// Use unified session validation
$current_user = SessionValidator::requireAuth();

// Get user information with guaranteed consistency
$user_id = $current_user['id'];
$user_name = $current_user['name'];
$user_email = $current_user['email'];
$login_time = $_SESSION['login_time'] ?? date('Y-m-d H:i:s');

// Log session refresh if it occurred
if ($current_user['session_refreshed']) {
    error_log("Index.php: Session data refreshed for user {$user_id} - {$user_email}");
}

// Try to get data from database, fall back to mock data if database unavailable
try {
    global $db;
    
    if ($user_id) {
        // Get real data from database
        $daily_stats = $db->getDailyStats($user_id);
        $channels = $db->getUserChannels($user_id);
        $delivery_logs = $db->getDeliveryLogs($user_id);
        
        // Convert database format to template format for channels
        $formatted_channels = [];
        foreach ($channels as $channel) {
            $messages = $db->getChannelMessages($channel['id'], 3);
            $formatted_messages = [];
            
            foreach ($messages as $msg) {
                $type = 'normal';
                if ($msg['priority'] === 'urgent') $type = 'urgent';
                elseif ($msg['has_mentions']) $type = 'mention';
                
                $formatted_messages[] = [
                    'time' => $msg['formatted_time'],
                    'type' => $type,
                    'content' => $msg['content'],
                    'author' => $msg['author_name']
                ];
            }
            
            $formatted_channels[] = [
                'name' => $channel['display_name'],
                'icon' => $channel['icon'],
                'message_count' => $channel['message_count'],
                'urgent_count' => $channel['urgent_count'],
                'mentions_count' => $channel['mentions_count'],
                'messages' => $formatted_messages
            ];
        }
        $channels = $formatted_channels;
        
        // Format delivery logs
        $formatted_delivery_logs = [];
        foreach ($delivery_logs as $log) {
            $formatted_delivery_logs[] = [
                'type' => $log['status'],
                'title' => $log['subject'] ?: 'Notification delivered',
                'time' => $log['formatted_time'],
                'recipient' => $log['recipient'],
                'status' => ucfirst($log['status']),
                'error' => $log['error_message']
            ];
        }
        $delivery_logs = $formatted_delivery_logs;
        
    } else {
        throw new Exception("Using demo data");
    }
} catch (Exception $e) {
    // Fall back to mock data if database is unavailable
    $daily_stats = [
        'urgent_messages' => rand(3, 8),
        'mentions' => rand(8, 15),
        'missed_chats' => rand(5, 12)
    ];
    
    // Mock data for demo users or when database is unavailable

$channels = [
    [
        'name' => 'General',
        'icon' => 'hashtag',
        'message_count' => rand(20, 30),
        'urgent_count' => rand(1, 3),
        'mentions_count' => rand(3, 8),
        'messages' => [
            [
                'time' => '10:30 AM',
                'type' => 'urgent',
                'content' => 'Project deadline moved to Friday. Need immediate feedback on the proposal.',
                'author' => 'Sarah Johnson'
            ],
            [
                'time' => '11:45 AM',
                'type' => 'mention',
                'content' => '@' . explode('@', $user_email)[0] . ' can you review the updated design mockups?',
                'author' => 'Mike Chen'
            ],
            [
                'time' => '2:15 PM',
                'type' => 'normal',
                'content' => 'Great work on the presentation today, team!',
                'author' => 'Lisa Wang'
            ]
        ]
    ],
    [
        'name' => 'Development Team',
        'icon' => 'users',
        'message_count' => rand(40, 50),
        'urgent_count' => rand(1, 2),
        'mentions_count' => rand(2, 5),
        'messages' => [
            [
                'time' => '9:15 AM',
                'type' => 'urgent',
                'content' => 'Production deployment failed. Rolling back to previous version.',
                'author' => 'DevOps Team'
            ],
            [
                'time' => '1:20 PM',
                'type' => 'mention',
                'content' => '@' . explode('@', $user_email)[0] . ' the API endpoints are ready for testing.',
                'author' => 'Alex Rodriguez'
            ]
        ]
    ],
    [
        'name' => 'Direct Messages',
        'icon' => 'comment',
        'message_count' => rand(10, 15),
        'urgent_count' => rand(1, 3),
        'mentions_count' => rand(3, 6),
        'messages' => [
            [
                'time' => '8:45 AM',
                'type' => 'urgent',
                'content' => 'Client meeting moved to 3 PM today. Please confirm attendance.',
                'author' => 'Manager'
            ],
            [
                'time' => '12:30 PM',
                'type' => 'normal',
                'content' => 'Thanks for the code review feedback!',
                'author' => 'Teammate'
            ]
        ]
    ]
];

$delivery_logs = [
    [
        'type' => 'success',
        'title' => 'Daily digest sent via email',
        'time' => 'Today, 8:00 AM',
        'recipient' => $user_email,
        'status' => 'Delivered'
    ],
    [
        'type' => 'success',
        'title' => 'Urgent alert sent via Teams webhook',
        'time' => 'Today, 10:35 AM',
        'recipient' => 'Development Team channel',
        'status' => 'Delivered'
    ],
    [
        'type' => 'failed',
        'title' => 'Weekly summary delivery failed',
        'time' => 'Yesterday, 5:00 PM',
        'recipient' => 'team@company.com',
        'status' => 'Failed',
        'error' => 'SMTP timeout'
    ],
    [
        'type' => 'pending',
        'title' => 'Evening digest queued',
        'time' => 'Today, 6:00 PM',
        'recipient' => $user_email,
        'status' => 'Pending'
    ]
];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Activity Dashboard</title>
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
                <li class="nav-item active">
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
                    <h1>Dashboard</h1>
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

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Daily Digest Summary Card -->
                <section class="summary-section">
                    <div class="card daily-digest">
                        <div class="card-header">
                            <h2><i class="fas fa-calendar-day"></i> Today's Teams Activity</h2>
                            <span class="timestamp">Last updated: <?php echo date('g:i A'); ?></span>
                        </div>
                        <div class="card-content">
                            <div class="summary-stats">
                                <div class="stat-item urgent">
                                    <div class="stat-number"><?php echo $daily_stats['urgent_messages']; ?></div>
                                    <div class="stat-label">Urgent Messages</div>
                                </div>
                                <div class="stat-item mentions">
                                    <div class="stat-number"><?php echo $daily_stats['mentions']; ?></div>
                                    <div class="stat-label">Mentions</div>
                                </div>
                                <div class="stat-item missed">
                                    <div class="stat-number"><?php echo $daily_stats['missed_chats']; ?></div>
                                    <div class="stat-label">Missed Chats</div>
                                </div>
                            </div>
                            <div class="summary-text">
                                <p>You have <?php echo $daily_stats['urgent_messages']; ?> urgent messages requiring attention, <?php echo $daily_stats['mentions']; ?> mentions across different channels, and <?php echo $daily_stats['missed_chats']; ?> missed chat conversations from today.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Filters -->
                <section class="filters-section">
                    <div class="filters">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="urgent">Urgent Only</button>
                        <button class="filter-btn" data-filter="mentions">Mentions Only</button>
                    </div>
                </section>

                <!-- Channel & Chat List Section -->
                <section class="channels-section">
                    <div class="section-header">
                        <h3>Channels & Chats</h3>
                        <button class="expand-all-btn">Expand All</button>
                    </div>
                    
                    <div class="channels-grid">
                        <?php foreach ($channels as $channel): ?>
                        <div class="card channel-card">
                            <div class="channel-header" onclick="toggleChannel(this)">
                                <div class="channel-info">
                                    <i class="fas fa-<?php echo $channel['icon']; ?>"></i>
                                    <span class="channel-name"><?php echo htmlspecialchars($channel['name']); ?></span>
                                    <span class="message-count"><?php echo $channel['message_count']; ?> messages</span>
                                </div>
                                <div class="channel-badges">
                                    <?php if ($channel['urgent_count'] > 0): ?>
                                    <span class="badge urgent"><?php echo $channel['urgent_count']; ?> urgent</span>
                                    <?php endif; ?>
                                    <?php if ($channel['mentions_count'] > 0): ?>
                                    <span class="badge mentions"><?php echo $channel['mentions_count']; ?> mentions</span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-down expand-icon"></i>
                            </div>
                            <div class="channel-content collapsed">
                                <?php foreach ($channel['messages'] as $message): ?>
                                <div class="summary-item">
                                    <div class="summary-meta">
                                        <span class="time"><?php echo $message['time']; ?></span>
                                        <?php if ($message['type'] === 'urgent'): ?>
                                        <span class="badge urgent">Urgent</span>
                                        <?php elseif ($message['type'] === 'mention'): ?>
                                        <span class="badge mention">@mention</span>
                                        <?php endif; ?>
                                    </div>
                                    <p><?php echo htmlspecialchars($message['content']); ?></p>
                                    <span class="author">- <?php echo htmlspecialchars($message['author']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Delivery Logs -->
                <section class="logs-section">
                    <div class="card delivery-logs">
                        <div class="card-header">
                            <h3><i class="fas fa-paper-plane"></i> Delivery Logs</h3>
                            <button class="refresh-btn"><i class="fas fa-sync-alt"></i></button>
                        </div>
                        <div class="card-content">
                            <div class="log-filters">
                                <select class="log-type-filter">
                                    <option value="all">All Deliveries</option>
                                    <option value="email">Email Only</option>
                                    <option value="teams">Teams Webhook Only</option>
                                </select>
                            </div>
                            <div class="logs-list">
                                <?php foreach ($delivery_logs as $log): ?>
                                <div class="log-item <?php echo $log['type']; ?>">
                                    <div class="log-icon">
                                        <i class="fas <?php echo $log['type'] === 'success' ? 'fa-check-circle' : ($log['type'] === 'failed' ? 'fa-exclamation-circle' : 'fa-clock'); ?>"></i>
                                    </div>
                                    <div class="log-content">
                                        <div class="log-title"><?php echo htmlspecialchars($log['title']); ?></div>
                                        <div class="log-meta">
                                            <span class="log-time"><?php echo $log['time']; ?></span>
                                            <span class="log-recipient"><?php echo htmlspecialchars($log['recipient']); ?></span>
                                            <?php if (isset($log['error'])): ?>
                                            <span class="log-error"><?php echo htmlspecialchars($log['error']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="log-status <?php echo $log['type']; ?>"><?php echo $log['status']; ?></div>
                                </div>
                                <?php endforeach; ?>
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
            loginTime: '<?php echo $login_time; ?>',
            currentPage: 'index.php'
        };
    </script>
</body>
</html>