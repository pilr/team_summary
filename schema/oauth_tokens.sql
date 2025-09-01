-- Microsoft OAuth2 tokens storage schema
-- This table stores user-specific Microsoft Graph API tokens for accessing Teams data

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
    
    -- Foreign key constraint (assuming users table exists)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Unique constraint to prevent duplicate tokens per user/provider
    UNIQUE KEY unique_user_provider (user_id, provider),
    
    -- Index for faster lookups
    INDEX idx_user_provider (user_id, provider),
    INDEX idx_expires_at (expires_at)
);

-- Add stored procedure to clean up expired tokens
DELIMITER //
CREATE PROCEDURE CleanupExpiredTokens()
BEGIN
    DELETE FROM oauth_tokens 
    WHERE expires_at < NOW() AND refresh_token IS NULL;
    
    -- Log cleanup
    SELECT ROW_COUNT() as tokens_cleaned, NOW() as cleanup_time;
END //
DELIMITER ;

-- Add event scheduler to run cleanup daily (optional)
-- CREATE EVENT IF NOT EXISTS cleanup_expired_tokens
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO CALL CleanupExpiredTokens();