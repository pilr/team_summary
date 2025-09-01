<?php
require_once 'teams_config.php';

echo "=== Debug Token Request ===\n";
echo "Client ID: " . TEAMS_CLIENT_ID . "\n";
echo "Tenant ID: " . TEAMS_TENANT_ID . "\n";
echo "Token URL: " . TEAMS_TOKEN_URL . "\n\n";

// Test token request manually
$tokenData = [
    'client_id' => TEAMS_CLIENT_ID,
    'client_secret' => TEAMS_CLIENT_SECRET,
    'scope' => 'https://graph.microsoft.com/.default',
    'grant_type' => 'client_credentials'
];

echo "Making token request...\n";

$ch = curl_init(TEAMS_TOKEN_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "CURL Error: $error\n";
}
echo "Response:\n";
echo $response . "\n";

if ($httpCode === 200) {
    $tokenResponse = json_decode($response, true);
    if ($tokenResponse && isset($tokenResponse['access_token'])) {
        echo "\n✓ Token obtained successfully!\n";
        echo "Token type: " . ($tokenResponse['token_type'] ?? 'unknown') . "\n";
        echo "Expires in: " . ($tokenResponse['expires_in'] ?? 'unknown') . " seconds\n";
    }
} else {
    echo "\n✗ Failed to get token\n";
    $errorData = json_decode($response, true);
    if ($errorData) {
        echo "Error: " . ($errorData['error'] ?? 'unknown') . "\n";
        echo "Description: " . ($errorData['error_description'] ?? 'no description') . "\n";
    }
}
?>