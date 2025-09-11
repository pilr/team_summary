<?php
ob_start();
require_once 'config.php';
session_start();

require_once 'database_helper.php';
require_once 'teams_api.php';
require_once 'session_validator.php';

global $db;
if (!$db) {
    $db = new DatabaseHelper();
}

$current_user = SessionValidator::requireAuth();
$user_id = $current_user['id'];

// Initialize Teams API
require_once 'user_teams_api.php';
$userTeamsAPI = new UserTeamsAPIHelper($user_id);

$user_is_connected = $userTeamsAPI->isConnected();

if ($user_is_connected) {
    $teamsAPI = $userTeamsAPI;
} else {
    $teamsAPI = new TeamsAPIHelper();
}

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_team_selection') {
        $selected_teams = $_POST['selected_teams'] ?? [];
        
        // Save team selection preferences
        $db->saveTeamSelectionPreferences($user_id, $selected_teams);
        
        $success_message = "Team selection preferences saved successfully!";
    }
}

// Get all available teams
$all_teams = $teamsAPI->getTeams();

// Group channels by team
$teams_with_channels = [];
foreach ($all_teams as $team) {
    $channels = $teamsAPI->getTeamChannels($team['id']);
    $teams_with_channels[] = [
        'team' => $team,
        'channels' => $channels
    ];
}

