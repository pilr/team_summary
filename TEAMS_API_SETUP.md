# Microsoft Teams API Setup

This application integrates with Microsoft Teams via the Microsoft Graph API to fetch real channel and message data.

## Setup Instructions

### Option 1: Environment Variables (Recommended for Production)

Set the following environment variables:

```bash
export TEAMS_CLIENT_ID="your-client-id-here"
export TEAMS_CLIENT_SECRET="your-client-secret-here"
export TEAMS_SECRET_ID="your-secret-id-here"
```

### Option 2: Credentials File (Development)

Create a file named `team_summary.txt` in the root directory with the following format:

```
Client ID: your-client-id-here
Client Secret: your-client-secret-here
Secret ID: your-secret-id-here
```

**Note**: The `team_summary.txt` file is excluded from git via `.gitignore` for security.

## Microsoft Graph API Setup

1. **Register Application**: Go to [Azure Portal](https://portal.azure.com/) → Azure Active Directory → App registrations
2. **Create New Registration**: Click "New registration"
3. **Configure Application**:
   - Name: "Teams Activity Dashboard"
   - Supported account types: "Accounts in any organizational directory"
   - Redirect URI: Leave blank for now
4. **Note the Application (client) ID**: This is your `TEAMS_CLIENT_ID`
5. **Create Client Secret**:
   - Go to "Certificates & secrets"
   - Click "New client secret"
   - Add description and expiration
   - Copy the secret value: This is your `TEAMS_CLIENT_SECRET`
6. **Configure API Permissions**:
   - Go to "API permissions"
   - Add the following Microsoft Graph Application permissions:
     - `Team.ReadBasic.All`
     - `Channel.ReadBasic.All` 
     - `ChannelMessage.Read.All`
     - `User.Read.All`
   - Click "Grant admin consent"

## Features

- **Automatic Token Management**: Handles OAuth2 client credentials flow
- **Caching**: 5-minute cache for API responses to reduce API calls
- **Fallback Data**: Shows mock data when API is unavailable
- **Real-time Statistics**: Uses actual message counts when available
- **Error Handling**: Comprehensive logging and graceful degradation

## File Structure

- `teams_config.php`: Configuration and credential loading
- `teams_api.php`: Microsoft Graph API integration class
- `summaries.php`: Main page using Teams data
- `cache/`: Directory for API response caching (auto-created)

## Troubleshooting

1. **Check Logs**: API errors are logged to PHP error log
2. **Verify Permissions**: Ensure all required permissions are granted and consented
3. **Test Credentials**: Verify Client ID and Secret are correct
4. **Cache Issues**: Delete `cache/` directory to refresh cached data

## Security Notes

- Never commit credentials to version control
- Use environment variables in production
- Regularly rotate client secrets
- Monitor API usage and access logs