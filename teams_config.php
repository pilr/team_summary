<?php
// Microsoft Teams API Configuration
// Load credentials from environment variables or team_summary.txt file

// Try to load from environment variables first
$clientId = getenv('TEAMS_CLIENT_ID');
$clientSecret = getenv('TEAMS_CLIENT_SECRET'); 
$secretId = getenv('TEAMS_SECRET_ID');

// If not found in environment, try to load from team_summary.txt
if (!$clientId || !$clientSecret || !$secretId) {
    $credentialsFile = __DIR__ . '/team_summary.txt';
    if (file_exists($credentialsFile)) {
        $lines = file($credentialsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'Client ID:') === 0) {
                $clientId = trim(substr($line, 10));
            } elseif (strpos($line, 'Client Secret:') === 0) {
                $clientSecret = trim(substr($line, 14));
            } elseif (strpos($line, 'Secret ID:') === 0) {
                $secretId = trim(substr($line, 10));
            }
        }
    }
}

// Define constants
define('TEAMS_CLIENT_ID', $clientId ?: 'YOUR_CLIENT_ID_HERE');
define('TEAMS_CLIENT_SECRET', $clientSecret ?: 'YOUR_CLIENT_SECRET_HERE');
define('TEAMS_SECRET_ID', $secretId ?: 'YOUR_SECRET_ID_HERE');

// Microsoft Graph API endpoints (use tenant-specific URLs for client credentials)
$tenantId = $secretId ?: 'common';
define('TEAMS_AUTH_URL', "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize");
define('TEAMS_TOKEN_URL', "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token");
define('TEAMS_GRAPH_URL', 'https://graph.microsoft.com/v1.0');

// Required scopes for Teams data
define('TEAMS_SCOPES', [
    'https://graph.microsoft.com/Team.ReadBasic.All',
    'https://graph.microsoft.com/Channel.ReadBasic.All',
    'https://graph.microsoft.com/ChannelMessage.Read.All',
    'https://graph.microsoft.com/User.Read'
]);

// Cache settings
define('TEAMS_CACHE_DURATION', 300); // 5 minutes
define('TEAMS_CACHE_DIR', __DIR__ . '/cache');
?>