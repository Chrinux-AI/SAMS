<?php
/**
 * SAMS README Auto-Documentation System
 * Automatically updates README.md based on current system state
 */

class SAMS_ReadmeAutoDoc {
    private $db;
    private $projectPath;
    private $readmePath;
    
    public function __construct() {
        $this->db = db();
        $this->projectPath = realpath(__DIR__ . '/../..');
        $this->readmePath = $this->projectPath . '/README.md';
    }
    
    /**
     * Generate and update README.md
     */
    public function generateReadme() {
        $content = $this->buildReadme();
        file_put_contents($this->readmePath, $content);
        return 'README.md updated successfully';
    }
    
    /**
     * Build complete README content
     */
    private function buildReadme() {
        $sections = [
            $this->buildHeader(),
            $this->buildOverview(),
            $this->buildArchitecture(),
            $this->buildFeatures(),
            $this->buildDirectoryStructure(),
            $this->buildDatabaseSchema(),
            $this->buildAdminWorkflows(),
            $this->buildApiDocumentation(),
            $this->buildRolePermissions(),
            $this->buildSetupInstructions(),
            $this->buildSecurity(),
            $this->buildAiSystem(),
            $this->buildChatbot(),
            $this->buildTesting(),
            $this->buildDeployment(),
            $this->buildContributing(),
            $this->buildFooter()
        ];
        
        return implode("\n\n", $sections);
    }
    
    /**
     * Build README header
     */
    private function buildHeader() {
        return "# SAMS - School Attendance Management System\n\n" .
               "![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)\n" .
               "![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)\n" .
               "![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)\n" .
               "![License](https://img.shields.io/badge/license-MIT-green.svg)\n\n" .
               "<p align=\"center\">\n" .
               "  <img src=\"assets/logo/logo.svg\" alt=\"SAMS Logo\" width=\"200\">\n" .
               "</p>\n\n" .
               "> A comprehensive, multi-tenant school attendance and management platform with AI-assisted user onboarding.\n\n";
    }
    
    /**
     * Build project overview
     */
    private function buildOverview() {
        return "## 🎯 Project Overview\n\n" .
               "SAMS is a production-grade School Attendance Management System designed for educational institutions. " .
               "It provides a complete solution for managing teachers, students, classes, attendance, and communication.\n\n" .
               "### Key Capabilities\n\n" .
               "- **Multi-Tenant Architecture**: Support multiple schools with complete data isolation\n" .
               "- **AI-Assisted Onboarding**: Automatic user creation from Google Forms\n" .
               "- **Secure Account Activation**: OTP-based verification with password creation\n" .
               "- **Role-Based Access**: Granular permissions for 9+ user roles\n" .
               "- **Real-time Chatbot**: AI-powered assistance for all users\n" .
               "- **Progressive Web App**: Mobile-friendly with offline capabilities\n\n";
    }
    
    /**
     * Build architecture section
     */
    private function buildArchitecture() {
        return "## 🏗 Architecture\n\n" .
               "### Technology Stack\n\n" .
               "| Layer | Technology |\n" .
               "|-------|-----------|\n" .
               "| Backend | PHP 8.0+ (Procedural + OOP Services) |\n" .
               "| Database | MySQL 5.7+ / MariaDB |\n" .
               "| Frontend | HTML5, CSS3, JavaScript (Vanilla) |\n" .
               "| Authentication | Session-based with OTP |\n" .
               "| AI Integration | Form parsing, Intent recognition |\n\n" .
               "### Design Patterns\n\n" .
               "- **Service Layer**: Business logic encapsulated in service classes\n" .
               "- **Dependency Injection**: Service container pattern\n" .
               "- **Multi-Tenancy**: Shared database with tenant_id isolation\n" .
               "- **Repository Pattern**: Database abstraction through services\n\n";
    }
    
