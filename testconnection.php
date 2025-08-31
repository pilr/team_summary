<?php
session_start();

// Initialize variables
$clientId = '';
$clientSecret = '';
$tenantId = '';
$testResults = null;
$error = '';

// Handle form submission
if ($_POST) {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $tenantId = trim($_POST['tenant_id'] ?? '');
    
    // Validate inputs
    if (empty($clientId) || empty($clientSecret) || empty($tenantId)) {
        $error = 'All fields are required.';
    } else {
        // Test the connection
        $testResults = testTeamsConnection($clientId, $clientSecret, $tenantId);
    }
} else {
    // Try to load existing credentials from team_summary.txt
    $credentialsFile = __DIR__ . '/team_summary.txt';
    if (file_exists($credentialsFile)) {
        $lines = file($credentialsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'Client ID:') === 0) {
                $clientId = trim(substr($line, 10));
            } elseif (strpos($line, 'Client Secret:') === 0) {
                $clientSecret = trim(substr($line, 14));
            } elseif (strpos($line, 'Secret ID:') === 0) {
                $tenantId = trim(substr($line, 10));
            }
        }
    }
}

function testTeamsConnection($clientId, $clientSecret, $tenantId) {
    $results = [
        'success' => false,
        'token_test' => null,
        'teams_test' => null,
        'error_details' => null,
        'http_codes' => []
    ];
    
    // Step 1: Test authentication
    $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
    $tokenData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $results['http_codes']['token'] = $httpCode;
    
    if ($curlError) {
        $results['error_details'] = "cURL Error: $curlError";
        return $results;
    }
    
    if ($httpCode !== 200) {
        $errorResponse = json_decode($response, true);
        $results['token_test'] = 'FAILED';
        $results['error_details'] = [
            'http_code' => $httpCode,
            'response' => $errorResponse,
            'url' => $tokenUrl
        ];
        return $results;
    }
    
    $tokenResponse = json_decode($response, true);
    if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
        $results['token_test'] = 'FAILED';
        $results['error_details'] = 'Invalid token response format';
        return $results;
    }
    
    $accessToken = $tokenResponse['access_token'];
    $results['token_test'] = 'SUCCESS';
    
    // Step 2: Test Teams API call
    $teamsUrl = 'https://graph.microsoft.com/v1.0/teams';
    $ch = curl_init($teamsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $teamsResponse = curl_exec($ch);
    $teamsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $teamsCurlError = curl_error($ch);
    curl_close($ch);
    
    $results['http_codes']['teams'] = $teamsHttpCode;
    
    if ($teamsCurlError) {
        $results['teams_test'] = 'FAILED';
        $results['error_details'] = "Teams API cURL Error: $teamsCurlError";
        return $results;
    }
    
    if ($teamsHttpCode === 200) {
        $teamsData = json_decode($teamsResponse, true);
        $teamCount = isset($teamsData['value']) ? count($teamsData['value']) : 0;
        $results['teams_test'] = 'SUCCESS';
        $results['teams_count'] = $teamCount;
        $results['success'] = true;
        
        if ($teamCount === 0) {
            $results['warning'] = 'No teams found - check app permissions';
        }
    } else {
        $results['teams_test'] = 'FAILED';
        $teamsErrorResponse = json_decode($teamsResponse, true);
        $results['error_details'] = [
            'teams_http_code' => $teamsHttpCode,
            'teams_response' => $teamsErrorResponse,
            'teams_url' => $teamsUrl
        ];
    }
    
    return $results;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Teams API Connection</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: #6366f1;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .content {
            padding: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .btn {
            background: #6366f1;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background: #5b5bb4;
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .results-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f9fafb;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .result-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .result-icon {
            font-size: 1.5rem;
        }
        
        .result-icon.success {
            color: #10b981;
        }
        
        .result-icon.error {
            color: #ef4444;
        }
        
        .result-icon.warning {
            color: #f59e0b;
        }
        
        .result-content {
            flex: 1;
        }
        
        .result-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        
        .result-description {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .error-details {
            margin-top: 1rem;
            padding: 1rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.875rem;
            white-space: pre-wrap;
        }
        
        .success-details {
            margin-top: 1rem;
            padding: 1rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        
        .alert.error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }
        
        .alert.success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #16a34a;
        }
        
        .alert.warning {
            background: #fffbeb;
            border-color: #fed7aa;
            color: #d97706;
        }
        
        .credentials-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .credentials-info h3 {
            color: #0369a1;
            margin-bottom: 0.5rem;
        }
        
        .credentials-info ul {
            list-style-position: inside;
            color: #0c4a6e;
        }
        
        .credentials-info li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plug"></i> Teams API Connection Test</h1>
            <p>Test your Microsoft Graph API credentials and permissions</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="credentials-info">
                <h3><i class="fas fa-info-circle"></i> How to Get Your Credentials</h3>
                <ul>
                    <li><strong>Client ID:</strong> From your Azure App Registration overview page</li>
                    <li><strong>Client Secret:</strong> Create a new client secret in "Certificates & secrets"</li>
                    <li><strong>Tenant ID:</strong> From your Azure Active Directory overview (Directory ID)</li>
                    <li><strong>Required Permissions:</strong> Team.ReadBasic.All, Channel.ReadBasic.All, ChannelMessage.Read.All</li>
                </ul>
            </div>
            
            <form method="POST" class="form-section">
                <div class="form-group">
                    <label for="client_id">
                        <i class="fas fa-key"></i> Client ID (Application ID)
                    </label>
                    <input type="text" 
                           id="client_id" 
                           name="client_id" 
                           value="<?php echo htmlspecialchars($clientId); ?>" 
                           placeholder="e.g., 12345678-1234-1234-1234-123456789012"
                           required>
                    <div class="help-text">The Application (client) ID from your Azure App Registration</div>
                </div>
                
                <div class="form-group">
                    <label for="client_secret">
                        <i class="fas fa-lock"></i> Client Secret
                    </label>
                    <input type="password" 
                           id="client_secret" 
                           name="client_secret" 
                           value="<?php echo htmlspecialchars($clientSecret); ?>" 
                           placeholder="Your client secret value"
                           required>
                    <div class="help-text">The client secret value (not the ID) from "Certificates & secrets"</div>
                </div>
                
                <div class="form-group">
                    <label for="tenant_id">
                        <i class="fas fa-building"></i> Tenant ID (Directory ID)
                    </label>
                    <input type="text" 
                           id="tenant_id" 
                           name="tenant_id" 
                           value="<?php echo htmlspecialchars($tenantId); ?>" 
                           placeholder="e.g., 87654321-4321-4321-4321-210987654321"
                           required>
                    <div class="help-text">The Directory (tenant) ID from your Azure Active Directory</div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-flask"></i>
                    Test Connection
                </button>
            </form>
            
            <?php if ($testResults): ?>
            <div class="results-section">
                <h3><i class="fas fa-chart-line"></i> Connection Test Results</h3>
                
                <!-- Token Test Result -->
                <div class="result-item">
                    <div class="result-icon <?php echo $testResults['token_test'] === 'SUCCESS' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $testResults['token_test'] === 'SUCCESS' ? 'check-circle' : 'times-circle'; ?>"></i>
                    </div>
                    <div class="result-content">
                        <div class="result-title">
                            OAuth2 Authentication 
                            <span style="font-weight: normal;">(HTTP <?php echo $testResults['http_codes']['token']; ?>)</span>
                        </div>
                        <div class="result-description">
                            <?php if ($testResults['token_test'] === 'SUCCESS'): ?>
                                ✅ Successfully obtained access token from Microsoft Graph API
                            <?php else: ?>
                                ❌ Failed to authenticate with provided credentials
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Teams API Test Result -->
                <?php if ($testResults['teams_test']): ?>
                <div class="result-item">
                    <div class="result-icon <?php echo $testResults['teams_test'] === 'SUCCESS' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $testResults['teams_test'] === 'SUCCESS' ? 'check-circle' : 'times-circle'; ?>"></i>
                    </div>
                    <div class="result-content">
                        <div class="result-title">
                            Teams API Access
                            <span style="font-weight: normal;">(HTTP <?php echo $testResults['http_codes']['teams'] ?? 'N/A'; ?>)</span>
                        </div>
                        <div class="result-description">
                            <?php if ($testResults['teams_test'] === 'SUCCESS'): ?>
                                ✅ Successfully accessed Teams API - Found <?php echo $testResults['teams_count'] ?? 0; ?> teams
                            <?php else: ?>
                                ❌ Failed to access Teams API - Check app permissions
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Overall Result -->
                <?php if ($testResults['success']): ?>
                    <div class="success-details">
                        <h4><i class="fas fa-thumbs-up"></i> Connection Successful!</h4>
                        <p>Your Teams API integration is working correctly. You can now use the dashboard with real Teams data.</p>
                        <?php if (isset($testResults['warning'])): ?>
                        <div class="alert warning" style="margin-top: 1rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($testResults['warning']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="error-details">
                        <strong>❌ Connection Failed</strong>
                        
                        <?php if (is_array($testResults['error_details'])): ?>
                            <?php if (isset($testResults['error_details']['response']['error'])): ?>
Error: <?php echo $testResults['error_details']['response']['error']; ?>
Description: <?php echo $testResults['error_details']['response']['error_description'] ?? 'No description provided'; ?>
                            <?php endif; ?>
                            
HTTP Code: <?php echo $testResults['error_details']['http_code'] ?? $testResults['error_details']['teams_http_code'] ?? 'Unknown'; ?>
                            
                            <?php if (isset($testResults['error_details']['teams_response']['error'])): ?>
Teams API Error: <?php echo $testResults['error_details']['teams_response']['error']['code'] ?? 'Unknown'; ?>
Message: <?php echo $testResults['error_details']['teams_response']['error']['message'] ?? 'No message'; ?>
                            <?php endif; ?>
                        <?php else: ?>
<?php echo $testResults['error_details']; ?>
                        <?php endif; ?>
                        
Common Solutions:
• Verify your Client ID, Client Secret, and Tenant ID are correct
• Ensure app has required permissions: Team.ReadBasic.All, Channel.ReadBasic.All, ChannelMessage.Read.All
• Click "Grant admin consent" in Azure Portal under API permissions
• Make sure the app registration is in the correct Azure tenant
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem; text-align: center;">
                <a href="summaries.php" class="btn" style="text-decoration: none; margin-right: 1rem;">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <a href="test_teams_api.php" class="btn" style="text-decoration: none; background: #10b981;">
                    <i class="fas fa-cogs"></i>
                    Full Diagnostic Test
                </a>
            </div>
        </div>
    </div>
</body>
</html>