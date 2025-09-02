<?php
/**
 * Microsoft Teams API Connection Test Script
 * This script tests the connection to Microsoft Graph API and diagnoses configuration issues
 */

require_once 'teams_config.php';
require_once 'teams_api.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Microsoft Teams API Connection Test</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .code { background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; }
    pre { background: #f9f9f9; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>\n";

// Function to test step and display results
function testStep($stepName, $callback, $description = '') {
    echo "<div class='section'>";
    echo "<h3>🔧 Testing: $stepName</h3>";
    if ($description) {
        echo "<p class='info'>$description</p>";
    }
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "<p class='success'>✅ PASSED</p>";
        } elseif ($result === false) {
            echo "<p class='error'>❌ FAILED</p>";
        } else {
            echo "<div class='code'>";
            echo is_array($result) ? "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>" : "<p>$result</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>\n";
}

echo "<h2>📋 Step 1: Configuration Check</h2>";

// Test 1: Check if configuration constants are defined
testStep("Configuration Constants", function() {
    $constants = ['TEAMS_CLIENT_ID', 'TEAMS_CLIENT_SECRET', 'TEAMS_SECRET_ID'];
    $results = [];
    
    foreach ($constants as $constant) {
        if (defined($constant)) {
            $value = constant($constant);
            if ($value && $value !== 'YOUR_CLIENT_ID_HERE' && $value !== 'YOUR_CLIENT_SECRET_HERE' && $value !== 'YOUR_SECRET_ID_HERE') {
                $results[$constant] = "✅ Set (" . substr($value, 0, 10) . "...)";
            } else {
                $results[$constant] = "❌ Not configured (using placeholder)";
            }
        } else {
            $results[$constant] = "❌ Not defined";
        }
    }
    
    return $results;
}, "Checking if API credentials are properly configured");

// Test 2: Check if credentials file exists
testStep("Credentials File", function() {
    $credentialsFile = __DIR__ . '/team_summary_ke.txt';
    if (file_exists($credentialsFile)) {
        $lines = file($credentialsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $found = [];
        foreach ($lines as $line) {
            if (strpos($line, 'Client ID:') === 0) $found['client_id'] = true;
            if (strpos($line, 'Client Secret:') === 0) $found['client_secret'] = true;  
            if (strpos($line, 'Secret ID:') === 0) $found['secret_id'] = true;
        }
        return [
            'file_exists' => '✅ team_summary_ke.txt found',
            'client_id' => isset($found['client_id']) ? '✅ Client ID found' : '❌ Client ID missing',
            'client_secret' => isset($found['client_secret']) ? '✅ Client Secret found' : '❌ Client Secret missing',
            'secret_id' => isset($found['secret_id']) ? '✅ Secret ID found' : '❌ Secret ID missing'
        ];
    } else {
        return "⚠️ team_summary_ke.txt file not found. Using environment variables or default values.";
    }
}, "Checking if team_summary_ke.txt credentials file exists and contains required fields");

// Test 3: Check PHP extensions
testStep("PHP Extensions", function() {
    $required = ['curl', 'json', 'openssl'];
    $results = [];
    
    foreach ($required as $ext) {
        $results[$ext] = extension_loaded($ext) ? "✅ Available" : "❌ Missing";
    }
    
    return $results;
}, "Checking required PHP extensions for API communication");

echo "<h2>🌐 Step 2: Network and API Tests</h2>";

// Test 4: Test internet connectivity
testStep("Internet Connectivity", function() {
    $ch = curl_init('https://httpbin.org/get');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return "❌ cURL Error: $error";
    } elseif ($httpCode === 200) {
        return "✅ Internet connection working";
    } else {
        return "❌ HTTP Error: $httpCode";
    }
}, "Testing basic internet connectivity");

// Test 5: Test Microsoft Graph API reachability
testStep("Microsoft Graph API Reachability", function() {
    $ch = curl_init('https://graph.microsoft.com/v1.0/$metadata');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return "❌ Cannot reach Microsoft Graph: $error";
    } elseif ($httpCode === 200) {
        return "✅ Microsoft Graph API is reachable";
    } else {
        return "⚠️ HTTP Code: $httpCode (may still work for authentication)";
    }
}, "Testing if Microsoft Graph API endpoints are accessible");

echo "<h2>🔑 Step 3: Authentication Tests</h2>";

// Test 6: Initialize Teams API and test token generation
testStep("Teams API Initialization & Token", function() {
    $teamsAPI = new TeamsAPIHelper();
    $token = $teamsAPI->getAccessToken();
    
    if (!$token) {
        return "❌ Failed to get access token. Check credentials and permissions.";
    }
    
    // Validate token format (JWT should have 3 parts separated by dots)
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return "❌ Invalid token format received";
    }
    
    return [
        'token_received' => '✅ Access token obtained',
        'token_length' => strlen($token) . ' characters',
        'token_parts' => count($parts) . ' parts (header.payload.signature)',
        'expires' => 'Check cache/access_token.json for expiration'
    ];
}, "Testing OAuth2 authentication and access token generation");

// Test 7: Test Teams API calls
testStep("Teams API Calls", function() {
    $teamsAPI = new TeamsAPIHelper();
    
    try {
        // Test getting teams
        $teams = $teamsAPI->getTeams();
        $teamCount = count($teams);
        
        if ($teamCount === 0) {
            return [
                'teams_call' => '⚠️ API call successful but no teams returned',
                'possible_reasons' => [
                    'App may not have permission to access teams',
                    'User may not be member of any teams',
                    'App permissions may not be granted by admin'
                ],
                'next_steps' => 'Check app permissions in Azure Portal'
            ];
        }
        
        $results = [
            'teams_call' => "✅ Successfully retrieved $teamCount teams",
            'teams' => []
        ];
        
        // Get details of first few teams
        foreach (array_slice($teams, 0, 3) as $team) {
            $results['teams'][] = [
                'id' => substr($team['id'] ?? 'unknown', 0, 20) . '...',
                'displayName' => $team['displayName'] ?? 'Unknown',
                'description' => substr($team['description'] ?? '', 0, 50)
            ];
        }
        
        return $results;
        
    } catch (Exception $e) {
        return "❌ API call failed: " . $e->getMessage();
    }
}, "Testing actual Teams API calls to fetch teams data");

