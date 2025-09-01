<?php
session_start();
require_once 'database_helper.php';

echo "=== External User Teams API Debug ===\n";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "❌ Not logged in\n";
    exit();
}

global $db;
$token_info = $db->getOAuthToken($user_id, 'microsoft');
if (!$token_info) {
    echo "❌ No token found\n";
    exit();
}

$access_token = $token_info['access_token'];

echo "=== User Profile Analysis ===\n";
$profile = getUserProfile($access_token);
if ($profile) {
    echo "✓ User Profile Retrieved\n";
    echo "Display Name: " . ($profile['displayName'] ?? 'N/A') . "\n";
    echo "User Principal Name: " . ($profile['userPrincipalName'] ?? 'N/A') . "\n";
    echo "Mail: " . ($profile['mail'] ?? 'N/A') . "\n";
    echo "Account Type: " . ($profile['accountEnabled'] ? 'Enabled' : 'Disabled') . "\n";
    
    // Check if external user
    $upn = $profile['userPrincipalName'] ?? '';
    $isExternal = strpos($upn, '#EXT#') !== false;
    echo "External User: " . ($isExternal ? 'YES' : 'NO') . "\n";
    
    if ($isExternal) {
        echo "⚠️  DETECTED: External user - this may cause API restrictions\n";
        
        // Extract original domain and tenant
        if (preg_match('/([^#]+)#EXT#@(.+)/', $upn, $matches)) {
            $originalEmail = str_replace('_', '@', $matches[1]);
            $hostTenant = $matches[2];
            echo "Original Email: $originalEmail\n";
            echo "Host Tenant: $hostTenant\n";
        }
    }
} else {
    echo "❌ Failed to get user profile\n";
    exit();
}

echo "\n=== Token Analysis ===\n";
echo "Token Scopes: " . ($token_info['scope'] ?? 'N/A') . "\n";
echo "Expires At: " . $token_info['expires_at'] . "\n";

// Try to decode JWT token for more details
$tokenParts = explode('.', $access_token);
if (count($tokenParts) === 3) {
    try {
        $header = json_decode(base64_decode($tokenParts[0]), true);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        echo "\nJWT Token Details:\n";
        echo "- Issuer: " . ($payload['iss'] ?? 'N/A') . "\n";
        echo "- Audience: " . ($payload['aud'] ?? 'N/A') . "\n";
        echo "- Tenant ID: " . ($payload['tid'] ?? 'N/A') . "\n";
        echo "- App ID: " . ($payload['appid'] ?? 'N/A') . "\n";
        echo "- Scopes: " . ($payload['scp'] ?? 'N/A') . "\n";
        
        // Check for external user indicators in token
        if (isset($payload['acr']) && $payload['acr'] === '0') {
            echo "⚠️  Token ACR=0 indicates external user authentication\n";
        }
        
    } catch (Exception $e) {
        echo "Could not decode JWT token\n";
    }
}

echo "\n=== API Endpoint Tests ===\n";

// Test different Teams-related endpoints to see which ones work
$endpoints = [
    '/me' => 'User Profile (should work)',
    '/me/joinedTeams' => 'Joined Teams (main issue)',
    '/me/teamwork' => 'Teamwork root (alternative)',
    '/me/chats' => 'Chats (different permission)',
    '/groups' => 'Groups (might work for external users)',
    '/me/memberOf' => 'Member Of (groups/teams)'
];

foreach ($endpoints as $endpoint => $description) {
    echo "\nTesting: $endpoint ($description)\n";
    $result = makeAPICall($endpoint, $access_token);
    
    echo "  HTTP Code: " . $result['http_code'] . "\n";
    
    if ($result['http_code'] === 200) {
        echo "  ✓ SUCCESS\n";
        if ($endpoint === '/me/memberOf') {
            $data = json_decode($result['response'], true);
            $groups = $data['value'] ?? [];
            echo "    Found " . count($groups) . " groups/teams\n";
            
            // Check for Teams specifically
            $teamsCount = 0;
            foreach ($groups as $group) {
                if (isset($group['resourceProvisioningOptions']) && 
                    in_array('Team', $group['resourceProvisioningOptions'] ?? [])) {
                    $teamsCount++;
                }
            }
            echo "    Teams found via memberOf: $teamsCount\n";
        }
    } elseif ($result['http_code'] === 403) {
        echo "  ❌ FORBIDDEN\n";
        $error = json_decode($result['response'], true);
        if ($error && isset($error['error']['message'])) {
            $errorMsg = $error['error']['message'];
            echo "  Error: " . substr($errorMsg, 0, 100) . "...\n";
            
            // Check for specific error indicators
            if (strpos($errorMsg, 'external user') !== false || 
                strpos($errorMsg, 'cross-tenant') !== false) {
                echo "  🔍 LIKELY CAUSE: External user restrictions\n";
            } elseif (strpos($errorMsg, 'license') !== false) {
                echo "  🔍 LIKELY CAUSE: License requirements\n";
            } elseif (strpos($errorMsg, 'policy') !== false) {
                echo "  🔍 LIKELY CAUSE: Organizational policy\n";
            }
        }
    } else {
        echo "  ❌ FAILED (HTTP " . $result['http_code'] . ")\n";
    }
}

echo "\n=== Recommendations ===\n";
if ($isExternal) {
    echo "🔍 ROOT CAUSE: External user trying to access Teams API\n\n";
    echo "SOLUTIONS:\n";
    echo "1. Check Cross-Tenant Access Settings:\n";
    echo "   - Azure AD > External Identities > Cross-tenant access settings\n";
    echo "   - Ensure API access is allowed for external users\n\n";
    echo "2. Verify External User Permissions:\n";
    echo "   - The external user needs proper Teams licensing\n";
    echo "   - Check if external users can access Teams in the org\n\n";
    echo "3. Alternative API Approach:\n";
    echo "   - Use /me/memberOf endpoint instead of /me/joinedTeams\n";
    echo "   - Filter results for Teams (resourceProvisioningOptions contains 'Team')\n\n";
    echo "4. Organization Policy:\n";
    echo "   - Some orgs block external users from API access entirely\n";
    echo "   - Contact IT admin about external user API access policies\n";
} else {
    echo "User is not external - investigating other causes...\n";
}

function getUserProfile($access_token) {
    $result = makeAPICall('/me', $access_token);
    return $result['http_code'] === 200 ? json_decode($result['response'], true) : false;
}

function makeAPICall($endpoint, $access_token) {
    $url = 'https://graph.microsoft.com/v1.0' . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'http_code' => $http_code];
}

echo "\nDone.\n";
?>