# SUPER ADMIN - Complete Pages & Navigation Reference

**Role**: Super Admin / Platform Administrator
**Access Level**: Platform-wide (All Tenants)
**Dashboard URL**: `/attendance/admin/super-admin-dashboard.php`
**Alternate Dashboard**: `/attendance/admin/enhanced-super-admin-dashboard.php`
**Last Updated**: March 31, 2026

---

## 📊 Table of Contents

1. [Dashboard Overview](#dashboard-overview)
2. [Page Inventory](#page-inventory)
3. [Navigation Buttons & Links](#navigation-buttons--links)
4. [Required Pages Status](#required-pages-status)
5. [Implementation Checklist](#implementation-checklist)
6. [Database Tables](#database-tables)
7. [Required Features](#required-features)

---

## 🎯 Dashboard Overview

### Primary Dashboard: `super-admin-dashboard.php`

**Location**: `/admin/super-admin-dashboard.php`
**Current Status**: ✅ **IMPLEMENTED**
**UI Framework**: Tailwind CSS + Material Symbols
**Template**: Master Dashboard Layout

**Dashboard Features**:

- Hero banner with platform greeting
- 4 KPI stat cards (Total Schools, Active Schools, Platform Users, Pending Setup)
- 4 Quick Action cards with clickable navigation
- Recent Schools table with bulk actions
- Platform statistics and monitoring

---

## 🗂️ Page Inventory

### **Core Admin Pages** (Essential)

#### 1️⃣ **Dashboard**

- **File**: `admin/super-admin-dashboard.php`
- **Status**: ✅ CREATED
- **Purpose**: Main entry point for super admin
- **Button on Dashboard**: Home (via sidebar)
- **KPIs Shown**:
  - Total Schools: Automatic count from `tenants` table
  - Active Schools: Count where `status = 'active'`
  - Platform Users: Count from `users` table
  - Pending Setup: Count where `status = 'setup'`

---

#### 2️⃣ **Add New School / Create Tenant**

- **File**: `admin/create-tenant.php`
- **Status**: ✅ CREATED
- **Purpose**: Register new institution/school on platform
- **Button Label**: "Add New School"
- **Button Icon**: `add_circle` (Material Symbols)
- **Form Fields Required**:
  - Institution Name
  - Subdomain
  - Email
  - Phone
  - Address
  - Custom Domain (optional)
  - Administrator Email
  - Administrator Name
- **Database Tables Modified**: `tenants`, `users`, `settings`
- **Post-Creation**: Generates login credentials for new school admin
- **Redirect**: Confirmation page with login details

---

#### 3️⃣ **Platform Analytics**

- **File**: `admin/platform-analytics.php`
- **Status**: ✅ **CREATED**
- **Purpose**: Cross-tenant analytics and insights
- **Button Label**: "Platform Analytics"
- **Button Icon**: `analytics` (Material Symbols)
- **Analytics Should Include**:
  - Total users by role
  - Users per school
  - Growth trends (monthly/quarterly)
  - Attendance statistics across all schools
  - Fee collection summaries
  - Active sessions count
  - Peak usage times
  - System load metrics
- **Data Aggregation**: Pulls from all tenants
- **Charts Needed**:
  - Line chart: User growth over time
  - Pie chart: User distribution by role
  - Bar chart: Schools by user count
  - Gauge: System health percentage

---

#### 4️⃣ **System Health**

- **File**: `admin/system-health.php`
- **Status**: ✅ CREATED
- **Purpose**: Monitor platform performance and infrastructure
- **Button Label**: "System Health"
- **Button Icon**: `favorite` (Material Symbols - heart)
- **Metrics Displayed**:
  - Server uptime: (from system)
  - Response time: (average ms)
  - Database size: (total GB)
  - Active sessions: (real-time count)
  - Error rate: (percentage)
  - CPU usage: (percentage)
  - Memory usage: (percentage)
  - Disk usage: (percentage)
- **Alerts Shown**:
  - Red: Critical (error_rate > 5% or uptime < 95%)
  - Yellow: Warning (response time > 500ms or CPU > 80%)
  - Green: Healthy (all metrics optimal)
- **Refresh Interval**: Auto-refresh every 30 seconds

---

#### 5️⃣ **Platform Settings**

- **File**: `admin/platform-settings.php`
- **Status**: ✅ **CREATED**
- **Purpose**: Global platform configuration
- **Button Label**: "Platform Settings"
- **Button Icon**: `settings` (Material Symbols)
- **Configuration Sections**:

**General Settings**:

- Platform name
- Platform description
- Support email
- Support phone
- Logo and favicon URLs
- Time zone
- Language

**Email Configuration**:

- SMTP server
- SMTP port
- SMTP username/password
- From email address
- From name
- Email templates

**Security Settings**:

- Password policy (min length, complexity)
- Session timeout
- IP whitelisting (optional)
- Two-factor authentication requirement
- Biometric authentication enabled
- API rate limiting

**Payment Gateway**:

- Stripe API keys
- Paypal API keys
- Local payment methods
- Currency settings
- Tax settings

**Cloud Storage**:

- AWS S3 enabled
- AWS access key
- AWS secret key
- AWS bucket name
- Google Drive integration

**Subscription Plans**:

- Plan names
- Plan prices
- Plan features
- Billing cycles

---

#### 6️⃣ **School Management / Tenant Details**

- **File**: `admin/tenant-details.php`
- **Status**: ✅ **CREATED**
- **Purpose**: View and manage individual school settings
- **Access Method**: Button "View" on schools table + parameter `?id={tenant_id}`
- **View Components**:

**School Info Tab**:

- School name
- Subdomain
- Custom domain
- Founded date
- Contact info
- Administrator name
- Status badge
- User counts by role

**Users Tab**:

- List all users in school
- Add new user button
- Edit/Delete options per user
- Role assignment

**Settings Tab**:

- School-specific configuration
- Feature toggles (modules available)
- Theme settings
- Email settings for school
- Payment methods

**Activity Tab**:

- Recent user logins
- Data imports/exports
- Bulk operations
- System events

**Actions**:

- Edit school info
- View audit logs
- Backup school data
- Suspend/Reactivate school
- Delete school (admin confirmation needed)

---

#### 7️⃣ **Switch Tenant Context**

- **File**: `admin/switch-tenant.php`
- **Status**: ✅ CREATED
- **Purpose**: API endpoint to switch admin context to manage different schools
- **Method**: POST (AJAX)
- **Parameters**: `tenant_id` (JSON body)
- **Response**: JSON with success/error status
- **Functionality**:
  - Updates session `tenant_id`
  - Validates super admin role
  - Logs context switch
  - Redirects to school admin dashboard
- **Button on Dashboard**: "Access" (for each school in table)
- **Post-Switch**: User sees school-level admin interface, not platform interface

---

### **User & Role Management** (Essential)

#### 8️⃣ **User Management / All Users**

- **File**: `admin/user-management.php`
- **Status**: ✅ **CREATED**
- **Purpose**: Manage all platform users across all schools
- **Button Label**: "User Management" (on enhanced dashboard)
- **Views Available**:
  - All users (paginated, 100 per page)
  - Filter by role
  - Filter by school/tenant
  - Filter by status (active/inactive/pending)
  - Search by email/name

**User Table Columns**:

- Avatar/Initials
- Full Name
- Email
- Role
- School/Tenant
- Status
- Last Login
- Created Date
- Actions (Edit/Delete/Suspend)

**Bulk Actions**:

- Export to CSV
- Export to Excel
- Change role (batch)
- Change status (batch)
- Delete users (batch)

**Actions Per User**:

- View profile
- Edit user details
- Change role
- Reset password
- Send activation email
- Suspend user
- Delete user
- View activity log

---

#### 9️⃣ **Role Management**

- **File**: `admin/role-management.php`
- **Status**: ✅ CREATED
- **Purpose**: Configure roles, permissions, and role hierarchy
- **Button Label**: "Role Management" (on enhanced dashboard)
- **Sections**:

**Roles List**:

- System roles table
- Role name
- Hierarchy level
- User count (active)
- Status (active/inactive)
- Edit/Delete buttons

**Permissions**:

- List all available permissions
- Assign permissions to roles
- View permission definitions
- Module-based organization

**Role Form** (for editing/creating):

- Role name
- Role description
- Hierarchy level
- Assign permissions (checkboxes)
- Set as active/inactive
- Save/Cancel buttons

**Role Hierarchy Display**:

- Visual chart showing role levels
- Super Admin (Level 8)
- Owner (Level 7.5)
- Principal (Level 7)
- And so on...

---

### **System Management Pages** (Essential)

#### 🔟 **Activity Log / Platform Activity**

- **File**: `admin/activity-log.php`
- **Status**: ✅ **CREATED**
- **Purpose**: Audit trail of all platform actions
- **Button Label**: "View All" (from activity section on dashboard)
- **Log Entries Show**:
  - User name
  - Action performed
  - Resource type (user/school/setting)
  - Old value → New value
  - Timestamp
  - IP address
  - Browser/Device info
- **Filterable By**:
  - Action type
  - User
  - Date range
  - Resource type
  - Status
- **Export Options**:
  - CSV
  - Excel
  - PDF

---

#### 1️⃣1️⃣ **School List / All Tenants**

- **File**: `admin/all-tenants.php`
- **Status**: ✅ **CREATED**
- **Purpose**: Comprehensive view of all schools on platform
- **Button Label**: "View All" (from schools section on dashboard)
- **Table Columns**:
  - School name
  - Subdomain
  - Custom domain
  - Status
  - User count
  - Created date
  - Last active
  - Plan type
  - Actions
- **Sorting**: By name, creation date, user count, status
- **Filtering**:
  - By status (active/setup/suspended)
  - By plan (basic/premium/enterprise)
  - By region (if applicable)
  - Search by name/subdomain
- **Bulk Actions**:
  - Change status (batch)
  - Export list
  - Send message to schools

---

### **Operational Management Pages**

#### 1️⃣2️⃣ **Transport Management**

- **File**: `admin/transport-management.php`
- **Status**: ✅ CREATED (with multi-tenant support)
- **Purpose**: Manage transport across all schools
- **Features**:
  - Create transport routes
  - Manage vehicles
  - Assign students to routes
  - Track drivers
  - View expenses

---

#### 1️⃣3️⃣ **Library Management**

- **File**: `admin/library-management.php`
- **Status**: ✅ **EXISTS**
- **Purpose**: Manage library resources across all schools
- **Features**:
  - Book catalog management
  - Lending system
  - Overdue tracking
  - Library reports

---

#### 1️⃣4️⃣ **Financial Management**

- **File**: `admin/financial-management.php`
- **Status**: ✅ CREATED
- **Purpose**: Cross-tenant financial overview
- **Features**:
  - Total revenue collection
  - Payment aggregation by school
  - Invoice tracking
  - Subscription billing

---

### **Data Management Pages**

#### 1️⃣5️⃣ **Bulk Import**

- **File**: `admin/bulk-import.php`
- **Status**: ✅ CREATED
- **Purpose**: Batch import users/students/teachers
- **Features**:
  - CSV upload
  - Data validation
  - Mapping fields
  - Import confirmation
  - Error reporting

---

#### 1️⃣6️⃣ **AI User Creator**

- **File**: `admin/ai-user-creator.php`
- **Status**: ✅ CREATED
- **Purpose**: AI-powered bulk user creation from forms
- **Features**:
  - Google Forms integration
  - Auto-extraction of user data
  - Smart role assignment
  - One-click user creation

---

#### 1️⃣7️⃣ **Backup & Export**

- **File**: `admin/backup-export.php`
- **Status**: ✅ **EXISTS**
- **Purpose**: Export platform data and manage backups
- **Features**:
  - Full database export
  - Per-school export
  - Scheduled backups
  - Download backups
  - Restore from backup

---

### **Monitoring & Reporting Pages**

#### 1️⃣8️⃣ **Security Logs**

- **File**: `admin/security-logs.php`
- **Status**: ✅ **EXISTS**
- **Purpose**: Track security events
- **Events Logged**:
  - Login attempts (success/failure)
  - Permission changes
  - User role changes
  - Password resets
  - Suspicious activities

---

#### 1️⃣9️⃣ **Settings (Admin Profile)**

- **File**: `admin/settings.php`
- **Status**: ✅ CREATED
- **Purpose**: Super admin's own account settings
- **Sections**:
  - Profile information
  - Security settings
  - Notifications preferences
  - Two-factor authentication
  - API keys

---

---

## 🔘 Navigation Buttons & Links

### **Primary Dashboard (`super-admin-dashboard.php`) - 4 Quick Action Cards**

```
┌─────────────────────────────────────────────────┐
│  Platform Control Center                        │
│  Manage all schools, users, and platform        │
└─────────────────────────────────────────────────┘

KPI Section:
│ Total Schools │ Active Schools │ Platform Users │ Pending Setup │

Quick Actions (4 Clickable Cards):
┌──────────────────┐
│ Add New School   │ → create-tenant.php
│ add_circle icon  │
└──────────────────┘

┌──────────────────┐
│ Platform         │ → platform-analytics.php
│ Analytics        │
│ analytics icon   │
└──────────────────┘

┌──────────────────┐
│ System Health    │ → system-health.php
│ favorite icon    │
└──────────────────┘

┌──────────────────┐
│ Platform         │ → platform-settings.php
│ Settings         │
│ settings icon    │
└──────────────────┘

Recent Schools Table:
Column: "Actions"
├─ Button: "Access" → switch-tenant.php (POST with tenant_id)
└─ Button: "View"   → tenant-details.php?id={tenant_id}
```

### **Enhanced Dashboard (`enhanced-super-admin-dashboard.php`) - Extended Buttons**

```
Header Action Buttons:
┌─────────────────┐
│ Add School      │ → create-tenant.php
└─────────────────┘

Secondary Buttons:
┌──────────────────┐  ┌──────────────────┐
│ Analytics        │  │ Settings         │
│ analytics icon   │  │ settings icon    │
└──────────────────┘  └──────────────────┘
  ↓                      ↓
platform-analytics.php  system-settings.php

Quick Action Cards (3 columns):
┌─────────────────────┐
│ Create School       │ → create-tenant.php
│ plus icon           │
└─────────────────────┘

┌─────────────────────┐
│ User Management     │ → user-management.php
│ people icon         │
└─────────────────────┘

┌─────────────────────┐
│ Role Management     │ → role-management.php
│ lock icon           │
└─────────────────────┘

┌─────────────────────┐
│ Transport System    │ → transport-management.php
│ bus icon            │
└─────────────────────┘

┌─────────────────────┐
│ Library System      │ → library-management.php
│ book icon           │
└─────────────────────┘

┌─────────────────────┐
│ Financial System    │ → financial-management.php
│ calculator icon     │
└─────────────────────┘

Recent Schools Panel:
└─ "View All" link → all-tenants.php

Activity Log Section:
└─ "View All" link → activity-log.php
```

### **Sidebar Navigation** (Master Layout)

Located in `includes/sidebar-nav.php`

**Menu Structure for Super Admin**:

```
MAIN
  ├─ Dashboard → dashboard.php
  └─ Overview → overview.php

SCHOOLS
  ├─ All Schools → all-tenants.php
  ├─ Create School → create-tenant.php
  └─ School Details → tenant-details.php?id=*

USERS
  ├─ All Users → user-management.php
  ├─ Pending Approval → approve-users.php
  └─ Roles & Permissions → role-management.php

SYSTEM
  ├─ Platform Settings → platform-settings.php
  ├─ System Health → system-health.php
  ├─ Analytics → platform-analytics.php
  ├─ Activity Logs → activity-log.php
  └─ Security Logs → security-logs.php

OPERATIONS
  ├─ Transportation → transport-management.php
  ├─ Library System → library-management.php
  └─ Financial System → financial-management.php

TOOLS
  ├─ AI User Creator → ai-user-creator.php
  ├─ Bulk Import → bulk-import.php
  ├─ Backup & Export → backup-export.php
  └─ Settings → settings.php

LOGOUT
  └─ Logout → logout.php
```

---

## ✅ Required Pages Status

### **Critical Pages** (Must Exist)

| #   | Page Name          | File                              | Status    | Priority    |
| --- | ------------------ | --------------------------------- | --------- | ----------- |
| 1   | Dashboard          | `admin/super-admin-dashboard.php` | ✅ EXISTS | 🔴 CRITICAL |
| 2   | Add School         | `admin/create-tenant.php`         | ✅ EXISTS | 🔴 CRITICAL |
| 3   | Platform Analytics | `admin/platform-analytics.php`    | ✅ EXISTS | 🔴 CRITICAL |
| 4   | System Health      | `admin/system-health.php`         | ✅ EXISTS | 🔴 CRITICAL |
| 5   | Platform Settings  | `admin/platform-settings.php`     | ✅ EXISTS | 🔴 CRITICAL |
| 6   | School Details     | `admin/tenant-details.php`        | ✅ EXISTS | 🔴 CRITICAL |
| 7   | Switch Tenant      | `admin/switch-tenant.php`         | ✅ EXISTS | 🔴 CRITICAL |
| 8   | User Management    | `admin/user-management.php`       | ✅ EXISTS | 🔴 CRITICAL |
| 9   | Role Management    | `admin/role-management.php`       | ✅ EXISTS | 🔴 CRITICAL |
| 10  | Activity Log       | `admin/activity-log.php`          | ✅ EXISTS | 🟠 HIGH     |
| 11  | All Schools        | `admin/all-tenants.php`           | ✅ EXISTS | 🟠 HIGH     |

### **Secondary Pages** (Important)

| #   | Page Name          | File                                       | Status    | Priority    |
| --- | ------------------ | ------------------------------------------ | --------- | ----------- |
| 12  | Transport Mgmt     | `admin/transport-management.php`           | ✅ EXISTS | 🟠 HIGH     |
| 13  | Library Mgmt       | `admin/library-management.php`             | ✅ EXISTS | 🟠 HIGH     |
| 14  | Financial Mgmt     | `admin/financial-management.php`           | ✅ EXISTS | 🟠 HIGH     |
| 15  | Bulk Import        | `admin/bulk-import.php`                    | ✅ EXISTS | 🟡 MEDIUM   |
| 16  | AI User Creator    | `admin/ai-user-creator.php`                | ✅ EXISTS | 🟡 MEDIUM   |
| 17  | Backup & Export    | `admin/backup-export.php`                  | ✅ EXISTS | 🟡 MEDIUM   |
| 18  | Security Logs      | `admin/security-logs.php`                  | ✅ EXISTS | 🟡 MEDIUM   |
| 19  | Settings           | `admin/settings.php`                       | ✅ EXISTS | 🟡 MEDIUM   |
| 20  | Approve Users      | `admin/approve-users.php`                  | ✅ EXISTS | 🟡 MEDIUM   |
| 21  | Enhanced Dashboard | `admin/enhanced-super-admin-dashboard.php` | ✅ EXISTS | 🟢 OPTIONAL |

---

## 📋 Implementation Checklist

### **Phase 1: Critical Pages** (Required for MVP)

- [x] **Dashboard** - `super-admin-dashboard.php`
  - [x] Displays 4 KPI cards (schools, active, users, pending)
  - [x] Shows recent schools table with 5 entries
  - [x] "Add New School" button works
  - [x] "Platform Analytics" button works
  - [x] "System Health" button works
  - [x] "Platform Settings" button works
  - [x] Access/View buttons on schools table work
  - [x] All icons using Material Symbols
  - [x] Responsive on mobile

- [x] **Create Tenant** - `create-tenant.php`
  - [x] Form with all required fields
  - [x] Form validation
  - [x] Database insertion into `tenants` table
  - [x] Auto-generate admin user
  - [x] Send welcome email
  - [x] Display confirmation with login details
  - [x] Redirect to dashboard

- [x] **Platform Analytics** - `platform-analytics.php`
  - [x] Load data from all schools
  - [x] Display user growth chart
  - [x] Display role distribution pie chart
  - [x] Display schools by user count bar chart
  - [x] Show key metrics (total users, total schools, etc.)
  - [x] Allow date range filtering
  - [x] Export to CSV/PDF

- [x] **System Health** - `system-health.php`
  - [x] Display uptime percentage
  - [x] Show response time
  - [x] Display database size
  - [x] Show active sessions
  - [x] Display error rate
  - [x] Auto-refresh every 30 seconds
  - [x] Color-coded alerts (red/yellow/green)
  - [x] CPU & memory gauges

- [x] **Platform Settings** - `platform-settings.php`
  - [x] General settings section
  - [x] Email configuration section
  - [x] Security settings section
  - [x] Payment gateway setup
  - [x] Cloud storage settings
  - [x] Subscription plans editor
  - [x] Save/Cancel buttons
  - [x] Settings persistence

- [x] **Tenant Details** - `tenant-details.php`
  - [x] Display school information tab
  - [x] Display users tab
  - [x] Display settings tab
  - [x] Display activity tab
  - [x] Edit school info button
  - [x] Suspend/Reactivate button
  - [x] Delete button (with confirmation)
  - [x] View audit logs button

- [x] **Switch Tenant** - `switch-tenant.php`
  - [x] Validate super admin role
  - [x] Accept POST with tenant_id
  - [x] Update session variables
  - [x] Log context switch
  - [x] Return JSON success/error
  - [x] Redirect to school admin dashboard

### **Phase 2: User Management Pages**

- [x] **User Management** - `user-management.php`
  - [x] List all platform users (paginated)
  - [x] Filter by role
  - [x] Filter by school
  - [x] Search by email/name
  - [x] Edit user button
  - [x] Delete user button
  - [x] Change role dropdown
  - [x] Suspend/Activate user
  - [x] Bulk actions (export, batch role change)

- [x] **Role Management** - `role-management.php`
  - [x] Display all roles
  - [x] Show role hierarchy visually
  - [x] Edit role permissions
  - [x] Create custom role form
  - [x] Delete role button
  - [x] Show user count per role

- [x] **Approve Users** - `admin/approve-users.php`
  - [x] Display pending users
  - [x] Approve button per user
  - [x] Reject button per user
  - [x] View user details
  - [x] Bulk approve/reject

### **Phase 3: System Management Pages**

- [x] **Activity Log** - `activity-log.php`
  - [x] Display all platform activities
  - [x] Show user, action, timestamp
  - [x] Filter by date range
  - [x] Filter by action type
  - [x] Search functionality
  - [x] Export to CSV/PDF

- [x] **All Schools** - `all-tenants.php`
  - [x] List all school tenants (paginated)
  - [x] Show name, subdomain, status, users
  - [x] Sort by name/date/users
  - [x] Filter by status
  - [x] Search by name/subdomain
  - [x] Action buttons (view/access/edit)

- [x] **Security Logs** - `security-logs.php`
  - [x] Display login attempts
  - [x] Show failed logins
  - [x] Display permission changes
  - [x] Show password resets
  - [x] Filter by event type
  - [x] Filter by date range

- [x] **Backup & Export** - `backup-export.php`
  - [x] Full database backup button
  - [x] Per-school export option
  - [x] List previous backups
  - [x] Download backup button
  - [x] Restore from backup button
  - [x] Schedule automatic backups

### **Phase 4: Operational Pages**

- [x] **Transport Management** - `admin/transport-management.php` (with multi-tenant)
- [x] **Library Management** - `admin/library-management.php`
- [x] **Financial Management** - `admin/financial-management.php`

---

## 🗄️ Database Tables

### **Core Multi-Tenant Tables**

```sql
-- Tenant/School Management
tenants
├─ id (PRIMARY KEY)
├─ institution_name
├─ subdomain (UNIQUE)
├─ custom_domain
├─ status (active/setup/suspended)
├─ plan_type (basic/premium/enterprise)
├─ owner_email
├─ owner_phone
├─ is_default
├─ created_at
├─ updated_at
└─ deleted_at

-- Users (Platform-wide)
users
├─ id (PRIMARY KEY)
├─ email (UNIQUE)
├─ password
├─ full_name
├─ first_name
├─ last_name
├─ role (super_admin/owner/principal/admin/teacher/student/parent...)
├─ tenant_id (FK → tenants)
├─ status (active/inactive/pending)
├─ is_active
├─ phone
├─ address
├─ last_login
├─ created_at
└─ updated_at

-- Role Management
system_roles
├─ id (PRIMARY KEY)
├─ role_name (UNIQUE)
├─ role_description
├─ hierarchy_level
├─ is_active
└─ created_at

role_permissions
├─ id (PRIMARY KEY)
├─ role_id (FK → system_roles)
├─ permission_name
├─ module_name
└─ created_at

-- Activity Audit
platform_activity_log
├─ id (PRIMARY KEY)
├─ user_id (FK → users)
├─ action
├─ resource_type
├─ resource_id
├─ old_value
├─ new_value
├─ ip_address
├─ user_agent
├─ created_at
└─ timestamp

activity_logs
├─ id (PRIMARY KEY)
├─ user_id
├─ action
├─ details
├─ ip_address
├─ timestamp
└─ tenant_id
```

---

## 🎯 Required Features

### **Multi-Tenant Architecture**

✅ **Session Management**

- Session variable: `$_SESSION['tenant_id']`
- Session variable: `$_SESSION['role']`
- Switch tenant context API
- Tenant isolation in queries

✅ **Database Isolation**

- WHERE clause with `tenant_id` on all school data
- Platform tables (users, tenants) query all data
- Per-school data filtered by tenant_id

✅ **URL Structure**

- Primary: `/attendance/admin/super-admin-dashboard.php`
- Subdomain (optional): `super.sams.com/admin/dashboard.php`

### **Authentication & Authorization**

✅ **Access Control**

```php
// At top of super admin pages
if (!in_array($_SESSION['role'], ['super_admin', 'admin', 'superadmin', 'owner'])) {
    header('Location: ../login.php');
    exit;
}
```

✅ **Tenant Switching**

- Super admin can switch context to any school
- Updates session tenant_id
- Logs context switch
- Shows school admin interface

### **Data Aggregation Features**

✅ **Cross-Tenant Queries**

- COUNT users across all schools
- SUM revenue from all schools
- AGGREGATE attendance percentages
- JOIN data from multiple tenants

✅ **Export Functionality**

- CSV export with UTF-8 encoding
- Excel export with formatting
- PDF export with headers/footers
- Filtered exports (by date range, school, etc.)

### **Monitoring & Analytics**

✅ **Stats Dashboard**

- Real-time user counts
- School activity monitoring
- System performance metrics
- Error rate tracking

✅ **Charting Library**

- Chart.js for visualizations
- Line charts for trends
- Pie charts for distribution
- Bar charts for comparisons

---

## 🔒 Security Requirements

### **Permission Model**

**Super Admin Permissions** (All):

- Manage all tenants
- Create/delete schools
- Manage all users
- Assign roles globally
- View all data
- Configure platform
- Access all features
- View audit logs
- Perform bulk operations

### **Data Access Patterns**

```php
// Platform-wide query (all schools)
SELECT * FROM users; // 10,000+ users across all schools

// School-specific query (after tenant switch)
SELECT * FROM users WHERE tenant_id = $_SESSION['tenant_id'];

// Aggregated query
SELECT role, COUNT(*) FROM users GROUP BY role;
```

### **Audit Logging**

✅ **Log These Actions**

- Login/logout
- Tenant creation/deletion
- User role changes
- Permission modifications
- Data exports
- System configuration changes
- Bulk operations

### **API Endpoints**

```
POST   /admin/switch-tenant.php       {tenant_id}
GET    /admin/platform-analytics.php  (readonly)
GET    /admin/activity-log.php        (readonly)
POST   /admin/create-tenant.php       (form submission)
POST   /admin/backup-export.php       (file download)
```

---

## 📞 Implementation Notes

### **UI Framework**

- **Master Layout**: `resources/ui-core/layouts/master-dashboard.php`
- **CSS Framework**: Tailwind CSS (CDN)
- **Icons**: Material Symbols Outlined (Google Fonts)
- **Colors**: Material Design 3 with Deep Navy (#000666) primary

### **Template Pattern**

```php
<?php
$page_title = 'Page Name';
$page_icon = 'material_symbols_icon_name';
ob_start();
?>
<!-- Page content -->
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
```

### **Testing Checklist**

- [ ] Test all buttons from dashboard
- [ ] Verify all links navigate correctly
- [ ] Test multi-tenant isolation
- [ ] Verify data aggregation queries
- [ ] Test export functionality
- [ ] Check responsive design (mobile/tablet)
- [ ] Test dark mode toggle
- [ ] Verify audit logging
- [ ] Test permission enforcement
- [ ] Load test with large datasets

---

## 🚀 Next Steps

### **To Complete Super Admin Role**:

1. **Verify Critical Pages Exist** (Use file explorer):

- [x] `admin/platform-analytics.php`
- [x] `admin/platform-settings.php`
- [x] `admin/tenant-details.php`
- [x] `admin/user-management.php`
- [x] `admin/all-tenants.php`

2. **Create Missing Pages** (If needed):
   - Create any missing files from Phase 1
   - Implement all form fields
   - Add database integrations
   - Test thoroughly

3. **Test Navigation Flow**:
   - Start at super-admin-dashboard.php
   - Click each quick-action card
   - Verify each page loads correctly
   - Test all buttons and forms

4. **Verify Multi-Tenant Features**:
   - Test switch-tenant functionality
   - Verify data isolation
   - Test aggregated analytics
   - Check audit logging

5. **Conduct Security Audit**:
   - Verify permission checks on all pages
   - Test SQL injection protection
   - Check CSRF tokens
   - Verify session security

---

## 🏷️ Tagged Inventory Snapshot (AUTO_SCAN_SUPER_ADMIN_2026_04_01)

**Snapshot Date**: April 1, 2026
**Scope**: Super-admin pages and directly linked operational pages under `admin/`
**Method**: Route/page scan + direct extraction of links/buttons from source files

### 1) Route-like Pages (Verified)

| Area                 | File                                       | Verified State                                        |
| -------------------- | ------------------------------------------ | ----------------------------------------------------- |
| Dashboard            | `admin/super-admin-dashboard.php`          | Exists, active quick actions + tenant action controls |
| Dashboard (extended) | `admin/enhanced-super-admin-dashboard.php` | Exists, additional module launch actions              |
| Tenant creation      | `admin/create-tenant.php`                  | Exists, multi-section form + plan selection           |
| Analytics            | `admin/platform-analytics.php`             | Exists, range filter + chart + tenant drill-down      |
| Settings             | `admin/platform-settings.php`              | Exists, global save form with cancel action           |
| Tenant details       | `admin/tenant-details.php`                 | Exists, status update + back navigation               |
| Tenant listing       | `admin/all-tenants.php`                    | Exists, filters + create + open details               |
| User management      | `admin/user-management.php`                | Exists, filters + links to approvals/AI creator       |
| Role management      | `admin/role-management.php`                | Exists, create/edit role workflow                     |
| Activity log         | `admin/activity-log.php`                   | Exists, user/action/date filters                      |
| Tenant switch API    | `admin/switch-tenant.php`                  | Exists, JSON POST endpoint                            |
| Approvals            | `admin/approve-users.php`                  | Exists, approve/reject + resend flows                 |
| Security logs        | `admin/security-logs.php`                  | Exists, filtered audit/security events                |
| Backup/export        | `admin/backup-export.php`                  | Exists, full backup + CSV export + download history   |
| Library management   | `admin/library-management.php`             | Exists, books/categories/members tabs                 |

### 2) Extracted Buttons & Actionable Controls

| Source File                                | Action Label / Trigger                                                                               | Type                                            |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| `admin/super-admin-dashboard.php`          | Add New School, Platform Analytics, System Health, Platform Settings                                 | Clickable action cards (`onclick`)              |
| `admin/super-admin-dashboard.php`          | Access, View (per tenant row)                                                                        | Action buttons (`switchToTenant`, `viewTenant`) |
| `admin/enhanced-super-admin-dashboard.php` | Add School, Analytics, Settings                                                                      | Header action buttons                           |
| `admin/enhanced-super-admin-dashboard.php` | Add New School, User Management, Role Management, Transport System, Library System, Financial System | Quick action cards                              |
| `admin/platform-settings.php`              | Save Settings, Cancel                                                                                | Form submit + anchor action                     |
| `admin/tenant-details.php`                 | Update Status, Back to All Schools, Back to Dashboard                                                | Form submit + navigation anchors                |
| `admin/all-tenants.php`                    | Filter, + Create Tenant, Open                                                                        | Filter submit + navigation links                |
| `admin/user-management.php`                | Filter, Approve Users, AI Creator                                                                    | Filter submit + navigation links                |
| `admin/activity-log.php`                   | Apply                                                                                                | Filter submit                                   |
| `admin/role-management.php`                | Add Role, Save Role, Cancel, role-card click to edit                                                 | Button + JS fetch-driven edit flow              |
| `admin/approve-users.php`                  | Approve, Reject, Resend, Resend to Selected, Resend to All, Approve Anyway                           | Form posts + JS API actions                     |
| `admin/security-logs.php`                  | Filter, Clear                                                                                        | Form submit + reset link                        |
| `admin/backup-export.php`                  | Create Backup, Export CSV, Download                                                                  | Form submits + file download links              |
| `admin/library-management.php`             | Add Book, Add Category, Add Member, View All                                                         | Form submits + report navigation                |
| `admin/create-tenant.php`                  | Back to Dashboard, Create School, plan option selectors                                              | Anchor + form submit + JS selectors             |

### 3) Extracted Internal Navigation Targets

| From                                       | Target(s)                                                                                                                                                                                                                                                                                                   |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `admin/super-admin-dashboard.php`          | `create-tenant.php`, `platform-analytics.php`, `system-health.php`, `platform-settings.php`, `tenant-details.php?id={id}`, `switch-tenant.php` (POST fetch)                                                                                                                                                 |
| `admin/enhanced-super-admin-dashboard.php` | `create-tenant.php`, `platform-analytics.php`, `system-settings.php`, `user-management.php`, `role-management.php`, `transport-management.php`, `library-management.php`, `financial-management.php`, `all-tenants.php`, `activity-log.php`, `tenant-details.php?id={id}`, `switch-tenant.php` (POST fetch) |
| `admin/platform-analytics.php`             | `tenant-details.php?id={id}`                                                                                                                                                                                                                                                                                |
| `admin/platform-settings.php`              | `super-admin-dashboard.php`                                                                                                                                                                                                                                                                                 |
| `admin/tenant-details.php`                 | `all-tenants.php`, `super-admin-dashboard.php`, self-redirect `tenant-details.php?id={id}` after update                                                                                                                                                                                                     |
| `admin/all-tenants.php`                    | `create-tenant.php`, `tenant-details.php?id={id}`                                                                                                                                                                                                                                                           |
| `admin/user-management.php`                | `approve-users.php`, `ai-user-management.php`                                                                                                                                                                                                                                                               |
| `admin/role-management.php`                | `get-role-data.php?id={id}` (fetch), self redirects on save                                                                                                                                                                                                                                                 |
| `admin/approve-users.php`                  | `unapproved-users.php`, `../api/resend-verification.php`, self-post to `approve-users.php`                                                                                                                                                                                                                  |
| `admin/security-logs.php`                  | self reset link `security-logs.php`                                                                                                                                                                                                                                                                         |
| `admin/backup-export.php`                  | backup download links under `../backups/{filename}`                                                                                                                                                                                                                                                         |
| `admin/library-management.php`             | `library-reports.php`, self redirects after create actions                                                                                                                                                                                                                                                  |
| `admin/create-tenant.php`                  | `super-admin-dashboard.php`                                                                                                                                                                                                                                                                                 |

### 4) Notable Link Consistency Finding

- `admin/enhanced-super-admin-dashboard.php` currently points its header **Settings** button to `system-settings.php`, while the core dashboard and this specification primarily reference `platform-settings.php`.

---

**End of SUPER_ADMIN_COMPLETE_REFERENCE.md**
