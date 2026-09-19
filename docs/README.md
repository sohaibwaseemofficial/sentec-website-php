# SENTEC - Society for Promotion of Science Engineering and Technology

![SENTEC Logo](sentec-logo-without-bg.webp)

A comprehensive event management and information website for SENTEC (Society for Promotion of Science Engineering and Technology), featuring event registration, team management, gallery, and contact system with spam protection.

## 🌟 Features

### Public-Facing Features
- **Responsive Homepage** with hero sections and event highlights
- **Event Registration System** with dynamic forms based on institution type
- **Team Member Showcase** with detailed profiles
- **Photo Gallery** with organized event collections
- **Partner Showcase** displaying collaborating organizations
- **Contact Form** with Google reCAPTCHA v2 spam protection
- **Mobile-Responsive Design** using Bootstrap 5

### Admin Panel Features
- **Modern Dashboard** with real-time statistics and analytics
- **Event Management** 
  - Add, edit, and delete events
  - Track registrations per event
  - Manage event status (active/inactive)
- **Registration Management**
  - Review and approve/reject registrations
  - Filter by status (pending/approved/rejected)
  - CSV export functionality
  - View participant details and uploaded documents
- **Team Management**
  - Add, edit, and delete team members
  - Upload member photos and manage profiles
- **Gallery Management**
  - Upload and organize event photos
  - Create and manage gallery categories
- **Partner Management**
  - Add and manage partner organizations
  - Display partner logos
- **Secure Authentication**
  - Session-based login system
  - 10-minute inactivity timeout
  - Protected admin routes

## 🚀 Technologies Used

### Frontend
- **HTML5 & CSS3**
- **Bootstrap 5.3.3** - Responsive framework
- **JavaScript & jQuery 3.6.0**
- **FontAwesome 6.0.0** - Icons
- **Owl Carousel** - Image sliders
- **Google reCAPTCHA v2** - Spam protection

### Backend
- **PHP 7.4+** - Server-side scripting
- **MySQL/MariaDB** - Database management
- **AJAX** - Asynchronous requests

