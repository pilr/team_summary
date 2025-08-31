-- Teams Summary Application Database Schema
-- Run this in phpMyAdmin or MySQL command line to create all necessary tables
-- Database already exists: u175828155_team_summary

USE u175828155_team_summary;

-- =====================================================
-- 1. USERS TABLE - User authentication and profiles
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    display_name VARCHAR(200) GENERATED ALWAYS AS (CONCAT(first_name, ' ', last_name)) STORED,
    avatar_url VARCHAR(500) NULL,
    job_title VARCHAR(150) NULL,
    department VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    timezone VARCHAR(50) DEFAULT 'America/New_York',
    language VARCHAR(10) DEFAULT 'en',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    last_login DATETIME NULL,
    login_method ENUM('email', 'microsoft', 'google', 'sso') DEFAULT 'email',
    notification_preferences JSON NULL, -- Store email/push notification settings
    theme_preferences JSON NULL, -- Store dark/light mode, layout preferences
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_last_login (last_login),
    INDEX idx_department (department)
) ENGINE=InnoDB;

-- =====================================================
-- 2. USER SESSIONS - Track login sessions
-- =====================================================
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    remember_me BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- =====================================================
-- 3. TEAMS TABLE - Team/organization information
-- =====================================================
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) DEFAULT 'users',
    color VARCHAR(7) DEFAULT '#6366f1', -- Hex color code
    team_type ENUM('public', 'private', 'org-wide') DEFAULT 'public',
    created_by INT NOT NULL,
    status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
    settings JSON NULL, -- Team-specific settings
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_team_type (team_type)
) ENGINE=InnoDB;

-- =====================================================
-- 4. TEAM MEMBERS - User membership in teams
-- =====================================================
CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member', 'guest') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NULL,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_team_user (team_id, user_id),
    INDEX idx_team_id (team_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- =====================================================
-- 5. CHANNELS TABLE - Communication channels within teams
-- =====================================================
CREATE TABLE channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    display_name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    channel_type ENUM('standard', 'private', 'shared', 'announcement') DEFAULT 'standard',
    icon VARCHAR(50) DEFAULT 'hashtag',
    created_by INT NOT NULL,
    status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
    settings JSON NULL, -- Channel-specific settings like notifications
    last_activity TIMESTAMP NULL,
    message_count INT DEFAULT 0,
    member_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    UNIQUE KEY unique_team_channel (team_id, name),
    INDEX idx_team_id (team_id),
    INDEX idx_channel_type (channel_type),
    INDEX idx_status (status),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- =====================================================
-- 6. MESSAGES TABLE - All messages/communications
-- =====================================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT NULL, -- For replies/threads
    message_type ENUM('text', 'file', 'system', 'meeting', 'announcement') DEFAULT 'text',
    content TEXT NOT NULL,
    formatted_content TEXT NULL, -- HTML formatted content
    priority ENUM('normal', 'important', 'urgent') DEFAULT 'normal',
    has_mentions BOOLEAN DEFAULT FALSE,
    mentioned_users JSON NULL, -- Array of user IDs mentioned
    has_attachments BOOLEAN DEFAULT FALSE,
    attachment_count INT DEFAULT 0,
    reactions JSON NULL, -- Store reactions like thumbs up, heart, etc.
    edited_at TIMESTAMP NULL,
    edited_by INT NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_channel_id (channel_id),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_created_at (created_at),
    INDEX idx_priority (priority),
    INDEX idx_message_type (message_type),
    INDEX idx_has_mentions (has_mentions),
    INDEX idx_deleted_at (deleted_at),
    FULLTEXT idx_content (content, formatted_content)
) ENGINE=InnoDB;

-- =====================================================
-- 7. MESSAGE ATTACHMENTS - File attachments to messages
-- =====================================================
CREATE TABLE message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL, -- Size in bytes
    file_type VARCHAR(100) NOT NULL, -- MIME type
    file_extension VARCHAR(10) NOT NULL,
    uploaded_by INT NOT NULL,
    is_image BOOLEAN DEFAULT FALSE,
    thumbnail_path VARCHAR(500) NULL,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_message_id (message_id),
    INDEX idx_file_type (file_type),
    INDEX idx_is_image (is_image)
) ENGINE=InnoDB;

-- =====================================================
-- 8. SUMMARIES TABLE - Generated activity summaries
-- =====================================================
CREATE TABLE summaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_id INT NULL, -- NULL for cross-team summaries
    channel_id INT NULL, -- NULL for team-wide summaries
    summary_type ENUM('daily', 'weekly', 'monthly', 'custom') DEFAULT 'daily',
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL, -- Generated summary content
    summary_data JSON NOT NULL, -- Structured summary data (stats, highlights, etc.)
    message_count INT DEFAULT 0,
    urgent_count INT DEFAULT 0,
    mention_count INT DEFAULT 0,
    file_count INT DEFAULT 0,
    participant_count INT DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('generating', 'completed', 'failed') DEFAULT 'completed',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_team_id (team_id),
    INDEX idx_channel_id (channel_id),
    INDEX idx_date_range (date_from, date_to),
    INDEX idx_summary_type (summary_type),
    INDEX idx_generated_at (generated_at)
) ENGINE=InnoDB;