    /**
     * Build features section
     */
    private function buildFeatures() {
        $stats = $this->getSystemStats();
        
        return "## ✨ Features\n\n" .
               "### Core Modules\n\n" .
               "| Module | Status | Description |\n" .
               "|--------|--------|-------------|\n" .
               "| User Management | ✅ Active | Create, manage, activate users |\n" .
               "| AI Onboarding | ✅ Active | Google Form → Account pipeline |\n" .
               "| OTP Security | ✅ Active | 6-digit, 10min expiry, rate limited |\n" .
               "| Class Management | ✅ Active | Create classes, assign teachers/students |\n" .
               "| Attendance | ✅ Active | Mark and track attendance |\n" .
               "| Chatbot | ✅ Active | Role-aware assistance |\n" .
               "| Multi-Tenant | ✅ Active | School isolation |\n\n" .
               "### Statistics\n\n" .
               "- Total Files: {$stats['total_files']}\n" .
               "- PHP Files: {$stats['php_files']}\n" .
               "- Database Tables: {$stats['db_tables']}\n" .
               "- User Roles: {$stats['roles']}\n\n";
    }
    
    /**
     * Build directory structure
     */
    private function buildDirectoryStructure() {
        return "## 📁 Directory Structure\n\n" .
               "```\n" .
               "attendance/\n" .
               "├── admin/              # Admin dashboard and management\n" .
               "├── teacher/            # Teacher portal\n" .
               "├── student/            # Student portal\n" .
               "├── parent/             # Parent portal\n" .
               "├── accountant/         # Financial management\n" .
               "├── bursar/             # Payment processing\n" .
               "├── librarian/          # Library management\n" .
               "├── transport/          # Transport management\n" .
               "├── forum/              # Discussion forums\n" .
               "├── api/                # REST API endpoints\n" .
               "├── includes/           # Core PHP files\n" .
               "│   ├── services/       # Service layer classes\n" .
               "│   ├── SAMSServices.php # Service autoloader\n" .
               "│   └── *.php           # Helper files\n" .
               "├── assets/             # Static assets\n" .
               "│   ├── theme/          # CSS and styling\n" .
               "│   ├── logo/           # Project logos and icons\n" .
               "│   └── js/             # JavaScript files\n" .
               "├── database/           # Schema and migrations\n" .
               "├── scripts/            # Utility scripts\n" .
               "├── chatbot/            # Chatbot system\n" .
               "├── docs/               # Documentation\n" .
               "├── logs/               # System logs\n" .
               "├── uploads/            # User uploads\n" .
               "├── tests/              # Test suite\n" .
               "├── index.php           # Landing page\n" .
               "├── login.php           # Authentication\n" .
               "└── README.md           # This file\n" .
               "```\n\n";
    }
    
    /**
     * Build database schema section
     */
    private function buildDatabaseSchema() {
        $tables = $this->getDatabaseTables();
        
        $content = "## 🗄 Database Schema\n\n";
        
        foreach ($tables as $table => $columns) {
            $content .= "### {$table}\n\n";
            $content .= "| Column | Type | Nullable | Default |\n";
            $content .= "|--------|------|----------|---------|\n";
            
            foreach ($columns as $col) {
                $content .= "| {$col['Field']} | {$col['Type']} | {$col['Null']} | {$col['Default']} |\n";
            }
            
            $content .= "\n";
        }
        
        return $content;
    }
    
    /**
     * Build admin workflows section
     */
    private function buildAdminWorkflows() {
        return "## 👨‍💼 Admin Workflows\n\n" .
               "### 1. Add Teacher\n\n" .
               "```\n" .
               "Admin Dashboard → Teachers → Add Teacher\n" .
               "→ Enter details → Submit\n" .
               "→ Account created (pending_activation)\n" .
               "→ Activation email sent\n" .
               "→ Teacher receives email with activation link\n" .
               "→ Teacher verifies OTP and creates password\n" .
               "→ Account activated\n" .
               "```\n\n" .
               "### 2. Bulk Import Students\n\n" .
               "```\n" .
               "Admin Dashboard → Students → Bulk Import\n" .
               "→ Upload CSV file\n" .
               "→ System validates data\n" .
               "→ Creates accounts for each student\n" .
               "→ Assigns to classes\n" .
               "→ Sends activation emails\n" .
               "→ Generates error report (if any)\n" .
               "```\n\n" .
               "### 3. Create Class\n\n" .
               "```\n" .
               "Admin Dashboard → Classes → Create Class\n" .
               "→ Enter class name and grade\n" .
               "→ Assign teacher\n" .
               "→ Select students\n" .
               "→ Class created with enrollments\n" .
               "```\n\n";
    }
    
