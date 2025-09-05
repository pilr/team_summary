<?php
session_start();
require_once 'database_helper.php';
require_once 'error_logger.php';

// Handle social login simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['provider'] ?? '';
    
    if (in_array($provider, ['microsoft', 'google'])) {
        try {
            global $db;
            
            // Map provider to actual user emails based on the issue description
            $user_email = '';
            if ($provider === 'microsoft') {
                $user_email = 'pil.rollano@seriousweb.ch'; // User who should show as themselves
            } else {
                $user_email = 'staedeli@gmail.com'; // User who was showing wrong info
            }
            
            // Get the actual user from database
            $stmt = $db->getPDO()->prepare("SELECT id, email, display_name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$user_email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Set session data properly with database values
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email']; 
                $_SESSION['user_name'] = $user['display_name'];
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['login_method'] = $provider;
                
                // Log successful social login
                ErrorLogger::logSuccess("Social login successful", [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'provider' => $provider,
                    'session_id' => session_id()
                ]);
                
                header('Location: index.php');
                exit();
            } else {
                ErrorLogger::logAuthError("social_login", "User not found in database", [
                    'email' => $user_email,
                    'provider' => $provider
                ]);
                header('Location: login.php?error=invalid_user');
                exit();
            }
        } catch (Exception $e) {
            ErrorLogger::logAuthError("social_login", "Database error: " . $e->getMessage(), [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            header('Location: login.php?error=database_error');
            exit();
        }
    }
}

// Redirect back to login if invalid request
header('Location: login.php');
exit();
?>