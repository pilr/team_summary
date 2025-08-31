<?php
// Script to create a user manually in the database
require_once 'database_helper.php';

// Get user input from command line arguments
if ($argc < 4) {
    echo "Usage: php create_user.php <email> <password> <display_name> [first_name] [last_name]\n";
    echo "Example: php create_user.php user@example.com mypassword \"John Smith\" John Smith\n";
    exit(1);
}

$email = $argv[1];
$password = $argv[2];
$displayName = $argv[3];
$firstName = $argv[4] ?? '';
$lastName = $argv[5] ?? '';

try {
    global $db;
    
    // Check if user already exists
    $existingUser = $db->getUserByEmail($email);
    
    if ($existingUser) {
        echo "Error: User with email '$email' already exists with ID: " . $existingUser['id'] . "\n";
        exit(1);
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Error: Invalid email address format.\n";
        exit(1);
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        echo "Error: Password must be at least 6 characters long.\n";
        exit(1);
    }
    
    // Create user
    $userId = $db->createUser($email, $password, $displayName, $firstName, $lastName);
    
    if ($userId) {
        echo "User created successfully!\n";
        echo "User ID: " . $userId . "\n";
        echo "Email: " . $email . "\n";
        echo "Password: " . $password . "\n";
        echo "Display Name: " . $displayName . "\n";
        if ($firstName) echo "First Name: " . $firstName . "\n";
        if ($lastName) echo "Last Name: " . $lastName . "\n";
        echo "\nYou can now login with these credentials.\n";
    } else {
        echo "Failed to create user. Check error logs for details.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Make sure your database is properly configured and running.\n";
    exit(1);
}
?>