// Test 8: Test channels API
testStep("Channels API Test", function() {
    $teamsAPI = new TeamsAPIHelper();
    
    try {
        $teams = $teamsAPI->getTeams();
        
        if (empty($teams)) {
            return "❌ Cannot test channels - no teams available";
        }
        
        $firstTeam = $teams[0];
        $channels = $teamsAPI->getTeamChannels($firstTeam['id']);
        $channelCount = count($channels);
        
        $results = [
            'team_tested' => $firstTeam['displayName'] ?? 'Unknown Team',
            'channels_call' => "✅ Successfully retrieved $channelCount channels"
        ];
        
        if ($channelCount > 0) {
            $results['sample_channels'] = [];
            foreach (array_slice($channels, 0, 3) as $channel) {
                $results['sample_channels'][] = [
                    'displayName' => $channel['displayName'] ?? 'Unknown',
                    'membershipType' => $channel['membershipType'] ?? 'standard'
                ];
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        return "❌ Channels API call failed: " . $e->getMessage();
    }
}, "Testing channels API to fetch channel data from first available team");

echo "<h2>📊 Step 4: Summary and Recommendations</h2>";

// Test 9: Overall system check
testStep("System Status Summary", function() {
    $teamsAPI = new TeamsAPIHelper();
    
    // Quick overall test
    $hasToken = (bool)$teamsAPI->getAccessToken();
    $teams = $teamsAPI->getTeams();
    $hasTeams = !empty($teams);
    
    if (!$hasToken) {
        return [
            'status' => '❌ CRITICAL: Cannot authenticate with Microsoft Graph API',
            'recommendations' => [
                '1. Verify Client ID and Client Secret in team_summary_ke.txt or environment variables',
                '2. Check if app is registered in Azure Active Directory',
                '3. Ensure app has required permissions: Team.ReadBasic.All, Channel.ReadBasic.All',
                '4. Verify admin consent has been granted for application permissions'
            ]
        ];
    } elseif (!$hasTeams) {
        return [
            'status' => '⚠️ WARNING: Authentication works but no teams data available',
            'recommendations' => [
                '1. Check if the app has admin consent for required permissions',
                '2. Verify the app registration has correct API permissions',
                '3. Ensure the organization has Microsoft Teams with active teams',
                '4. Check if application permissions include Team.ReadBasic.All'
            ]
        ];
    } else {
        return [
            'status' => '✅ SUCCESS: Microsoft Teams API is working correctly',
            'teams_found' => count($teams) . ' teams accessible',
            'ready_to_use' => 'The summaries page should now display real Teams data'
        ];
    }
}, "Overall assessment of Microsoft Teams API integration status");

echo "<div class='section'>";
echo "<h3>🔧 Troubleshooting Guide</h3>";
echo "<div class='info'>";
echo "<h4>If you're seeing 'No Teams Data Available':</h4>";
echo "<ol>";
echo "<li><strong>Check Credentials:</strong> Ensure team_summary_ke.txt exists with correct values</li>";
echo "<li><strong>Azure App Registration:</strong> Verify app is registered in Azure AD</li>";
echo "<li><strong>API Permissions:</strong> Required permissions:
    <ul>
        <li>Microsoft Graph → Application permissions</li>
        <li>Team.ReadBasic.All</li>
        <li>Channel.ReadBasic.All</li>
        <li>ChannelMessage.Read.All</li>
        <li>User.Read.All</li>
    </ul>
</li>";
echo "<li><strong>Admin Consent:</strong> Click 'Grant admin consent' in Azure Portal</li>";
echo "<li><strong>File Permissions:</strong> Ensure PHP can read team_summary_ke.txt and create cache/ directory</li>";
echo "</ol>";
echo "</div>";
echo "</div>";

echo "<div class='section'>";
echo "<h3>📁 Files to Check</h3>";
echo "<ul>";
echo "<li><strong>team_summary_ke.txt:</strong> " . (file_exists(__DIR__ . '/team_summary_ke.txt') ? '✅ Found' : '❌ Missing') . "</li>";
echo "<li><strong>cache/ directory:</strong> " . (is_dir(__DIR__ . '/cache') ? '✅ Exists' : '❌ Missing - will be created automatically') . "</li>";
echo "<li><strong>teams_config.php:</strong> " . (file_exists(__DIR__ . '/teams_config.php') ? '✅ Found' : '❌ Missing') . "</li>";
echo "<li><strong>teams_api.php:</strong> " . (file_exists(__DIR__ . '/teams_api.php') ? '✅ Found' : '❌ Missing') . "</li>";
echo "</ul>";
echo "</div>";

$executionTime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
echo "<div class='section'>";
echo "<p class='info'>Test completed in " . round($executionTime, 2) . " seconds</p>";
echo "<p><strong>Next steps:</strong> If all tests pass, refresh your summaries.php page to see real Teams data.</p>";
echo "</div>";

echo "<script>
// Auto-refresh every 30 seconds if user wants to monitor
setTimeout(function() {
    if (confirm('Refresh the test to check again?')) {
        location.reload();
    }
}, 30000);
</script>";
?>