<?php
ob_start(); // Start output buffering
session_start();
require_once 'teams_api.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get user information from session (all from database)
$user_name = $_SESSION['user_name'] ?? 'Unknown User';
$user_email = $_SESSION['user_email'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// If user_id is missing, redirect to login (database authentication required)
if (!$user_id) {
    error_log("Missing user_id in session, redirecting to login");
    header('Location: login.php');
    exit();
}

// Initialize Teams API
$teamsAPI = new TeamsAPIHelper();

// Handle date range and filters
$date_range = $_GET['range'] ?? 'today';
$channel_filter = $_GET['channel'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Get channels from Teams API
$channels = $teamsAPI->getAllChannels();

// Get statistics data based on filters
function getStatistics($channel_filter, $type_filter, $teamsAPI, $channels) {
    $base_stats = [
        'total_messages' => 1247,
        'urgent_messages' => 23,
        'mentions' => 89,
        'files_shared' => 156
    ];
    
    // If specific channel is selected, try to get real data
    if ($channel_filter !== 'all') {
        $selectedChannel = null;
        foreach ($channels as $channel) {
            if ($channel['id'] === $channel_filter) {
                $selectedChannel = $channel;
                break;
            }
        }
        
        if ($selectedChannel) {
            try {
                // Try to get real messages count from the API
                $messages = $teamsAPI->getChannelMessages($selectedChannel['teamId'], $selectedChannel['id'], 100);
                if (!empty($messages)) {
                    $messageCount = count($messages);
                    $urgentCount = 0;
                    $mentionCount = 0;
                    
                    foreach ($messages as $message) {
                        // Check for urgent indicators
                        if (isset($message['importance']) && $message['importance'] === 'high') {
                            $urgentCount++;
                        }
                        // Check for mentions (simplified check)
                        if (isset($message['body']['content']) && strpos($message['body']['content'], '@') !== false) {
                            $mentionCount++;
                        }
                    }
                    
                    $base_stats['total_messages'] = $messageCount;
                    $base_stats['urgent_messages'] = $urgentCount;
                    $base_stats['mentions'] = $mentionCount;
                    $base_stats['files_shared'] = floor($messageCount * 0.15); // Estimate
                }
            } catch (Exception $e) {
                error_log("Error getting real channel data: " . $e->getMessage());
            }
        }
    }
    
    // Apply filter modifications for type filter
    if ($type_filter !== 'all') {
        $modifier = 0.3 + (rand(0, 70) / 100);
        foreach ($base_stats as $key => $value) {
            if ($type_filter === 'urgent' && $key !== 'urgent_messages') {
                $base_stats[$key] = floor($value * $modifier);
            } elseif ($type_filter === 'mentions' && $key !== 'mentions') {
                $base_stats[$key] = floor($value * $modifier);
            }
        }
    }
    
    return $base_stats;
}

$statistics = getStatistics($channel_filter, $type_filter, $teamsAPI, $channels);

// Mock timeline data
$timeline_items = [
    [
        'time' => '9:15 AM',
        'type' => 'urgent',
        'channel' => '#development',
        'message' => 'Production deployment failed. Rolling back to previous version immediately.',
        'author' => 'DevOps Team',
        'reactions' => 5
    ],
    [
        'time' => '10:30 AM',
        'type' => 'mention',
        'channel' => '#general',
        'message' => '@' . explode('@', $user_email)[0] . ' Project deadline moved to Friday. Need immediate feedback on the proposal.',
        'author' => 'Sarah Johnson',
        'reactions' => 12
    ],
    [
        'time' => '11:45 AM',
        'type' => 'file',
        'channel' => '#design',
        'message' => 'Updated mockups for the new feature. Please review and provide feedback.',
        'author' => 'Mike Chen',
        'reactions' => 8,
        'attachment' => 'UI_Mockups_v2.3.fig'
    ],
    [
        'time' => '2:00 PM',
        'type' => 'meeting',
        'channel' => '#general',
        'message' => 'Weekly team standup completed. Action items assigned to team members.',
        'author' => 'Meeting Bot',
        'reactions' => 3,
        'meeting_data' => [
            'duration' => '45 minutes',
            'attendees' => '12 people',
            'action_items' => '7 assigned'
        ]
    ],
    [
        'time' => '3:30 PM',
        'type' => 'normal',
        'channel' => '#marketing',
        'message' => 'Campaign performance metrics for Q4 are looking great! Exceeded targets by 15%.',
        'author' => 'Lisa Wang',
        'reactions' => 15
    ]
];

// Mock summary cards data
$summary_cards = [
    [
        'channel' => 'General',
        'icon' => 'hashtag',
        'time_range' => 'Today, 8:00 AM - 6:00 PM',
        'metrics' => [
            'messages' => rand(100, 150),
            'urgent' => rand(5, 12),
            'mentions' => rand(15, 30)
        ],
        'highlights' => [
            'Project deadline moved to Friday - requires immediate action',
            'New client onboarding process approved',
            'Q4 planning meeting scheduled for next week',
            'Security policy updates require team acknowledgment'
        ],
        'contributors' => [
            ['name' => 'Sarah J.', 'avatar' => 'Sarah+Johnson', 'color' => '10b981', 'messages' => 34],
            ['name' => 'Mike C.', 'avatar' => 'Mike+Chen', 'color' => '6366f1', 'messages' => 28],
            ['name' => 'Lisa W.', 'avatar' => 'Lisa+Wang', 'color' => '8b5cf6', 'messages' => 19]
        ]
    ],
    [
        'channel' => 'Development Team',
        'icon' => 'users',
        'time_range' => 'Today, 9:00 AM - 5:30 PM',
        'metrics' => [
            'messages' => rand(70, 100),
            'urgent' => rand(1, 5),
            'mentions' => rand(10, 20)
        ],
        'highlights' => [
            'Production deployment rollback completed successfully',
            'API endpoints ready for testing phase',
            'Code review process improvements discussed',
            'New feature branch merged to main'
        ],
        'contributors' => [
            ['name' => 'Alex R.', 'avatar' => 'Alex+Rodriguez', 'color' => 'ef4444', 'messages' => 25],
            ['name' => 'DevOps', 'avatar' => 'DevOps+Team', 'color' => 'f59e0b', 'messages' => 18],
            ['name' => 'Emma W.', 'avatar' => 'Emma+Wilson', 'color' => '06b6d4', 'messages' => 12]
        ]
    ],
    [
        'channel' => 'Design Team',
        'icon' => 'paint-brush',
        'time_range' => 'Today, 10:00 AM - 4:00 PM',
        'metrics' => [
            'messages' => rand(35, 60),
            'urgent' => rand(0, 2),
            'mentions' => rand(5, 12)
        ],
        'highlights' => [
            'New UI mockups v2.3 shared for review',
            'Design system updates approved',
            'User feedback incorporated into prototypes',
            'Accessibility guidelines review completed'
        ],
        'contributors' => [
            ['name' => 'Mike C.', 'avatar' => 'Mike+Chen', 'color' => '6366f1', 'messages' => 16],
            ['name' => 'Sophie D.', 'avatar' => 'Sophie+Davis', 'color' => 'ec4899', 'messages' => 14],
            ['name' => 'Tom W.', 'avatar' => 'Tom+Wilson', 'color' => '84cc16', 'messages' => 8]
        ]
    ]
];

// Function to get badge class for timeline items
function getBadgeClass($type) {
    switch ($type) {
        case 'urgent': return 'urgent';
        case 'mention': return 'mention';
        case 'file': return 'file';
        case 'meeting': return 'meeting';
        default: return '';
    }
}

// Function to get badge text
function getBadgeText($type) {
    switch ($type) {
        case 'urgent': return 'Urgent';
        case 'mention': return '@mention';
        case 'file': return 'File';
        case 'meeting': return 'Meeting';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summaries - Teams Activity Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <link href="summaries-styles.css" rel="stylesheet">
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
                <li class="nav-item active">
                    <a href="summaries.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span>Summaries</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
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
                    <h1>Summaries</h1>
                </div>
                <div class="top-bar-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">2</span>
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

            <!-- Summaries Content -->
            <div class="summaries-content">
                <!-- Summary Controls -->
                <section class="summary-controls">
                    <div class="controls-left">
                        <div class="date-range-picker">
                            <button class="date-preset <?php echo $date_range === 'today' ? 'active' : ''; ?>" data-range="today">Today</button>
                            <button class="date-preset <?php echo $date_range === 'week' ? 'active' : ''; ?>" data-range="week">This Week</button>
                            <button class="date-preset <?php echo $date_range === 'month' ? 'active' : ''; ?>" data-range="month">This Month</button>
                            <button class="date-preset <?php echo $date_range === 'custom' ? 'active' : ''; ?>" data-range="custom">Custom Range</button>
                        </div>
                        <div class="custom-date-inputs" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                            <input type="date" id="startDate" class="date-input" value="<?php echo date('Y-m-d'); ?>">
                            <span class="date-separator">to</span>
                            <input type="date" id="endDate" class="date-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="controls-right">
                        <div class="summary-filters">
                            <select class="filter-select" id="channelFilter">
                                <option value="all" <?php echo $channel_filter === 'all' ? 'selected' : ''; ?>>All Channels</option>
                                <?php foreach ($channels as $channel): ?>
                                <option value="<?php echo htmlspecialchars($channel['id']); ?>" <?php echo $channel_filter === $channel['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($channel['displayName']); ?>
                                    <?php if (!empty($channel['teamName'])): ?>
                                        (<?php echo htmlspecialchars($channel['teamName']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="filter-select" id="typeFilter">
                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="urgent" <?php echo $type_filter === 'urgent' ? 'selected' : ''; ?>>Urgent Only</option>
                                <option value="mentions" <?php echo $type_filter === 'mentions' ? 'selected' : ''; ?>>Mentions Only</option>
                                <option value="files" <?php echo $type_filter === 'files' ? 'selected' : ''; ?>>File Shares</option>
                                <option value="meetings" <?php echo $type_filter === 'meetings' ? 'selected' : ''; ?>>Meeting Notes</option>
                            </select>
                        </div>
                        <button class="generate-report-btn">
                            <i class="fas fa-download"></i>
                            <span>Export Report</span>
                        </button>
                    </div>
                </section>

                <!-- Summary Statistics -->
                <section class="summary-stats-section">
                    <div class="stats-grid">
                        <div class="stat-card messages">
                            <div class="stat-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($statistics['total_messages']); ?></div>
                                <div class="stat-label">Total Messages</div>
                                <div class="stat-change positive">+12% from last period</div>
                            </div>
                        </div>
                        <div class="stat-card urgent">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $statistics['urgent_messages']; ?></div>
                                <div class="stat-label">Urgent Messages</div>
                                <div class="stat-change negative">-5% from last period</div>
                            </div>
                        </div>
                        <div class="stat-card mentions">
                            <div class="stat-icon">
                                <i class="fas fa-at"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $statistics['mentions']; ?></div>
                                <div class="stat-label">Mentions</div>
                                <div class="stat-change positive">+8% from last period</div>
                            </div>
                        </div>
                        <div class="stat-card files">
                            <div class="stat-icon">
                                <i class="fas fa-paperclip"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $statistics['files_shared']; ?></div>
                                <div class="stat-label">Files Shared</div>
                                <div class="stat-change positive">+15% from last period</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Summary Timeline -->
                <section class="timeline-section">
                    <div class="card timeline-card">
                        <div class="card-header">
                            <h3><i class="fas fa-clock"></i> Activity Timeline</h3>
                            <div class="timeline-controls">
                                <button class="timeline-view active" data-view="day">Day View</button>
                                <button class="timeline-view" data-view="week">Week View</button>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="timeline">
                                <?php foreach ($timeline_items as $item): ?>
                                <div class="timeline-item <?php echo $item['type']; ?>">
                                    <div class="timeline-time"><?php echo $item['time']; ?></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <span class="timeline-channel"><?php echo htmlspecialchars($item['channel']); ?></span>
                                            <?php if ($item['type'] !== 'normal'): ?>
                                            <span class="timeline-badge <?php echo getBadgeClass($item['type']); ?>">
                                                <?php echo getBadgeText($item['type']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="timeline-message"><?php echo htmlspecialchars($item['message']); ?></p>
                                        
                                        <?php if (isset($item['attachment'])): ?>
                                        <div class="timeline-attachment">
                                            <i class="fas fa-file-image"></i>
                                            <span><?php echo htmlspecialchars($item['attachment']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($item['meeting_data'])): ?>
                                        <div class="timeline-meeting-summary">
                                            <div class="meeting-stat">
                                                <span class="meeting-label">Duration:</span>
                                                <span class="meeting-value"><?php echo $item['meeting_data']['duration']; ?></span>
                                            </div>
                                            <div class="meeting-stat">
                                                <span class="meeting-label">Attendees:</span>
                                                <span class="meeting-value"><?php echo $item['meeting_data']['attendees']; ?></span>
                                            </div>
                                            <div class="meeting-stat">
                                                <span class="meeting-label">Action Items:</span>
                                                <span class="meeting-value"><?php echo $item['meeting_data']['action_items']; ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="timeline-meta">
                                            <span class="timeline-author"><?php echo htmlspecialchars($item['author']); ?></span>
                                            <span class="timeline-reactions"><?php echo $item['reactions']; ?> reactions</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Summary Cards Grid -->
                <section class="summary-cards-section">
                    <div class="section-header">
                        <h3>Detailed Summaries</h3>
                        <div class="view-toggle">
                            <button class="view-btn active" data-view="cards">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="summaries-grid" id="summariesGrid">
                        <?php foreach ($summary_cards as $card): ?>
                        <div class="card summary-card">
                            <div class="summary-card-header">
                                <div class="summary-channel">
                                    <i class="fas fa-<?php echo $card['icon']; ?>"></i>
                                    <span><?php echo htmlspecialchars($card['channel']); ?></span>
                                </div>
                                <div class="summary-time"><?php echo $card['time_range']; ?></div>
                            </div>
                            <div class="summary-card-content">
                                <div class="summary-metrics">
                                    <div class="metric">
                                        <span class="metric-value"><?php echo $card['metrics']['messages']; ?></span>
                                        <span class="metric-label">Messages</span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-value"><?php echo $card['metrics']['urgent']; ?></span>
                                        <span class="metric-label">Urgent</span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-value"><?php echo $card['metrics']['mentions']; ?></span>
                                        <span class="metric-label">Mentions</span>
                                    </div>
                                </div>
                                <div class="summary-highlights">
                                    <h4>Key Highlights</h4>
                                    <ul>
                                        <?php foreach ($card['highlights'] as $highlight): ?>
                                        <li><?php echo htmlspecialchars($highlight); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="summary-top-contributors">
                                    <h4>Top Contributors</h4>
                                    <div class="contributors-list">
                                        <?php foreach ($card['contributors'] as $contributor): ?>
                                        <div class="contributor">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($contributor['avatar']); ?>&background=<?php echo $contributor['color']; ?>&color=fff" alt="<?php echo htmlspecialchars($contributor['name']); ?>">
                                            <span><?php echo htmlspecialchars($contributor['name']); ?></span>
                                            <span class="message-count"><?php echo $contributor['messages']; ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="summary-card-footer">
                                <button class="action-btn primary">View Details</button>
                                <button class="action-btn secondary">Export</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Mobile Navigation Overlay -->
    <div class="mobile-nav-overlay"></div>

    <script src="script.js"></script>
    <script src="summaries-script.js"></script>
    <script>
        // PHP data for JavaScript
        window.phpData = {
            userName: '<?php echo addslashes($user_name); ?>',
            userEmail: '<?php echo addslashes($user_email); ?>',
            currentFilters: {
                dateRange: '<?php echo $date_range; ?>',
                channel: '<?php echo $channel_filter; ?>',
                type: '<?php echo $type_filter; ?>'
            },
            statistics: <?php echo json_encode($statistics); ?>,
            currentPage: 'summaries.php'
        };
    </script>
</body>
</html>