    /**
     * Build API documentation
     */
    private function buildApiDocumentation() {
        return "## 🔌 API Documentation\n\n" .
               "### Authentication\n\n" .
               "All API requests require authentication via session or API token.\n\n" .
               "### Endpoints\n\n" .
               "| Endpoint | Method | Description |\n" .
               "|----------|--------|-------------|\n" .
               "| `/api/auth/login` | POST | User login |\n" .
               "| `/api/auth/logout` | POST | User logout |\n" .
               "| `/api/users` | GET | List users |\n" .
               "| `/api/users` | POST | Create user |\n" .
               "| `/api/classes` | GET | List classes |\n" .
               "| `/api/classes` | POST | Create class |\n" .
               "| `/api/attendance` | GET | Get attendance |\n" .
               "| `/api/attendance` | POST | Mark attendance |\n" .
               "| `/api/ai/process-form` | POST | Process form submission |\n" .
               "| `/api/chatbot/message` | POST | Send chatbot message |\n\n";
    }
    
    /**
     * Build role permissions section
     */
    private function buildRolePermissions() {
        return "## 🔐 Role Permissions\n\n" .
               "| Role | Dashboard | Teachers | Students | Classes | Attendance | Reports | Settings |\n" .
               "|------|-----------|----------|----------|---------|------------|---------|----------|\n" .
               "| Admin | ✅ | ✅ CRUD | ✅ CRUD | ✅ CRUD | ✅ CRUD | ✅ CRUD | ✅ CRUD |\n" .
               "| Teacher | ✅ | ❌ | ✅ View Own | ✅ View Own | ✅ Mark | ✅ View | ❌ |\n" .
               "| Student | ✅ | ❌ | ✅ View Self | ✅ View Own | ✅ View | ✅ View | ❌ |\n" .
               "| Parent | ✅ | ❌ | ✅ View Children | ✅ View | ✅ View | ✅ View | ❌ |\n" .
               "| Accountant | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ Financial | ❌ |\n" .
               "| Bursar | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ Payments | ❌ |\n\n" .
               "*CRUD = Create, Read, Update, Delete*\n\n";
    }
    
    /**
     * Build setup instructions
     */
    private function buildSetupInstructions() {
        return "## 🚀 Setup Instructions\n\n" .
               "### Prerequisites\n\n" .
               "- PHP 8.0 or higher\n" .
               "- MySQL 5.7+ or MariaDB 10.2+\n" .
               "- Apache/Nginx web server\n" .
               "- SSL certificate (recommended)\n\n" .
               "### Installation\n\n" .
               "1. **Clone the repository**\n" .
               "   ```bash\n" .
               "   git clone <repository-url>\n" .
               "   cd attendance\n" .
               "   ```\n\n" .
               "2. **Configure database**\n" .
               "   - Create MySQL database\n" .
               "   - Copy `config/database.sample.php` to `config/database.php`\n" .
               "   - Update database credentials\n\n" .
               "3. **Run migrations**\n" .
               "   ```bash\n" .
               "   php migrate.php\n" .
               "   ```\n\n" .
               "4. **Generate logos**\n" .
               "   ```bash\n" .
               "   php includes/LogoGenerator.php\n" .
               "   ```\n\n" .
               "5. **Create admin account**\n" .
               "   ```bash\n" .
               "   php setup-admin.php\n" .
               "   ```\n\n" .
               "6. **Configure SMTP** (for emails)\n" .
               "   - Update `config/email.php` with SMTP settings\n\n" .
               "7. **Access the application**\n" .
               "   - Open `http://localhost/attendance` in browser\n\n";
    }
    
