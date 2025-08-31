<?php
// Script to create a demo user in the database
require_once 'database_helper.php';

try {
    global $db;
    
    // Check if demo user already exists
    $stmt = $db->pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['demo@company.com']);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "Demo user already exists with ID: " . $existingUser['id'] . "\n";
        echo "Email: demo@company.com\n";
        echo "Password: demo123\n";
        exit();
    }
    
    // Create demo user
    $userId = $db->createUser(
        'demo@company.com',
        'demo123',
        'John Doe (Demo)',
        'John',
        'Doe'
    );
    
    if ($userId) {
        echo "Demo user created successfully!\n";
        echo "User ID: " . $userId . "\n";
        echo "Email: demo@company.com\n";
        echo "Password: demo123\n";
        echo "Display Name: John Doe (Demo)\n";
        echo "\nYou can now login with these credentials.\n";
    } else {
        echo "Failed to create demo user. Check error logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Make sure your database is properly configured and running.\n";
}
?>