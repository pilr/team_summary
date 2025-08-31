<?php
// Database Helper Functions for Teams Summary Application
require_once 'config.php';

class DatabaseHelper {
    private $pdo;
    
    public function __construct() {
        $this->pdo = $this->getDatabaseConnection();
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
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
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
}

// Global database helper instance
$db = new DatabaseHelper();
?>