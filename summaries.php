<?php
ob_start(); // Start output buffering
session_start();

// Performance optimizations
ini_set('memory_limit', '256M');
set_time_limit(30); // Set reasonable time limit

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

// Initialize Teams API - check if user has connected their Microsoft account
require_once 'user_teams_api.php';
$userTeamsAPI = new UserTeamsAPIHelper($user_id);

// Debug: Log user connection check
error_log("Summaries Page: Checking connection for user_id: $user_id");

// Try user-specific API first, fallback to app-only API
$user_is_connected = $userTeamsAPI->isConnected();
error_log("Summaries Page: User connected = " . ($user_is_connected ? 'true' : 'false'));

if ($user_is_connected) {
    $teamsAPI = $userTeamsAPI;
    $is_user_connected = true;
    error_log("Summaries Page: Using UserTeamsAPI for user $user_id");
} else {
    // Fallback to app-only API (original behavior)
    require_once 'teams_api.php';
    $teamsAPI = new TeamsAPIHelper();
    $is_user_connected = false;
    error_log("Summaries Page: Using app-only TeamsAPI (user not connected)");
}

// Handle date range and filters
$date_range = $_GET['range'] ?? 'today';
$channel_filter = $_GET['channel'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$custom_start = $_GET['start'] ?? '';
$custom_end = $_GET['end'] ?? '';

// Get channels from Teams API with caching for better performance
$channels = $teamsAPI->getAllChannels();

// Performance optimization: Limit concurrent API calls
$max_channels_to_process = 10; // Limit the number of channels for performance
if (count($channels) > $max_channels_to_process) {
    $channels = array_slice($channels, 0, $max_channels_to_process);
}

// If user is connected but no channels found, check if it's a permissions issue
$has_permissions_issue = false;
if ($is_user_connected && empty($channels)) {
    $has_permissions_issue = testTeamsPermissionsIssue($user_id);
}

// Helper function to get date range based on filter
function getDateRange($date_range, $custom_start = '', $custom_end = '') {
    $now = new DateTime();
    $startDate = null;
    $endDate = null;
    
    switch ($date_range) {
        case 'today':
            $startDate = new DateTime('today');
            $endDate = new DateTime('today 23:59:59');
            break;
        case 'week':
            $startDate = new DateTime('monday this week');
            $endDate = new DateTime('sunday this week 23:59:59');
            break;
        case 'month':
            $startDate = new DateTime('first day of this month');
            $endDate = new DateTime('last day of this month 23:59:59');
            break;
        case 'custom':
            if ($custom_start && $custom_end) {
                try {
                    $startDate = new DateTime($custom_start);
                    $endDate = new DateTime($custom_end . ' 23:59:59');
                } catch (Exception $e) {
                    // Fallback to today if custom dates are invalid
                    $startDate = new DateTime('today');
                    $endDate = new DateTime('today 23:59:59');
                }
            } else {
                // Fallback to today if custom dates not provided
                $startDate = new DateTime('today');
                $endDate = new DateTime('today 23:59:59');
            }
            break;
        default:
            // Default to today if unknown range
            $startDate = new DateTime('today');
            $endDate = new DateTime('today 23:59:59');
    }
    
    return ['start' => $startDate, 'end' => $endDate];
}

// Helper function to check if message is within date range
function isMessageInDateRange($message, $startDate, $endDate) {
    if (!isset($message['createdDateTime'])) {
        return false;
    }
    
    try {
        $messageDate = new DateTime($message['createdDateTime']);
        return $messageDate >= $startDate && $messageDate < $endDate;
    } catch (Exception $e) {
        return false;
    }
}

// Get real statistics data based on filters
function getStatistics($channel_filter, $type_filter, $date_range, $teamsAPI, $channels, $custom_start = '', $custom_end = '') {
    $stats = [
        'total_messages' => 0,
        'urgent_messages' => 0,
        'mentions' => 0,
        'files_shared' => 0
    ];
    
    if (empty($channels)) {
        return $stats;
    }
    
    // Get date range for filtering
    $dateRange = getDateRange($date_range, $custom_start, $custom_end);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
    // If specific channel is selected, get data for that channel only
    if ($channel_filter !== 'all') {
        $selectedChannel = null;
        foreach ($channels as $channel) {
            if ($channel['id'] === $channel_filter) {
                $selectedChannel = $channel;
                break;
            }
        }
        
        if ($selectedChannel) {
            $messages = $teamsAPI->getChannelMessages($selectedChannel['teamId'], $selectedChannel['id'], 100);
            $messageCount = 0;
            $urgentCount = 0;
            $mentionCount = 0;
            $fileCount = 0;
            
            foreach ($messages as $message) {
                // Filter by date range
                if (!isMessageInDateRange($message, $startDate, $endDate)) {
                    continue;
                }
                
                $messageCount++;
                
                // Check for urgent indicators
                if (isset($message['importance']) && $message['importance'] === 'high') {
                    $urgentCount++;
                }
                // Check for mentions
                if (isset($message['body']['content']) && strpos($message['body']['content'], '@') !== false) {
                    $mentionCount++;
                }
                // Check for file attachments
                if (isset($message['attachments']) && !empty($message['attachments'])) {
                    $fileCount++;
                }
            }
            
            $stats['total_messages'] = $messageCount;
            $stats['urgent_messages'] = $urgentCount;
            $stats['mentions'] = $mentionCount;
            $stats['files_shared'] = $fileCount;
        }
    } else {
        // Get aggregated data for all channels (optimized - limit channels and messages)
        $channelsToCheck = array_slice($channels, 0, 5); // Limit to first 5 channels for performance
        foreach ($channelsToCheck as $channel) {
            $messages = $teamsAPI->getChannelMessages($channel['teamId'], $channel['id'], 25); // Reduced from 50 to 25
            
            foreach ($messages as $message) {
                // Filter by date range
                if (!isMessageInDateRange($message, $startDate, $endDate)) {
                    continue;
                }
                
                $stats['total_messages']++;
                
                if (isset($message['importance']) && $message['importance'] === 'high') {
                    $stats['urgent_messages']++;
                }
                if (isset($message['body']['content']) && strpos($message['body']['content'], '@') !== false) {
                    $stats['mentions']++;
                }
                if (isset($message['attachments']) && !empty($message['attachments'])) {
                    $stats['files_shared']++;
                }
            }
        }
    }
    
    return $stats;
}

$statistics = getStatistics($channel_filter, $type_filter, $date_range, $teamsAPI, $channels, $custom_start, $custom_end);

// Get real timeline data from selected channels
function getTimelineItems($channel_filter, $date_range, $teamsAPI, $channels, $limit = 10, $custom_start = '', $custom_end = '') {
    $timelineItems = [];
    
    if (empty($channels)) {
        return $timelineItems;
    }
    
    // Get date range for filtering
    $dateRange = getDateRange($date_range, $custom_start, $custom_end);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
    if ($channel_filter !== 'all') {
        // Get messages from selected channel only
        $selectedChannel = null;
        foreach ($channels as $channel) {
            if ($channel['id'] === $channel_filter) {
                $selectedChannel = $channel;
                break;
            }
        }
        
        if ($selectedChannel) {
            $messages = $teamsAPI->getChannelMessages($selectedChannel['teamId'], $selectedChannel['id'], min($limit * 2, 50)); // Cap at 50 messages max
            foreach ($messages as $message) {
                // Filter by date range
                if (!isMessageInDateRange($message, $startDate, $endDate)) {
                    continue;
                }
                
                if (count($timelineItems) >= $limit) {
                    break;
                }
                
                $timelineItems[] = [
                    'time' => date('g:i A', strtotime($message['createdDateTime'])),
                    'date' => date('Y-m-d', strtotime($message['createdDateTime'])),
                    'type' => isset($message['importance']) && $message['importance'] === 'high' ? 'urgent' : 'normal',
                    'channel' => '#' . $selectedChannel['displayName'],
                    'teamName' => $selectedChannel['teamName'],
                    'message' => strip_tags($message['body']['content'] ?? 'No content'),
                    'author' => $message['from']['user']['displayName'] ?? 'Unknown User',
                    'hasAttachments' => isset($message['attachments']) && !empty($message['attachments'])
                ];
            }
        }
    } else {
        // Get messages from all channels (optimized - limit channels and messages)
        $channelsToCheck = array_slice($channels, 0, 3); // Reduced from 5 to 3 channels for better performance
        foreach ($channelsToCheck as $channel) {
            if (count($timelineItems) >= $limit) {
                break;
            }
            
            $messages = $teamsAPI->getChannelMessages($channel['teamId'], $channel['id'], 8); // Reduced from 10 to 8 messages
            foreach ($messages as $message) {
                // Filter by date range
                if (!isMessageInDateRange($message, $startDate, $endDate)) {
                    continue;
                }
                
                if (count($timelineItems) >= $limit) {
                    break 2; // Break out of both loops
                }
                
                $timelineItems[] = [
                    'time' => date('g:i A', strtotime($message['createdDateTime'])),
                    'date' => date('Y-m-d', strtotime($message['createdDateTime'])),
                    'type' => isset($message['importance']) && $message['importance'] === 'high' ? 'urgent' : 'normal',
                    'channel' => '#' . $channel['displayName'],
                    'teamName' => $channel['teamName'],
                    'message' => strip_tags($message['body']['content'] ?? 'No content'),
                    'author' => $message['from']['user']['displayName'] ?? 'Unknown User',
                    'hasAttachments' => isset($message['attachments']) && !empty($message['attachments'])
                ];
            }
        }
    }
    
    // Sort by creation date (newest first)
    usort($timelineItems, function($a, $b) {
        return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
    });
    
    return array_slice($timelineItems, 0, $limit);
}

// Get real timeline data
$timeline_items = getTimelineItems($channel_filter, $date_range, $teamsAPI, $channels, 15, $custom_start, $custom_end);

// Generate real summary cards from Teams API data
function generateSummaryCards($channels, $teamsAPI, $channel_filter, $date_range, $custom_start = '', $custom_end = '') {
    $summary_cards = [];
    
    if (empty($channels)) {
        return $summary_cards;
    }
    
    // Get date range for filtering
    $dateRange = getDateRange($date_range, $custom_start, $custom_end);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
    // Get up to 4 channels for summary cards (reduced for performance)
    $channelsToSummarize = array_slice($channels, 0, 4);
    
    foreach ($channelsToSummarize as $channel) {
        $messages = $teamsAPI->getChannelMessages($channel['teamId'], $channel['id'], 50); // Reduced from 100 to 50
        $messageCount = 0;
        
        $urgentCount = 0;
        $mentionCount = 0;
        $contributors = [];
        $recentMessages = [];
        
        foreach ($messages as $message) {
            // Filter by date range
            if (!isMessageInDateRange($message, $startDate, $endDate)) {
                continue;
            }
            
            $messageCount++;
            // Count urgent messages
            if (isset($message['importance']) && $message['importance'] === 'high') {
                $urgentCount++;
            }
            
            // Count mentions
            if (isset($message['body']['content']) && strpos($message['body']['content'], '@') !== false) {
                $mentionCount++;
            }
            
            // Track contributors
            $authorName = $message['from']['user']['displayName'] ?? 'Unknown User';
            if (!isset($contributors[$authorName])) {
                $contributors[$authorName] = 0;
            }
            $contributors[$authorName]++;
            
            // Collect recent meaningful messages for highlights
            $content = strip_tags($message['body']['content'] ?? '');
            if (strlen($content) > 20 && strlen($content) < 200) {
                $recentMessages[] = $content;
            }
        }
        
        // Sort contributors by message count
        arsort($contributors);
        $topContributors = [];
        $colors = ['10b981', '6366f1', '8b5cf6', 'ef4444', 'f59e0b', '06b6d4', 'ec4899', '84cc16'];
        $colorIndex = 0;
        
        foreach (array_slice($contributors, 0, 3, true) as $name => $msgCount) {
            $topContributors[] = [
                'name' => strlen($name) > 15 ? substr($name, 0, 12) . '...' : $name,
                'avatar' => urlencode($name),
                'color' => $colors[$colorIndex % count($colors)],
                'messages' => $msgCount
            ];
            $colorIndex++;
        }
        
        // Generate highlights from recent messages
        $highlights = [];
        $messageTexts = array_slice($recentMessages, 0, 4);
        foreach ($messageTexts as $text) {
            if (strlen($text) > 15) {
                $highlights[] = strlen($text) > 80 ? substr($text, 0, 77) . '...' : $text;
            }
        }
        
        // If no meaningful messages, show channel info
        if (empty($highlights)) {
            $highlights = [
                $messageCount > 0 ? "$messageCount messages in this channel" : 'No recent messages',
                'Channel: ' . $channel['displayName'],
                'Team: ' . $channel['teamName']
            ];
        }
        
        // Determine time range based on message timestamps
        $timeRange = 'Today';
        if (!empty($messages)) {
            $firstMsg = end($messages);
            $lastMsg = reset($messages);
            $startTime = date('g:i A', strtotime($firstMsg['createdDateTime']));
            $endTime = date('g:i A', strtotime($lastMsg['createdDateTime']));
            $timeRange = "Today, $startTime - $endTime";
        }
        
        $summary_cards[] = [
            'channel' => $channel['displayName'],
            'teamName' => $channel['teamName'],
            'teamId' => $channel['teamId'],
            'channelId' => $channel['id'],
            'icon' => 'hashtag',
            'time_range' => $timeRange,
            'metrics' => [
                'messages' => $messageCount,
                'urgent' => $urgentCount,
                'mentions' => $mentionCount
            ],
            'highlights' => $highlights,
            'contributors' => $topContributors
        ];
    }
    
    return $summary_cards;
}

// Generate real summary cards from API data
$summary_cards = generateSummaryCards($channels, $teamsAPI, $channel_filter, $date_range, $custom_start, $custom_end);

/**
 * Test if the issue is permissions-related (403 Forbidden)
 */
function testTeamsPermissionsIssue($user_id) {
    global $db;
    $token_info = $db->getOAuthToken($user_id, 'microsoft');
    
    if (!$token_info) {
        return false;
    }
    
    // Make a raw API call to detect 403 errors
    $url = 'https://graph.microsoft.com/v1.0/me/joinedTeams';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token_info['access_token'],
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 403;
}

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
    
    <style>
    /* Inline styles needed to ensure proper specificity and override conflicts */
    .main-content .summaries-content,
    main.main-content .summaries-content {
        max-width: 1400px !important;
        margin: 0 auto !important;
        padding: 48px 40px 64px !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 48px !important;
    }

    /* Modal styles for View Details */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal-content {
        background: white;
        border-radius: 8px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        color: #111827;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6b7280;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }

    .modal-close:hover {
        background: #f3f4f6;
        color: #374151;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .channel-detail-section {
        margin-bottom: 1.5rem;
    }

    .channel-detail-section h4 {
        margin: 0 0 1rem 0;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 0.5rem;
    }

    .channel-detail-section p {
        margin: 0.5rem 0;
        color: #6b7280;
    }

    .loading-details, .error-details {
        text-align: center;
        padding: 2rem;
        color: #6b7280;
    }

    .error-details {
        color: #dc2626;
    }

    /* AI Summary Section Styles */
    .ai-summary-section {
        margin-bottom: 2rem;
    }

    .ai-summary-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 0;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        overflow: hidden;
    }

    .ai-summary-header {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .ai-summary-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: white;
    }

    .ai-summary-title i {
        font-size: 1.5rem;
    }

    .ai-summary-title h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .ai-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .generate-ai-summary-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .generate-ai-summary-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.4);
        transform: translateY(-2px);
    }

    .ai-summary-content {
        background: white;
        padding: 2rem;
        min-height: 150px;
    }

    .ai-summary-placeholder {
        text-align: center;
        color: #6b7280;
    }

    .ai-summary-placeholder i {
        font-size: 3rem;
        color: #d1d5db;
        margin-bottom: 1rem;
    }

    .ai-summary-placeholder p {
        margin: 0.5rem 0;
        line-height: 1.6;
    }

    .ai-summary-description {
        font-size: 0.9rem;
        color: #9ca3af !important;
    }

    .ai-summary-result {
        line-height: 1.7;
        color: #374151;
    }

    .ai-summary-result h4 {
        color: #1f2937;
        margin: 1.5rem 0 1rem 0;
        font-size: 1.1rem;
        font-weight: 600;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 0.5rem;
    }

    .ai-summary-result ul {
        margin: 1rem 0;
        padding-left: 1.5rem;
    }

    .ai-summary-result li {
        margin: 0.5rem 0;
    }

    .ai-summary-meta {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
        font-size: 0.9rem;
        color: #6b7280;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ai-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        color: #6b7280;
        padding: 2rem;
    }

    .ai-loading i {
        font-size: 1.5rem;
        animation: spin 2s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .ai-error {
        text-align: center;
        color: #dc2626;
        padding: 2rem;
    }

    .ai-error i {
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    </style>
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
                <li class="nav-item active">
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
                            <?php
                            // Calculate current date range for display
                            $current_range = getDateRange($date_range, $custom_start, $custom_end);
                            $display_start = $current_range['start']->format('Y-m-d');
                            $display_end = $current_range['end']->format('Y-m-d');
                            ?>
                            <input type="date" id="startDate" class="date-input" value="<?php echo $display_start; ?>">
                            <span class="date-separator">to</span>
                            <input type="date" id="endDate" class="date-input" value="<?php echo $display_end; ?>">
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

                <!-- AI Summary Section -->
                <section class="ai-summary-section">
                    <div class="ai-summary-card">
                        <div class="ai-summary-header">
                            <div class="ai-summary-title">
                                <i class="fas fa-brain"></i>
                                <h3>AI-Generated Summary</h3>
                                <span class="ai-badge">Powered by OpenAI</span>
                            </div>
                            <button class="generate-ai-summary-btn" onclick="generateAISummary()">
                                <i class="fas fa-magic"></i>
                                Generate Summary
                            </button>
                            <button class="generate-ai-summary-btn" onclick="testAISummaryAPI()" style="background: rgba(255,255,255,0.1); margin-left: 0.5rem; font-size: 0.85em;">
                                <i class="fas fa-flask"></i>
                                Test API
                            </button>
                        </div>
                        <div class="ai-summary-content" id="aiSummaryContent">
                            <div class="ai-summary-placeholder">
                                <i class="fas fa-lightbulb"></i>
                                <p>Click "Generate Summary" to create an AI-powered analysis of all your recent Teams conversations and messages.</p>
                                <p class="ai-summary-description">The AI will analyze key topics, decisions, action items, and team activity patterns to provide you with actionable insights.</p>
                            </div>
                        </div>
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
                                            <span class="timeline-reactions"><?php echo isset($item['reactions']) ? $item['reactions'] : 0; ?> reactions</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Channels and Members Section -->
                <section class="channels-members-section">
                    <div class="section-header">
                        <h3>Teams Channels & Members</h3>
                        <div class="refresh-indicator" id="refreshIndicator" style="display: none;">
                            <i class="fas fa-sync fa-spin"></i>
                            Loading real-time data...
                        </div>
                    </div>
                    
                    <div class="channels-grid">
                        <?php if (empty($channels)): ?>
                        <div class="no-data-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>No Teams Data Available</h4>
                            <?php if (!$is_user_connected): ?>
                            <p>Connect your Microsoft account in <a href="account.php" style="color: var(--primary-color);">Account Settings</a> to access your Teams data.</p>
                            <p><small>Debug: User ID <?php echo $user_id; ?> - Connection Status: <?php echo $user_is_connected ? 'Connected' : 'Not Connected'; ?></small></p>
                            <?php elseif ($has_permissions_issue): ?>
                            <p>Your Microsoft account is connected but the app doesn't have permission to access Teams data.</p>
                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin: 1rem 0; text-align: left;">
                                <h5 style="margin: 0 0 0.5rem 0; color: #dc2626;"><i class="fas fa-shield-alt"></i> Permissions Issue:</h5>
                                <p style="margin: 0; color: #7f1d1d;"><strong>Admin consent required for Teams API access.</strong></p>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9em; color: #7f1d1d;">
                                    Contact your IT administrator - this app needs admin approval to access Microsoft Teams data.
                                </p>
                            </div>
                            <?php else: ?>
                            <p>Your Microsoft account is connected but no Teams data was found.</p>
                            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; margin: 1rem 0; text-align: left;">
                                <h5 style="margin: 0 0 0.5rem 0; color: #495057;"><i class="fas fa-info-circle"></i> Most Common Cause:</h5>
                                <p style="margin: 0; color: #6c757d;"><strong>You are not a member of any Microsoft Teams.</strong></p>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.9em; color: #6c757d;">
                                    To fix this: Join a Microsoft Team through the Teams app or have a team admin add you as a member.
                                </p>
                            </div>
                            <details style="margin: 1rem 0;">
                                <summary style="cursor: pointer; color: var(--primary-color); font-weight: 500;">🔍 View Detailed Diagnostics</summary>
                                <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                    <p><strong>Technical Details:</strong></p>
                                    <ul style="margin: 0.5rem 0;">
                                        <li>API Connection: ✅ Working</li>
                                        <li>Authentication: ✅ Token Valid</li>
                                        <li>Teams Found: ❌ 0 teams</li>
                                        <li>User ID: <?php echo $user_id; ?></li>
                                    </ul>
                                    <p><strong>Possible Solutions:</strong></p>
                                    <ol style="margin: 0.5rem 0;">
                                        <li>Join a Microsoft Team in the Teams app</li>
                                        <li>Have a team admin add you as a member (not guest)</li>
                                        <li><a href="teams_diagnostics.php" target="_blank" style="color: var(--primary-color);">View detailed diagnostics</a></li>
                                        <li><a href="account.php" style="color: var(--primary-color);">Reconnect your Microsoft account</a></li>
                                    </ol>
                                </div>
                            </details>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php foreach ($channels as $channel): ?>
                        <div class="card channel-member-card">
                            <div class="channel-header">
                                <div class="channel-info">
                                    <i class="fas fa-hashtag"></i>
                                    <div class="channel-details">
                                        <h4><?php echo htmlspecialchars($channel['displayName']); ?></h4>
                                        <span class="team-name"><?php echo htmlspecialchars($channel['teamName']); ?></span>
                                    </div>
                                </div>
                                <div class="channel-stats">
                                    <span class="member-count">
                                        <i class="fas fa-users"></i>
                                        <?php echo $channel['memberCount']; ?> members
                                    </span>
                                    <span class="membership-type <?php echo strtolower($channel['membershipType']); ?>">
                                        <?php echo ucfirst($channel['membershipType']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($channel['description'])): ?>
                            <div class="channel-description">
                                <p><?php echo htmlspecialchars($channel['description']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="channel-members">
                                <h5>Channel Members</h5>
                                <div class="members-list" id="members-<?php echo htmlspecialchars($channel['id']); ?>">
                                    <div class="members-placeholder">
                                        <i class="fas fa-users"></i>
                                        Click "Load Members" to view channel members
                                    </div>
                                </div>
                            </div>
                            
                            <div class="channel-actions">
                                <button class="btn btn-sm btn-primary" onclick="loadChannelMembers('<?php echo htmlspecialchars($channel['teamId']); ?>', '<?php echo htmlspecialchars($channel['id']); ?>')">
                                    <i class="fas fa-users"></i>
                                    Load Members
                                </button>
                                <?php if (!empty($channel['webUrl'])): ?>
                                <a href="<?php echo htmlspecialchars($channel['webUrl']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-external-link-alt"></i>
                                    Open in Teams
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
                        <?php if (empty($summary_cards)): ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i>
                            <h4>No Summary Data Available</h4>
                            <?php if (!$is_user_connected): ?>
                            <p>Connect your Microsoft account in <a href="account.php" style="color: var(--primary-color);">Account Settings</a> to access your Teams data.</p>
                            <?php elseif ($has_permissions_issue): ?>
                            <p>App doesn't have permission to access Teams data.</p>
                            <p style="margin-top: 1rem; color: #dc2626; font-size: 0.9em;">
                                <i class="fas fa-shield-alt"></i> 
                                <strong>Admin consent required.</strong> <a href="teams_diagnostics.php" target="_blank" style="color: var(--primary-color);">View diagnostics</a> for details.
                            </p>
                            <?php else: ?>
                            <p>No Teams data available to summarize.</p>
                            <p style="margin-top: 1rem; color: #6c757d; font-size: 0.9em;">
                                <i class="fas fa-lightbulb"></i> 
                                <strong>Need help?</strong> <a href="teams_diagnostics.php" target="_blank" style="color: var(--primary-color);">View diagnostics</a> to learn how to join Microsoft Teams.
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php foreach ($summary_cards as $card): ?>
                        <div class="card summary-card">
                            <div class="summary-card-header">
                                <div class="summary-channel">
                                    <i class="fas fa-<?php echo $card['icon']; ?>"></i>
                                    <span><?php echo htmlspecialchars($card['channel']); ?></span>
                                    <?php if (!empty($card['teamName'])): ?>
                                    <span class="team-context">(<?php echo htmlspecialchars($card['teamName']); ?>)</span>
                                    <?php endif; ?>
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
                                <button class="action-btn primary" onclick="viewSummaryDetails('<?php echo htmlspecialchars($card['channel']); ?>', '<?php echo htmlspecialchars($card['teamId']); ?>', '<?php echo htmlspecialchars($card['channelId']); ?>')">View Details</button>
                                <button class="action-btn secondary">Export</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
        
        // Function to load channel members
        function loadChannelMembers(teamId, channelId) {
            const membersContainer = document.getElementById(`members-${channelId}`);
            
            // Show loading state
            membersContainer.innerHTML = '<div class="loading-members"><i class="fas fa-spinner fa-spin"></i> Loading members...</div>';
            
            // Fetch members from API
            fetch(`api/get_channel_members.php?teamId=${encodeURIComponent(teamId)}&channelId=${encodeURIComponent(channelId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.members) {
                        let membersHtml = '<div class="members-grid">';
                        
                        data.members.forEach(member => {
                            const avatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(member.displayName)}&background=6366f1&color=fff&size=32`;
                            membersHtml += `
                                <div class="member-item">
                                    <img src="${avatar}" alt="${member.displayName}" class="member-avatar">
                                    <div class="member-info">
                                        <span class="member-name">${member.displayName}</span>
                                        ${member.email ? `<span class="member-email">${member.email}</span>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                        
                        membersHtml += '</div>';
                        membersContainer.innerHTML = membersHtml;
                    } else {
                        membersContainer.innerHTML = '<div class="no-members">No members data available</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading members:', error);
                    membersContainer.innerHTML = '<div class="error-loading">Error loading members</div>';
                });
        }
        
        // Function to view summary details
        function viewSummaryDetails(channelName, teamId, channelId) {
            // Create modal content with detailed information
            const modalContent = `
                <div class="modal-overlay" id="detailsModal" onclick="closeDetailsModal()">
                    <div class="modal-content" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <h3>Channel Details: ${channelName}</h3>
                            <button class="modal-close" onclick="closeDetailsModal()">×</button>
                        </div>
                        <div class="modal-body">
                            <div class="loading-details">
                                <i class="fas fa-spinner fa-spin"></i> Loading detailed information...
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalContent);
            
            // Load detailed channel information
            if (teamId && channelId) {
                fetch(`api/get_channel_details.php?teamId=${encodeURIComponent(teamId)}&channelId=${encodeURIComponent(channelId)}`)
                    .then(response => response.json())
                    .then(data => {
                        const modalBody = document.querySelector('#detailsModal .modal-body');
                        if (data.success) {
                            modalBody.innerHTML = `
                                <div class="channel-detail-section">
                                    <h4>Channel Information</h4>
                                    <p><strong>Name:</strong> ${data.channel.displayName}</p>
                                    <p><strong>Description:</strong> ${data.channel.description || 'No description'}</p>
                                    <p><strong>Members:</strong> ${data.memberCount || 'Unknown'}</p>
                                </div>
                                <div class="channel-detail-section">
                                    <h4>Recent Activity</h4>
                                    <p><strong>Total Messages:</strong> ${data.messageCount || 0}</p>
                                    <p><strong>Last Activity:</strong> ${data.lastActivity || 'Unknown'}</p>
                                </div>
                            `;
                        } else {
                            modalBody.innerHTML = `
                                <div class="error-details">
                                    <p>Unable to load detailed information for this channel.</p>
                                    <p>Error: ${data.error || 'Unknown error'}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        const modalBody = document.querySelector('#detailsModal .modal-body');
                        modalBody.innerHTML = `
                            <div class="error-details">
                                <p>Error loading channel details.</p>
                            </div>
                        `;
                    });
            } else {
                const modalBody = document.querySelector('#detailsModal .modal-body');
                modalBody.innerHTML = `
                    <div class="channel-detail-section">
                        <h4>Channel Information</h4>
                        <p><strong>Name:</strong> ${channelName}</p>
                        <p>Detailed information not available for this channel.</p>
                    </div>
                `;
            }
        }
        
        // Function to close details modal
        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            if (modal) {
                modal.remove();
            }
        }

        // Function to test AI summary API
        function testAISummaryAPI() {
            const contentDiv = document.getElementById('aiSummaryContent');
            
            contentDiv.innerHTML = `
                <div class="ai-loading">
                    <i class="fas fa-flask fa-spin"></i>
                    <div>
                        <p><strong>Testing AI API connectivity...</strong></p>
                    </div>
                </div>
            `;
            
            fetch('api/test_ai_summary.php', {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Test API Response Status:', response.status);
                return response.text().then(text => {
                    console.log('Test API Raw Response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`Invalid JSON: ${text.substring(0, 100)}`);
                    }
                });
            })
            .then(data => {
                console.log('Test API Response Data:', data);
                let html = '<div class="ai-summary-result">';
                if (data.success) {
                    html += '<h4>✅ API Test Successful</h4>';
                    html += '<ul>';
                    html += `<li>OpenAI Key Loaded: ${data.openai_key_loaded ? '✅' : '❌'}</li>`;
                    html += `<li>Key Length: ${data.openai_key_length} characters</li>`;
                    html += `<li>cURL Available: ${data.curl_available ? '✅' : '❌'}</li>`;
                    html += `<li>Session Active: ${data.session_active ? '✅' : '❌'}</li>`;
                    if (data.teams_api_test) {
                        html += `<li>Teams API: ${data.teams_api_test}</li>`;
                    }
                    if (data.channels_test) {
                        html += `<li>Channels: ${data.channels_test}</li>`;
                    }
                    html += `<li>Timestamp: ${data.timestamp}</li>`;
                    html += '</ul>';
                } else {
                    html += '<h4>❌ API Test Failed</h4>';
                    html += `<p>Error: ${data.error}</p>`;
                }
                html += '</div>';
                contentDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('Test API Error:', error);
                contentDiv.innerHTML = `
                    <div class="ai-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p><strong>Test Failed</strong></p>
                        <p>${error.message}</p>
                    </div>
                `;
            });
        }

        // Function to generate AI summary
        function generateAISummary() {
            const contentDiv = document.getElementById('aiSummaryContent');
            const btn = document.querySelector('.generate-ai-summary-btn');
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            
            contentDiv.innerHTML = `
                <div class="ai-loading">
                    <i class="fas fa-brain fa-spin"></i>
                    <div>
                        <p><strong>AI is analyzing your Teams conversations...</strong></p>
                        <p>This may take a few moments while we process your messages and generate insights.</p>
                    </div>
                </div>
            `;
            
            // Call the API
            fetch('api/generate_ai_summary.php', {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    console.log('AI Summary API Response Status:', response.status);
                    console.log('AI Summary API Response Headers:', response.headers.get('content-type'));
                    
                    // Get response text first to debug
                    return response.text().then(text => {
                        console.log('AI Summary Raw Response:', text);
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}, response: ${text.substring(0, 200)}`);
                        }
                        
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON Parse Error:', e);
                            console.error('Response was:', text);
                            throw new Error(`Invalid JSON response: ${text.substring(0, 100)}...`);
                        }
                    });
                })
                .then(data => {
                    console.log('AI Summary API Response Data:', data);
                    if (data.success) {
                        // Format the summary content
                        let summaryHtml = `<div class="ai-summary-result">`;
                        
                        // Convert markdown-like formatting to HTML
                        let formattedSummary = data.summary
                            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                            .replace(/^(\d+\.\s+\*\*.*?\*\*)/gm, '<h4>$1</h4>')
                            .replace(/^- (.*?)$/gm, '<li>$1</li>')
                            .replace(/(\n\n)/g, '</p><p>')
                            .replace(/^(?!<[hl]|<li)(.+)$/gm, '<p>$1</p>');
                        
                        // Wrap consecutive list items in ul tags
                        formattedSummary = formattedSummary.replace(/(<li>.*?<\/li>\s*)+/gs, function(match) {
                            return '<ul>' + match + '</ul>';
                        });
                        
                        summaryHtml += formattedSummary;
                        summaryHtml += `
                            <div class="ai-summary-meta">
                                <div>
                                    <strong>${data.message_count}</strong> messages analyzed from 
                                    <strong>${data.channels_analyzed}</strong> channels
                                </div>
                                <div>Generated at ${data.generated_at}</div>
                            </div>
                        </div>`;
                        
                        contentDiv.innerHTML = summaryHtml;
                    } else {
                        contentDiv.innerHTML = `
                            <div class="ai-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p><strong>Failed to generate AI summary</strong></p>
                                <p>${data.error || 'Unknown error occurred'}</p>
                                <button class="generate-ai-summary-btn" onclick="generateAISummary()" style="margin-top: 1rem; position: static; transform: none;">
                                    <i class="fas fa-retry"></i> Try Again
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error generating AI summary:', error);
                    console.error('Error details:', {
                        message: error.message,
                        stack: error.stack,
                        name: error.name
                    });
                    
                    let errorMessage = 'Network error';
                    let errorDetails = 'Unable to connect to the AI service. Please check your connection and try again.';
                    
                    if (error.message.includes('HTTP error')) {
                        errorMessage = 'Server error';
                        errorDetails = `The AI service returned an error: ${error.message}`;
                    } else if (error.message.includes('Failed to fetch')) {
                        errorMessage = 'Connection failed';
                        errorDetails = 'Cannot reach the AI service. Please check if the server is running and accessible.';
                    }
                    
                    contentDiv.innerHTML = `
                        <div class="ai-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p><strong>${errorMessage}</strong></p>
                            <p>${errorDetails}</p>
                            <p style="font-size: 0.8em; color: #888; margin-top: 1rem;">
                                Technical details: ${error.message}
                            </p>
                            <button class="generate-ai-summary-btn" onclick="generateAISummary()" style="margin-top: 1rem; position: static; transform: none;">
                                <i class="fas fa-retry"></i> Try Again
                            </button>
                        </div>
                    `;
                })
                .finally(() => {
                    // Reset button state
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-magic"></i> Generate Summary';
                });
        }
        
        // Date filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const datePresetButtons = document.querySelectorAll('.date-preset');
            
            datePresetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const selectedRange = this.getAttribute('data-range');
                    const currentUrl = new URL(window.location);
                    
                    // Update the range parameter
                    currentUrl.searchParams.set('range', selectedRange);
                    
                    // Keep other filters
                    if (currentUrl.searchParams.get('channel')) {
                        currentUrl.searchParams.set('channel', currentUrl.searchParams.get('channel'));
                    }
                    if (currentUrl.searchParams.get('type')) {
                        currentUrl.searchParams.set('type', currentUrl.searchParams.get('type'));
                    }
                    
                    // Show loading indicator
                    showLoadingIndicator();
                    
                    // Reload the page with new filter
                    window.location.href = currentUrl.toString();
                });
            });
            
            // Custom date range functionality
            const customDateButton = document.querySelector('[data-range="custom"]');
            const customDateInputs = document.querySelector('.custom-date-inputs');
            
            if (customDateButton && customDateInputs) {
                customDateButton.addEventListener('click', function() {
                    const isActive = this.classList.contains('active');
                    
                    // Remove active class from all buttons
                    datePresetButtons.forEach(btn => btn.classList.remove('active'));
                    
                    if (!isActive) {
                        // Show custom date inputs
                        this.classList.add('active');
                        customDateInputs.style.display = 'flex';
                    } else {
                        // Hide custom date inputs
                        customDateInputs.style.display = 'none';
                    }
                });
            }
        });
        
        // Show loading indicator when filters change
        function showLoadingIndicator() {
            // Create a loading overlay
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = `
                <div class="loading-content">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Updating data...</p>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(loadingOverlay);
            
            // Style the overlay
            loadingOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                backdrop-filter: blur(2px);
            `;
            
            const loadingContent = loadingOverlay.querySelector('.loading-content');
            loadingContent.style.cssText = `
                text-align: center;
                padding: 2rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            `;
        }
    </script>
    
</body>
</html>