-- Sample Data for Teams Summary Application
-- Run this AFTER creating the database schema to populate with test data
-- Using existing database: u175828155_team_summary

USE u175828155_team_summary;

-- =====================================================
-- 1. INSERT SAMPLE USERS
-- =====================================================

-- Note: In production, passwords should be properly hashed using PHP's password_hash()
-- These are demo passwords hashed with PASSWORD() function for testing
INSERT INTO users (email, password_hash, first_name, last_name, job_title, department, phone, status, email_verified, login_method, notification_preferences, theme_preferences) VALUES
('demo@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', 'Senior Developer', 'Engineering', '+1-555-0101', 'active', TRUE, 'email', '{"email": true, "push": true, "sms": false}', '{"theme": "light", "sidebar": "expanded"}'),
('sarah.johnson@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Johnson', 'Project Manager', 'Project Management', '+1-555-0102', 'active', TRUE, 'microsoft', '{"email": true, "push": true, "sms": false}', '{"theme": "light", "sidebar": "collapsed"}'),
('mike.chen@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Chen', 'UI/UX Designer', 'Design', '+1-555-0103', 'active', TRUE, 'google', '{"email": true, "push": false, "sms": false}', '{"theme": "dark", "sidebar": "expanded"}'),
('lisa.wang@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa', 'Wang', 'Marketing Director', 'Marketing', '+1-555-0104', 'active', TRUE, 'email', '{"email": true, "push": true, "sms": true}', '{"theme": "light", "sidebar": "expanded"}'),
('alex.rodriguez@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alex', 'Rodriguez', 'DevOps Engineer', 'Engineering', '+1-555-0105', 'active', TRUE, 'email', '{"email": true, "push": true, "sms": false}', '{"theme": "dark", "sidebar": "collapsed"}'),
('emma.wilson@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emma', 'Wilson', 'Backend Developer', 'Engineering', '+1-555-0106', 'active', TRUE, 'microsoft', '{"email": true, "push": false, "sms": false}', '{"theme": "light", "sidebar": "expanded"}'),
('sophie.davis@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sophie', 'Davis', 'Graphic Designer', 'Design', '+1-555-0107', 'active', TRUE, 'google', '{"email": true, "push": true, "sms": false}', '{"theme": "light", "sidebar": "expanded"}'),
('tom.wilson@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom', 'Wilson', 'QA Engineer', 'Engineering', '+1-555-0108', 'active', TRUE, 'email', '{"email": true, "push": true, "sms": false}', '{"theme": "dark", "sidebar": "expanded"}');

-- =====================================================
-- 2. INSERT SAMPLE TEAMS
-- =====================================================

INSERT INTO teams (name, description, icon, color, team_type, created_by, status, settings) VALUES
('Engineering Team', 'Development and technical discussions', 'code', '#6366f1', 'public', 1, 'active', '{"allow_guests": false, "require_approval": false}'),
('Design Team', 'UI/UX design collaboration and reviews', 'paint-brush', '#8b5cf6', 'public', 3, 'active', '{"allow_guests": true, "require_approval": true}'),
('Marketing Team', 'Marketing campaigns and strategy', 'bullhorn', '#10b981', 'public', 4, 'active', '{"allow_guests": true, "require_approval": false}'),
('Project Management', 'Project coordination and updates', 'tasks', '#f59e0b', 'public', 2, 'active', '{"allow_guests": false, "require_approval": true}'),
('Company Wide', 'General company announcements', 'building', '#ef4444', 'org-wide', 1, 'active', '{"allow_guests": false, "require_approval": false}');

-- =====================================================
-- 3. INSERT TEAM MEMBERS
-- =====================================================

-- Engineering Team members
INSERT INTO team_members (team_id, user_id, role, added_by, status) VALUES
(1, 1, 'owner', NULL, 'active'),
(1, 5, 'admin', 1, 'active'),
(1, 6, 'member', 1, 'active'),
(1, 8, 'member', 1, 'active'),
(1, 2, 'member', 1, 'active'); -- Project manager in engineering

-- Design Team members
INSERT INTO team_members (team_id, user_id, role, added_by, status) VALUES
(2, 3, 'owner', NULL, 'active'),
(2, 7, 'member', 3, 'active'),
(2, 2, 'member', 3, 'active'); -- Project manager in design

-- Marketing Team members
INSERT INTO team_members (team_id, user_id, role, added_by, status) VALUES
(3, 4, 'owner', NULL, 'active'),
(3, 2, 'member', 4, 'active'); -- Project manager in marketing

-- Project Management Team
INSERT INTO team_members (team_id, user_id, role, added_by, status) VALUES
(4, 2, 'owner', NULL, 'active'),
(4, 1, 'member', 2, 'active'),
(4, 3, 'member', 2, 'active'),
(4, 4, 'member', 2, 'active');

