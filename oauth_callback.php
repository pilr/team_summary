<?php
session_start();
require_once 'teams_config.php';
require_once 'database_helper.php';
require_once 'error_logger.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?error=session_expired');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Unknown';
$user_email = $_SESSION['user_email'] ?? 'Unknown';

// Enhanced session validation - verify user exists in database
if (!$user_id) {
    ErrorLogger::logOAuthError("callback_validation", "No user_id in session", [
        'session_data' => $_SESSION,
        'redirect' => 'login.php?error=invalid_session'
    ]);
    header('Location: login.php?error=invalid_session');
    exit();
}

// Verify the user_id corresponds to a valid user in the database
try {
    global $db;
    $stmt = $db->getPDO()->prepare("SELECT id, email, display_name FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $dbUser = $stmt->fetch();
    
    if (!$dbUser) {
        ErrorLogger::logOAuthError("callback_validation", "User ID from session not found in database", [
            'session_user_id' => $user_id,
            'session_email' => $user_email,
            'redirect' => 'login.php?error=invalid_user'
        ]);
        // Clear session and redirect to login
        session_destroy();
        header('Location: login.php?error=invalid_user');
        exit();
    }
    
    // Additional validation: ensure session email matches database email
    if ($dbUser['email'] !== $user_email) {
        ErrorLogger::logOAuthError("callback_validation", "Session email mismatch with database", [
            'session_user_id' => $user_id,
            'session_email' => $user_email,
            'database_email' => $dbUser['email'],
            'redirect' => 'login.php?error=session_mismatch'
        ]);
        // Clear session and redirect to login
        session_destroy();
        header('Location: login.php?error=session_mismatch');
        exit();
    }
    
    ErrorLogger::log("OAuth Callback: Validated user session", [
        'user_id' => $user_id,
        'user_email' => $user_email,
        'user_name' => $user_name,
        'database_verified' => true
    ]);
    
} catch (Exception $dbError) {
    ErrorLogger::logOAuthError("callback_validation", "Database error during user validation: " . $dbError->getMessage(), [
        'session_user_id' => $user_id,
        'session_email' => $user_email,
        'exception' => $dbError->getMessage()
    ]);
    header('Location: login.php?error=database_error');
    exit();
}

// Handle OAuth callback
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $error_description = $_GET['error_description'] ?? 'Unknown error occurred';
    ErrorLogger::logOAuthError("callback_error", "$error - $error_description", [
        'error_code' => $error,
        'error_description' => $error_description,
        'get_params' => $_GET
    ]);
    header('Location: account.php?error=oauth_failed&message=' . urlencode($error_description));
    exit();
}

if (!isset($_GET['code'])) {
    ErrorLogger::logOAuthError("callback_validation", "No authorization code received", [
        'get_params' => $_GET,
        'redirect' => 'account.php?error=no_auth_code'
    ]);
    header('Location: account.php?error=no_auth_code');
    exit();
}

$auth_code = $_GET['code'];
$state = $_GET['state'] ?? '';

// Verify state parameter to prevent CSRF attacks and code reuse
if (empty($state)) {
    ErrorLogger::logOAuthError("state_validation", "No state parameter provided", [
        'get_params' => $_GET
    ]);
    header('Location: account.php?error=invalid_state');
    exit();
}

// Check if this authorization code has already been processed
$processed_codes_key = 'processed_oauth_codes';
if (!isset($_SESSION[$processed_codes_key])) {
    $_SESSION[$processed_codes_key] = [];
}

// Create a hash of the auth code to check for duplicates
$code_hash = hash('sha256', $auth_code);
if (in_array($code_hash, $_SESSION[$processed_codes_key])) {
    ErrorLogger::logOAuthError("code_reuse", "Authorization code already processed", [
        'code_hash' => $code_hash,
        'state' => $state
    ]);
    header('Location: account.php?error=code_already_used');
    exit();
}

// Mark this code as processed
$_SESSION[$processed_codes_key][] = $code_hash;
// Keep only last 5 processed codes to prevent memory bloat
$_SESSION[$processed_codes_key] = array_slice($_SESSION[$processed_codes_key], -5);

