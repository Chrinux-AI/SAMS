# AI-Powered School Management System (SAMS)

A comprehensive, multi-tenant school management platform with AI-powered user creation, secure authentication, and role-based access control.

## Implementation Status (March 2026)

Recent execution work completed in code:

- Stabilized core admin workflows:
  - teacher creation
  - class creation/assignment
  - student add
  - bulk student import
- Added security hardening:
  - centralized role routing helper
  - backward-compatible CSRF enforcement on critical admin forms
  - standardized API response/error envelope
  - production-safe database connection failure handling
- Added operational reliability:
  - `api/health.php` health endpoint
  - `scripts/core-smoke-check.php`
  - `scripts/schema-drift-check.php`
  - `scripts/export-schema.php` -> generates `database/schema.sql`
- Added onboarding/AI integration endpoints:
  - `api/process-form-submission.php` (webhook form processing)
  - `activate-account.php` (activation token bridge)
- Added bulk import error report export:
  - downloadable CSV report for failed rows in `admin/students-bulk-import.php`

## 🚀 Features

### Core Features

- **Multi-tenant Architecture**: Support for multiple schools with data isolation
- **AI-Powered User Creation**: Bulk teacher/student creation via Google Forms integration
- **Secure OTP Authentication**: Passwordless account creation with email verification
- **Role-Based Access Control**: 12+ distinct roles with granular permissions
- **Real-time Communication**: Integrated chat system with role-based messaging
- **Biometric Integration**: Optional biometric authentication support
- **Progressive Web App**: PWA-ready with offline capabilities

### User Roles

- **Super Admin**: Platform-wide administration
- **Owner**: Institution ownership
- **Principal**: School management
- **Admin**: Administrative operations
- **Teacher**: Classroom management
- **Student**: Learning portal
- **Parent**: Guardian access
- **Staff**: Non-teaching staff
- **Librarian**: Library management
- **Bursar**: Financial management
- **Accountant**: Accounting operations
- **Transport**: Transportation management
- **Forum Moderator**: Community oversight

## 📁 Project Structure

```
attendance/
├── index.php, login.php, ...     # Public entry points (clean root)
├── confirm-account.php            # OTP verification flow
├── setup-admin.php                # First-time admin setup
├── migrate.php                    # Database migrations
│
├── admin/                 # Admin portal (60+ pages)
├── student/               # Student portal
├── teacher/               # Teacher portal
├── parent/                # Parent portal
├── accountant/            # Accountant dashboard
├── bursar/                # Bursar dashboard
├── librarian/             # Librarian dashboard
├── transport/             # Transport dashboard
├── forum/                 # Community forum
│
├── api/                   # REST API endpoints
├── includes/              # Shared PHP (config, database, functions, email)
├── assets/                # CSS, JS, images, fonts, icons
├── config/                # Environment configuration
│
├── database/              # SQL schemas
│   ├── migrations/        # Migration files
│   └── setup/             # One-time setup SQL
├── docs/                  # All documentation (see docs/INDEX.md)
├── scripts/               # Utility scripts
│   ├── setup/             # Setup & migration scripts
│   ├── utilities/         # Ongoing tools (cache, dev logins, etc.)
│   ├── fixes/             # Historical fix scripts (archived)
│   ├── generators/        # Page/icon generation (archived)
│   └── theme-conversion/  # Theme switch scripts (archived)
├── backups/               # Old/backup files (archived)
├── tests/                 # PHPUnit test suite
└── vendor/                # Composer dependencies
```

## 🛠 Installation

### Prerequisites

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.2+
- Apache/Nginx web server
- Composer (for PHP dependencies)