-- =====================================================
-- 9. DELIVERY LOGS - Track notification deliveries
-- =====================================================
CREATE TABLE delivery_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    summary_id INT NULL, -- Related summary if applicable
    delivery_type ENUM('email', 'teams_webhook', 'slack_webhook', 'push') NOT NULL,
    recipient VARCHAR(255) NOT NULL, -- Email address, webhook URL, etc.
    subject VARCHAR(255) NULL,
    content TEXT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed', 'bounced') DEFAULT 'pending',
    error_message TEXT NULL,
    retry_count INT DEFAULT 0,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (summary_id) REFERENCES summaries(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_summary_id (summary_id),
    INDEX idx_delivery_type (delivery_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_retry_count (retry_count)
) ENGINE=InnoDB;

-- =====================================================
-- 10. USER ACTIVITY LOG - Track user actions
-- =====================================================
CREATE TABLE user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) NULL, -- team, channel, message, summary
    resource_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- 11. NOTIFICATION PREFERENCES - User notification settings
-- =====================================================
CREATE TABLE notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    channel_id INT NULL, -- NULL for global preferences
    notification_type ENUM('email', 'push', 'sms') NOT NULL,
    event_type ENUM('mentions', 'urgent', 'daily_summary', 'weekly_summary', 'all_messages') NOT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    schedule_time TIME NULL, -- For scheduled notifications
    days_of_week SET('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_channel_notification (user_id, channel_id, notification_type, event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB;

-- =====================================================
-- 12. API TOKENS - For external integrations
-- =====================================================
CREATE TABLE api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_name VARCHAR(100) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    permissions JSON NULL, -- Array of allowed permissions
    last_used TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'revoked', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- =====================================================
-- TRIGGERS FOR MAINTAINING COUNTS
-- =====================================================

-- Update channel message count when messages are added
DELIMITER //
CREATE TRIGGER update_channel_message_count_insert
    AFTER INSERT ON messages
    FOR EACH ROW
BEGIN
    UPDATE channels 
    SET message_count = message_count + 1,
        last_activity = NEW.created_at
    WHERE id = NEW.channel_id;
END//
DELIMITER ;

-- Update channel message count when messages are deleted
DELIMITER //
CREATE TRIGGER update_channel_message_count_delete
    AFTER UPDATE ON messages
    FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN
        UPDATE channels 
        SET message_count = message_count - 1
        WHERE id = NEW.channel_id;
    END IF;
END//
DELIMITER ;

-- Update attachment count when attachments are added
DELIMITER //
CREATE TRIGGER update_message_attachment_count_insert
    AFTER INSERT ON message_attachments
    FOR EACH ROW
BEGIN
    UPDATE messages 
    SET has_attachments = TRUE,
        attachment_count = attachment_count + 1
    WHERE id = NEW.message_id;
END//
DELIMITER ;

-- Update attachment count when attachments are removed
DELIMITER //
CREATE TRIGGER update_message_attachment_count_delete
    AFTER DELETE ON message_attachments
    FOR EACH ROW
BEGIN
    UPDATE messages 
    SET attachment_count = attachment_count - 1
    WHERE id = OLD.message_id;
    
    UPDATE messages 
    SET has_attachments = FALSE
    WHERE id = OLD.message_id AND attachment_count = 0;
END//
DELIMITER ;

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- Active users with their team memberships
CREATE VIEW active_users_with_teams AS
SELECT 
    u.id,
    u.email,
    u.display_name,
    u.department,
    u.last_login,
    COUNT(tm.team_id) as team_count
FROM users u
LEFT JOIN team_members tm ON u.id = tm.user_id AND tm.status = 'active'
WHERE u.status = 'active'
GROUP BY u.id;

-- Channel activity summary
CREATE VIEW channel_activity_summary AS
SELECT 
    c.id,
    c.team_id,
    c.name,
    c.display_name,
    c.message_count,
    COUNT(CASE WHEN m.priority = 'urgent' AND m.deleted_at IS NULL THEN 1 END) as urgent_count,
    COUNT(CASE WHEN m.has_mentions = TRUE AND m.deleted_at IS NULL THEN 1 END) as mention_count,
    COUNT(CASE WHEN m.has_attachments = TRUE AND m.deleted_at IS NULL THEN 1 END) as file_count,
    MAX(m.created_at) as last_message_at
FROM channels c
LEFT JOIN messages m ON c.id = m.channel_id
WHERE c.status = 'active'
GROUP BY c.id;

-- Recent activity for dashboard
CREATE VIEW recent_activity AS
SELECT 
    m.id,
    m.channel_id,
    c.name as channel_name,
    c.icon as channel_icon,
    m.user_id,
    u.display_name as user_name,
    m.content,
    m.message_type,
    m.priority,
    m.has_mentions,
    m.has_attachments,
    m.created_at,
    t.name as team_name
FROM messages m
JOIN channels c ON m.channel_id = c.id
JOIN users u ON m.user_id = u.id
JOIN teams t ON c.team_id = t.id
WHERE m.deleted_at IS NULL
AND c.status = 'active'
AND t.status = 'active'
ORDER BY m.created_at DESC;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Composite indexes for common queries
CREATE INDEX idx_messages_channel_priority ON messages(channel_id, priority, created_at);
CREATE INDEX idx_messages_user_date ON messages(user_id, created_at);
CREATE INDEX idx_summaries_user_date ON summaries(user_id, date_from, date_to);
CREATE INDEX idx_delivery_logs_status_type ON delivery_logs(status, delivery_type);
CREATE INDEX idx_team_members_active ON team_members(team_id, status, role);

-- =====================================================
-- COMMENTS AND DOCUMENTATION
-- =====================================================

-- Add table comments
ALTER TABLE users COMMENT = 'User accounts and authentication information';
ALTER TABLE teams COMMENT = 'Teams/organizations within the system';
ALTER TABLE channels COMMENT = 'Communication channels within teams';
ALTER TABLE messages COMMENT = 'All messages and communications';
ALTER TABLE summaries COMMENT = 'Generated activity summaries and reports';
ALTER TABLE delivery_logs COMMENT = 'Notification delivery tracking';