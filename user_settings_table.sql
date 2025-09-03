-- User Settings Table for Team Summary Application
-- This table stores user preferences and custom settings as JSON

USE u175828155_team_summary;

-- Create user_settings table if it doesn't exist
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    settings_json TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB COMMENT='User settings and preferences stored as JSON';

-- Example settings JSON structure:
-- {
--   "email_notifications": true,
--   "urgent_alerts": true,
--   "mention_notifications": true,
--   "daily_digest": true,
--   "weekly_summary": true,
--   "notification_frequency": "immediate",
--   "digest_time": "08:00",
--   "theme": "light",
--   "timezone": "America/New_York",
--   "language": "en",
--   "compact_view": false,
--   "show_read_messages": true,
--   "auto_mark_read": false,
--   "ai_summary_prompt": "Please analyze these Microsoft Teams messages..."
-- }