### Design
- Custom gradient themes (#6c838f brand color)
- Modern card-based layouts
- Smooth animations and transitions
- Professional admin interface

## 📋 Prerequisites

- **Web Server**: Apache/Nginx with PHP support
- **PHP**: Version 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Extensions**: 
  - PHP `mysqli` extension
  - PHP `GD` library (for image processing)
  - PHP `fileinfo` extension
- **Google reCAPTCHA keys** (for contact form)

## ⚙️ Installation

### 🔧 Local Development Setup

### 1. Clone the Repository

```bash
git clone https://github.com/MohammadShayan1/SENTEC.git
cd SENTEC
```

### 2. Set Up Environment Variables

```bash
# Copy the example environment file
cp .env.example .env
```

Edit `.env` file with your configuration:

```env
# Database Configuration
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=your_password
DB_NAME=sentec

# Google reCAPTCHA Configuration
RECAPTCHA_SITE_KEY=your_site_key
RECAPTCHA_SECRET_KEY=your_secret_key

# Email Configuration
ADMIN_EMAIL=your-email@sentec.live
FROM_EMAIL=no-reply@sentec.live
FROM_NAME=SENTEC
```

**🔒 Security Note**: The `.env` file is already in `.gitignore` and will never be committed to Git!

### 3. Database Setup

1. Create a new database:
```sql
CREATE DATABASE sentec;
```

2. Import the database structure:
```bash
mysql -u root -p sentec < sentec.sql
```

### 4. Configure Google reCAPTCHA

1. Get your keys from [Google reCAPTCHA](https://www.google.com/recaptcha/admin/create)
2. Add them to your `.env` file (already configured to load from environment variables)

### 5. Set Up File Permissions

```bash
# Create upload directories if they don't exist
mkdir -p images/uploads/event
mkdir -p images/uploads/gallery
mkdir -p images/uploads/team
mkdir -p images/uploads/partners
mkdir -p images/uploads/event_registrations

# Set proper permissions
chmod 755 images/uploads/
chmod 755 images/uploads/*/
```

### 6. Admin Account Setup

Default admin credentials (change immediately after first login):
- **Username**: abc
- **Password**: 1234

Or create a new admin user:
```sql
INSERT INTO admin (username, password) 
VALUES ('your_username', MD5('your_secure_password'));
```

### 7. Access the Application

- **Public Website**: `http://localhost/SENTEC/`
- **Admin Panel**: `http://localhost/SENTEC/admin/`

---

## ☁️ Azure Deployment (Perfect for Students!)

### 🎓 Deploy to Azure for Students

Get **$100 free credit** with [Azure for Students](https://azure.microsoft.com/free/students/) - no credit card required!

### Quick Azure Setup

1. **Get Azure for Students**
   - Visit [azure.microsoft.com/free/students](https://azure.microsoft.com/free/students/)
   - Sign up with your student email
   - Get $100 free credit instantly!

2. **Create Azure Web App**
   - Choose **PHP 8.2** runtime
   - Select **F1 Free** tier (perfect for students)
   - Deploy from GitHub with auto-deployment

3. **Configure Environment**
   - Add your `.env` variables as Azure App Settings
   - Set up MySQL database (free MySQL In App or Azure MySQL)
   - Import your database

4. **Deploy & Go Live!**
   - Push to GitHub
   - Azure auto-deploys
   - Your site is live! 🚀

### 📖 Detailed Guides

- **Complete Azure Deployment Guide**: [AZURE_DEPLOYMENT.md](AZURE_DEPLOYMENT.md)
- **Environment Setup Guide**: [ENV_SETUP.md](ENV_SETUP.md)
- **GitHub Actions CI/CD**: [.github/workflows/azure-deploy.yml](.github/workflows/azure-deploy.yml)

### 💰 Cost (Student Account)

- **Web App (F1 Free)**: $0/month
- **MySQL In App**: $0/month
- **SSL Certificate**: $0/month (free managed)
- **Bandwidth**: Included in free tier
- **Total**: **FREE** 🎉

### Azure Features You Get

✅ Auto-deployment from GitHub  
✅ Free SSL/HTTPS  
✅ Application monitoring  
✅ Real-time logs  
✅ Custom domain support  
✅ Scaling capabilities  
✅ 99.95% uptime SLA

## 📁 Project Structure

```
sentec.live/
├── admin/                          # Admin panel
│   ├── add_event.php              # Add new events
│   ├── add_team_member.php        # Add team members
│   ├── admin_login.php            # Admin authentication
│   ├── admin_logout.php           # Logout handler
│   ├── admin_partners.php         # Partner management
│   ├── db_connection.php          # Database config
│   ├── delete_event.php           # Delete events
│   ├── delete_gallery.php         # Delete galleries
│   ├── delete_team_member.php     # Delete team members
│   ├── download_registrations_csv.php  # CSV export
│   ├── edit_event.php             # Edit events
│   ├── edit_gallery.php           # Edit galleries
│   ├── edit_team_member.php       # Edit team members
│   ├── footer.php                 # Admin footer
│   ├── header.php                 # Admin sidebar navigation
│   ├── index.php                  # Admin dashboard
│   ├── manage_gallery.php         # Gallery management
│   ├── manage_registrations.php   # Registration management
│   ├── manage_team.php            # Team management
│   └── update_registration_status.php  # Update registration status
├── css/                            # Stylesheets
│   ├── header.css                 # Header styles
│   ├── registration.css           # Registration form styles
│   ├── style.css                  # Main styles
│   ├── team.css                   # Team page styles
│   └── owl.carousel.min.css       # Carousel styles
├── images/                         # Image assets
│   ├── favicon/                   # Favicon files
│   ├── hero/                      # Hero section images
│   └── uploads/                   # User-uploaded files
│       ├── event/                 # Event images
│       ├── gallery/               # Gallery images
│       ├── partners/              # Partner logos
│       ├── team/                  # Team member photos
│       └── event_registrations/   # Registration documents
├── js/                             # JavaScript files
│   ├── header.js                  # Header interactions
│   ├── main.js                    # Main functionality
│   ├── registration.js            # Registration form logic
│   ├── jquery.min.js              # jQuery library
│   └── owl.carousel.min.js        # Carousel library
├── .github/                        # GitHub Actions
│   └── workflows/
│       └── azure-deploy.yml       # Azure CI/CD pipeline
├── .env                            # Environment variables (not in Git)
├── .env.example                    # Environment template
├── .gitignore                      # Git ignore rules
├── .deployment                     # Azure deployment config
├── .user.ini                       # PHP configuration
├── deploy.cmd                      # Azure deployment script
├── web.config                      # IIS/Azure configuration
├── applicationHost.xdt             # Azure transform
├── env_loader.php                  # Environment loader
├── contact.php                     # Contact form page
├── contact_work.php                # Contact form handler
├── db_connection.php               # Database connection
├── event_registration.php          # Event registration form
├── footer.php                      # Public footer
├── gallery.php                     # Photo gallery page
├── header.php                      # Public header/navigation
├── index.php                       # Homepage
├── OurPartners.php                 # Partners showcase
├── recaptcha_config.php            # reCAPTCHA configuration
├── sentec.sql                      # Database dump
├── submit_registration.php         # Registration form handler
├── team.php                        # Team members page
├── AZURE_DEPLOYMENT.md             # Azure deployment guide
├── ENV_SETUP.md                    # Environment setup guide
└── README.md                       # This file
```

## 🎯 Key Features Explained

### Event Registration System

The registration system supports three institution types:
- **NED University Students**
- **Non-NED University Students**
- **Colleges**

**Features:**
- Dynamic form labels based on institution type
- Support for 1-4 team members
- Conditional validation (min 2, max 4 participants)
- File uploads for each participant:
  - Face photograph
  - Student/College ID card
- Registration fee screenshot upload
- Admin approval workflow

**Database Schema:**
- 34 columns per registration
- Tracks team information, module selection
- Stores participant details and document paths
- Status tracking (pending/approved/rejected)

### Contact Form with Spam Protection

**Security Measures:**
- Google reCAPTCHA v2 verification
- Server-side validation
- Spam pattern detection:
  - Blocks promotional keywords
  - Prevents URL submissions
  - Detects excessive capitalization
- XSS protection through input sanitization

**Email Features:**
- Auto-reply to users
- Admin notification emails
- Professional email templates
- Timestamp tracking

### Admin Panel

**Dashboard Statistics:**
- Total events count
- Active/Inactive event breakdown
- Registration metrics
- Team member count
- Gallery statistics
- Partner count
- Recent registrations overview

**Security:**
- Session-based authentication
- 10-minute inactivity timeout
- Password-protected routes
- Automatic logout

## 🎨 Customization

### Changing Brand Colors

Update the color scheme in relevant CSS files:

```css
/* Main brand color */
--primary-color: #6c838f;
--secondary-color: #546978;

/* Gradient backgrounds */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

### Updating Logo

Replace the logo file:
- Location: `sentec-logo-without-bg.webp`
- Format: WebP, PNG, or JPG
- Recommended size: 200x200px (transparent background)

### Email Templates

Edit email content in `contact_work.php`:

```php
$user_subject = "Your Custom Subject";
$user_message = "Your custom message template...";
```

## 📊 Database Tables

### Core Tables
- `admin` - Admin user accounts
- `events` - Event information
- `event_registrations` - Event registration submissions
- `team_members` - Team member profiles
- `gallery` - Gallery categories
- `gallery_images` - Gallery photos
- `partners` - Partner organizations

### Event Registrations Table Structure
```sql
- id (Primary Key)
- team_name, institution_type, module_selection
- fees_screenshot
- participant1_name through participant4_name
- participant1_contact through participant4_contact
- participant1_email through participant4_email
- participant1_cnic through participant4_cnic
- participant1_roll_number through participant4_roll_number
- participant1_face_image through participant4_face_image
- participant1_id_card through participant4_id_card
- status (pending/approved/rejected)
- created_at (timestamp)
```

## 🔒 Security Best Practices

1. **Change Default Credentials**
   ```sql
   UPDATE admin SET password = MD5('new_secure_password') WHERE username = 'admin';
   ```

2. **Protect Configuration Files**
   Add to `.gitignore`:
   ```
   db_connection.php
   admin/db_connection.php
   recaptcha_config.php
   ```

3. **Set Proper File Permissions**
   ```bash
   chmod 644 *.php
   chmod 755 admin/
   chmod 755 images/uploads/
   ```

4. **Enable HTTPS**
   - Obtain SSL certificate
   - Configure redirect in `.htaccess`

5. **Regular Backups**
   ```bash
   mysqldump -u username -p sentec > backup_$(date +%Y%m%d).sql
   ```

## 🐛 Troubleshooting

### reCAPTCHA Not Working
- Verify keys in `recaptcha_config.php`
- Check domain registration in Google reCAPTCHA admin
- Ensure JavaScript is enabled

### File Upload Errors
- Check folder permissions (755 for directories)
- Verify `upload_max_filesize` in `php.ini`
- Ensure GD library is installed

### Email Not Sending
- Configure SMTP settings
- Check spam folders
- Verify `mail()` function is enabled
- Consider using PHPMailer for better reliability

### Database Connection Issues
- Verify credentials in `db_connection.php`
- Check MySQL service is running
- Ensure database exists and user has permissions

### Session Timeout Issues
- Adjust timeout in `admin/header.php`:
  ```php
  $timeout_duration = 600; // 10 minutes
  ```

## 📝 Development Roadmap

- [ ] Add email verification for registrations
- [ ] Implement SMTP for reliable email delivery
- [ ] Add pagination for large data sets
- [ ] Implement search functionality
- [ ] Add event calendar view
- [ ] Create REST API for mobile app
- [ ] Add multi-language support
- [ ] Implement advanced analytics dashboard
- [ ] Add export functionality (PDF reports)
- [ ] Integrate payment gateway for registration fees

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards
- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Comment complex logic
- Test thoroughly before submitting PR

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Developer

**Mohammad Shayan**
- LinkedIn: [LinkedIn](https://www.linkedin.com/in/mohammad-shayan786/)
- GitHub: [@MohammadShayan1](https://github.com/MohammadShayan1)

## 🙏 Acknowledgments

- Bootstrap Team for the excellent framework
- FontAwesome for the icon library
- Google for reCAPTCHA service
- All contributors and testers

## 📞 Support

For support, please:
1. Check the documentation first
2. Search existing issues on GitHub
3. Create a new issue with detailed description
4. Contact: no-reply@sentec.live

## 📈 Version History

### Version 2.0.0 (November 2025)
- ✅ Complete admin panel revamp
- ✅ Event registration system
- ✅ Google reCAPTCHA integration
- ✅ Spam protection for contact form
- ✅ Enhanced security features
- ✅ Modern UI/UX design
- ✅ CSV export functionality
- ✅ Mobile-responsive admin panel

### Version 1.0.0 (Initial Release)
- Basic website structure
- Team management
- Gallery system
- Contact form
- Partner showcase

---

**Made with ❤️ for SENTEC - Society for Promotion of Science Engineering and Technology**

*Last Updated: November 3, 2025*