    /**
     * Build security section
     */
    private function buildSecurity() {
        return "## 🔒 Security Features\n\n" .
               "- **Password Hashing**: bcrypt with cost factor 10\n" .
               "- **Session Security**: HTTP-only, secure cookies, 30min timeout\n" .
               "- **CSRF Protection**: Tokens on all forms\n" .
               "- **OTP Verification**: 6-digit, 10min expiry, 5 attempts\n" .
               "- **Rate Limiting**: 60s cooldown, 10 daily limit\n" .
               "- **Input Sanitization**: XSS and SQL injection prevention\n" .
               "- **Tenant Isolation**: Complete data separation between schools\n" .
               "- **Audit Logging**: All critical actions logged\n\n";
    }
    
    /**
     * Build AI system section
     */
    private function buildAiSystem() {
        return "## 🤖 AI System\n\n" .
               "### Google Form Integration\n\n" .
               "1. Create Google Form with fields:\n" .
               "   - Name\n" .
               "   - Email\n" .
               "   - Role (teacher/student)\n" .
               "   - Department/Grade\n\n" .
               "2. Configure webhook:\n" .
               "   - Set webhook URL: `https://yoursite.com/api/ai/process-form`\n" .
               "   - Set secret key in config\n\n" .
               "3. Form submissions automatically:\n" .
               "   - Extract and validate data\n" .
               "   - Create user accounts\n" .
               "   - Send activation emails\n\n" .
               "### Supported Formats\n\n" .
               "- JSON (Google Forms native)\n" .
               "- CSV export\n" .
               "- Key-value text\n\n";
    }
    
    /**
     * Build chatbot section
     */
    private function buildChatbot() {
        return "## 💬 Chatbot\n\n" .
               "The SAMS chatbot provides instant help to all users.\n\n" .
               "### Features\n\n" .
               "- **Navigation Help**: \"How do I add a student?\"\n" .
               "- **Intent Recognition**: 6+ intent types\n" .
               "- **Role-Aware**: Different responses per user role\n" .
               "- **Safe Fallback**: Graceful handling of unknown queries\n\n" .
               "### Usage\n\n" .
               "Click the help icon in the top bar to open the chatbot.\n\n";
    }
    
    /**
     * Build testing section
     */
    private function buildTesting() {
        return "## 🧪 Testing\n\n" .
               "### Run All Tests\n\n" .
               "```bash\n" .
               "php includes/services/TestFramework.php\n" .
               "```\n\n" .
               "### Test Coverage\n\n" .
               "- **Syntax Check**: All PHP files\n" .
               "- **Service Tests**: Core business logic\n" .
               "- **Database Tests**: Connection and schema\n" .
               "- **Security Tests**: Password, input validation\n" .
               "- **Integration Tests**: End-to-end workflows\n\n" .
               "### Generate Test Report\n\n" .
               "```bash\n" .
               "php includes/services/TestFramework.php > tests/report.html\n" .
               "```\n\n";
    }
    
    /**
     * Build deployment section
     */
    private function buildDeployment() {
        return "## 📦 Deployment\n\n" .
               "### Production Checklist\n\n" .
               "- [ ] Set `ENVIRONMENT = 'production'` in config\n" .
               "- [ ] Disable error display\n" .
               "- [ ] Enable HTTPS\n" .
               "- [ ] Configure SMTP\n" .
               "- [ ] Set up backups\n" .
               "- [ ] Configure cron jobs\n" .
               "- [ ] Test all workflows\n\n" .
               "### Docker (Optional)\n\n" .
               "```bash\n" .
               "docker-compose up -d\n" .
               "```\n\n";
    }
    
