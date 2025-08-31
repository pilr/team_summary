<?php
session_start();

// Handle social login simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['provider'] ?? '';
    
    if (in_array($provider, ['microsoft', 'google'])) {
        // Simulate successful OAuth login
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = $provider === 'microsoft' ? 'john.doe@company.com' : 'john.doe@gmail.com';
        $_SESSION['user_name'] = 'John Doe';
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        $_SESSION['login_method'] = $provider;
        
        header('Location: index.php');
        exit();
    }
}

// Redirect back to login if invalid request
header('Location: login.php');
exit();
?>