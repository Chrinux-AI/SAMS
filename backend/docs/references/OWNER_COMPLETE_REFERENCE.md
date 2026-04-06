# OWNER - Complete Pages & Navigation Reference# OWNER - Complete Pages & Navigation Reference

**End of OWNER_COMPLETE_REFERENCE.md**---- Makes strategic decisions- Can delegate to Principal/Admin- Has highest authority within school- Can create full backups- Receives financial summaries### **Unique to Owner**- Cannot switch contexts- Cannot create schools- Cannot access multi-tenant features- Full institutional permissions- Single institution bindingOwner role uses the same admin pages as Principal and Admin, but with:### **Role-Based Access**## 📞 Implementation Notes---All accessible from sidebar menu or dashboard quick links.`    └─ Analytics (admin/overview.php)    ├─ Transport Management (admin/transport-management.php)    ├─ Library Management (admin/library-management.php)    ├─ Activity Monitor (admin/activity-monitor.php)    ├─ Bulk Import (admin/bulk-import.php)    ├─ AI User Creator (admin/ai-user-creator.php)    ├─ Financial Management (admin/financial-management.php)    ├─ System Health (admin/system-health.php)    ├─ Settings (admin/settings.php)    ├─ Role Management (admin/role-management.php)    ├─ User Management (admin/users.php)    ├─ User Approval (admin/approve-users.php)    ├─ Reports (admin/reports.php)    ├─ Attendance (admin/attendance.php)    ├─ Enrollment (admin/class-enrollment.php)    ├─ Classes Management (admin/classes.php)    ├─ Teachers Management (admin/teachers.php)    ├─ Students Management (admin/students.php)Dashboard (admin/dashboard.php)`**From Dashboard, Owner can reach**:## 🚀 Quick Navigation Map---- [ ] Permissions enforced (cannot bypass with URL)- [ ] Sidebar navigation all links work- [ ] Dark mode toggle works- [ ] Works on mobile (responsive)- [ ] Cannot switch institution context- [ ] Cannot create new schools- [ ] Cannot access super-admin features- [ ] Activity logs show all Owner actions- [ ] Can see only institutional data (no cross-school visibility)- [ ] Can export data in multiple formats- [ ] Can change own password in settings- [ ] Can approve pending users- [ ] Can mark attendance and generate reports- [ ] Can create class and enroll students- [ ] Can create new teacher and assign to class- [ ] Can create new student and save to database- [ ] Can navigate to all assigned pages- [ ] KPI cards show correct data- [ ] Can access dashboard without errors## 🎯 Testing Checklist for Owner Role---All queries must filter by `tenant_id` to ensure single-school isolation.- `payments` (institution-scoped)- `fees` (institution-scoped)- `settings` (institution-scoped)- `activity_logs` (institution-scoped)- `role_permissions` (shared)- `system_roles` (shared)- `attendance` (institution-scoped)- `class_enrollments` (institution-scoped)- `classes` (institution-scoped)- `teachers` (institution-scoped)- `students` (institution-scoped)- `users` (institution-scoped)**Primary Tables Accessed**:## 📄 Database Tables Used by Owner---`SELECT * FROM classes WHERE tenant_id = ? AND academic_year = ?SELECT * FROM users WHERE tenant_id = ? AND role = 'teacher'SELECT * FROM students WHERE tenant_id = ? AND is_active = 1// Examples:  AND (other conditions)WHERE tenant_id = $_SESSION['tenant_id']// All queries must include tenant filtering`php### **Data Isolation Pattern**`}    exit;    header('Location: ../login.php?error=no_institution');if (empty($_SESSION['tenant_id'])) {// Check tenant_id for single-school restriction}    exit;    header('Location: ../login.php?error=unauthorized');if (!in_array($user_role, ['owner', 'admin', 'principal', 'super_admin'])) {$user_role = $_SESSION['role'] ?? '';}    exit;    header('Location: ../login.php?error=not_logged_in');if (!isset($_SESSION['user_id'])) {// At top of all admin pages`php### **Session Management**## 🛡️ Security & Access Control---- Follows Principal's direction- Cannot create backups- Limited financial access- Manages day-to-day operations- Assists Principal- Operational support**Admin**:- Reports to Owner- Day-to-day governance- Oversees quality- Manages discipline- Focuses on academics- Educational leader**Principal**:- Makes strategic decisions- Can create backups- Sees financial overview- Can delegate all tasks- Highest authority within school- Business/Legal owner of institution**Owner**:### **Key Differences**| **System Settings** | ✅ Full | ✅ Partial | ⚠️ Read-Only || **Delete Users** | ✅ Yes | ✅ Yes | ✅ Yes || **View Audit Logs** | ✅ Yes | ✅ Yes | ✅ Yes || **Backup/Restore** | ✅ Yes | ❌ No | ❌ No || **Role Assignment** | ✅ Full | ✅ Full | ❌ Limited || **Financial** | ✅ Full | 📋 Overview | 📋 Assist || **Attendance** | ✅ View Only | ✅ View/Manage | ✅ View/Manage || **Class Management** | ✅ Full | ✅ Full | ✅ Full || **Teacher Management** | ✅ Full | ✅ Full | ✅ Full || **Student Registration** | ✅ Full | ✅ Full | ✅ Full || **User Management** | ✅ Full | ✅ Full | ✅ Full ||-----------|-------|-----------|-------|| Capability | Owner | Principal | Admin |### **Permission Comparison**## 👥 Owner vs Admin vs Principal---`└─ Access super-admin dashboard├─ Switch institution context├─ Modify multi-tenant config├─ Access platform analytics├─ Create custom roles (system-wide)├─ Modify platform settings├─ Create Super Admin users├─ Access other schools├─ Create new schools/tenants❌ NOT ALLOWED (Owner Cannot):└─ Reset passwords for users├─ Create backups├─ Configure fee structure├─ Manage finances (overview)├─ Export institutional data├─ Run audits/activity logs├─ View all institutional data├─ Configure institution settings├─ Approve user registrations├─ Assign roles to staff├─ Manage user accounts├─ Create announcements├─ Generate reports├─ Mark attendance├─ Create/modify/delete classes├─ Create/modify/delete teachers├─ Create/modify/delete students✅ ALLOWED (Owner Can):`### **Owner Permissions** (Institution-Scoped)## 🔐 Permission Model--- - [ ] Restore functionality - [ ] Download management - [ ] Selective exports - [ ] Full backup option- [ ] **Backup & Export** - `admin/backup-export.php` - [x] Student assignments - [x] Vehicles list - [x] Routes overview- [x] **Transport Management** - `admin/transport-management.php` - [ ] Overdue tracking - [ ] Lending system - [ ] Book catalog view- [ ] **Library Management** - `admin/library-management.php` - [ ] Archive management - [ ] Schedule sending - [ ] Target audience - [ ] Create announcement- [ ] **Announcements** - `admin/announcements.php`### **Phase 4: Optional/Enhanced Pages** - [x] Export logs - [x] Login tracking - [x] Modification history - [x] User activity log- [x] **Activity Monitor** - `admin/activity-monitor.php` - [x] Validation errors - [x] Preview - [x] Field mapping - [x] CSV upload- [x] **Bulk Import** - `admin/bulk-import.php` - [x] Validation - [x] Bulk creation - [x] Data extraction - [x] Form integration- [x] **AI User Creator** - `admin/ai-user-creator.php` - [x] Financial reports - [x] Fee structure overview - [x] Payment tracking - [x] Revenue summary- [x] **Financial Management** - `admin/financial-management.php` - [ ] Date range selectors - [ ] Trend analysis - [ ] Charts and graphs - [ ] Statistical summary- [ ] **Overview/Analytics** - `admin/overview.php`### **Phase 3: Operational Pages** - [x] Save changes - [x] Account tab - [x] Appearance/Theme tab - [x] Notifications tab - [x] Security settings tab - [x] Profile settings tab- [x] **Settings** - `admin/settings.php` - [x] Understand role hierarchy - [x] Assign roles to users - [x] View permissions per role - [x] List system roles- [x] **Role Management** - `admin/role-management.php` - [x] Deactivate/Activate - [x] Reset password - [x] Change role - [x] Edit user - [x] Create user - [x] All users list- [x] **Users Management** - `admin/users.php` - [x] System recommendations - [x] Health status indicators - [x] Server metrics display- [x] **System Health** - `admin/system-health.php`### **Phase 2: Administrative Pages** - [x] Date range filtering - [x] Export to PDF/Excel - [x] Generate performance reports - [x] Generate attendance reports- [x] **Reports** - `admin/reports.php` - [x] Bulk actions - [x] Reject button - [x] Approve button - [x] List pending users- [x] **Approve Users** - `admin/approve-users.php` - [x] Filter by date/class - [x] Export attendance records - [x] Generate reports - [x] View attendance summary - [x] Mark attendance- [x] **Attendance** - `admin/attendance.php` - [x] Change student class - [x] Remove enrollment - [x] Bulk enroll from CSV - [x] Enroll student in class - [x] List enrollments- [x] **Enrollment** - `admin/class-enrollment.php` - [x] Delete class - [x] Assign form teacher - [x] Edit class details - [x] Create new class - [x] Classes list- [x] **Classes** - `admin/classes.php` - [x] Filter by department - [x] Assign to classes - [x] Delete teacher - [x] Edit teacher details - [x] Add teacher form - [x] Teachers list- [x] **Teachers** - `admin/teachers.php` - [x] Export as CSV/Excel - [x] Search functionality - [x] Filter by class/status - [x] Delete student (with confirmation) - [x] Edit student details - [x] Add student form - [x] Student list with pagination- [x] **Students** - `admin/students.php` - [x] Responsive design - [x] Quick links to key pages - [x] At-risk students list - [x] Recent attendance records - [x] Today's attendance summary - [x] KPI cards (students, teachers, classes)- [x] **Dashboard** - `admin/dashboard.php`### **Phase 1: Core Pages** (Minimum Viable Owner Setup)## 📋 Implementation Checklist---| 26 | Security Logs | `admin/security-logs.php` | ⚠️ CHECK | 🟢 OPTIONAL || 25 | Facilities | `admin/facilities.php` | ⚠️ CHECK | 🟢 OPTIONAL || 24 | Events | `admin/events.php` | ⚠️ CHECK | 🟢 OPTIONAL || 23 | Team Selection | `admin/team-selection.php` | ⚠️ CHECK | 🟢 OPTIONAL || 22 | Enhanced Analytics | `admin/enhanced-analytics.php` | ⚠️ CHECK | 🟢 OPTIONAL || 21 | Backup & Export | `admin/backup-export.php` | ⚠️ CHECK | 🟢 OPTIONAL ||---|-----------|------|--------|----------|| # | Page Name | File | Status | Priority |### **Optional Pages** (If Available)| 20 | Transport Mgmt | `admin/transport-management.php` | ✅ CREATED | 🟡 MEDIUM || 19 | Library Mgmt | `admin/library-management.php` | ⚠️ CHECK | 🟡 MEDIUM || 18 | Activity Monitor | `admin/activity-monitor.php` | ✅ CREATED | 🟡 MEDIUM || 17 | Bulk Import | `admin/bulk-import.php` | ✅ CREATED | 🟡 MEDIUM || 16 | AI User Creator | `admin/ai-user-creator.php` | ✅ CREATED | 🟡 MEDIUM || 15 | Announcements | `admin/announcements.php` | ⚠️ CHECK | 🟡 MEDIUM || 14 | Financial Mgmt | `admin/financial-management.php` | ✅ CREATED | 🟠 HIGH || 13 | Overview/Analytics | `admin/overview.php` | ⚠️ CHECK | 🟠 HIGH ||---|-----------|------|--------|----------|| # | Page Name | File | Status | Priority |### **Important Pages** (Highly Recommended)| 12 | Settings | `admin/settings.php` | ✅ CREATED | 🟠 HIGH || 11 | Role Management | `admin/role-management.php` | ✅ CREATED | 🟠 HIGH || 10 | Users Management | `admin/users.php` | ✅ CREATED | 🟠 HIGH || 9 | System Health | `admin/system-health.php` | ✅ CREATED | 🟠 HIGH || 8 | Reports | `admin/reports.php` | ✅ CREATED | 🔴 CRITICAL || 7 | Approve Users | `admin/approve-users.php` | ✅ CREATED | 🔴 CRITICAL || 6 | Attendance | `admin/attendance.php` | ✅ CREATED | 🔴 CRITICAL || 5 | Enrollment | `admin/class-enrollment.php` | ✅ CREATED | 🔴 CRITICAL || 4 | Classes | `admin/classes.php` | ✅ CREATED | 🔴 CRITICAL || 3 | Teachers | `admin/teachers.php` | ✅ EXISTS | 🔴 CRITICAL || 2 | Students | `admin/students.php` | ✅ EXISTS | 🔴 CRITICAL || 1 | Dashboard | `admin/dashboard.php` | ✅ EXISTS | 🔴 CRITICAL ||---|-----------|------|--------|----------|| # | Page Name | File | Status | Priority |### **Critical Pages** (Must Exist)## ✅ Required Pages Status---`└─ Logout├─ Dark Mode Toggle├─ Change Password├─ Help & Support├─ My Profile → settings.phpMenu Options:└─────────────────────────────────────────┘│             (if available)               ││  Dashboard  | Messages | Profile | Menu ▼│┌─────────────────────────────────────────┐`**Top right corner when logged in as Owner**:### **Header Action Buttons**`└─ At-Risk Students (high absence - click to view details)├─ Recent Attendance Records (table - clickable rows)Recent Sections:students.php      teachers.php      classes.php      attendance.php   ↓                  ↓                  ↓                  ↓└──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘│              │  │              │  │ Classes      │  │ Attendance   ││ Add Student  │  │ Add Teacher  │  │ Manage       │  │ View         │┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐Quick Action Cards (Below):│ [Count]          │ [Count]          │ [Count]       │ [Percentage]     ││ Total Students    │ Total Teachers   │ Total Classes │ Today Attendance │KPI Cards (Top Row):└─────────────────────────────────────────┘│  Manage your institution                ││  Welcome, [Owner Name]!                 │┌─────────────────────────────────────────┐`**Located on main dashboard** with clickable areas:### **Dashboard Quick Action Cards**`└─ Logout → logout.phpLOGOUT└─ AI Center (if available)├─ Analytics → enhanced-analytics.php├─ System Health → system-health.phpSYSTEM└─ Settings → settings.php├─ Activity Monitor → activity-monitor.php├─ Role Management → role-management.php├─ User Management → user-management.php├─ Backup & Export → backup-export.php├─ Bulk Import → bulk-import.php├─ AI User Creator → ai-user-creator.phpADMIN TOOLS└─ Resource Management├─ Transport System → transport-management.php├─ Library System → library-management.phpRESOURCES└─ Budgets (if available)├─ Financial Management → financial-management.php├─ Fee Management → fee-management.phpFINANCE└─ Facilities → facilities.php├─ Emergency Alerts → emergency-alerts.php├─ Events → events.php├─ Announcements → announcements.phpOPERATIONS└─ Reports → reports.php├─ Enrollment → class-enrollment.php├─ Attendance → attendance.php├─ Classes → classes.phpACADEMIC└─ All Users → users.php├─ Parents → parents.php├─ Teachers → teachers.php├─ Students → students.phpPEOPLE└─ Team Selection → team-selection.php├─ Overview → overview.php (analytics)├─ Dashboard → dashboard.phpMAIN└─────────────────────────────────┘│  (Owner/Principal/Admin View)   ││  SAMS ADMIN PANEL               │┌─────────────────────────────────┐`**Sidebar Navigation** (via `includes/sidebar-nav.php`):### **Primary Dashboard (`admin/dashboard.php`) - Quick Links**## 🔘 Navigation Buttons & Links------ - Export capabilities - Predictive insights - Trend analysis - Data visualization - Custom report builder- **Features**:- **Purpose**: Advanced institutional analytics- **Icon**: `auto_graph` (Material Symbols)- **Status**: ⚠️ CHECK IF EXISTS- **File**: `admin/enhanced-analytics.php`#### 2️⃣3️⃣ **Advanced Analytics** (for deeper insights)--- - Archive announcements - View announcement history - Schedule announcement - Target audience (teachers/students/parents) - Create announcement- **Features**:- **Purpose**: Create institution-wide announcements- **Icon**: `campaign` (Material Symbols)- **Status**: ⚠️ CHECK IF EXISTS- **File**: `admin/announcements.php` or `admin/announcements-system.php`#### 2️⃣2️⃣ **Announcements**---- **Note**: May be multi-tenant specific (skip for single-school Owner)- **Purpose**: Quick selection of schools/departments- **Icon**: `groups` (Material Symbols)- **Status**: ⚠️ CHECK IF EXISTS- **File**: `admin/team-selection.php`#### 2️⃣1️⃣ **Team Selection**### **Advanced Management Pages** (Optional for Owner)--- - Key indicators - Performance metrics - Trends analysis - Charting capabilities - Statistical overview- **Features**:- **Purpose**: Institutional analytics dashboard- **Icon**: `pie_chart` (Material Symbols)- **Status**: ⚠️ CHECK IF EXISTS- **File**: `admin/overview.php` or `admin/enhanced-analytics.php`#### 2️⃣0️⃣ **Overview / Analytics**--- - Export activity logs - Data access logs - Permission changes - Login/logout tracking - Data modification history - User activity log- **Features**:- **Purpose**: Track system activities and changes- **Icon**: `history` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/activity-monitor.php` or `admin/audit-logs.php`#### 1️⃣9️⃣ **Activity Logs / Audit Trail**--- - Backup history - Scheduled backups (if available) - Restore functionality - Download backups - Selective exports - Full institutional backup- **Features**:- **Purpose**: Institutional data backup and export- **Icon**: `download` (Material Symbols)- **Status**: ⚠️ CHECK IF EXISTS- **File**: `admin/backup-export.php`#### 1️⃣8️⃣ **Backup & Export**---- Enrollments- Classes- Parents- Teachers- Students**Import Types**: - Import confirmation - Error reporting - Preview before import - Field mapping - Data validation - CSV upload- **Features**:- **Purpose**: Import large datasets (CSV)- **Icon**: `file_upload` (Material Symbols)- **Status**: ✅ CREATED (for admin role)- **File**: `admin/bulk-import.php`#### 1️⃣7️⃣ **Bulk Import**--- - Batch user creation - Validation before creation - Auto-role assignment - Data extraction - Google Forms integration- **Features**:- **Purpose**: Bulk create users via AI and forms- **Icon**: `psychology` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/ai-user-creator.php`#### 1️⃣6️⃣ **AI User Creator**### **Data & System Management Pages**--- - Transport reports - Driver management - Student assignments - Vehicle fleet overview - Routes management- **Features**:- **Purpose**: School transport oversight- **Icon**: `transit_entitlements` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/transport-management.php`#### 1️⃣5️⃣ **Transport Management**--- - Library reports - System health - Library staff access - Overdue books - Active loans - Total books in library- **Features**:- **Purpose**: Library resource overview- **Icon**: `menu_book` (Material Symbols)- **Status**: ⚠️ CHECK IF EXISTS- **File**: `admin/library-management.php`#### 1️⃣4️⃣ **Library Management**--- - Budget overview - Expense tracking - Bursar/Accountant performance - Financial trends - Fee structure overview - Payment collection status - Revenue summary- **Features**:- **Purpose**: Institutional financial reporting and oversight- **Icon**: `currency_exchange` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/financial-management.php`#### 1️⃣3️⃣ **Financial Management**### **Financial & Operational Pages** (For Owner oversight)---- Linked devices- Account status- Last login info- Member since date**Account Tab**:- Color scheme customization- Font size preferences- Theme selection (light/dark mode)**Appearance Tab**:- System notifications- Alert preferences- Email notification toggles**Notifications Tab**:- Active sessions- Login activity- Password history- Change password- Current password verification**Security Tab**:- Bio/About- Address- Phone number- Email address- Full name**Profile Tab**:**Configuration Sections**: - Theme selection (light/dark/auto) - Two-factor authentication - Password management - Email configuration - Theme/appearance - Notification preferences - Security settings - Profile settings- **Features**:- **Purpose**: Institution-wide configuration- **Icon**: `settings` (Material Symbols)- **Status**: ✅ CREATED (1,328 lines)- **File**: `admin/settings.php`#### 1️⃣2️⃣ **Settings**---- Understand role hierarchy- View permission definitions- Assign teacher role- Assign admin role to staff**Typical Actions**: - (Custom role creation may be restricted to super admin) - View role permissions (informational) - Assign roles to users - View system roles (standard)- **Features**:- **Note**: For single-school context, not institution-to-institution- **Purpose**: Configure roles and permissions (institutional)- **Icon**: `lock` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/role-management.php`#### 1️⃣1️⃣ **Role Management**---- View activity log- Update phone/address- Change email- Enable/Disable account- Set password- Assign role- Create user form**User Management Actions**: - Search by name/email - Filter by role/status - Export user list - Delete user (with confirmation) - Deactivate/Activate user - Reset user password - Change user role - Edit user details - Create new user - List all users (institutional only)- **Features**:- **Purpose**: Manage all institutional users- **Icon**: `people` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/users.php` or `admin/user-management.php`#### 🔟 **User Management** (Institution-wide)---- 🔴 Red: Critical (immediate action needed)- 🟡 Yellow: Warning (may need attention)- 🟢 Green: All systems optimal**Health Indicators**: - System recommendations - Error logs summary - Status of key modules - Max execution time - Upload file limit - Disk space - Database size - Server memory usage - PHP version- **Features**:- **Purpose**: Monitor institutional system performance- **Icon**: `favorite` (Material Symbols - heart)- **Status**: ✅ CREATED- **File**: `admin/system-health.php`#### 9️⃣ **System Health / Institutional Health**---- Institutional statistics (overview)- Teacher workload- Class enrollment summary- At-risk students- Attendance by student- Attendance by class**Available Reports**: - Date range filtering - Schedule reports - Export to CSV/PDF - Financial summaries - Student activity reports - Class-wise statistics - Performance summaries - Attendance reports- **Features**:- **Purpose**: Generate institutional reports- **Icon**: `chart_line` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/reports.php`#### 8️⃣ **Reports & Analytics**---- Documents (if applicable)- Email verified status- Date registered- Role requested- Email- Name**User Verification Fields**: - Resend activation email - View rejection reasons - Bulk approve/reject - Send approval/rejection email - Reject user button - Approve user button - View user details before approval - List pending users- **Features**:- **Purpose**: Verify and approve new user registrations- **Icon**: `person_check` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/approve-users.php`#### 7️⃣ **Approve Users**---- At-risk students (>20% absent)- Weekly/Monthly attendance- Student attendance summary- Daily attendance rate**Reports Available**: - Filter by date range/class - Export attendance data - View attendance trends - Identify absent students - Generate attendance reports - Edit recorded attendance - Mark attendance - View attendance by class- **Features**:- **Purpose**: View and manage attendance records- **Icon**: `fact_check` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/attendance.php`#### 6️⃣ **Attendance Tracking**---- Notes- Status (active/inactive)- Enrollment date- Select class- Select student**Enrollment Form**: - Filter by class/status - Export enrollment list - View enrollment history - Change student class - Remove student from class - Bulk enroll (CSV upload) - Enroll student in class - List all enrollments- **Features**:- **Purpose**: Manage student-to-class enrollments- **Icon**: `group_add` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/class-enrollment.php`#### 5️⃣ **Class Enrollment**---- Status (active/inactive)- Academic year- Form teacher assignment- Room number- Capacity (max students)- Section/Stream- Grade level- Class name (e.g., "Form 3A")**Class Form Fields**: - Export class list - Delete class (with confirmation) - View enrolled students - Set class capacity - Assign form teacher - Edit class details - Create new class - List all classes- **Features**:- **Purpose**: Class structure and organization- **Icon**: `meeting_room` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/classes.php`#### 4️⃣ **Classes Management**---- Contact information- View performance metrics- Change status (active/leave/resigned)- View taught classes- Assign subjects- Assign to class (as form teacher)- Edit qualifications**Actions Per Teacher**: - Filter by department/qualification - Export teacher list - Delete teacher - Change employment status - View experience details - Set specializations - Assign to classes - Edit teacher profile - Add new teacher - List all teachers- **Features**:- **Purpose**: Teacher roster and assignments- **Icon**: `person` (Material Symbols)- **Status**: ✅ EXISTS- **File**: `admin/teachers.php`#### 3️⃣ **Teachers Management**---- Send notice to parents- View grades- View attendance history- Delete account- Deactivate/Reactivate- Change class assignment- Edit details- View profile**Actions Per Student**: - Search by name/admission number - Filter by class, status, gender - Export student list (CSV/Excel) - Bulk import students - Change student status - View student profile - Assign to classes - Delete student (with confirmation) - Edit student details - Add new student button - List all students- **Features**:- **Purpose**: Student roster and management- **Icon**: `school` (Material Symbols)- **Status**: ✅ EXISTS- **File**: `admin/students.php`#### 2️⃣ **Students Management**--- - Academic year selector - Quick links to key pages - Recent attendance records - At-risk students list - Today's attendance summary - KPI cards (students, teachers, classes)- **Features**:- **Purpose**: Main entry point and overview- **Icon**: `dashboard` (Material Symbols)- **Status**: ✅ CREATED- **File**: `admin/dashboard.php`#### 1️⃣ **Dashboard**### **Core Management Pages** (Essential for Owner)## 🗂️ Page Inventory---| **Dashboard** | admin/dashboard.php | admin/dashboard.php | admin/dashboard.php | super-admin-dashboard.php || **Can Run Reports** | ✅ | ✅ | ✅ | ✅ || **Can Delete Users** | ✅ | ✅ | ✅ | ✅ || **Can Disable Modules** | ✅ | ✅ | ❌ | ✅ || **Full Financial Control** | ✅ | 📋 | 📋 | ✅ || **Can Switch Schools** | ❌ | ❌ | ❌ | ✅ || **Can Create Schools** | ❌ | ❌ | ❌ | ✅ || **Hierarchy** | 7.5 | 7 | 6 | 8 || **Scope** | Single School | Single School | Single School | All Schools ||---------|-------|-----------|-------|-------------|| Feature | Owner | Principal | Admin | Super Admin |**Key Differences from Other Roles**:- Business owner (non-academic)- Board chairman- Institution owner- School founder- School proprietor**Typical Users**:**Can Modify Institution Settings**: YES**Can Create Sub-Admins**: YES (can delegate to principal/admin) **Multi-Tenant Access**: NO (Fixed to their school) **Institutional Scope**: Single School Only **Hierarchy Level**: 7.5/8 ### **OWNER Role Definition**## 🏛️ Role Context & Scope---- Risk students (high absenteeism)- Present/Late/Absent counts for today- Today's Attendance Rate (%)- Total Parents (active only)- Total Classes (active only)- Total Teachers (active only)- Total Students (active only)**Key Statistics Shown**:- Role-appropriate menu in sidebar- Academic year display- Quick action cards linking to key pages- At-risk students list (>20% absent in last 30 days)- Recent attendance records (last 10 entries)- Today's attendance summary with percentages- 4-6 KPI stat cards (students, teachers, classes, attendance rate, etc.)- Welcome banner with personalized greeting**Dashboard Features**:**Template**: Master Dashboard Layout with Stitch Academic Sentinel UI**UI Framework**: Tailwind CSS + Material Symbols **Current Status**: ✅ **IMPLEMENTED** **File**: `/admin/dashboard.php` ### Primary Dashboard: `admin/dashboard.php`## 🎯 Dashboard Overview---8. [Owner vs Admin vs Principal](#owner-vs-admin-vs-principal)7. [Permission Model](#permission-model)6. [Implementation Checklist](#implementation-checklist)5. [Required Pages Status](#required-pages-status)4. [Navigation Buttons & Links](#navigation-buttons--links)3. [Page Inventory](#page-inventory)2. [Role Context & Scope](#role-context--scope)1. [Dashboard Overview](#dashboard-overview)## 📋 Table of Contents---**Last Updated**: March 31, 2026**Permission Level**: 7.5/8 (Highest single-school role) **Dashboard URL**: `/attendance/admin/dashboard.php` **Scope**: Full institutional control **Access Level**: Single Institution (School) **Role**: Owner / Institution Proprietor
**Role**: Owner / Institution Proprietor
**Access Level**: Single Institution (School)
**Scope**: Full institutional control
**Dashboard URL**: `/attendance/admin/dashboard.php`
**Permission Level**: 7.5/8 (Highest single-school role)
**Last Updated**: March 31, 2026

