# Persistent Teams Connection Setup

This document explains how to set up and configure persistent Microsoft Teams connections that survive user logouts.

## Overview

The persistent Teams service maintains OAuth tokens and automatically refreshes them in the background, ensuring continuous access to Teams data even when users are logged out of the application.

## Components

### Core Files
- `persistent_teams_service.php` - Main service class
- `teams_connection_daemon.php` - Background daemon script
- `maintain_teams_connections.php` - Cron job script
- `manage_teams_daemon.bat` - Windows service manager

### API Endpoints
- `api/check_teams_connection.php` - Updated to use persistent service
- `api/refresh_teams_token.php` - Updated to use persistent service
- `api/check_teams_connection_persistent.php` - Session-independent status check

## Setup Instructions

### 1. Basic Configuration

Ensure your `teams_config.php` has the correct Microsoft app credentials:
```php
define('TEAMS_CLIENT_ID', 'your-client-id');
define('TEAMS_CLIENT_SECRET', 'your-client-secret');
define('TEAMS_TENANT_ID', 'your-tenant-id');
```

### 2. Database Requirements

The system uses the existing `oauth_tokens` table. No additional database changes are required.

### 3. Cron Job Setup (Recommended)

Add this cron job to run every 15 minutes:
```bash
*/15 * * * * /usr/bin/php /path/to/your/maintain_teams_connections.php
```

### 4. Background Daemon (Alternative)

For continuous monitoring, you can run the daemon service:

#### Linux/Unix:
```bash
php teams_connection_daemon.php start
```

#### Windows:
```cmd
manage_teams_daemon.bat start
```

### 5. Windows Service Installation (Advanced)

To run as a Windows service:

1. Download NSSM from https://nssm.cc/download
2. Add NSSM to your PATH
3. Run: `manage_teams_daemon.bat install`
4. Start service: `net start TeamsConnectionDaemon`

## Features

### Automatic Token Refresh
- Refreshes tokens 30 minutes before expiration
- Handles failed refresh attempts gracefully
- Maintains detailed logs for troubleshooting

### Session Independence
- Teams connections persist across user logouts
- Background processes can access Teams data
- API endpoints work without active sessions (where appropriate)

### Monitoring and Health Checks
- Built-in connection status monitoring
- API test functionality
- Comprehensive error logging

## API Usage

### Check Connection Status (Session Required)
```javascript
fetch('/api/check_teams_connection.php')
  .then(response => response.json())
  .then(data => console.log(data.status));
```

### Check Connection Status (Session Independent)
```javascript
fetch('/api/check_teams_connection_persistent.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    user_id: 123,
    test_api: true
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

### Refresh Token
```javascript
fetch('/api/refresh_teams_token.php', { method: 'POST' })
  .then(response => response.json())
  .then(data => console.log(data.success));
```

## Security Considerations

### API Key Protection
Set an environment variable for API key authentication:
```bash
export TEAMS_API_KEY="your-secure-api-key"
```

### File Permissions
Ensure proper permissions on daemon and log files:
```bash
chmod 600 teams_daemon.pid
chmod 644 teams_daemon.log
```

### Network Security
- Restrict access to persistent API endpoints
- Use HTTPS for all Teams API communications
- Implement rate limiting for API endpoints

## Troubleshooting

### Check Daemon Status
```bash
php teams_connection_daemon.php status
```

### View Daemon Logs
```bash
tail -f teams_daemon.log
```

### Manual Token Refresh
```bash
php maintain_teams_connections.php
```

### Common Issues

1. **Tokens Not Refreshing**
   - Check refresh token validity
   - Verify Microsoft app permissions
   - Review error logs

2. **Daemon Not Starting**
   - Check PHP path and permissions
   - Verify all required files exist
   - Review system logs

3. **API Access Failures**
   - Test Microsoft Graph API connectivity
   - Verify token scopes and permissions
   - Check firewall and proxy settings

## Monitoring

### Log Files
- `teams_daemon.log` - Daemon activity log
- PHP error logs - Check for specific errors
- Web server logs - API endpoint access

### Health Checks
The system provides several health check endpoints:
- Connection status per user
- Token expiration monitoring
- API accessibility tests

### Alerts
Consider setting up monitoring alerts for:
- Token refresh failures
- API connectivity issues
- Daemon service downtime

## Migration Notes

### Existing Users
Existing Teams connections will automatically work with the persistent service. No user action required.

### Logout Behavior
Users can now safely logout without losing their Teams connection. The background service will maintain their tokens automatically.

### Performance Impact
The persistent service has minimal performance impact:
- Lightweight background processes
- Efficient database queries
- Configurable refresh intervals