// Get user's current team selection preferences
$selected_team_preferences = $db->getTeamSelectionPreferences($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - Teams Activity Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    
    <style>
    .team-management-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .page-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .page-header h1 {
        color: #1f2937;
        margin-bottom: 0.5rem;
    }
    
    .page-header p {
        color: #6b7280;
        font-size: 1.1rem;
    }
    
    .success-message {
        background: #dcfce7;
        border: 1px solid #bbf7d0;
        color: #166534;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .team-selection-form {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .form-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .bulk-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .bulk-actions button {
        padding: 0.5rem 1rem;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    
    .bulk-actions button:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    
    .teams-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .team-card {
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        background: white;
        transition: all 0.3s ease;
    }
    
    .team-card.selected {
        border-color: #6366f1;
        background: #f8faff;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
    }
    
    .team-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .team-checkbox {
        width: 20px;
        height: 20px;
        margin-top: 2px;
        cursor: pointer;
    }
    
    .team-info h3 {
        margin: 0 0 0.5rem 0;
        color: #1f2937;
        font-size: 1.2rem;
    }
    
    .team-description {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        line-height: 1.4;
    }
    
    .team-stats {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }
    
    .team-stat {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        color: #6b7280;
    }
    
    .team-stat i {
        color: #9ca3af;
    }
    
    .channels-preview {
        border-top: 1px solid #e5e7eb;
        padding-top: 1rem;
        margin-top: 1rem;
    }
    
    .channels-preview h4 {
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
        color: #374151;
        font-weight: 600;
    }
    
    .channel-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }
    
    .channel-tag {
        background: #f3f4f6;
        color: #374151;
        padding: 0.2rem 0.6rem;
        border-radius: 4px;
        font-size: 0.8rem;
        border: 1px solid #e5e7eb;
    }
    
    .team-card.selected .channel-tag {
        background: #e0e7ff;
        color: #3730a3;
        border-color: #c7d2fe;
    }
    
    .no-teams-message {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
        background: white;
        border-radius: 12px;
        border: 2px dashed #d1d5db;
    }
    
    .no-teams-message i {
        font-size: 3rem;
        color: #d1d5db;
        margin-bottom: 1rem;
    }
    
    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
    }
    
    .save-btn {
        background: #6366f1;
        color: white;
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .save-btn:hover {
        background: #5856eb;
    }
    
    .save-btn:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }
    
    .selection-summary {
        color: #6b7280;
        font-size: 0.9rem;
    }
    
    .connection-warning {
        background: #fef3c7;
        border: 1px solid #fcd34d;
        color: #92400e;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
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
                <li class="nav-item">
                    <a href="summaries.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        <span>Summaries</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="team_management.php" class="nav-link">
                        <i class="fas fa-users-cog"></i>
                        <span>Team Management</span>
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
                    <h1>Team Management</h1>
                </div>
                <div class="top-bar-right">
                    <a href="summaries.php" class="settings-btn">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <a href="account.php" class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user['name']); ?>&background=6366f1&color=fff" alt="User Avatar" class="user-avatar">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></span>
                            <span class="user-email"><?php echo htmlspecialchars($current_user['email']); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                </div>
            </header>

            <!-- Team Management Content -->
            <div class="team-management-content">
                <div class="page-header">
                    <h1><i class="fas fa-users-cog"></i> Team Management</h1>
                    <p>Select which Microsoft Teams you want to include in your summaries and analysis.</p>
                </div>

                <?php if (isset($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <?php if (!$user_is_connected): ?>
                <div class="connection-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Microsoft account not connected. <a href="account.php" style="color: #92400e; text-decoration: underline;">Connect your account</a> to access your Teams.
                </div>
                <?php endif; ?>

                <form method="POST" class="team-selection-form" id="teamSelectionForm">
                    <input type="hidden" name="action" value="update_team_selection">
                    
                    <?php if (!empty($teams_with_channels)): ?>
                    <div class="form-controls">
                        <div class="bulk-actions">
                            <button type="button" onclick="selectAllTeams()">
                                <i class="fas fa-check-square"></i> Select All
                            </button>
                            <button type="button" onclick="deselectAllTeams()">
                                <i class="fas fa-square"></i> Deselect All
                            </button>
                        </div>
                        <div class="selection-summary" id="selectionSummary">
                            0 of <?php echo count($teams_with_channels); ?> teams selected
                        </div>
                    </div>

                    <div class="teams-grid">
                        <?php foreach ($teams_with_channels as $team_data): ?>
                        <?php 
                        $team = $team_data['team'];
                        $channels = $team_data['channels'];
                        $is_selected = in_array($team['id'], $selected_team_preferences);
                        ?>
                        <div class="team-card <?php echo $is_selected ? 'selected' : ''; ?>" data-team-id="<?php echo htmlspecialchars($team['id']); ?>">
                            <div class="team-header">
                                <input type="checkbox" 
                                       name="selected_teams[]" 
                                       value="<?php echo htmlspecialchars($team['id']); ?>"
                                       class="team-checkbox"
                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                       onchange="updateTeamSelection(this)">
                                <div class="team-info">
                                    <h3><?php echo htmlspecialchars($team['displayName']); ?></h3>
                                    <?php if (!empty($team['description'])): ?>
                                    <div class="team-description">
                                        <?php echo htmlspecialchars($team['description']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="team-stats">
                                <div class="team-stat">
                                    <i class="fas fa-hashtag"></i>
                                    <span><?php echo count($channels); ?> channels</span>
                                </div>
                                <div class="team-stat">
                                    <i class="fas fa-users"></i>
                                    <span>Team members</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($channels)): ?>
                            <div class="channels-preview">
                                <h4>Channels:</h4>
                                <div class="channel-list">
                                    <?php foreach (array_slice($channels, 0, 6) as $channel): ?>
                                    <span class="channel-tag">#<?php echo htmlspecialchars($channel['displayName']); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($channels) > 6): ?>
                                    <span class="channel-tag">+<?php echo count($channels) - 6; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <div class="selection-summary">
                            Changes will apply to all future summaries and analysis.
                        </div>
                        <button type="submit" class="save-btn">
                            <i class="fas fa-save"></i>
                            Save Team Selection
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="no-teams-message">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Teams Available</h3>
                        <p>No Microsoft Teams were found for your account.</p>
                        <?php if (!$user_is_connected): ?>
                        <p><a href="account.php" style="color: #6366f1;">Connect your Microsoft account</a> to access your Teams.</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>

    <script>
    function selectAllTeams() {
        const checkboxes = document.querySelectorAll('.team-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
            updateTeamSelection(checkbox);
        });
        updateSelectionSummary();
    }
    
    function deselectAllTeams() {
        const checkboxes = document.querySelectorAll('.team-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            updateTeamSelection(checkbox);
        });
        updateSelectionSummary();
    }
    
    function updateTeamSelection(checkbox) {
        const teamCard = checkbox.closest('.team-card');
        if (checkbox.checked) {
            teamCard.classList.add('selected');
        } else {
            teamCard.classList.remove('selected');
        }
        updateSelectionSummary();
    }
    
    function updateSelectionSummary() {
        const checkboxes = document.querySelectorAll('.team-checkbox');
        const selectedCount = document.querySelectorAll('.team-checkbox:checked').length;
        const totalCount = checkboxes.length;
        
        const summaryElements = document.querySelectorAll('.selection-summary');
        summaryElements.forEach(element => {
            if (element.id === 'selectionSummary') {
                element.textContent = `${selectedCount} of ${totalCount} teams selected`;
            }
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectionSummary();
    });
    </script>
</body>
</html>