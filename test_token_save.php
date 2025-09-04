<?php
/**
 * Test Token Save Functionality
 * Simple test to verify oauth token saving works
 */
require_once 'database_helper.php';

// Prevent web access for security
if (isset($_SERVER['REQUEST_METHOD'])) {
    header('Content-Type: text/plain');
    echo "This script is for testing only.\n";
}

echo "=== Testing OAuth Token Save Functionality ===\n";

try {
    $db = new DatabaseHelper();
    
    // Test with sample data
    $testUserId = 999; // Use a test user ID
    $testToken = 'test_token_' . time();
    $testRefreshToken = 'refresh_token_' . time();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    echo "1. Testing token save...\n";
    echo "   User ID: $testUserId\n";
    echo "   Expires: $expiresAt\n";
    
    $result = $db->saveOAuthToken(
        $testUserId,
        'microsoft',
        $testToken,
        $testRefreshToken,
        'Bearer',
        $expiresAt,
        'test scope'
    );
    
    if ($result) {
        echo "✓ Token save successful!\n";
        
        // Test retrieval
        echo "2. Testing token retrieval...\n";
        $retrieved = $db->getOAuthToken($testUserId, 'microsoft');
        
        if ($retrieved && $retrieved['access_token'] === $testToken) {
            echo "✓ Token retrieval successful!\n";
            
            // Clean up
            echo "3. Cleaning up test data...\n";
            $pdo = $db->getPDO();
            $stmt = $pdo->prepare("DELETE FROM oauth_tokens WHERE user_id = ? AND provider = ?");
            $stmt->execute([$testUserId, 'microsoft']);
            echo "✓ Cleanup complete!\n";
            
            echo "\n=== SUCCESS ===\n";
            echo "OAuth token functionality is working correctly!\n";
            echo "You can now try connecting to Microsoft Teams.\n";
        } else {
            echo "✗ Token retrieval failed!\n";
            exit(1);
        }
    } else {
        echo "✗ Token save failed!\n";
        echo "Check the error logs for more details.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>