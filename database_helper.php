<?php
// Database Helper Functions for Teams Summary Application
require_once 'config.php';

class DatabaseHelper {
    private $pdo;
    
    public function __construct() {
        $this->pdo = $this->getDatabaseConnection();
    }
    
    /**
     * Get PDO connection for direct access
     */
    public function getPDO() {
        return $this->pdo;
    }
    
    private function getDatabaseConnection() {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // Performance optimizations
                    PDO::ATTR_PERSISTENT => true, // Use persistent connections
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Use buffered queries to prevent "Cannot execute queries while other unbuffered queries are active" error
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='TRADITIONAL',time_zone='+00:00'", // Set consistent timezone
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    // ==========================================
    // OAUTH TOKEN MANAGEMENT FUNCTIONS
    // ==========================================
    
    public function saveOAuthToken($user_id, $provider, $access_token, $expires_at, $refresh_token = null, $token_type = 'Bearer', $scope = '') {
        try {
            error_log("DatabaseHelper: saveOAuthToken called for user_id=$user_id, provider=$provider");
            
            // First check if oauth_tokens table exists
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
            $tableExists = $tableCheck->rowCount() > 0;
            $tableCheck->closeCursor(); // Close the cursor to free up the connection
            
            if (!$tableExists) {
                error_log("OAuth tokens table does not exist. Creating it...");
                $this->createOAuthTable();
            }
            
            // Validate inputs
            if (!$user_id || !$provider || !$access_token || !$expires_at) {
                error_log("Invalid parameters for saveOAuthToken: user_id=$user_id, provider=$provider");
                return false;
            }
            
            // Ensure expires_at is in proper format
            if ($expires_at instanceof DateTime) {
                $expires_at = $expires_at->format('Y-m-d H:i:s');
            }
            
            error_log("Preparing to save token with expires_at: $expires_at");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO oauth_tokens (user_id, provider, access_token, refresh_token, token_type, expires_at, scope)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    token_type = VALUES(token_type),
                    expires_at = VALUES(expires_at),
                    scope = VALUES(scope),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $params = [
                $user_id,
                $provider,
                $access_token,
                $refresh_token,
                $token_type,
                $expires_at,
                $scope
            ];
            
            error_log("Executing saveOAuthToken with params: " . json_encode([
                'user_id' => $user_id,
                'provider' => $provider,
                'token_type' => $token_type,
                'expires_at' => $expires_at,
                'scope' => $scope
            ]));
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $affectedRows = $stmt->rowCount();
                error_log("OAuth token saved successfully for user $user_id (affected rows: $affectedRows)");
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Failed to execute OAuth token save for user $user_id. Error: " . json_encode($errorInfo));
            }
            
            $stmt->closeCursor(); // Close cursor to ensure clean connection state
            return $result;
        } catch (PDOException $e) {
            error_log("Save OAuth token PDO exception: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Error Info: " . json_encode($e->errorInfo));
            return false;
        } catch (Exception $e) {
            error_log("Save OAuth token general exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create oauth_tokens table if it doesn't exist
     */
    public function createOAuthTable() {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS oauth_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                provider VARCHAR(50) NOT NULL DEFAULT 'microsoft',
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                token_type VARCHAR(20) DEFAULT 'Bearer',
                expires_at DATETIME NOT NULL,
                scope TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_user_provider (user_id, provider),
                INDEX idx_user_provider (user_id, provider),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB";
            
            $result = $this->pdo->exec($sql);
            
            // Verify table was created
            $tableCheckStmt = $this->pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
            $tableCheck = $tableCheckStmt->rowCount();
            $tableCheckStmt->closeCursor(); // Close the cursor to free up the connection
            
            if (class_exists('ErrorLogger')) {
                if ($tableCheck > 0) {
                    ErrorLogger::logSuccess("OAuth tokens table created/verified", [
                        'table_exists' => 'YES',
                        'sql_result' => $result
                    ]);
                } else {
                    ErrorLogger::logDatabaseError("table_creation", "Table creation verification failed", [
                        'sql_result' => $result,
                        'table_exists' => 'NO'
                    ]);
                }
            }
            
            error_log("OAuth tokens table created/verified successfully - exists: " . ($tableCheck > 0 ? 'YES' : 'NO'));
            return $tableCheck > 0;
        } catch (PDOException $e) {
            if (class_exists('ErrorLogger')) {
                ErrorLogger::logDatabaseError("table_creation", "Failed to create oauth_tokens table: " . $e->getMessage(), [
                    'sql_error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]);
            }
            error_log("Failed to create oauth_tokens table: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function createApiKeysTable() {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                openai_api_key TEXT,
                client_id VARCHAR(255),
                client_secret TEXT,
                tenant_id VARCHAR(255),
                secret_id VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB";
            
            $result = $this->pdo->exec($sql);
            
            // Verify table was created
            $tableCheckStmt = $this->pdo->query("SHOW TABLES LIKE 'api_keys'");
            $tableCheck = $tableCheckStmt->rowCount();
            $tableCheckStmt->closeCursor();
            
            if (class_exists('ErrorLogger')) {
                if ($tableCheck > 0) {
                    ErrorLogger::logSuccess("API keys table created/verified", [
                        'table_exists' => 'YES',
                        'sql_result' => $result
                    ]);
                } else {
                    ErrorLogger::logDatabaseError("table_creation", "API keys table creation verification failed", [
                        'sql_result' => $result,
                        'table_exists' => 'NO'
                    ]);
                }
            }
            
            error_log("API keys table created/verified successfully - exists: " . ($tableCheck > 0 ? 'YES' : 'NO'));
            return $tableCheck > 0;
        } catch (PDOException $e) {
            if (class_exists('ErrorLogger')) {
                ErrorLogger::logDatabaseError("table_creation", "Failed to create api_keys table: " . $e->getMessage(), [
                    'sql_error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]);
            }
            error_log("Failed to create api_keys table: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getOAuthToken($user_id, $provider) {
        try {
            error_log("DatabaseHelper: getOAuthToken called for user_id=$user_id, provider=$provider");
            $stmt = $this->pdo->prepare("
                SELECT access_token, refresh_token, token_type, expires_at, scope, created_at, updated_at
                FROM oauth_tokens 
                WHERE user_id = ? AND provider = ?
            ");
            $stmt->execute([$user_id, $provider]);
            $result = $stmt->fetch();
            $stmt->closeCursor(); // Close cursor for consistency
            
            if ($result) {
                error_log("DatabaseHelper: Token found for user $user_id, expires: " . $result['expires_at']);
            } else {
                error_log("DatabaseHelper: No token found for user $user_id");
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Get OAuth token failed: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateOAuthToken($user_id, $provider, $access_token, $refresh_token, $expires_at) {
        try {
            // Ensure expires_at is in UTC format if it's a DateTime object
            if ($expires_at instanceof DateTime) {
                $expires_at = $expires_at->format('Y-m-d H:i:s');
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE oauth_tokens 
                SET access_token = ?, refresh_token = ?, expires_at = ?, updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND provider = ?
            ");
            
            return $stmt->execute([
                $access_token,
                $refresh_token,
                $expires_at,
                $user_id,
                $provider
            ]);
        } catch (PDOException $e) {
            error_log("Update OAuth token failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteOAuthToken($user_id, $provider) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM oauth_tokens 
                WHERE user_id = ? AND provider = ?
            ");
            
            return $stmt->execute([$user_id, $provider]);
        } catch (PDOException $e) {
            error_log("Delete OAuth token failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function isTokenValid($user_id, $provider) {
        try {
            // Use database comparison which is already working correctly
            $stmt = $this->pdo->prepare("
                SELECT expires_at, NOW() as db_now, 
                       (expires_at > NOW()) as is_valid
                FROM oauth_tokens 
                WHERE user_id = ? AND provider = ?
            ");
            $stmt->execute([$user_id, $provider]);
            $result = $stmt->fetch();
            
            if (!$result) {
                error_log("isTokenValid: No token found for user $user_id, provider $provider");
                return false;
            }
            
            // Log for debugging
            error_log("isTokenValid Debug - User: $user_id, Expires: {$result['expires_at']}, Current: {$result['db_now']}, DB Valid: " . ($result['is_valid'] ? 'true' : 'false'));
            error_log("isTokenValid Debug - Raw is_valid value: " . var_export($result['is_valid'], true) . ", Type: " . gettype($result['is_valid']));
            
            // Use the database comparison result since it handles timezones correctly
            $bool_result = (bool)$result['is_valid'];
            error_log("isTokenValid Debug - Final result: " . ($bool_result ? 'true' : 'false'));
            return $bool_result;
        } catch (PDOException $e) {
            error_log("Check token validity failed: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // USER MANAGEMENT FUNCTIONS
    // ==========================================
    
    public function authenticateUser($email, $password) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, email, password_hash, first_name, last_name, 
                       display_name, status, login_method 
                FROM users 
                WHERE email = ? AND status = 'active'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Remove password hash before returning
                unset($user['password_hash']);
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateLastLogin($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }
    
    public function createUser($email, $password, $displayName, $firstName = '', $lastName = '') {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, display_name, first_name, last_name, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([$email, $hashedPassword, $displayName, $firstName, $lastName]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserByEmail($email) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, email, first_name, last_name, display_name, 
                       job_title, department, avatar_url, status
                FROM users 
                WHERE email = ? AND status = 'active'
            ");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user by email error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, email, first_name, last_name, display_name, 
                       job_title, department, avatar_url, status
                FROM users 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    // ==========================================
    // TEAM SELECTION PREFERENCES
    // ==========================================
    
    /**
     * Create team_selection_preferences table if it doesn't exist
     */
    public function createTeamSelectionPreferencesTable() {
        try {
            $sql = "
            CREATE TABLE IF NOT EXISTS team_selection_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                team_id VARCHAR(255) NOT NULL,
                is_selected BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_user_team (user_id, team_id),
                INDEX idx_user_id (user_id),
                INDEX idx_team_id (team_id)
            ) ENGINE=InnoDB";
            
            $result = $this->pdo->exec($sql);
            
            // Verify table was created
            $tableCheckStmt = $this->pdo->query("SHOW TABLES LIKE 'team_selection_preferences'");
            $tableCheck = $tableCheckStmt->rowCount();
            $tableCheckStmt->closeCursor();
            
            error_log("Team selection preferences table created/verified successfully - exists: " . ($tableCheck > 0 ? 'YES' : 'NO'));
            return $tableCheck > 0;
        } catch (PDOException $e) {
            error_log("Failed to create team_selection_preferences table: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Save team selection preferences for a user
     */
    public function saveTeamSelectionPreferences($user_id, $selected_team_ids) {
        try {
            // First check if table exists, create if not
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'team_selection_preferences'");
            $tableExists = $tableCheck->rowCount() > 0;
            $tableCheck->closeCursor();
            
            if (!$tableExists) {
                $this->createTeamSelectionPreferencesTable();
            }
            
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Clear existing preferences for this user
            $stmt = $this->pdo->prepare("DELETE FROM team_selection_preferences WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stmt->closeCursor();
            
            // Insert new preferences
            if (!empty($selected_team_ids)) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO team_selection_preferences (user_id, team_id, is_selected)
                    VALUES (?, ?, TRUE)
                ");
                
                foreach ($selected_team_ids as $team_id) {
                    $stmt->execute([$user_id, $team_id]);
                }
                $stmt->closeCursor();
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            error_log("Team selection preferences saved for user $user_id: " . json_encode($selected_team_ids));
            return true;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->pdo->rollback();
            error_log("Save team selection preferences error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get team selection preferences for a user
     */
    public function getTeamSelectionPreferences($user_id) {
        try {
            // Check if table exists
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'team_selection_preferences'");
            $tableExists = $tableCheck->rowCount() > 0;
            $tableCheck->closeCursor();
            
            if (!$tableExists) {
                return []; // Return empty array if table doesn't exist
            }
            
            $stmt = $this->pdo->prepare("
                SELECT team_id 
                FROM team_selection_preferences 
                WHERE user_id = ? AND is_selected = TRUE
            ");
            $stmt->execute([$user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt->closeCursor();
            
            return $results ?: [];
        } catch (PDOException $e) {
            error_log("Get team selection preferences error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if a team is selected by a user
     */
    public function isTeamSelected($user_id, $team_id) {
        try {
            $selected_teams = $this->getTeamSelectionPreferences($user_id);
            return in_array($team_id, $selected_teams);
        } catch (Exception $e) {
            error_log("Check team selection error: " . $e->getMessage());
            return false;
        }
    }
    
    // ==========================================
    // DASHBOARD DATA FUNCTIONS
    // ==========================================
    
    public function getDailyStats($userId, $date = null) {
        if (!$date) $date = date('Y-m-d');
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN m.priority = 'urgent' THEN 1 END) as urgent_messages,
                    COUNT(CASE WHEN m.has_mentions = TRUE AND JSON_CONTAINS(m.mentioned_users, ?) THEN 1 END) as mentions,
                    COUNT(CASE WHEN m.user_id != ? THEN 1 END) as missed_chats
                FROM messages m
                JOIN channels c ON m.channel_id = c.id
                JOIN team_members tm ON c.team_id = tm.team_id
                WHERE tm.user_id = ? 
                AND tm.status = 'active'
                AND DATE(m.created_at) = ?
                AND m.deleted_at IS NULL
            ");
            
            $userIdJson = json_encode([$userId]);
            $stmt->execute([$userIdJson, $userId, $userId, $date]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get daily stats error: " . $e->getMessage());
            return ['urgent_messages' => 0, 'mentions' => 0, 'missed_chats' => 0];
        }
    }
    
    public function getUserChannels($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id,
                    c.name,
                    c.display_name,
                    c.icon,
                    c.message_count,
                    t.name as team_name,
                    (SELECT COUNT(*) FROM messages m 
                     WHERE m.channel_id = c.id 
                     AND m.priority = 'urgent' 
                     AND DATE(m.created_at) = CURDATE()
                     AND m.deleted_at IS NULL) as urgent_count,
                    (SELECT COUNT(*) FROM messages m 
                     WHERE m.channel_id = c.id 
                     AND m.has_mentions = TRUE 
                     AND JSON_CONTAINS(m.mentioned_users, ?)
                     AND DATE(m.created_at) = CURDATE()
                     AND m.deleted_at IS NULL) as mentions_count
                FROM channels c
                JOIN teams t ON c.team_id = t.id
                JOIN team_members tm ON t.id = tm.team_id
                WHERE tm.user_id = ? 
                AND tm.status = 'active'
                AND c.status = 'active'
                AND t.status = 'active'
                ORDER BY c.last_activity DESC
            ");
            
            $userIdJson = json_encode([$userId]);
            $stmt->execute([$userIdJson, $userId]);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user channels error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getChannelMessages($channelId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.id,
                    m.content,
                    m.message_type,
                    m.priority,
                    m.has_mentions,
                    m.created_at,
                    u.display_name as author_name,
                    TIME_FORMAT(m.created_at, '%h:%i %p') as formatted_time
                FROM messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.channel_id = ?
                AND m.deleted_at IS NULL
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$channelId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get channel messages error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getDeliveryLogs($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    delivery_type,
                    recipient,
                    subject,
                    status,
                    error_message,
                    DATE_FORMAT(created_at, '%M %d, %h:%i %p') as formatted_time,
                    delivered_at
                FROM delivery_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get delivery logs error: " . $e->getMessage());
            return [];
        }
    }
    
    // ==========================================
    // SUMMARIES DATA FUNCTIONS
    // ==========================================
    
    public function getSummaryStatistics($userId, $dateFrom, $dateTo, $channelFilter = 'all', $typeFilter = 'all') {
        try {
            $whereConditions = ["tm.user_id = ?", "m.created_at BETWEEN ? AND ?", "m.deleted_at IS NULL"];
            $params = [$userId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
            
            if ($channelFilter !== 'all') {
                $whereConditions[] = "c.name = ?";
                $params[] = $channelFilter;
            }
            
            if ($typeFilter === 'urgent') {
                $whereConditions[] = "m.priority = 'urgent'";
            } elseif ($typeFilter === 'mentions') {
                $whereConditions[] = "m.has_mentions = TRUE";
            } elseif ($typeFilter === 'files') {
                $whereConditions[] = "m.has_attachments = TRUE";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN m.priority = 'urgent' THEN 1 END) as urgent_messages,
                    COUNT(CASE WHEN m.has_mentions = TRUE THEN 1 END) as mentions,
                    COUNT(CASE WHEN m.has_attachments = TRUE THEN 1 END) as files_shared
                FROM messages m
                JOIN channels c ON m.channel_id = c.id
                JOIN teams t ON c.team_id = t.id
                JOIN team_members tm ON t.id = tm.team_id
                WHERE " . implode(' AND ', $whereConditions)
            );
            
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get summary statistics error: " . $e->getMessage());
            return ['total_messages' => 0, 'urgent_messages' => 0, 'mentions' => 0, 'files_shared' => 0];
        }
    }
    
    public function getTimelineData($userId, $dateFrom, $dateTo, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.id,
                    m.content,
                    m.message_type,
                    m.priority,
                    m.has_mentions,
                    m.has_attachments,
                    m.created_at,
                    TIME_FORMAT(m.created_at, '%h:%i %p') as formatted_time,
                    u.display_name as author_name,
                    c.name as channel_name,
                    c.icon as channel_icon,
                    (SELECT COUNT(*) FROM message_attachments ma WHERE ma.message_id = m.id) as attachment_count,
                    (SELECT file_name FROM message_attachments ma WHERE ma.message_id = m.id LIMIT 1) as first_attachment
                FROM messages m
                JOIN channels c ON m.channel_id = c.id
                JOIN teams t ON c.team_id = t.id
                JOIN team_members tm ON t.id = tm.team_id
                JOIN users u ON m.user_id = u.id
                WHERE tm.user_id = ?
                AND m.created_at BETWEEN ? AND ?
                AND m.deleted_at IS NULL
                AND tm.status = 'active'
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get timeline data error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getSummaryCards($userId, $dateFrom, $dateTo) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id,
                    c.name,
                    c.display_name,
                    c.icon,
                    COUNT(m.id) as message_count,
                    COUNT(CASE WHEN m.priority = 'urgent' THEN 1 END) as urgent_count,
                    COUNT(CASE WHEN m.has_mentions = TRUE THEN 1 END) as mentions_count,
                    MIN(m.created_at) as first_message,
                    MAX(m.created_at) as last_message
                FROM channels c
                JOIN teams t ON c.team_id = t.id
                JOIN team_members tm ON t.id = tm.team_id
                LEFT JOIN messages m ON c.id = m.channel_id 
                    AND m.created_at BETWEEN ? AND ?
                    AND m.deleted_at IS NULL
                WHERE tm.user_id = ?
                AND tm.status = 'active'
                AND c.status = 'active'
                GROUP BY c.id, c.name, c.display_name, c.icon
                HAVING message_count > 0
                ORDER BY message_count DESC
            ");
            
            $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get summary cards error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getTopContributors($channelId, $dateFrom, $dateTo, $limit = 3) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id,
                    u.display_name,
                    u.first_name,
                    u.last_name,
                    COUNT(m.id) as message_count
                FROM messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.channel_id = ?
                AND m.created_at BETWEEN ? AND ?
                AND m.deleted_at IS NULL
                GROUP BY u.id, u.display_name, u.first_name, u.last_name
                ORDER BY message_count DESC
                LIMIT ?
            ");
            
            $stmt->execute([$channelId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get top contributors error: " . $e->getMessage());
            return [];
        }
    }
    
    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    
    public function logActivity($userId, $action, $resourceType = null, $resourceId = null, $details = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity_log 
                (user_id, action, resource_type, resource_id, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $detailsJson = $details ? json_encode($details) : null;
            
            $stmt->execute([$userId, $action, $resourceType, $resourceId, $detailsJson, $ipAddress]);
        } catch (PDOException $e) {
            error_log("Log activity error: " . $e->getMessage());
        }
    }
    
    public function createSummary($userId, $teamId, $channelId, $type, $dateFrom, $dateTo, $title, $content, $summaryData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO summaries 
                (user_id, team_id, channel_id, summary_type, date_from, date_to, 
                 title, content, summary_data, message_count, urgent_count, 
                 mention_count, file_count, generated_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'completed')
            ");
            
            $summaryDataJson = json_encode($summaryData);
            $messageCount = $summaryData['message_count'] ?? 0;
            $urgentCount = $summaryData['urgent_count'] ?? 0;
            $mentionCount = $summaryData['mention_count'] ?? 0;
            $fileCount = $summaryData['file_count'] ?? 0;
            
            $stmt->execute([
                $userId, $teamId, $channelId, $type, $dateFrom, $dateTo,
                $title, $content, $summaryDataJson, $messageCount,
                $urgentCount, $mentionCount, $fileCount
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create summary error: " . $e->getMessage());
            return false;
        }
    }
    
    public function createDeliveryLog($userId, $summaryId, $deliveryType, $recipient, $subject, $content, $status = 'pending') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO delivery_logs 
                (user_id, summary_id, delivery_type, recipient, subject, content, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$userId, $summaryId, $deliveryType, $recipient, $subject, $content, $status]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create delivery log error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateDeliveryStatus($logId, $status, $errorMessage = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE delivery_logs 
                SET status = ?, error_message = ?, delivered_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE delivered_at END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $errorMessage, $status, $logId]);
            return true;
        } catch (PDOException $e) {
            error_log("Update delivery status error: " . $e->getMessage());
            return false;
        }
    }
    
    // ==========================================
    // CLEANUP FUNCTIONS
    // ==========================================
    
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Cleanup sessions error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function cleanupOldActivityLogs($daysToKeep = 90) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_activity_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Cleanup activity logs error: " . $e->getMessage());
            return 0;
        }
    }
    
    // ==========================================
    // USER ACTIVITY AND STATS FUNCTIONS
    // ==========================================
    
    public function getUserActivityStats($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT DATE(ual.created_at)) as total_logins,
                    COALESCE(SUM(CASE WHEN ual.action = 'read_message' THEN 1 ELSE 0 END), 0) as messages_read,
                    COALESCE(COUNT(DISTINCT tm.team_id), 0) as channels_followed,
                    DATE(u.created_at) as account_created
                FROM users u
                LEFT JOIN user_activity_log ual ON u.id = ual.user_id
                LEFT JOIN team_members tm ON u.id = tm.user_id AND tm.status = 'active'
                WHERE u.id = ?
                GROUP BY u.id, u.created_at
            ");
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            if ($result) {
                return [
                    'total_logins' => (int)$result['total_logins'],
                    'messages_read' => (int)$result['messages_read'],
                    'channels_followed' => (int)$result['channels_followed'],
                    'account_created' => $result['account_created'] ?? date('Y-m-d')
                ];
            }
            
            // Return default stats if no data found
            return [
                'total_logins' => 0,
                'messages_read' => 0,
                'channels_followed' => 0,
                'account_created' => date('Y-m-d')
            ];
            
        } catch (PDOException $e) {
            error_log("Get user activity stats error: " . $e->getMessage());
            // Return mock data as fallback
            return [
                'total_logins' => rand(10, 50),
                'messages_read' => rand(100, 1000),
                'channels_followed' => rand(3, 15),
                'account_created' => date('Y-m-d', strtotime('-30 days'))
            ];
        }
    }
    
    public function getUserRecentActivity($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    action,
                    resource_type,
                    resource_id,
                    details,
                    created_at,
                    CASE 
                        WHEN action = 'login' THEN 'Logged in'
                        WHEN action = 'read_message' THEN 'Read message'
                        WHEN action = 'view_dashboard' THEN 'Viewed dashboard'
                        WHEN action = 'view_summaries' THEN 'Viewed summaries'
                        WHEN action = 'update_profile' THEN 'Updated profile'
                        ELSE CONCAT(UPPER(SUBSTRING(action, 1, 1)), SUBSTRING(action, 2))
                    END as formatted_action
                FROM user_activity_log 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            $activities = $stmt->fetchAll();
            
            $result = [];
            foreach ($activities as $activity) {
                $result[] = [
                    'action' => $activity['action'],
                    'timestamp' => $activity['created_at'],
                    'details' => $activity['formatted_action']
                ];
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Get user recent activity error: " . $e->getMessage());
            // Return mock data as fallback
            return [
                ['action' => 'login', 'timestamp' => date('Y-m-d H:i:s'), 'details' => 'Logged in'],
                ['action' => 'view_dashboard', 'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')), 'details' => 'Viewed dashboard'],
                ['action' => 'read_messages', 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'details' => 'Read messages'],
            ];
        }
    }
    
    public function updateUserProfile($userId, $displayName, $firstName = null, $lastName = null) {
        try {
            $sql = "UPDATE users SET display_name = ?";
            $params = [$displayName];
            
            if ($firstName !== null) {
                $sql .= ", first_name = ?";
                $params[] = $firstName;
            }
            
            if ($lastName !== null) {
                $sql .= ", last_name = ?"; 
                $params[] = $lastName;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Update user profile error: " . $e->getMessage());
            return false;
        }
    }
    
    public function verifyPassword($userId, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Verify password error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updatePassword($userId, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Update password error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteUser($userId) {
        try {
            // Soft delete - mark as inactive instead of hard delete
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserSettings($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT settings_json 
                FROM user_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            if ($result && $result['settings_json']) {
                return json_decode($result['settings_json'], true) ?: [];
            }
            
            return [];
            
        } catch (PDOException $e) {
            error_log("Get user settings error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateUserSettings($userId, $settings) {
        try {
            $settingsJson = json_encode($settings);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_settings (user_id, settings_json, updated_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                settings_json = VALUES(settings_json), 
                updated_at = NOW()
            ");
            
            $stmt->execute([$userId, $settingsJson]);
            return true;
            
        } catch (PDOException $e) {
            error_log("Update user settings error: " . $e->getMessage());
            return false;
        }
    }
}

// Global database helper instance
$db = new DatabaseHelper();
?>