# Teams Activity Dashboard - PHP Version

A modern, responsive web application for summarizing Microsoft Teams activity with real-time notifications and comprehensive reporting.

## 🚀 Features

- **User Authentication** - Secure login with session management
- **Dashboard Overview** - Real-time activity summaries and metrics
- **Detailed Summaries** - Comprehensive reports with filters and exports
- **Responsive Design** - Optimized for desktop and mobile devices
- **Professional UI** - Modern SaaS-style interface with animations

## 📋 Requirements

- PHP 7.4 or higher
- Web server (Apache/Nginx)
- MySQL database
- Modern web browser

## 🛠 Installation

1. **Download Files**
   ```bash
   # Clone repository
   git clone https://github.com/pilr/team_summary.git
   ```

2. **Configure Database**
   - Import `database_schema.sql` to create tables
   - Import `sample_data.sql` for test data
   - Update `config.php` with your database credentials

3. **Configure Web Server**
   - Ensure PHP is enabled
   - Set document root to the project folder
   - Enable URL rewriting (optional)

## 🗄️ Database Setup

1. **Import Schema**
   - Use phpMyAdmin or MySQL command line
   - Import `database_schema.sql` first
   - Then import `sample_data.sql`

2. **Update Configuration**
   - Edit `config.php` with your database credentials
   - Test connection with `database_helper.php`

## 📁 File Structure

```
team_summary/
├── index.php              # Main dashboard
├── login.php              # User authentication
├── summaries.php          # Detailed reports page
├── logout.php             # Session termination
├── social-login.php       # OAuth handler
├── config.php             # Configuration settings
├── database_helper.php    # Database functions
├── database_schema.sql    # Database structure
├── sample_data.sql        # Test data
├── styles.css             # Main stylesheet
├── login-styles.css       # Login page styles
├── summaries-styles.css   # Summaries page styles
├── script.js              # Main JavaScript
├── login-script.js        # Login functionality
├── summaries-script.js    # Summaries functionality
└── README.md              # This file
```

## 🔐 Demo Credentials

For demonstration purposes, use these credentials:

- **Email**: `demo@company.com`
- **Password**: `demo123`

## 💻 Usage

1. **Access the Application**
   - Open your web browser
   - Navigate to your server URL

2. **Login**
   - Use demo credentials or click the demo notice
   - Choose "Remember me" for persistent sessions

3. **Dashboard**
   - View real-time activity summaries
   - Expand/collapse channel details
   - Apply filters for urgent messages or mentions

4. **Summaries Page**
   - Select date ranges and filters
   - View detailed timeline and statistics
   - Export reports to CSV format

## 🎨 Features Details

### Authentication System
- Server-side session management
- Secure password handling with PHP password_hash()
- Remember me functionality
- Social login placeholders (Microsoft/Google)
- Automatic redirects and protection

### Dashboard
- Real-time activity metrics from database
- Expandable channel summaries
- Filtering by urgency and mentions
- Delivery logs with status tracking
- Mobile-responsive design

### Summaries Page
- Advanced date range selection
- Multiple filter options
- Interactive timeline view
- Detailed channel breakdowns
- CSV export functionality
- Statistics with trend indicators

### Database Features
- 12 comprehensive tables
- Proper relationships and constraints
- Automated triggers for data consistency
- Sample data for immediate testing
- Database helper functions for easy queries

## 🛡 Security Considerations

- All user inputs are sanitized
- Sessions are properly configured
- Passwords are properly hashed
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars()

## 🔄 Future Enhancements

- Real Microsoft Teams API integration
- Email notifications system
- User management and roles
- Advanced analytics and reporting
- Mobile app companion
- Multi-tenant support

## 🐛 Troubleshooting

**Database Issues:**
- Ensure MySQL is running
- Check database credentials in config.php
- Verify tables were imported correctly

**Login Issues:**
- Ensure sessions are enabled in PHP
- Check file permissions
- Verify demo credentials exist in database

**Display Problems:**
- Ensure all CSS/JS files are accessible
- Check browser console for errors
- Verify web server configuration

## 📞 Support

This is a demo application. For production use:
- Implement proper database backups
- Add comprehensive error handling
- Configure proper logging
- Set up monitoring and alerts
- Implement rate limiting

## 📄 License

This project is for demonstration purposes. Customize as needed for your organization.

---

Built with ❤️ for modern team collaboration