---

## 📋 Table of Contents

1. [Dashboard Overview](#dashboard-overview)
2. [Role Context & Scope](#role-context--scope)
3. [Page Inventory](#page-inventory)
4. [Navigation Buttons & Links](#navigation-buttons--links)
5. [Required Pages Status](#required-pages-status)
6. [Implementation Checklist](#implementation-checklist)
7. [Permission Model](#permission-model)
8. [Owner vs Admin vs Principal](#owner-vs-admin-vs-principal)

---

## 🎯 Dashboard Overview

### Primary Dashboard: `admin/dashboard.php`

**File**: `/admin/dashboard.php`
**Current Status**: ✅ **IMPLEMENTED**
**UI Framework**: Tailwind CSS + Material Symbols
**Template**: Master Dashboard Layout with Stitch Academic Sentinel UI

**Dashboard Features**:

- Welcome banner with personalized greeting
- 4-6 KPI stat cards (students, teachers, classes, attendance rate, etc.)
- Today's attendance summary with percentages
- Recent attendance records (last 10 entries)
- At-risk students list (>20% absent in last 30 days)
- Quick action cards linking to key pages
- Academic year display
- Role-appropriate menu in sidebar

**Key Statistics Shown**:

- Total Students (active only)
- Total Teachers (active only)
- Total Classes (active only)
- Total Parents (active only)
- Today's Attendance Rate (%)
- Present/Late/Absent counts for today
- Risk students (high absenteeism)

---

## 🏛️ Role Context & Scope

### **OWNER Role Definition**

**Hierarchy Level**: 7.5/8
**Institutional Scope**: Single School Only
**Multi-Tenant Access**: NO (Fixed to their school)
**Can Create Sub-Admins**: YES (can delegate to principal/admin)
**Can Modify Institution Settings**: YES

**Typical Users**:

- School proprietor
- School founder
- Institution owner
- Board chairman
- Business owner (non-academic)

**Key Differences from Other Roles**:

| Feature                    | Owner               | Principal           | Admin               | Super Admin               |
| -------------------------- | ------------------- | ------------------- | ------------------- | ------------------------- |
| **Scope**                  | Single School       | Single School       | Single School       | All Schools               |
| **Hierarchy**              | 7.5                 | 7                   | 6                   | 8                         |
| **Can Create Schools**     | ❌                  | ❌                  | ❌                  | ✅                        |
| **Can Switch Schools**     | ❌                  | ❌                  | ❌                  | ✅                        |
| **Full Financial Control** | ✅                  | 📋                  | 📋                  | ✅                        |
| **Can Disable Modules**    | ✅                  | ✅                  | ❌                  | ✅                        |
| **Can Delete Users**       | ✅                  | ✅                  | ✅                  | ✅                        |
| **Can Run Reports**        | ✅                  | ✅                  | ✅                  | ✅                        |
| **Dashboard**              | admin/dashboard.php | admin/dashboard.php | admin/dashboard.php | super-admin-dashboard.php |

---

## 🗂️ Page Inventory

### **Core Management Pages** (Essential for Owner)

#### 1️⃣ **Dashboard**

- **File**: `admin/dashboard.php`
- **Status**: ✅ CREATED
- **Icon**: `dashboard` (Material Symbols)
- **Purpose**: Main entry point and overview
- **Features**:
  - KPI cards (students, teachers, classes)
  - Today's attendance summary
  - At-risk students list
  - Recent attendance records
  - Quick links to key pages
  - Academic year selector

---

#### 2️⃣ **Students Management**

- **File**: `admin/students.php`
- **Status**: ✅ EXISTS
- **Icon**: `school` (Material Symbols)
- **Purpose**: Student roster and management
- **Features**:
  - List all students
  - Add new student button
  - Edit student details
  - Delete student (with confirmation)
  - Assign to classes
  - View student profile
  - Change student status
  - Bulk import students
  - Export student list (CSV/Excel)
  - Filter by class, status, gender
  - Search by name/admission number

**Actions Per Student**:

- View profile
- Edit details
- Change class assignment
- Deactivate/Reactivate
- Delete account
- View attendance history
- View grades
- Send notice to parents

---

#### 3️⃣ **Teachers Management**

- **File**: `admin/teachers.php`
- **Status**: ✅ EXISTS
- **Icon**: `person` (Material Symbols)
- **Purpose**: Teacher roster and assignments
- **Features**:
  - List all teachers
  - Add new teacher
  - Edit teacher profile
  - Assign to classes
  - Set specializations
  - View experience details
  - Change employment status
  - Delete teacher
  - Export teacher list
  - Filter by department/qualification

**Actions Per Teacher**:

- Edit qualifications
- Assign to class (as form teacher)
- Assign subjects
- View taught classes
- Change status (active/leave/resigned)
- View performance metrics
- Contact information

---

#### 4️⃣ **Classes Management**

- **File**: `admin/classes.php`
- **Status**: ✅ CREATED
- **Icon**: `meeting_room` (Material Symbols)
- **Purpose**: Class structure and organization
- **Features**:
  - List all classes
  - Create new class
  - Edit class details
  - Assign form teacher
  - Set class capacity
  - View enrolled students
  - Delete class (with confirmation)
  - Export class list

**Class Form Fields**:

- Class name (e.g., "Form 3A")
- Grade level
- Section/Stream
- Capacity (max students)
- Room number
- Form teacher assignment
- Academic year
- Status (active/inactive)

---

#### 5️⃣ **Class Enrollment**

- **File**: `admin/class-enrollment.php`
- **Status**: ✅ CREATED
- **Icon**: `group_add` (Material Symbols)
- **Purpose**: Manage student-to-class enrollments
- **Features**:
  - List all enrollments
  - Enroll student in class
  - Bulk enroll (CSV upload)
  - Remove student from class
  - Change student class
  - View enrollment history
  - Export enrollment list
  - Filter by class/status

**Enrollment Form**:

- Select student
- Select class
- Enrollment date
- Status (active/inactive)
- Notes

---

#### 6️⃣ **Attendance Tracking**

- **File**: `admin/attendance.php`
- **Status**: ✅ CREATED
- **Icon**: `fact_check` (Material Symbols)
- **Purpose**: View and manage attendance records
- **Features**:
  - View attendance by class
  - Mark attendance
  - Edit recorded attendance
  - Generate attendance reports
  - Identify absent students
  - View attendance trends
  - Export attendance data
  - Filter by date range/class

**Reports Available**:

- Daily attendance rate
- Student attendance summary
- Weekly/Monthly attendance
- At-risk students (>20% absent)

---

#### 7️⃣ **Approve Users**

- **File**: `admin/approve-users.php`
- **Status**: ✅ CREATED
- **Icon**: `person_check` (Material Symbols)
- **Purpose**: Verify and approve new user registrations
- **Features**:
  - List pending users
  - View user details before approval
  - Approve user button
  - Reject user button
  - Send approval/rejection email
  - Bulk approve/reject
  - View rejection reasons
  - Resend activation email

**User Verification Fields**:

- Name
- Email
- Role requested
- Date registered
- Email verified status
- Documents (if applicable)

---

#### 8️⃣ **Reports & Analytics**

- **File**: `admin/reports.php`
- **Status**: ✅ CREATED
- **Icon**: `chart_line` (Material Symbols)
- **Purpose**: Generate institutional reports
- **Features**:
  - Attendance reports
  - Performance summaries
  - Class-wise statistics
  - Student activity reports
  - Financial summaries
  - Export to CSV/PDF
  - Schedule reports
  - Date range filtering

**Available Reports**:

- Attendance by class
- Attendance by student
- At-risk students
- Class enrollment summary
- Teacher workload
- Institutional statistics (overview)

---

#### 9️⃣ **System Health / Institutional Health**

- **File**: `admin/system-health.php`
- **Status**: ✅ CREATED
- **Icon**: `favorite` (Material Symbols - heart)
- **Purpose**: Monitor institutional system performance
- **Features**:
  - PHP version
  - Server memory usage
  - Database size
  - Disk space
  - Upload file limit
  - Max execution time
  - Status of key modules
  - Error logs summary
  - System recommendations

**Health Indicators**:

- 🟢 Green: All systems optimal
- 🟡 Yellow: Warning (may need attention)
- 🔴 Red: Critical (immediate action needed)

---

#### 🔟 **User Management** (Institution-wide)

- **File**: `admin/users.php` or `admin/user-management.php`
- **Status**: ✅ CREATED
- **Icon**: `people` (Material Symbols)
- **Purpose**: Manage all institutional users
- **Features**:
  - List all users (institutional only)
  - Create new user
  - Edit user details
  - Change user role
  - Reset user password
  - Deactivate/Activate user
  - Delete user (with confirmation)
  - Export user list
  - Filter by role/status
  - Search by name/email

**User Management Actions**:

- Create user form
- Assign role
- Set password
- Enable/Disable account
- Change email
- Update phone/address
- View activity log

---

#### 1️⃣1️⃣ **Role Management**

- **File**: `admin/role-management.php`
- **Status**: ✅ CREATED
- **Icon**: `lock` (Material Symbols)
- **Purpose**: Configure roles and permissions (institutional)
- **Note**: For single-school context, not institution-to-institution
- **Features**:
  - View system roles (standard)
  - Assign roles to users
  - View role permissions (informational)
  - (Custom role creation may be restricted to super admin)

**Typical Actions**:

- Assign admin role to staff
- Assign teacher role
- View permission definitions
- Understand role hierarchy

---

#### 1️⃣2️⃣ **Settings**

- **File**: `admin/settings.php`
- **Status**: ✅ CREATED (1,328 lines)
- **Icon**: `settings` (Material Symbols)
- **Purpose**: Institution-wide configuration
- **Features**:
  - Profile settings
  - Security settings
  - Notification preferences
  - Theme/appearance
  - Email configuration
  - Password management
  - Two-factor authentication
  - Theme selection (light/dark/auto)

**Configuration Sections**:

**Profile Tab**:

- Full name
- Email address
- Phone number
- Address
- Bio/About

**Security Tab**:

- Current password verification
- Change password
- Password history
- Login activity
- Active sessions

**Notifications Tab**:

- Email notification toggles
- Alert preferences
- System notifications

**Appearance Tab**:

- Theme selection (light/dark mode)
- Font size preferences
- Color scheme customization

**Account Tab**:

- Member since date
- Last login info
- Account status
- Linked devices

---

### **Financial & Operational Pages** (For Owner oversight)

#### 1️⃣3️⃣ **Financial Management**

- **File**: `admin/financial-management.php`
- **Status**: ✅ CREATED
- **Icon**: `currency_exchange` (Material Symbols)
- **Purpose**: Institutional financial reporting and oversight
- **Features**:
  - Revenue summary
  - Payment collection status
  - Fee structure overview
  - Financial trends
  - Bursar/Accountant performance
  - Expense tracking
  - Budget overview

---

#### 1️⃣4️⃣ **Library Management**

- **File**: `admin/library-management.php`
- **Status**: ⚠️ CHECK IF EXISTS
- **Icon**: `menu_book` (Material Symbols)
- **Purpose**: Library resource overview
- **Features**:
  - Total books in library
  - Active loans
  - Overdue books
  - Library staff access
  - System health
  - Library reports

---

#### 1️⃣5️⃣ **Transport Management**

- **File**: `admin/transport-management.php`
- **Status**: ✅ CREATED
- **Icon**: `transit_entitlements` (Material Symbols)
- **Purpose**: School transport oversight
- **Features**:
  - Routes management
  - Vehicle fleet overview
  - Student assignments
  - Driver management
  - Transport reports

---

### **Data & System Management Pages**

#### 1️⃣6️⃣ **AI User Creator**

- **File**: `admin/ai-user-creator.php`
- **Status**: ✅ CREATED
- **Icon**: `psychology` (Material Symbols)
- **Purpose**: Bulk create users via AI and forms
- **Features**:
  - Google Forms integration
  - Data extraction
  - Auto-role assignment
  - Validation before creation
  - Batch user creation

---

#### 1️⃣7️⃣ **Bulk Import**

- **File**: `admin/bulk-import.php`
- **Status**: ✅ CREATED (for admin role)
- **Icon**: `file_upload` (Material Symbols)
- **Purpose**: Import large datasets (CSV)
- **Features**:
  - CSV upload
  - Data validation
  - Field mapping
  - Preview before import
  - Error reporting
  - Import confirmation

**Import Types**:

- Students
- Teachers
- Parents
- Classes
- Enrollments

---

#### 1️⃣8️⃣ **Backup & Export**

- **File**: `admin/backup-export.php`
- **Status**: ⚠️ CHECK IF EXISTS
- **Icon**: `download` (Material Symbols)
- **Purpose**: Institutional data backup and export
- **Features**:
  - Full institutional backup
  - Selective exports
  - Download backups
  - Restore functionality
  - Scheduled backups (if available)
  - Backup history

---

#### 1️⃣9️⃣ **Activity Logs / Audit Trail**

- **File**: `admin/activity-monitor.php` or `admin/audit-logs.php`
- **Status**: ✅ CREATED
- **Icon**: `history` (Material Symbols)
- **Purpose**: Track system activities and changes
- **Features**:
  - User activity log
  - Data modification history
  - Login/logout tracking
  - Permission changes
  - Data access logs
  - Export activity logs

---

#### 2️⃣0️⃣ **Overview / Analytics**

- **File**: `admin/overview.php` or `admin/enhanced-analytics.php`
- **Status**: ⚠️ CHECK IF EXISTS
- **Icon**: `pie_chart` (Material Symbols)
- **Purpose**: Institutional analytics dashboard
- **Features**:
  - Statistical overview
  - Charting capabilities
  - Trends analysis
  - Performance metrics
  - Key indicators

---

### **Advanced Management Pages** (Optional for Owner)

#### 2️⃣1️⃣ **Team Selection**

- **File**: `admin/team-selection.php`
- **Status**: ⚠️ CHECK IF EXISTS
- **Icon**: `groups` (Material Symbols)
- **Purpose**: Quick selection of schools/departments
- **Note**: May be multi-tenant specific (skip for single-school Owner)

---

#### 2️⃣2️⃣ **Announcements**

- **File**: `admin/announcements.php` or `admin/announcements-system.php`
- **Status**: ⚠️ CHECK IF EXISTS
- **Icon**: `campaign` (Material Symbols)
- **Purpose**: Create institution-wide announcements
- **Features**:
  - Create announcement
  - Target audience (teachers/students/parents)
  - Schedule announcement
  - View announcement history
  - Archive announcements

---

#### 2️⃣3️⃣ **Advanced Analytics** (for deeper insights)

- **File**: `admin/enhanced-analytics.php`
- **Status**: ⚠️ CHECK IF EXISTS
- **Icon**: `auto_graph` (Material Symbols)
- **Purpose**: Advanced institutional analytics
- **Features**:
  - Custom report builder
  - Data visualization
  - Trend analysis
  - Predictive insights
  - Export capabilities

---

---

## 🔘 Navigation Buttons & Links

### **Primary Dashboard (`admin/dashboard.php`) - Quick Links**

**Sidebar Navigation** (via `includes/sidebar-nav.php`):

```
┌─────────────────────────────────┐
│  SAMS ADMIN PANEL               │
│  (Owner/Principal/Admin View)   │
└─────────────────────────────────┘

MAIN
├─ Dashboard → dashboard.php
├─ Overview → overview.php (analytics)
└─ Team Selection → team-selection.php

PEOPLE
├─ Students → students.php
├─ Teachers → teachers.php
├─ Parents → parents.php
└─ All Users → users.php

ACADEMIC
├─ Classes → classes.php
├─ Attendance → attendance.php
├─ Enrollment → class-enrollment.php
└─ Reports → reports.php

OPERATIONS
├─ Announcements → announcements.php
├─ Events → events.php
├─ Emergency Alerts → emergency-alerts.php
└─ Facilities → facilities.php

FINANCE
├─ Fee Management → fee-management.php
├─ Financial Management → financial-management.php
└─ Budgets (if available)

RESOURCES
├─ Library System → library-management.php
├─ Transport System → transport-management.php
└─ Resource Management

ADMIN TOOLS
├─ AI User Creator → ai-user-creator.php
├─ Bulk Import → bulk-import.php
├─ Backup & Export → backup-export.php
├─ User Management → user-management.php
├─ Role Management → role-management.php
├─ Activity Monitor → activity-monitor.php
└─ Settings → settings.php

SYSTEM
├─ System Health → system-health.php
├─ Analytics → enhanced-analytics.php
└─ AI Center (if available)

LOGOUT
└─ Logout → logout.php
```

### **Dashboard Quick Action Cards**

**Located on main dashboard** with clickable areas:

```
┌─────────────────────────────────────────┐
│  Welcome, [Owner Name]!                 │
│  Manage your institution                │
└─────────────────────────────────────────┘

KPI Cards (Top Row):
│ Total Students    │ Total Teachers   │ Total Classes │ Today Attendance │
│ [Count]          │ [Count]          │ [Count]       │ [Percentage]     │

Quick Action Cards (Below):
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ Add Student  │  │ Add Teacher  │  │ Manage       │  │ View         │
│              │  │              │  │ Classes      │  │ Attendance   │
└──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘
   ↓                  ↓                  ↓                  ↓
students.php      teachers.php      classes.php      attendance.php

Recent Sections:
├─ Recent Attendance Records (table - clickable rows)
└─ At-Risk Students (high absence - click to view details)
```

### **Header Action Buttons**

**Top right corner when logged in as Owner**:

```
┌─────────────────────────────────────────┐
│  Dashboard  | Messages | Profile | Menu ▼│
│             (if available)               │
└─────────────────────────────────────────┘

Menu Options:
├─ My Profile → settings.php
├─ Help & Support
├─ Change Password
├─ Dark Mode Toggle
└─ Logout
```

---

## ✅ Required Pages Status

### **Critical Pages** (Must Exist)

| #   | Page Name        | File                         | Status     | Priority    |
| --- | ---------------- | ---------------------------- | ---------- | ----------- |
| 1   | Dashboard        | `admin/dashboard.php`        | ✅ EXISTS  | 🔴 CRITICAL |
| 2   | Students         | `admin/students.php`         | ✅ EXISTS  | 🔴 CRITICAL |
| 3   | Teachers         | `admin/teachers.php`         | ✅ EXISTS  | 🔴 CRITICAL |
| 4   | Classes          | `admin/classes.php`          | ✅ CREATED | 🔴 CRITICAL |
| 5   | Enrollment       | `admin/class-enrollment.php` | ✅ CREATED | 🔴 CRITICAL |
| 6   | Attendance       | `admin/attendance.php`       | ✅ CREATED | 🔴 CRITICAL |
| 7   | Approve Users    | `admin/approve-users.php`    | ✅ CREATED | 🔴 CRITICAL |
| 8   | Reports          | `admin/reports.php`          | ✅ CREATED | 🔴 CRITICAL |
| 9   | System Health    | `admin/system-health.php`    | ✅ CREATED | 🟠 HIGH     |
| 10  | Users Management | `admin/users.php`            | ✅ CREATED | 🟠 HIGH     |
| 11  | Role Management  | `admin/role-management.php`  | ✅ CREATED | 🟠 HIGH     |
| 12  | Settings         | `admin/settings.php`         | ✅ CREATED | 🟠 HIGH     |

### **Important Pages** (Highly Recommended)

| #   | Page Name          | File                             | Status     | Priority  |
| --- | ------------------ | -------------------------------- | ---------- | --------- |
| 13  | Overview/Analytics | `admin/overview.php`             | ⚠️ CHECK   | 🟠 HIGH   |
| 14  | Financial Mgmt     | `admin/financial-management.php` | ✅ CREATED | 🟠 HIGH   |
| 15  | Announcements      | `admin/announcements.php`        | ⚠️ CHECK   | 🟡 MEDIUM |
| 16  | AI User Creator    | `admin/ai-user-creator.php`      | ✅ CREATED | 🟡 MEDIUM |
| 17  | Bulk Import        | `admin/bulk-import.php`          | ✅ CREATED | 🟡 MEDIUM |
| 18  | Activity Monitor   | `admin/activity-monitor.php`     | ✅ CREATED | 🟡 MEDIUM |
| 19  | Library Mgmt       | `admin/library-management.php`   | ⚠️ CHECK   | 🟡 MEDIUM |
| 20  | Transport Mgmt     | `admin/transport-management.php` | ✅ CREATED | 🟡 MEDIUM |

### **Optional Pages** (If Available)

| #   | Page Name          | File                           | Status   | Priority    |
| --- | ------------------ | ------------------------------ | -------- | ----------- |
| 21  | Backup & Export    | `admin/backup-export.php`      | ⚠️ CHECK | 🟢 OPTIONAL |
| 22  | Enhanced Analytics | `admin/enhanced-analytics.php` | ⚠️ CHECK | 🟢 OPTIONAL |
| 23  | Team Selection     | `admin/team-selection.php`     | ⚠️ CHECK | 🟢 OPTIONAL |
| 24  | Events             | `admin/events.php`             | ⚠️ CHECK | 🟢 OPTIONAL |
| 25  | Facilities         | `admin/facilities.php`         | ⚠️ CHECK | 🟢 OPTIONAL |
| 26  | Security Logs      | `admin/security-logs.php`      | ⚠️ CHECK | 🟢 OPTIONAL |

---

## 📋 Implementation Checklist

### **Phase 1: Core Pages** (Minimum Viable Owner Setup)

- [x] **Dashboard** - `admin/dashboard.php`
  - [x] KPI cards (students, teachers, classes)
  - [x] Today's attendance summary
  - [x] Recent attendance records
  - [x] At-risk students list
  - [x] Quick links to key pages
  - [x] Responsive design

- [x] **Students** - `admin/students.php`
  - [x] Student list with pagination
  - [x] Add student form
  - [x] Edit student details
  - [x] Delete student (with confirmation)
  - [x] Filter by class/status
  - [x] Search functionality
  - [x] Export as CSV/Excel

- [x] **Teachers** - `admin/teachers.php`
  - [x] Teachers list
  - [x] Add teacher form
  - [x] Edit teacher details
  - [x] Delete teacher
  - [x] Assign to classes
  - [x] Filter by department

- [x] **Classes** - `admin/classes.php`
  - [x] Classes list
  - [x] Create new class
  - [x] Edit class details
  - [x] Assign form teacher
  - [x] Delete class

- [x] **Enrollment** - `admin/class-enrollment.php`
  - [x] List enrollments
  - [x] Enroll student in class
  - [x] Bulk enroll from CSV
  - [x] Remove enrollment
  - [x] Change student class

- [x] **Attendance** - `admin/attendance.php`
  - [x] Mark attendance
  - [x] View attendance summary
  - [x] Generate reports
  - [x] Export attendance records
  - [x] Filter by date/class

- [x] **Approve Users** - `admin/approve-users.php`
  - [x] List pending users
  - [x] Approve button
  - [x] Reject button
  - [x] Bulk actions

- [x] **Reports** - `admin/reports.php`
  - [x] Generate attendance reports
  - [x] Generate performance reports
  - [x] Export to PDF/Excel
  - [x] Date range filtering

### **Phase 2: Administrative Pages**

- [x] **System Health** - `admin/system-health.php`
  - [x] Server metrics display
  - [x] Health status indicators
  - [x] System recommendations

- [x] **Users Management** - `admin/users.php`
  - [x] All users list
  - [x] Create user
  - [x] Edit user
  - [x] Change role
  - [x] Reset password
  - [x] Deactivate/Activate

- [x] **Role Management** - `admin/role-management.php`
  - [x] List system roles
  - [x] View permissions per role
  - [x] Assign roles to users
  - [x] Understand role hierarchy

- [x] **Settings** - `admin/settings.php`
  - [x] Profile settings tab
  - [x] Security settings tab
  - [x] Notifications tab
  - [x] Appearance/Theme tab
  - [x] Account tab
  - [x] Save changes

### **Phase 3: Operational Pages**

- [ ] **Overview/Analytics** - `admin/overview.php`
  - [ ] Statistical summary
  - [ ] Charts and graphs
  - [ ] Trend analysis
  - [ ] Date range selectors

- [x] **Financial Management** - `admin/financial-management.php`
  - [x] Revenue summary
  - [x] Payment tracking
  - [x] Fee structure overview
  - [x] Financial reports

- [x] **AI User Creator** - `admin/ai-user-creator.php`
  - [x] Form integration
  - [x] Data extraction
  - [x] Bulk creation
  - [x] Validation

- [x] **Bulk Import** - `admin/bulk-import.php`
  - [x] CSV upload
  - [x] Field mapping
  - [x] Preview
  - [x] Validation errors

- [x] **Activity Monitor** - `admin/activity-monitor.php`
  - [x] User activity log
  - [x] Modification history
  - [x] Login tracking
  - [x] Export logs

### **Phase 4: Optional/Enhanced Pages**

- [ ] **Announcements** - `admin/announcements.php`
  - [ ] Create announcement
  - [ ] Target audience
  - [ ] Schedule sending
  - [ ] Archive management

- [ ] **Library Management** - `admin/library-management.php`
  - [ ] Book catalog view
  - [ ] Lending system
  - [ ] Overdue tracking

- [x] **Transport Management** - `admin/transport-management.php`
  - [x] Routes overview
  - [x] Vehicles list
  - [x] Student assignments

- [ ] **Backup & Export** - `admin/backup-export.php`
  - [ ] Full backup option
  - [ ] Selective exports
  - [ ] Download management
  - [ ] Restore functionality

---

## 🔐 Permission Model

### **Owner Permissions** (Institution-Scoped)

```
✅ ALLOWED (Owner Can):
├─ Create/modify/delete students
├─ Create/modify/delete teachers
├─ Create/modify/delete classes
├─ Mark attendance
├─ Generate reports
├─ Create announcements
├─ Manage user accounts
├─ Assign roles to staff
├─ Approve user registrations
├─ Configure institution settings
├─ View all institutional data
├─ Run audits/activity logs
├─ Export institutional data
├─ Manage finances (overview)
├─ Configure fee structure
├─ Create backups
└─ Reset passwords for users

❌ NOT ALLOWED (Owner Cannot):
├─ Create new schools/tenants
├─ Access other schools
├─ Create Super Admin users
├─ Modify platform settings
├─ Create custom roles (system-wide)
├─ Access platform analytics
├─ Modify multi-tenant config
├─ Switch institution context
└─ Access super-admin dashboard
```

---

## 👥 Owner vs Admin vs Principal

### **Permission Comparison**

| Capability               | Owner        | Principal      | Admin          |
| ------------------------ | ------------ | -------------- | -------------- |
| **User Management**      | ✅ Full      | ✅ Full        | ✅ Full        |
| **Student Registration** | ✅ Full      | ✅ Full        | ✅ Full        |
| **Teacher Management**   | ✅ Full      | ✅ Full        | ✅ Full        |
| **Class Management**     | ✅ Full      | ✅ Full        | ✅ Full        |
| **Attendance**           | ✅ View Only | ✅ View/Manage | ✅ View/Manage |
| **Financial**            | ✅ Full      | 📋 Overview    | 📋 Assist      |
| **Role Assignment**      | ✅ Full      | ✅ Full        | ❌ Limited     |
| **Backup/Restore**       | ✅ Yes       | ❌ No          | ❌ No          |
| **View Audit Logs**      | ✅ Yes       | ✅ Yes         | ✅ Yes         |
| **Delete Users**         | ✅ Yes       | ✅ Yes         | ✅ Yes         |
| **System Settings**      | ✅ Full      | ✅ Partial     | ⚠️ Read-Only   |

### **Key Differences**

**Owner**:

- Business/Legal owner of institution
- Highest authority within school
- Can delegate all tasks
- Sees financial overview
- Can create backups
- Makes strategic decisions

**Principal**:

- Educational leader
- Focuses on academics
- Manages discipline
- Oversees quality
- Day-to-day governance
- Reports to Owner

**Admin**:

- Operational support
- Assists Principal
- Manages day-to-day operations
- Limited financial access
- Cannot create backups
- Follows Principal's direction

---

## 🛡️ Security & Access Control

### **Session Management**

```php
// At top of all admin pages
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?error=not_logged_in');
    exit;
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['owner', 'admin', 'principal', 'super_admin'])) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

// Check tenant_id for single-school restriction
if (empty($_SESSION['tenant_id'])) {
    header('Location: ../login.php?error=no_institution');
    exit;
}
```

### **Data Isolation Pattern**

```php
// All queries must include tenant filtering
WHERE tenant_id = $_SESSION['tenant_id']
  AND (other conditions)

// Examples:
SELECT * FROM students WHERE tenant_id = ? AND is_active = 1
SELECT * FROM users WHERE tenant_id = ? AND role = 'teacher'
SELECT * FROM classes WHERE tenant_id = ? AND academic_year = ?
```

---

## 📄 Database Tables Used by Owner

**Primary Tables Accessed**:

- `users` (institution-scoped)
- `students` (institution-scoped)
- `teachers` (institution-scoped)
- `classes` (institution-scoped)
- `class_enrollments` (institution-scoped)
- `attendance` (institution-scoped)
- `system_roles` (shared)
- `role_permissions` (shared)
- `activity_logs` (institution-scoped)
- `settings` (institution-scoped)
- `fees` (institution-scoped)
- `payments` (institution-scoped)

All queries must filter by `tenant_id` to ensure single-school isolation.

---

## 🎯 Testing Checklist for Owner Role

- [ ] Can access dashboard without errors
- [ ] KPI cards show correct data
- [ ] Can navigate to all assigned pages
- [ ] Can create new student and save to database
- [ ] Can create new teacher and assign to class
- [ ] Can create class and enroll students
- [ ] Can mark attendance and generate reports
- [ ] Can approve pending users
- [ ] Can change own password in settings
- [ ] Can export data in multiple formats
- [ ] Can see only institutional data (no cross-school visibility)
- [ ] Activity logs show all Owner actions
- [ ] Cannot access super-admin features
- [ ] Cannot create new schools
- [ ] Cannot switch institution context
- [ ] Works on mobile (responsive)
- [ ] Dark mode toggle works
- [ ] Sidebar navigation all links work
- [ ] Permissions enforced (cannot bypass with URL)

---

## 🚀 Quick Navigation Map

**From Dashboard, Owner can reach**:

```
Dashboard (admin/dashboard.php)
    ├─ Students Management (admin/students.php)
    ├─ Teachers Management (admin/teachers.php)
    ├─ Classes Management (admin/classes.php)
    ├─ Enrollment (admin/class-enrollment.php)
    ├─ Attendance (admin/attendance.php)
    ├─ Reports (admin/reports.php)
    ├─ User Approval (admin/approve-users.php)
    ├─ User Management (admin/users.php)
    ├─ Role Management (admin/role-management.php)
    ├─ Settings (admin/settings.php)
    ├─ System Health (admin/system-health.php)
    ├─ Financial Management (admin/financial-management.php)
    ├─ AI User Creator (admin/ai-user-creator.php)
    ├─ Bulk Import (admin/bulk-import.php)
    ├─ Activity Monitor (admin/activity-monitor.php)
    ├─ Library Management (admin/library-management.php)
    ├─ Transport Management (admin/transport-management.php)
    └─ Analytics (admin/overview.php)
```

All accessible from sidebar menu or dashboard quick links.

---

## 📞 Implementation Notes

### **Role-Based Access**

Owner role uses the same admin pages as Principal and Admin, but with:

- Single institution binding
- Full institutional permissions
- Cannot access multi-tenant features
- Cannot create schools
- Cannot switch contexts

### **Unique to Owner**

- Receives financial summaries
- Can create full backups
- Has highest authority within school
- Can delegate to Principal/Admin
- Makes strategic decisions

---

**End of OWNER_COMPLETE_REFERENCE.md**
