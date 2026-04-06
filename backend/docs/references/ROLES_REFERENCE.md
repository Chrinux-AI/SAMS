# SAMS - Complete Roles Reference Guide

**System**: School Attendance Management System (SAMS)  
**Total Roles**: 14  
**Last Updated**: March 31, 2026  
**Version**: 1.0

---

## 📋 Table of Contents

1. [Administrative & Platform Roles](#administrative--platform-roles)
2. [Academic Roles](#academic-roles)
3. [Support & Staff Roles](#support--staff-roles)
4. [Operations Roles](#operations-roles)
5. [Community Roles](#community-roles)
6. [Role Hierarchy](#role-hierarchy)
7. [Permission Matrix](#permission-matrix)
8. [Role Access Control](#role-access-control)

---

## Administrative & Platform Roles

### 1. **Super Admin**

**Level**: Platform (Tier 1)  
**Scope**: Multi-tenant, all institutions  
**Permission Level**: 8/8 (Highest)

**Description**:
- Complete platform administration
- Oversees all schools and institutions
- System-wide configuration and governance
- Tenant management and isolation oversight
- Cross-tenant analytics and reporting

**Key Permissions**:
- `*` (All permissions - full access)
- manage_tenants
- manage_super_admins
- view_cross_tenant_analytics
- system_configuration
- backup_management
- security_audits

**Dashboard**: `/attendance/admin/super-admin-dashboard.php`

**Key Modules**:
- Tenant Management
- Super Admin Dashboard
- System Health & Performance
- Cross-Tenant Reports
- User Oversight
- Audit Logs (system-wide)
- Backup & Recovery

**Typical Users**:
- Platform owner
- System administrator
- SaaS provider team

---

### 2. **Owner**

**Level**: Institutional (Tier 2)  
**Scope**: Single institution/school  
**Permission Level**: 7.5/8

**Description**:
- Highest authority within their institution
- Can delegate to admins and principals
- Institutional configuration and policies
- School-wide decision making

**Key Permissions**:
- All institution-wide operations
- User and role management
- Financial oversight
- Institutional reporting
- Policy configuration

**Typical Users**:
- School/institution owner
- Board of trustees designate
- Proprietor

---

### 3. **Principal**

**Level**: Institutional (Tier 3)  
**Scope**: Single institution/school  
**Permission Level**: 7/8

**Description**:
- Educational leadership of the institution
- Academic and administrative oversight
- Staff and student discipline
- Institutional policy enforcement
- Academic calendar and schedule management

**Key Permissions**:
- manage_institution_policies
- view_all_students
- view_all_teachers
- approve_user_registrations
- institutional_reports
- manage_academic_calendar
- manage_fees_and_invoices (oversight)

**Key Modules**:
- Principal Dashboard
- Staff Management
- Student Oversight
- Academic Planning
- Discipline & Appeals
- Institutional Reports

**Typical Users**:
- Principal/Headmaster
- Deputy Principal
- Academic Head

---

### 4. **Admin**

**Level**: Institutional (Tier 4)  
**Scope**: Single institution/school  
**Permission Level**: 6/8

**Description**:
- Daily institutional administration
- User and account management
- System operations
- Data management and reporting
- Backs up principal/owner

**Key Permissions**:
- manage_users
- manage_classes
- manage_student_enrollments
- view_attendance (all)
- generate_reports
- manage_system_settings
- manage_configurations
- view_audit_logs
- create_backups

**Dashboard**: `/attendance/admin/dashboard.php`

**Key Modules**:
- Users Management
- Class Management
- Attendance Tracking
- Class Enrollment
- User Approval Workflow
- Reports & Analytics
- System Health Monitoring
- Settings & Profile
- Role Management
- AI User Creator
- Activity Monitor
- Announcements System
- Audit Logs
- Backup Export

**Pages Available**:
- `admin/users.php` — User management
- `admin/classes.php` — Class administration
- `admin/attendance.php` — Attendance marking
- `admin/class-enrollment.php` — Student enrollment
- `admin/approve-users.php` — User verification
- `admin/reports.php` — Data reports and exports
- `admin/system-health.php` — Performance monitoring
- `admin/settings.php` — Configuration
- `admin/role-management.php` — Role administration
- `admin/ai-user-creator.php` — Bulk user creation
- `admin/activity-monitor.php` — System activity
- `admin/analytics.php` — Institutional analytics

**Typical Users**:
- School administrator
- Administrative officer
- Office manager
- System operator

---

## Academic Roles

### 5. **Teacher**

**Level**: Classroom (Tier 5)  
**Scope**: Assigned class(es) and students  
**Permission Level**: 5/8

**Description**:
- Classroom and course management
- Student instruction tracking
- Assessment and grading
- Parent communication
- Class attendance recording

**Key Permissions**:
- view_classes (assigned)
- mark_attendance (own classes)
- submit_grades
- upload_assignments
- view_students (enrolled)
- view_attendance_reports (own classes)
- generate_class_reports
- manage_class_materials
- communicate_with_parents
- manage_grades

**Dashboard**: `/attendance/teacher/dashboard.php`

**Key Modules**:
- My Classes
- Class Attendance
- Student Grades
- Assignments Management
- Class Reports
- Parent Communication
- Materials Library
- Student Management

**Key Pages**:
- `teacher/dashboard.php` — Overview
- `teacher/my-classes.php` — Class selection
- `teacher/attendance.php` — Mark attendance
- `teacher/grades.php` — Grade management
- `teacher/assignments.php` — Assignment tracking
- `teacher/reports.php` — Class reports
- `teacher/settings.php` — Profile & preferences

**Typical Users**:
- Subject teacher
- Form/Home room teacher
- Specialist instructor

---

### 6. **Student**

**Level**: Personal (Tier 6)  
**Scope**: Own profile and enrolled classes  
**Permission Level**: 1/8 (Lowest student view)

**Description**:
- Personal learning dashboard
- View own academic progress
- Access course materials
- Submit assignments
- Communicate with teachers/peers
- Track own attendance

**Key Permissions**:
- view_attendance (own)
- view_grades (own)
- submit_assignments
- view_assignments (enrolled)
- view_class_schedule
- download_reports (own)
- participate_forum
- view_own_profile
- download_materials
- message_teachers

**Dashboard**: `/attendance/student/dashboard.php`

**Key Modules**:
- My Dashboard
- My Attendance
- My Grades
- My Assignments
- My Classes
- My Reports
- Forum & Community
- Messages
- Profile

**Key Pages**:
- `student/dashboard.php` — Overview
- `student/attendance.php` — Attendance view
- `student/grades.php` — Grade tracking
- `student/assignments.php` — Assignments
- `student/reports.php` — Personal reports
- `student/classes.php` — Enrolled classes
- `student/settings.php` — Profile settings

**Typical Users**:
- Primary student
- Secondary student
- Tertiary student

---

### 7. **Parent**

**Level**: Guardian (Tier 6)  
**Scope**: Child profile(s)  
**Permission Level**: 2/8

**Description**:
- Child/ren academic monitoring
- Attendance tracking overview
- Grade and progress review
- Communication with teachers
- Fee payment and billing
- School notifications

**Key Permissions**:
- view_child_attendance
- view_child_grades
- view_child_assignments
- receive_notifications
- communicate_with_teachers
- view_child_reports
- track_child_progress
- pay_fees

**Dashboard**: `/attendance/parent/dashboard.php`

**Key Modules**:
- Child Dashboard (per child)
- Child Attendance Tracking
- Child Grades & Progress
- Child Assignments
- Messages to Teachers
- School Notifications
- Fee Payments & Billing
- Child Reports

**Key Pages**:
- `parent/dashboard.php` — Overview
- `parent/children.php` — Child selection
- `parent/attendance.php` — Attendance view
- `parent/grades.php` — Grade tracking
- `parent/payments.php` — Payments & fees
- `parent/messages.php` — Communication
- `parent/settings.php` — Profile settings

**Typical Users**:
- Parent
- Guardian
- Custodian
- Authorized adult

---

## Support & Staff Roles

### 8. **Staff**

**Level**: Support (Tier 4.5)  
**Scope**: Assigned operations  
**Permission Level**: 4/8

**Description**:
- Non-teaching support
- General operational support
- Administrative assistance
- Facility and resource management
- Student welfare support

**Key Permissions**:
- view_student_list
- view_attendance_summary
- manage_facility_resources
- report_student_issues
- assist_administrative_tasks

**Key Modules**:
- Staff Dashboard
- Resource Management
- Student Welfare
- Administrative Support
- Attendance Summaries

**Typical Users**:
- Office assistant
- Clerical staff
- Facilities manager
- Support officer

---

### 9. **Nurse/School Nurse**

**Level**: Healthcare (Tier 4.5)  
**Scope**: All students - health focus  
**Permission Level**: 4/8

**Description**:
- Student health and wellness management
- Medical record keeping
- Health incident tracking
- Medication management
- Wellness alerts and follow-up
- Health screening records

**Key Permissions**:
- manage_health_records
- record_first_aid
- manage_medications
- send_wellness_alerts
- view_health_history
- track_medical_appointments
- generate_health_reports

**Key Modules**:
- Health Records Management
- First Aid Log
- Medication Tracking
- Wellness Alerts
- Medical Appointments
- Health Reports
- Emergency Contacts

**Key Pages**:
- `nurse/dashboard.php` — Overview
- `nurse/health-records.php` — Medical records
- `nurse/first-aid.php` — Incident tracking
- `nurse/medications.php` — Medication management
- `nurse/wellness.php` — Wellness alerts

**Typical Users**:
- School nurse
- Healthcare provider
- Wellness coordinator

---

### 10. **Librarian**

**Level**: Resource Management (Tier 4)  
**Scope**: Library and resources  
**Permission Level**: 4/8

**Description**:
- Library collection management
- Book lending and tracking
- Resource cataloging
- Overdue notice management
- Library inventory maintenance
- Student library access oversight

**Key Permissions**:
- manage_books
- lend_books
- return_books
- view_library_reports
- manage_library_inventory
- send_overdue_notices
- manage_library_catalog
- generate_library_reports

**Key Modules**:
- Library Dashboard
- Book Management
- Lending System
- Returns Processing
- Inventory Management
- Overdue Tracking
- Library Reports
- Student Borrowing History

**Key Pages**:
- `librarian/dashboard.php` — Overview
- `librarian/books.php` — Book management
- `librarian/lending.php` — Loan transactions
- `librarian/inventory.php` — Stock management
- `librarian/reports.php` — Library analytics
- `librarian/settings.php` — Configuration

**Typical Users**:
- Head librarian
- Library assistant
- Resource coordinator

---

### 11. **Bursar**

**Level**: Finance (Tier 4)  
**Scope**: Fees and financial operations  
**Permission Level**: 4/8

**Description**:
- Fee collection management
- Invoice generation
- Payment processing and tracking
- Financial reconciliation
- Student debt management
- Payment notifications and reminders

**Key Permissions**:
- manage_fees
- generate_invoices
- track_payments
- send_payment_reminders
- view_financial_reports
- reconcile_accounts
- manage_payment_methods
- generate_fee_reports

**Key Modules**:
- Bursar Dashboard
- Fee Management
- Invoice Management
- Payment Tracking
- Payment Reminders
- Financial Reports
- Student Accounts
- Payment Methods

**Key Pages**:
- `bursar/dashboard.php` — Overview
- `bursar/fees.php` — Fee configuration
- `bursar/invoices.php` — Invoice generation
- `bursar/payments.php` — Payment tracking
- `bursar/reports.php` — Financial reports
- `bursar/students.php` — Student accounts

**Typical Users**:
- Bursar
- Finance officer
- Fee collector
- Accounts assistant

---

### 12. **Accountant**

**Level**: Finance (Tier 4)  
**Scope**: Complete accounting  
**Permission Level**: 5/8

**Description**:
- Comprehensive accounting operations
- Financial reporting and ledgers
- Tax compliance
- Budget management
- Invoice and payment processing
- Financial auditing

**Key Permissions**:
- manage_invoices
- track_payments
- financial_reports
- manage_fees (read/modify)
- view_payment_history
- generate_financial_reports
- manage_budget
- reconcile_accounts
- tax_compliance_reporting
- audit_trail_access

**Key Modules**:
- Accountant Dashboard
- Ledger Management
- Invoice Processing
- Payment Reconciliation
- Financial Reports
- Budget Planning
- Tax Reports
- Audit Trails

**Key Pages**:
- `accountant/dashboard.php` — Overview
- `accountant/ledger.php` — General ledger
- `accountant/invoices.php` — Invoice management
- `accountant/payments.php` — Payment processing
- `accountant/reports.php` — Financial reports
- `accountant/budget.php` — Budget management
- `accountant/tax.php` — Tax reporting
- `accountant/audit-trail.php` — Audit logs

**Typical Users**:
- School accountant
- Finance controller
- Accounting officer

---

## Operations Roles

### 13. **Transport Officer/Transport Manager**

**Level**: Operations (Tier 4)  
**Scope**: Transportation and logistics  
**Permission Level**: 4/8

**Description**:
- Transportation logistics management
- Route planning and maintenance
- Vehicle fleet management
- Student transport assignment
- Driver management
- Transport expense tracking
- Safety and compliance monitoring

**Key Permissions**:
- manage_routes
- assign_students (to routes)
- view_transport_reports
- manage_vehicles
- track_transport_usage
- manage_drivers
- generate_route_reports
- manage_transport_expenses

**Key Modules**:
- Transport Dashboard
- Route Management
- Vehicle Management
- Student Assignment
- Driver Management
- Transport Reports
- Expense Tracking
- Safety Monitoring

**Key Pages**:
- `transport/dashboard.php` — Overview
- `transport/routes.php` — Route management
- `transport/vehicles.php` — Vehicle fleet
- `transport/assignments.php` — Student assignment
- `transport/drivers.php` — Driver management
- `transport/reports.php` — Reports & analytics
- `transport/expenses.php` — Cost tracking

**Typical Users**:
- Transport manager
- Fleet coordinator
- Route supervisor
- Logistics officer

---

## Community Roles

### 14. **Forum Moderator**

**Level**: Community (Tier 3)  
**Scope**: Forum and community management  
**Permission Level**: 3/8

**Description**:
- Community forum management
- Content moderation
- User safety oversight
- Discussion facilitation
- Violation reporting and enforcement
- Community guidelines enforcement

**Key Permissions**:
- review_posts
- delete_posts (inappropriate)
- manage_forum (categories, threads)
- moderate_content
- view_moderation_reports
- warn_users
- suspend_users (from forum)
- manage_community_guidelines
- send_moderator_messages

**Dashboard**: `/attendance/forum-moderator/dashboard.php`

**Key Modules**:
- Forum Dashboard
- Post Review
- User Management (forum-level)
- Moderation Reports
- Community Guidelines
- Discussion Topics
- User Warnings
- Thread Management

**Key Pages**:
- `forum-moderator/dashboard.php` — Overview
- `forum-moderator/posts.php` — Post review
- `forum-moderator/reports.php` — Violation reports
- `forum-moderator/users.php` — User management
- `forum-moderator/guidelines.php` — Rules & policies
- `forum-moderator/messages.php` — Moderator communications

**Typical Users**:
- Senior student moderator
- Faculty moderator
- Community manager

---

## Role Hierarchy

### Privilege Hierarchy (Highest to Lowest)

```
8 ─── Super Admin ─────────────────── Platform-wide control
      ↓
7.5 ─ Owner ─────────────────────── Institutional owner
      ↓
7 ─── Principal ───────────────────── Academic leadership
      ↓
6 ─── Admin ───────────────────────── Daily administration
      ↓
5 ─── Teacher ─────────────────────── Class management
5 ─── Accountant ──────────────────── Financial operations
      ↓
4 ─── Bursar ──────────────────────── Fee collection
4 ─── Librarian ───────────────────── Resource management
4 ─── Transport ───────────────────── Logistics management
4 ─── Nurse ───────────────────────── Health management
4 ─── Staff ───────────────────────── Support operations
      ↓
3 ─── Forum Moderator ─────────────── Community oversight
      ↓
2 ─── Parent ──────────────────────── Child monitoring
      ↓
1 ─── Student ─────────────────────── Personal dashboard
```

### Scope Categories

**Platform-Level** (Cross-institution):
- Super Admin

**Institutional-Level** (Single school):
- Owner, Principal, Admin

**Operational-Level** (Department/Function):
- Teacher, Accountant, Bursar, Librarian, Nurse, Transport, Staff

**Community-Level** (Social/Forum):
- Forum Moderator

**Personal-Level** (Self only):
- Student, Parent (restricted to child)

---

## Permission Matrix

| Permission | Super | Owner | Principal | Admin | Teacher | Student | Parent | Bursar | Accountant | Librarian | Transport | Nurse | Staff | Moderator |
|-----------|:-----:|:-----:|:---------:|:-----:|:-------:|:-------:|:------:|:------:|:--------:|:---------:|:---------:|:-----:|:-----:|:---------:|
| **Users** | ✅✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Classes CRUD** | ✅✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Attendance Mark** | ✅✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **View Attendance** | ✅✅ | ✅ | ✅ | ✅ | ✅ | 📋 | 📋 | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Grading** | ✅✅ | ✅ | ✅ | ✅ | ✅ | 📋 | 📋 | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Fee Management** | ✅✅ | ✅ | ✅ | ✅ | ❌ | ❌ | 📋 | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Reports** | ✅✅ | ✅ | ✅ | ✅ | ✅ | 📋 | 📋 | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Library** | ✅✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅✅ | ❌ | ❌ | ❌ | ❌ |
| **Transport** | ✅✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅✅ | ❌ | ❌ | ❌ |
| **Moderate Forum** | ✅✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅✅ |
| **Messages** | ✅✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Legend**: ✅✅ = Full Control | ✅ = Permitted | 📋 = Own/Child only | ❌ = Not Allowed

---

## Role Access Control

### Configuration Files

**Core Role Engine**: `core/role_engine.php`
- Permission definitions
- Role hierarchy
- Permission checking functions

**Security Middleware**: `includes/SecurityMiddleware.php`
- Role-based routing
- Access control enforcement
- Session validation

**Role Management UI**: `admin/role-management.php`
- Role CRUD operations
- Permission assignment
- User role management

### Session Variables

```php
$_SESSION['user_id']      // User ID
$_SESSION['role']         // User's role string
$_SESSION['full_name']    // Display name
$_SESSION['email']        // Email address
$_SESSION['tenant_id']    // Institution ID (multi-tenant)
$_SESSION['theme']        // User's theme preference
```

### Role Check Pattern

```php
// Required at top of role-specific pages
$allowed_roles = ['admin', 'principal'];
$current_role = $_SESSION['role'] ?? '';

if (!in_array($current_role, $allowed_roles)) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}
```

### Permission Check Function

```php
// In role_engine.php
role_engine()->hasPermission('manage_users');
role_engine()->hasAnyPermission(['view_reports', 'generate_reports']);
role_engine()->canAccessResource('admin');
```

---

## Default Test Accounts

| Role | Email | Password | Purpose |
|------|-------|----------|---------|
| Super Admin | `dev.superadmin@attendance.local` | `DevPass@2026` | Platform admin |
| Admin | `dev.admin@attendance.local` | `DevPass@2026` | School admin |
| Teacher | `dev.teacher@attendance.local` | `DevPass@2026` | Classroom teacher |
| Student | `dev.student@attendance.local` | `DevPass@2026` | Student account |
| Parent | `dev.parent@attendance.local` | `DevPass@2026` | Parent account |
| Bursar | `dev.bursar@attendance.local` | `DevPass@2026` | Fee collector |
| Accountant | `dev.accountant@attendance.local` | `DevPass@2026` | Finance operations |

---

## Adding New Roles

To create a custom role:

1. **Add role to database**:
   ```sql
   INSERT INTO system_roles (role_name, hierarchy_level, is_active)
   VALUES ('custom_role', 5, 1);
   ```

2. **Define permissions in `core/role_engine.php`**:
   ```php
   "custom_role" => [
       "view_dashboard",
       "view_reports",
       // ... permissions
   ]
   ```

3. **Create role-specific routes**:
   ```
   custom_role/dashboard.php
   custom_role/settings.php
   // etc.
   ```

4. **Add security checks**:
   ```php
   require_once '../includes/SecurityMiddleware.php';
   SAMS_SecurityMiddleware::requireRole(['custom_role']);
   ```

---

## Related Documentation

- **[ARCHITECTURE.md](docs/ARCHITECTURE.md)** — System design and database schema
- **[THEME_AND_UI.md](docs/THEME_AND_UI.md)** — UI structure by role
- **[Implementation Guide](IMPLEMENTATION_GUIDE.md)** — Setup and configuration
- **[Bug Elimination](docs/CHATGPT_BUG_ELIMINATION_SAMS.md)** — Role-based fixes

---

## Summary

**SAMS supports 14 comprehensive roles** organized across five categories:

| Category | Roles | Count |
|----------|-------|-------|
| Administrative & Platform | Super Admin, Owner, Principal, Admin | 4 |
| Academic | Teacher, Student, Parent | 3 |
| Support & Healthcare | Staff, Nurse, Librarian, Bursar, Accountant | 5 |
| Operations | Transport Officer | 1 |
| Community | Forum Moderator | 1 |

Each role is designed with **specific permissions, dashboards, and modules** appropriate to their responsibilities within the educational institution. The system ensures **data isolation, security, and role-appropriate access** through centralized role engine management.

---

**End of ROLES_REFERENCE.md**