-- Company Wide (everyone)
INSERT INTO team_members (team_id, user_id, role, added_by, status) VALUES
(5, 1, 'admin', NULL, 'active'),
(5, 2, 'member', 1, 'active'),
(5, 3, 'member', 1, 'active'),
(5, 4, 'member', 1, 'active'),
(5, 5, 'member', 1, 'active'),
(5, 6, 'member', 1, 'active'),
(5, 7, 'member', 1, 'active'),
(5, 8, 'member', 1, 'active');

-- =====================================================
-- 4. INSERT SAMPLE CHANNELS
-- =====================================================

-- Engineering Team Channels
INSERT INTO channels (team_id, name, display_name, description, channel_type, icon, created_by, status, settings) VALUES
(1, 'general', 'General', 'General engineering discussions', 'standard', 'hashtag', 1, 'active', '{"notifications": "all"}'),
(1, 'development', 'Development', 'Code reviews and development updates', 'standard', 'code', 1, 'active', '{"notifications": "mentions"}'),
(1, 'devops', 'DevOps', 'Deployment and infrastructure discussions', 'standard', 'server', 5, 'active', '{"notifications": "all"}'),
(1, 'bugs', 'Bug Reports', 'Bug tracking and resolution', 'standard', 'bug', 8, 'active', '{"notifications": "urgent"}');

-- Design Team Channels
INSERT INTO channels (team_id, name, display_name, description, channel_type, icon, created_by, status, settings) VALUES
(2, 'general', 'General', 'General design discussions', 'standard', 'hashtag', 3, 'active', '{"notifications": "all"}'),
(2, 'ui-reviews', 'UI Reviews', 'User interface design reviews', 'standard', 'eye', 3, 'active', '{"notifications": "mentions"}'),
(2, 'resources', 'Resources', 'Design resources and inspiration', 'standard', 'images', 7, 'active', '{"notifications": "none"}');

-- Marketing Team Channels
INSERT INTO channels (team_id, name, display_name, description, channel_type, icon, created_by, status, settings) VALUES
(3, 'general', 'General', 'General marketing discussions', 'standard', 'hashtag', 4, 'active', '{"notifications": "all"}'),
(3, 'campaigns', 'Campaigns', 'Marketing campaign planning and execution', 'standard', 'bullhorn', 4, 'active', '{"notifications": "urgent"}'),
(3, 'analytics', 'Analytics', 'Performance metrics and analysis', 'standard', 'chart-line', 4, 'active', '{"notifications": "mentions"}');

-- Project Management Channels
INSERT INTO channels (team_id, name, display_name, description, channel_type, icon, created_by, status, settings) VALUES
(4, 'general', 'General', 'Project coordination', 'standard', 'hashtag', 2, 'active', '{"notifications": "all"}'),
(4, 'deadlines', 'Deadlines', 'Important deadlines and milestones', 'announcement', 'calendar', 2, 'active', '{"notifications": "urgent"}'),
(4, 'updates', 'Updates', 'Project status updates', 'standard', 'info-circle', 2, 'active', '{"notifications": "all"}');

-- Company Wide Channels
INSERT INTO channels (team_id, name, display_name, description, channel_type, icon, created_by, status, settings) VALUES
(5, 'announcements', 'Announcements', 'Company-wide announcements', 'announcement', 'bullhorn', 1, 'active', '{"notifications": "urgent"}'),
(5, 'general', 'General', 'General company discussions', 'standard', 'hashtag', 1, 'active', '{"notifications": "mentions"}'),
(5, 'random', 'Random', 'Off-topic and casual conversations', 'standard', 'coffee', 1, 'active', '{"notifications": "none"}');

-- =====================================================
-- 5. INSERT SAMPLE MESSAGES
-- =====================================================

