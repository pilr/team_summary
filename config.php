<?php
// Configuration file for Teams Summary Dashboard

// Database configuration (for future use)
define('DB_HOST', 'localhost');
define('DB_NAME', 'u175828155_team_summary');
define('DB_USER', 'u175828155_team_summary');
define('DB_PASS', 'x[=5Pja3O');

// Application settings
define('APP_NAME', 'TeamsSummary');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://teamsummary.seriousweb.dev/');

// Session configuration
ini_set('session.cookie_lifetime', 3600 * 24 * 7); // 7 days
ini_set('session.gc_maxlifetime', 3600 * 24 * 7);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_only_cookies', 1);

// Demo credentials
define('DEMO_EMAIL', 'demo@company.com');
define('DEMO_PASSWORD', 'demo123');

// API endpoints (for future integration)
define('TEAMS_API_BASE', 'https://graph.microsoft.com/v1.0');
define('SLACK_API_BASE', 'https://slack.com/api');

// File upload settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Email settings (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

// Timezone
date_default_timezone_set('America/New_York');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Disable caching sitewide
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Function to get database connection (for future use)
function getDatabaseConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // For now, return null since we're using mock data
        return null;
    }
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Helper function to generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper function to validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to log activities (for future use)
function logActivity($user_id, $action, $details = '') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // In a real application, this would write to database or log file
    error_log('Activity Log: ' . json_encode($log_entry));
}
?>