### Setup Steps

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd attendance
   ```

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Configure database**
   - Create database and user
   - Import schema from `database/` directory
   - Update configuration in `config/`

4. **Set up admin account**

   ```bash
   php setup-admin.php
   ```

5. **Configure web server**
   - Point document root to project directory
   - Enable mod_rewrite for Apache
   - Set up SSL for production

## 📖 Documentation

All documentation lives in the `docs/` folder. Start with [docs/INDEX.md](docs/INDEX.md) for a full map.

| Document                                                  | Description                                                    |
| --------------------------------------------------------- | -------------------------------------------------------------- |
| [Architecture](docs/ARCHITECTURE.md)                      | Database schema, directory structure, auth flow, key functions |
| [Features](docs/FEATURES.md)                              | Complete feature reference with file locations                 |
| [Changelog](docs/CHANGELOG.md)                            | All major changes, fixes, and additions                        |
| [Theme & UI](docs/THEME_AND_UI.md)                        | Color palette, typography, page structure                      |
| [Multi-Tenant Guide](docs/MULTI_TENANT_PLATFORM_GUIDE.md) | Tenant setup and management                                    |
| [Quick Reference](docs/QUICK_REFERENCE.md)                | Quick lookup card                                              |

## 🔧 Configuration

### Environment Variables

Create `.env` file with:

```env
DB_HOST=localhost
DB_NAME=sams_db
DB_USER=username
DB_PASS=password

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password

AI_SECRET_KEY=your-secret-key
OTP_LENGTH=6
```

### Key Settings

- **OTP Expiry**: 15 minutes (configurable)
- **Max OTP Requests**: 5 per hour
- **Session Timeout**: 30 minutes
- **Password Requirements**: 8+ characters, mixed case

## 🤖 AI Features

### Bulk User Creation

1. Create Google Form with required fields
2. Export responses (JSON/CSV)
3. Paste into AI User Creator interface
4. System extracts, validates, and creates accounts
5. Users receive OTP confirmation emails

### Supported Formats

- **JSON**: Google Forms JSON export
- **CSV**: Comma-separated values
- **Key-Value**: Plain text format

## 🔒 Security Features

- **OTP-Based Authentication**: Secure, time-limited access codes
- **Rate Limiting**: Prevents brute force attacks
- **Role-Based Permissions**: Granular access control
- **Audit Logging**: Complete activity tracking
- **Password Security**: Hashed with bcrypt
- **CSRF Protection**: Cross-site request forgery prevention

## 📱 Mobile & PWA

- **Responsive Design**: Works on all devices
- **Offline Support**: Core functionality available offline
- **App-like Experience**: Installable PWA
- **Push Notifications**: Real-time updates

## 🔄 Maintenance

### Database Maintenance

```bash
# Clear cache
php scripts/utilities/clear_cache.php

# Update schema
php migrate.php

# Check system health
php verify-system.php

# Backup database
mysqldump -u root attendance_system > backup.sql
```

### Log Management

- Logs stored in `logs/` directory
- Rotate logs monthly
- Monitor error logs for issues

## 🚀 Deployment

### Production Setup

1. Configure SSL certificate
2. Set up cron jobs for maintenance
3. Configure backup strategy
4. Enable caching
5. Monitor performance

### Docker Support

```dockerfile
FROM php:8.0-apache
# Configuration details in docker/
```

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Make changes
4. Add tests
5. Submit pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Documentation

- Check [docs/](docs/) for comprehensive guides
- Review [FAQ](docs/guides/FAQ.md)

### Issues

- Report bugs via GitHub Issues
- Include system information and error logs

### Contact

- Email: support@sams.example.com
- Documentation: [docs/](docs/)

## 🎯 Roadmap

### Current Version: 2.0.0

- ✅ Multi-tenant architecture
- ✅ AI-powered user creation
- ✅ Enhanced security with OTP
- ✅ Mobile PWA support

### Upcoming Features

- 🔄 Advanced analytics dashboard
- 🔄 Video conferencing integration
- 🔄 Mobile apps (iOS/Android)
- 🔄 AI-powered recommendations

---

**Last Updated**: March 2026
**Version**: 2.0.0
**Framework**: PHP 8.0+, MySQL, JavaScript