-- Recent messages for today (adjust dates as needed)
-- Engineering General Channel Messages
INSERT INTO messages (channel_id, user_id, message_type, content, priority, has_mentions, mentioned_users, created_at) VALUES
(1, 2, 'announcement', 'Project deadline moved to Friday. Need immediate feedback on the proposal from everyone.', 'urgent', TRUE, '[1, 5, 6, 8]', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(1, 1, 'text', 'Thanks for the update Sarah. I''ll review the proposal this afternoon.', 'normal', TRUE, '[2]', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(1, 5, 'text', 'I can push the deployment to accommodate the new timeline.', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 6, 'text', 'The new API endpoints are ready for testing.', 'normal', TRUE, '[1, 8]', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1, 8, 'text', 'Great! I''ll start testing them right away.', 'normal', TRUE, '[6]', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Development Channel Messages
INSERT INTO messages (channel_id, user_id, message_type, content, priority, has_mentions, mentioned_users, created_at) VALUES
(2, 5, 'system', 'Production deployment failed. Rolling back to previous version immediately.', 'urgent', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(2, 1, 'text', 'What was the cause of the failure?', 'important', TRUE, '[5]', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
(2, 5, 'text', 'Database connection timeout. Investigating the root cause.', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(2, 6, 'text', 'I can help with the database investigation if needed.', 'normal', TRUE, '[5]', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(2, 5, 'text', 'Thanks Emma! The issue has been resolved. Ready to redeploy.', 'normal', TRUE, '[6]', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Design General Channel Messages
INSERT INTO messages (channel_id, user_id, message_type, content, priority, has_mentions, mentioned_users, created_at) VALUES
(5, 3, 'file', 'Updated mockups for the new feature. Please review and provide feedback.', 'normal', TRUE, '[7, 2]', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(5, 7, 'text', 'The new design looks great! Love the color scheme.', 'normal', TRUE, '[3]', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(5, 2, 'text', 'From a PM perspective, this aligns well with user requirements.', 'normal', TRUE, '[3]', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 3, 'text', 'Thanks for the feedback everyone!', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Marketing Channel Messages
INSERT INTO messages (channel_id, user_id, message_type, content, priority, has_mentions, mentioned_users, created_at) VALUES
(8, 4, 'text', 'Q4 campaign performance metrics are looking great! We exceeded our targets by 15%.', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(8, 2, 'text', 'Excellent work Lisa! This will definitely help with our year-end goals.', 'normal', TRUE, '[4]', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Company Announcements
INSERT INTO messages (channel_id, user_id, message_type, content, priority, has_mentions, mentioned_users, created_at) VALUES
(13, 1, 'announcement', 'Security policy updates require team acknowledgment by end of week.', 'urgent', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(13, 1, 'announcement', 'New client onboarding process has been approved and will go live next Monday.', 'important', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 10 HOUR));

-- Company General Messages
INSERT INTO messages (channel_id, user_id, message_type, content, priority, has_mentions, mentioned_users, created_at) VALUES
(14, 4, 'text', 'Great work on the presentation today, team!', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(14, 2, 'text', 'Weekly team standup completed. Action items assigned to team members.', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(14, 3, 'text', 'Thanks everyone for the collaborative effort this week!', 'normal', FALSE, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- =====================================================
-- 6. INSERT SAMPLE MESSAGE ATTACHMENTS
-- =====================================================

INSERT INTO message_attachments (message_id, file_name, original_name, file_path, file_size, file_type, file_extension, uploaded_by, is_image, thumbnail_path) VALUES
(10, 'ui_mockups_v2_3.fig', 'UI_Mockups_v2.3.fig', '/uploads/2024/ui_mockups_v2_3.fig', 2048576, 'application/octet-stream', 'fig', 3, FALSE, NULL),
(10, 'design_specs.pdf', 'Design_Specifications.pdf', '/uploads/2024/design_specs.pdf', 1024768, 'application/pdf', 'pdf', 3, FALSE, NULL);

-- Update the message to reflect attachments
UPDATE messages SET has_attachments = TRUE, attachment_count = 2 WHERE id = 10;

-- =====================================================
-- 7. INSERT SAMPLE SUMMARIES
-- =====================================================

INSERT INTO summaries (user_id, team_id, channel_id, summary_type, date_from, date_to, title, content, summary_data, message_count, urgent_count, mention_count, file_count, participant_count, status) VALUES
(1, NULL, NULL, 'daily', CURDATE(), CURDATE(), 'Daily Activity Summary', 
'Today you had significant activity across multiple teams. Key highlights include urgent messages about project deadlines and production issues that were successfully resolved.', 
'{"highlights": ["Project deadline moved to Friday", "Production deployment issue resolved", "New API endpoints ready for testing"], "top_channels": [{"name": "Engineering General", "messages": 5}, {"name": "Development", "messages": 5}], "top_contributors": [{"name": "Sarah Johnson", "messages": 2}, {"name": "Alex Rodriguez", "messages": 3}]}',
12, 3, 8, 2, 6, 'completed'),

(2, 1, NULL, 'weekly', DATE_SUB(CURDATE(), INTERVAL 6 DAY), CURDATE(), 'Engineering Team Weekly Summary',
'This week the engineering team focused on resolving critical deployment issues and preparing for the upcoming project deadline. Good collaboration across all team members.',
'{"highlights": ["Deployment issues resolved", "API development completed", "Testing phase initiated"], "metrics": {"response_time": "2.5 hours", "resolution_rate": "95%"}}',
25, 2, 12, 3, 5, 'completed'),

(3, 2, NULL, 'daily', CURDATE(), CURDATE(), 'Design Team Daily Summary',
'Design team had productive discussions around new feature mockups with positive feedback from stakeholders.',
'{"highlights": ["New mockups shared", "Positive stakeholder feedback", "Design system updates"], "files_shared": 2}',
4, 0, 3, 2, 3, 'completed');

-- =====================================================
-- 8. INSERT SAMPLE DELIVERY LOGS
-- =====================================================

INSERT INTO delivery_logs (user_id, summary_id, delivery_type, recipient, subject, status, delivered_at, created_at) VALUES
(1, 1, 'email', 'demo@company.com', 'Daily Teams Activity Summary', 'delivered', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, NULL, 'teams_webhook', 'Development Team channel', 'Urgent Alert: Production Issue', 'delivered', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(2, 2, 'email', 'sarah.johnson@company.com', 'Weekly Engineering Summary', 'delivered', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, NULL, 'email', 'lisa.wang@company.com', 'Marketing Metrics Update', 'failed', NULL, DATE_SUB(NOW(), INTERVAL 12 HOUR));

-- Update failed delivery with error message
UPDATE delivery_logs SET error_message = 'SMTP timeout error', retry_count = 2 WHERE status = 'failed';

-- =====================================================
-- 9. INSERT NOTIFICATION PREFERENCES
-- =====================================================

INSERT INTO notification_preferences (user_id, channel_id, notification_type, event_type, enabled, schedule_time, days_of_week) VALUES
(1, NULL, 'email', 'daily_summary', TRUE, '08:00:00', 'monday,tuesday,wednesday,thursday,friday'),
(1, NULL, 'email', 'urgent', TRUE, NULL, NULL),
(1, NULL, 'push', 'mentions', TRUE, NULL, NULL),
(2, 1, 'email', 'all_messages', TRUE, NULL, 'monday,tuesday,wednesday,thursday,friday'),
(2, NULL, 'email', 'weekly_summary', TRUE, '09:00:00', 'monday'),
(3, 5, 'email', 'mentions', TRUE, NULL, NULL),
(4, 8, 'email', 'urgent', TRUE, NULL, NULL),
(4, NULL, 'email', 'weekly_summary', TRUE, '10:00:00', 'friday');

-- =====================================================
-- 10. INSERT USER ACTIVITY LOG ENTRIES
-- =====================================================

INSERT INTO user_activity_log (user_id, action, resource_type, resource_id, details, ip_address, created_at) VALUES
(1, 'login', NULL, NULL, '{"method": "email", "success": true}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 'view_dashboard', NULL, NULL, '{"page": "index"}', '192.168.1.100', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'send_message', 'message', 1, '{"channel": "Engineering General", "priority": "urgent"}', '192.168.1.101', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(3, 'upload_file', 'message', 10, '{"files": 2, "total_size": "3MB"}', '192.168.1.102', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(4, 'generate_summary', 'summary', 3, '{"type": "daily", "channels": 2}', '192.168.1.103', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- =====================================================
-- 11. UPDATE TIMESTAMPS TO MAKE DATA CURRENT
-- =====================================================

-- Update last login times for users
UPDATE users SET last_login = DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 24) HOUR) WHERE status = 'active';

-- Update channel last activity
UPDATE channels c SET last_activity = (
    SELECT MAX(m.created_at) 
    FROM messages m 
    WHERE m.channel_id = c.id AND m.deleted_at IS NULL
) WHERE c.status = 'active';

-- =====================================================
-- 12. VERIFY DATA INTEGRITY
-- =====================================================

-- Update channel member counts
UPDATE channels c SET member_count = (
    SELECT COUNT(DISTINCT tm.user_id)
    FROM team_members tm
    WHERE tm.team_id = c.team_id AND tm.status = 'active'
) WHERE c.status = 'active';

-- =====================================================
-- SAMPLE QUERIES TO TEST THE DATA
-- =====================================================

-- View all active users and their teams
-- SELECT u.display_name, u.department, t.name as team_name, tm.role 
-- FROM users u 
-- JOIN team_members tm ON u.id = tm.user_id 
-- JOIN teams t ON tm.team_id = t.id 
-- WHERE u.status = 'active' AND tm.status = 'active' 
-- ORDER BY u.display_name, t.name;

-- View recent activity
-- SELECT * FROM recent_activity LIMIT 10;

-- View channel activity summary
-- SELECT * FROM channel_activity_summary WHERE team_id = 1;

-- View user's daily summary
-- SELECT s.title, s.message_count, s.urgent_count, s.mention_count 
-- FROM summaries s 
-- WHERE s.user_id = 1 AND s.summary_type = 'daily' 
-- ORDER BY s.generated_at DESC 
-- LIMIT 5;

COMMIT;