    /**
     * Build contributing section
     */
    private function buildContributing() {
        return "## 🤝 Contributing\n\n" .
               "1. Fork the repository\n" .
               "2. Create feature branch\n" .
               "3. Run tests: `php includes/services/TestFramework.php`\n" .
               "4. Commit changes\n" .
               "5. Push and create Pull Request\n\n" .
               "### Code Standards\n\n" .
               "- Follow PSR-12 coding standards\n" .
               "- Document all functions\n" .
               "- Include error handling\n" .
               "- Update README for new features\n\n";
    }
    
    /**
     * Build footer
     */
    private function buildFooter() {
        $year = date('Y');
        $lastUpdated = date('Y-m-d H:i:s');
        
        return "---\n\n" .
               "## 📄 License\n\n" .
               "This project is licensed under the MIT License.\n\n" .
               "## 🆘 Support\n\n" .
               "For support, email support@sams.edu or open an issue.\n\n" .
               "---\n\n" .
               "**Last Updated:** $lastUpdated  \n" .
               "**Version:** 2.0.0  \n" .
               "**© $year SAMS Project**\n";
    }
    
    /**
     * Get system statistics
     */
    private function getSystemStats() {
        $stats = [
            'total_files' => 0,
            'php_files' => 0,
            'db_tables' => 0,
            'roles' => 9
        ];
        
        // Count files
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $stats['total_files']++;
                if ($file->getExtension() === 'php') {
                    $stats['php_files']++;
                }
            }
        }
        
        // Count database tables
        $result = $this->db->query("SHOW TABLES");
        if ($result) {
            $stats['db_tables'] = mysqli_num_rows($result);
        }
        
        return $stats;
    }
    
    /**
     * Get database tables and columns
     */
    private function getDatabaseTables() {
        $tables = [];
        
        $result = $this->db->query("SHOW TABLES");
        if (!$result) {
            return $tables;
        }
        
        while ($row = mysqli_fetch_array($result)) {
            $tableName = $row[0];
            
            $colResult = $this->db->query("SHOW COLUMNS FROM $tableName");
            if ($colResult) {
                $tables[$tableName] = [];
                while ($col = mysqli_fetch_assoc($colResult)) {
                    $tables[$tableName][] = $col;
                }
            }
        }
        
        return $tables;
    }
    
    /**
     * Auto-update on system changes
     */
    public function autoUpdate() {
        // Check if system state has changed
        $currentHash = $this->calculateSystemHash();
        $storedHash = $this->getStoredHash();
        
        if ($currentHash !== $storedHash) {
            $this->generateReadme();
            $this->storeHash($currentHash);
            return 'README updated due to system changes';
        }
        
        return 'No changes detected';
    }
    
    /**
     * Calculate system hash
     */
    private function calculateSystemHash() {
        $hashes = [];
        
        // Hash key files
        $keyFiles = [
            'includes/services/',
            'database/schema.sql',
            'config/'
        ];
        
        foreach ($keyFiles as $path) {
            $fullPath = $this->projectPath . '/' . $path;
            if (is_dir($fullPath)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $hashes[] = md5_file($file->getPathname());
                    }
                }
            } elseif (is_file($fullPath)) {
                $hashes[] = md5_file($fullPath);
            }
        }
        
        return md5(implode('', $hashes));
    }
    
    /**
     * Get stored hash
     */
    private function getStoredHash() {
        $hashFile = $this->projectPath . '/.readme-hash';
        if (file_exists($hashFile)) {
            return file_get_contents($hashFile);
        }
        return '';
    }
    
    /**
     * Store hash
     */
    private function storeHash($hash) {
        $hashFile = $this->projectPath . '/.readme-hash';
        file_put_contents($hashFile, $hash);
    }
}

// Auto-update when called directly
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'ReadmeAutoDoc.php') {
    $doc = new SAMS_ReadmeAutoDoc();
    echo $doc->generateReadme() . "\n";
}
