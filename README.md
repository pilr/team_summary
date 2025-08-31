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
- Modern web browser

## 🛠 Installation

1. **Download Files**
   ```bash
   # Copy all PHP files to your web server directory
   cp -r team_summary/ /var/www/html/
   ```

2. **Configure Web Server**
   - Ensure PHP is enabled
   - Set document root to the project folder
   - Enable URL rewriting (optional)

3. **Set Permissions**
   ```bash
   chmod 755 /var/www/html/team_summary/
   chmod 644 /var/www/html/team_summary/*.php
   ```

## 🔧 Configuration

Edit `config.php` to customize:

- Database settings (for future database integration)
- Session configuration
- API endpoints
- Email settings
- Security settings

## 📁 File Structure

```
team_summary/
├── index.php              # Main dashboard
├── login.php              # User authentication
├── summaries.php          # Detailed reports page
├── logout.php             # Session termination
├── social-login.php       # OAuth handler
├── config.php             # Configuration settings
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
   - Navigate to `http://your-server/team_summary/`

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

5. **Navigation**
   - Use sidebar navigation between pages
   - Mobile-responsive hamburger menu
   - User profile dropdown (future feature)

## 🎨 Features Details

### Authentication System
- Server-side session management
- Secure password handling
- Remember me functionality
- Social login placeholders (Microsoft/Google)
- Automatic redirects and protection

### Dashboard
- Real-time activity metrics
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

### Security Features
- Session-based authentication
- CSRF token protection (configured)
- Input sanitization
- SQL injection prevention (prepared)
- XSS protection

## 🛡 Security Considerations

- All user inputs are sanitized
- Sessions are properly configured
- Passwords should be hashed in production
- HTTPS recommended for production
- Regular security updates advised

## 🔄 Future Enhancements

- Database integration for persistent data
- Real Microsoft Teams API integration
- Email notifications system
- User management and roles
- Advanced analytics and reporting
- Mobile app companion

## 🐛 Troubleshooting

**Login Issues:**
- Ensure sessions are enabled in PHP
- Check file permissions
- Verify demo credentials

**Display Problems:**
- Ensure all CSS/JS files are accessible
- Check browser console for errors
- Verify web server configuration

**Performance:**
- Enable PHP opcode caching
- Optimize images and assets
- Consider CDN for static files

## 📞 Support

This is a demo application. For production use:
- Implement proper database storage
- Add comprehensive error handling
- Configure proper logging
- Set up monitoring and backups
- Implement rate limiting

## 📄 License

This project is for demonstration purposes. Customize as needed for your organization.

---

Built with ❤️ for modern team collaboration