try {
    // Exchange authorization code for access token
    $token_data = [
        'client_id' => TEAMS_CLIENT_ID,
        'client_secret' => TEAMS_CLIENT_SECRET,
        'code' => $auth_code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => TEAMS_REDIRECT_URI
    ];

    $ch = curl_init(TEAMS_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        ErrorLogger::logAPIError("Microsoft", "token_endpoint", 0, "CURL Error: $curl_error", [
            'token_request' => $token_data,
            'curl_error' => $curl_error
        ]);
        throw new Exception("CURL Error: $curl_error");
    }

    if ($http_code !== 200) {
        ErrorLogger::logAPIError("Microsoft", "token_endpoint", $http_code, "Token request failed", [
            'token_request' => $token_data,
            'response' => $response,
            'http_code' => $http_code
        ]);
        throw new Exception("Token request failed with HTTP code: $http_code. Response: $response");
    }

    $token_response = json_decode($response, true);
    if (!$token_response || !isset($token_response['access_token'])) {
        ErrorLogger::logOAuthError("token_parse", "Invalid token response", [
            'response' => $response,
            'parsed_response' => $token_response
        ]);
        throw new Exception("Invalid token response: $response");
    }

    // Calculate expiration time in UTC
    $expires_in = $token_response['expires_in'] ?? 3600;
    $expires_at = new DateTime('now', new DateTimeZone('UTC'));
    $expires_at->add(new DateInterval("PT{$expires_in}S"));

    // Save token to database
    global $db;
    
    // Debug: Check if $db is properly initialized
    if (!$db) {
        ErrorLogger::logDatabaseError("initialization", "Global \$db is null, creating new instance", [
            'user_id' => $user_id,
            'callback_step' => 'token_save'
        ]);
        try {
            $db = new DatabaseHelper();
        } catch (Exception $dbEx) {
            ErrorLogger::logDatabaseError("initialization", "Failed to create DatabaseHelper: " . $dbEx->getMessage(), [
                'user_id' => $user_id,
                'exception' => $dbEx->getMessage()
            ]);
            throw new Exception("Database connection failed: " . $dbEx->getMessage());
        }
    }
    
    if (!$db) {
        ErrorLogger::logDatabaseError("initialization", "Cannot create DatabaseHelper instance", [
            'user_id' => $user_id
        ]);
        throw new Exception("Database helper initialization failed");
    }
    
    // Log token details for debugging (without sensitive data)
    ErrorLogger::logSuccess("OAuth Token Retrieved", [
        'user_id' => $user_id,
        'token_type' => $token_response['token_type'] ?? 'Bearer',
        'expires_at' => $expires_at->format('Y-m-d H:i:s'),
        'scope' => $token_response['scope'] ?? ''
    ]);
    
    $token_saved = $db->saveOAuthToken(
        $user_id,
        'microsoft',
        $token_response['access_token'],
        $token_response['refresh_token'] ?? null,
        $token_response['token_type'] ?? 'Bearer',
        $expires_at->format('Y-m-d H:i:s'),
        $token_response['scope'] ?? ''
    );

    if (!$token_saved) {
        ErrorLogger::logDatabaseError("token_save", "Failed to save OAuth token", [
            'user_id' => $user_id,
            'provider' => 'microsoft'
        ]);
        
        // Diagnostic information
        try {
            $pdo = $db->getPDO();
            if ($pdo) {
                $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
                $tableCheck = $tableCheckStmt->rowCount();
                $tableCheckStmt->closeCursor(); // Close cursor to prevent query conflicts
                ErrorLogger::log("Database diagnostic", [
                    'connection_status' => 'OK',
                    'oauth_tokens_table_exists' => $tableCheck > 0 ? 'YES' : 'NO',
                    'user_id' => $user_id
                ], 'DEBUG');
                
                // If table doesn't exist, try to create it
                if ($tableCheck == 0) {
                    ErrorLogger::log("Attempting to create oauth_tokens table", [], 'INFO');
                    try {
                        $table_created = $db->createOAuthTable();
                        if ($table_created) {
                            // Retry save after successful table creation
                            ErrorLogger::log("Table created successfully, retrying token save", ['user_id' => $user_id], 'INFO');
                            $token_saved = $db->saveOAuthToken(
                                $user_id,
                                'microsoft',
                                $token_response['access_token'],
                                $token_response['refresh_token'] ?? null,
                                $token_response['token_type'] ?? 'Bearer',
                                $expires_at->format('Y-m-d H:i:s'),
                                $token_response['scope'] ?? ''
                            );
                            if ($token_saved) {
                                ErrorLogger::logSuccess("OAuth token saved after table creation", ['user_id' => $user_id]);
                            } else {
                                ErrorLogger::logDatabaseError("token_save_retry", "Token save still failed after table creation", ['user_id' => $user_id]);
                            }
                        } else {
                            ErrorLogger::logDatabaseError("table_creation", "Table creation returned false", ['user_id' => $user_id]);
                        }
                    } catch (Exception $createEx) {
                        ErrorLogger::logDatabaseError("table_creation", "Exception during table creation: " . $createEx->getMessage(), [
                            'user_id' => $user_id,
                            'exception' => $createEx->getMessage()
                        ]);
                    }
                }
            } else {
                ErrorLogger::logDatabaseError("diagnostic", "PDO connection is NULL", ['user_id' => $user_id]);
            }
        } catch (Exception $debugEx) {
            ErrorLogger::logDatabaseError("diagnostic", "Database debug error: " . $debugEx->getMessage(), [
                'user_id' => $user_id,
                'exception' => $debugEx->getMessage()
            ]);
        }
        
        if (!$token_saved) {
            throw new Exception("Failed to save token to database");
        }
    }
    
    ErrorLogger::logSuccess("OAuth token saved successfully", ['user_id' => $user_id]);

    // Test the token by making a Graph API call
    $test_success = testGraphAPIAccess($token_response['access_token']);
    
    // Initialize persistent service to ensure token is properly managed
    require_once 'persistent_teams_service.php';
    global $persistentTeamsService;
    
    if ($test_success) {
        // Verify with persistent service as well
        $persistent_status = $persistentTeamsService->getUserTeamsStatus($user_id);
        ErrorLogger::logSuccess("OAuth callback completed", [
            'user_id' => $user_id,
            'api_test' => 'success',
            'persistent_status' => $persistent_status['status']
        ]);
        
        // Redirect to account page with success message
        header('Location: account.php?success=teams_connected&persistent=true');
    } else {
        ErrorLogger::logTeamsError("api_test", "Graph API test failed after token save", [
            'user_id' => $user_id,
            'token_saved' => true
        ]);
        // Token saved but API test failed
        header('Location: account.php?warning=teams_connected_limited');
    }
    exit();

} catch (Exception $e) {
    ErrorLogger::logOAuthError("callback_exception", $e->getMessage(), [
        'user_id' => $user_id ?? 'unknown',
        'auth_code_present' => isset($auth_code),
        'exception_trace' => $e->getTraceAsString()
    ]);
    header('Location: account.php?error=connection_failed&message=' . urlencode($e->getMessage()));
    exit();
}

/**
 * Test Graph API access with the obtained token
 */
function testGraphAPIAccess($access_token) {
    try {
        // Test with a simple Graph API call
        $ch = curl_init('https://graph.microsoft.com/v1.0/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            ErrorLogger::logAPIError("Microsoft Graph", "/v1.0/me", 0, "CURL error: $curl_error");
            return false;
        }

        if ($http_code === 200) {
            ErrorLogger::logSuccess("Graph API test", ['endpoint' => '/v1.0/me', 'http_code' => $http_code]);
            return true;
        } else {
            ErrorLogger::logAPIError("Microsoft Graph", "/v1.0/me", $http_code, "API test failed", [
                'response' => $response
            ]);
            return false;
        }
    } catch (Exception $e) {
        ErrorLogger::logAPIError("Microsoft Graph", "/v1.0/me", 0, "Exception: " . $e->getMessage());
        return false;
    }
}
?>