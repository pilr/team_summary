<?php
ob_start(); // Start output buffering
session_start();
require_once 'database_helper.php';

// Initialize variables
$error_message = '';

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check demo credentials first for immediate redirect
        if ($email === 'demo@company.com' && $password === 'demo123') {
            // Demo authentication successful
            $_SESSION['logged_in'] = true;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = 'John Doe';
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['login_method'] = 'demo';
            
            if ($remember) {
                // Set remember me cookie for 30 days
                setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
            }
            
            // Debug: Check session before redirect
            error_log("Login successful for demo user. Session ID: " . session_id());
            error_log("Session data: " . print_r($_SESSION, true));
            
            // Flush any output and redirect
            ob_end_clean(); // Clear output buffer
            header('Location: index.php');
            exit();
        }
        
        // Try database authentication for other users
        try {
            global $db;
            $user = $db->authenticateUser($email, $password);
            
            if ($user) {
                // Database authentication successful
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['display_name'];
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['login_method'] = 'database';
                
                if ($remember) {
                    // Set remember me cookie for 30 days
                    setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
                }
                
                // Log the successful login
                try {
                    $db->logActivity($user['id'], 'login', null, null, ['method' => 'email', 'success' => true]);
                } catch (Exception $log_error) {
                    // Ignore logging errors, don't prevent login
                }
                
                // Flush any output and redirect
                ob_end_clean(); // Clear output buffer
                header('Location: index.php');
                exit();
            } else {
                $error_message = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            // Database connection failed
            $error_message = 'Login system temporarily unavailable. Please try again later.';
        }
    }
}

// Get remembered email from cookie
$remembered_email = $_COOKIE['remember_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Teams Activity Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="login-styles.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-background">
            <div class="background-shape shape-1"></div>
            <div class="background-shape shape-2"></div>
            <div class="background-shape shape-3"></div>
        </div>
        
        <div class="login-content">
            <!-- Left Side - Branding -->
            <div class="login-branding">
                <div class="brand-logo">
                    <i class="fas fa-comments"></i>
                    <h1>TeamSummary</h1>
                </div>
                <div class="brand-tagline">
                    <h2>Stay on top of your Teams activity</h2>
                    <p>Get intelligent summaries, never miss important messages, and stay connected with your team effortlessly.</p>
                </div>
                <div class="feature-highlights">
                    <div class="feature-item">
                        <i class="fas fa-bolt"></i>
                        <span>Real-time notifications</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Smart activity insights</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Enterprise-grade security</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="login-form-container">
                <div class="login-form-wrapper">
                    <div class="login-header">
                        <h3>Welcome back</h3>
                        <p>Sign in to your account to continue</p>
                    </div>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    
                    <form class="login-form" id="loginForm" method="POST">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="Enter your email" 
                                       value="<?php echo htmlspecialchars($remembered_email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" id="passwordToggle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" id="remember" name="remember" 
                                       <?php echo $remembered_email ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                <span class="checkbox-label">Remember me</span>
                            </label>
                            <a href="#" class="forgot-password">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="login-btn">
                            <span class="btn-text">Sign In</span>
                            <i class="fas fa-spinner fa-spin loading-spinner" style="display: none;"></i>
                        </button>
                        
                        <div class="divider">
                            <span>or</span>
                        </div>
                        
                        <div class="social-login">
                            <button type="button" class="social-btn microsoft-btn" onclick="handleSocialLogin('microsoft')">
                                <i class="fab fa-microsoft"></i>
                                <span>Continue with Microsoft</span>
                            </button>
                            <button type="button" class="social-btn google-btn" onclick="handleSocialLogin('google')">
                                <i class="fab fa-google"></i>
                                <span>Continue with Google</span>
                            </button>
                        </div>
                    </form>
                    
                    <div class="login-footer">
                        <p>Don't have an account? <a href="#" class="signup-link">Sign up for free</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Demo Credentials Notice -->
        <div class="demo-notice">
            <div class="demo-content">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Demo Login:</strong>
                    <span>Email: demo@company.com | Password: demo123</span>
                </div>
            </div>
        </div>
    </div>
    
    <script src="login-script.js"></script>
    <script>
        // Handle social login
        function handleSocialLogin(provider) {
            // In a real application, this would redirect to OAuth provider
            showToast(`${provider.charAt(0).toUpperCase() + provider.slice(1)} login would redirect to OAuth`, 'info');
            
            // Simulate OAuth success for demo
            setTimeout(() => {
                // Create form and submit to simulate social login
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'social-login.php';
                
                const providerInput = document.createElement('input');
                providerInput.type = 'hidden';
                providerInput.name = 'provider';
                providerInput.value = provider;
                
                form.appendChild(providerInput);
                document.body.appendChild(form);
                form.submit();
            }, 2000);
        }
        
        // PHP error handling
        <?php if ($error_message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            form.classList.add('shake');
            setTimeout(() => {
                form.classList.remove('shake');
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>