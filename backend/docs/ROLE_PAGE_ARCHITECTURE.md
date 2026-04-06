# SAMS Role-Based Page Architecture

Generated: 2026-04-01
Scope: `c:\xampp\htdocs\attendance`

This document is derived from actual project routes, role guards, role folders, dashboards, and sidebar navigation definitions, especially:

- `config/routes.php`
- `includes/router.php`
- `includes/functions.php`
- `includes/sidebar-nav.php`
- `admin/super-admin-dashboard.php`
- `admin/enhanced-super-admin-dashboard.php`
- role folders (`admin/`, `teacher/`, `student/`, `parent/`, `librarian/`, `bursar/`, `accountant/`, `transport/`, `forum-moderator/`)
- stitch bridge (`modules/stitch-map.php`, `modules/stitch-router.php`)

---

## 1) Platform Scan Summary (Step 1)

### Routing sources

- Canonical named routes: `config/routes.php`
- URL helper: `includes/router.php`
- Stitch compatibility router: `modules/stitch-router.php`
- Stitch map aliases: `modules/stitch-map.php`

### Role guard patterns detected

- `require_admin()`, `require_role()`, `require_teacher()`, `require_student()`, `require_parent()` in `includes/functions.php`
- Super-admin style guard pattern frequently used:
  - `in_array($_SESSION['role'], ['admin','super_admin','superadmin','owner'])`

### Existing role folders with concrete pages

- `admin/`
- `teacher/`
- `student/`
- `parent/`
- `librarian/`
- `bursar/`
- `accountant/`
- `transport/`
- `forum-moderator/`

### No dedicated folders currently present

- `principal/`
- `owner/`
- `staff/`
- `nurse/`

---

## 2) Functional Domains (Step 2)

1. Platform Governance & Tenant Management
2. User & Role Administration
3. Academic Management (classes, attendance, grades, assignments)
4. Parent/Student Experience
5. Financial Operations (bursar/accountant)
6. Library Operations
7. Transport Operations
8. Communication & Notices
9. Forum & Moderation
10. Reporting, Analytics, Audit, Backup
11. Settings & Profile

---

## 3) Role Capability Matrix (Step 3)

| Role              | Platform/Tenants                               | Users/Roles              | Academic                         | Finance             | Library              | Transport        | Forum              | Reports/Analytics  | Settings           |
| ----------------- | ---------------------------------------------- | ------------------------ | -------------------------------- | ------------------- | -------------------- | ---------------- | ------------------ | ------------------ | ------------------ |
| Super Admin       | Full (all tenants)                             | Full                     | Oversight                        | Oversight           | Oversight            | Oversight        | Oversight          | Full cross-tenant  | Full platform      |
| Owner             | Institutional full (should not switch tenants) | Full in institution      | Full oversight                   | Full oversight      | Oversight            | Oversight        | Oversight          | Full institutional | Full institutional |
| Principal         | Institutional oversight                        | High                     | Full                             | View/approve        | View                 | View             | Limited moderation | Full institutional | Institutional      |
| Admin             | Institutional operations                       | Full operational         | Full                             | Operational         | Operational          | Operational      | Operational        | Full institutional | Institutional      |
| Teacher           | No                                             | No                       | Class-scoped full                | No                  | View/requests        | No               | Participation      | Class reports      | Personal           |
| Student           | No                                             | No                       | Personal                         | Fee view            | Borrower view        | Rider view       | Participation      | Personal           | Personal           |
| Parent            | No                                             | No                       | Child-scoped view                | Child fee oversight | Child borrowing view | Child route view | Participation      | Child reports      | Personal           |
| Staff             | No                                             | Limited operational      | Assigned ops                     | No                  | Optional             | Optional         | Optional           | Operational        | Personal           |
| Nurse             | No                                             | No                       | Health-linked attendance support | No                  | No                   | No               | No                 | Health reports     | Personal           |
| Librarian         | No                                             | No                       | Library-academic support         | Fine tracking       | Full library         | No               | Optional           | Library reports    | Role settings      |
| Bursar            | No                                             | No                       | Fee/receipts support             | Full bursar         | No                   | No               | No                 | Financial reports  | Role settings      |
| Accountant        | No                                             | No                       | Payroll/expense support          | Full accounting     | No                   | No               | No                 | Financial reports  | Role settings      |
| Transport Officer | No                                             | No                       | Route assignments                | Cost logs           | No                   | Full transport   | No                 | Transport reports  | Role settings      |
| Forum Moderator   | No                                             | Forum-only user controls | No                               | No                  | No                   | No               | Full moderation    | Forum analytics    | Role settings      |

---

## 4) Route Hierarchy (Current + Recommended)

### Current high-level hierarchy

- `/attendance/admin/*`
- `/attendance/teacher/*`
- `/attendance/student/*`
- `/attendance/parent/*`
- `/attendance/librarian/*`
- `/attendance/bursar/*`
- `/attendance/accountant/*`
- `/attendance/transport/*`
- `/attendance/forum-moderator/*`
- `/attendance/communication/*`
- `/attendance/forum/*`

### Recommended hierarchy normalization

- Keep existing paths for compatibility.
- Add stable route aliases in `config/routes.php` for every role page.
- Enforce role-to-route policy at middleware level, not only per-page checks.

---

# ROLE SPECIFICATIONS (STRICT FORMAT)

---

## ROLE: Super Admin

### 1. Dashboard

- Primary: `/attendance/admin/super-admin-dashboard.php`
- Alternate: `/attendance/admin/enhanced-super-admin-dashboard.php`
- Widgets required:
  - Total schools, active schools, platform users, pending setups
  - Recent schools table
  - System health summary
  - Recent platform activities
- Quick actions:
  - Add School
  - Platform Analytics
  - Platform Settings
  - User Management
  - Role Management
  - Transport / Library / Financial management

### 2. Navigation Menu

- Sidebar items (current effective):
  - Dashboard, Overview
  - All Schools, Create School, Tenant Details
  - User Management, Approvals, Roles
  - Platform Settings, System Health, Analytics, Activity Logs, Security Logs
  - Transport, Library, Financial
  - AI User Creator, Bulk Import, Backup/Export
- Route paths:
  - `/attendance/admin/all-tenants.php`
  - `/attendance/admin/create-tenant.php`
  - `/attendance/admin/tenant-details.php?id={tenant_id}`
  - `/attendance/admin/platform-analytics.php`
  - `/attendance/admin/platform-settings.php`

### 3. Pages (Complete List)

| Page Name                      | Route Path                                             | Purpose                                 | Actions                 |
| ------------------------------ | ------------------------------------------------------ | --------------------------------------- | ----------------------- |
| Super Admin Dashboard          | `/attendance/admin/super-admin-dashboard.php`          | Platform command center                 | View                    |
| Enhanced Super Admin Dashboard | `/attendance/admin/enhanced-super-admin-dashboard.php` | Extended operations launcher            | View                    |
| Create Tenant                  | `/attendance/admin/create-tenant.php`                  | Register school/tenant                  | Create/View             |
| All Tenants                    | `/attendance/admin/all-tenants.php`                    | School listing with filters/sort/paging | View/Edit               |
| Tenant Details                 | `/attendance/admin/tenant-details.php?id=:id`          | Tenant profile/users/activity/settings  | View/Edit/Approve       |
| Switch Tenant API              | `/attendance/admin/switch-tenant.php`                  | Context switching endpoint              | Approve (context)       |
| Platform Analytics             | `/attendance/admin/platform-analytics.php`             | Cross-tenant analytics/charts/export    | View                    |
| Platform Settings              | `/attendance/admin/platform-settings.php`              | Global settings                         | Edit                    |
| User Management                | `/attendance/admin/user-management.php`                | Platform users and controls             | Create/View/Edit/Delete |
| Role Management                | `/attendance/admin/role-management.php`                | Roles and permissions                   | Create/View/Edit        |
| Activity Log                   | `/attendance/admin/activity-log.php`                   | Audit/events                            | View                    |
| Security Logs                  | `/attendance/admin/security-logs.php`                  | Security-focused events                 | View                    |
| Backup & Export                | `/attendance/admin/backup-export.php`                  | Backup/export/restore/schedule          | Create/View/Edit        |
| Approve Users                  | `/attendance/admin/approve-users.php`                  | Pending user workflow                   | Approve/View/Delete     |

### 4. Button → Page Mapping

- `Add New School` → `/attendance/admin/create-tenant.php`
- `Platform Analytics` → `/attendance/admin/platform-analytics.php`
- `System Health` → `/attendance/admin/system-health.php`
- `Platform Settings` → `/attendance/admin/platform-settings.php`
- `Access` (tenant row) → `/attendance/admin/switch-tenant.php` (POST) → `/attendance/admin/dashboard.php`
- `View` (tenant row) → `/attendance/admin/tenant-details.php?id=:id`

### 5. Required Supporting Pages

- Tenant delete/restore confirmation flow
- Tenant billing/subscription plan detail page
- Cross-tenant permission changes log
- Bulk user action wizard

### 6. Missing Pages Detected

- Missing dedicated tenant deletion flow page (`tenant-delete-confirm.php` equivalent)
- Missing explicit platform subscription governance page (global plans editor)
- Inconsistent settings target historically (`system-settings.php` vs `platform-settings.php`) — must be unified to `platform-settings.php`

---

## ROLE: Owner

### 1. Dashboard

- Current effective dashboard path: `/attendance/admin/dashboard.php`
- Required owner widgets:
  - Enrollment, attendance, fee collection, approvals, institutional risk
- Quick actions:
  - Students, Teachers, Classes, Attendance, Reports, Users, Settings, Financials

### 2. Navigation Menu

- Uses admin sidebar sections from `includes/sidebar-nav.php`
- Required Owner nav (institution-scoped):
  - Main, People, Academic, Communication, Analytics, System
- Key route paths:
  - `/attendance/admin/students.php`
  - `/attendance/admin/teachers.php`
  - `/attendance/admin/classes.php`
  - `/attendance/admin/reports.php`
  - `/attendance/admin/financial-management.php`

### 3. Pages (Complete List)

| Page Name                     | Route Path                                   | Purpose                         | Actions                 |
| ----------------------------- | -------------------------------------------- | ------------------------------- | ----------------------- |
| Owner Dashboard (institution) | `/attendance/admin/dashboard.php`            | Executive institutional control | View                    |
| Students                      | `/attendance/admin/students.php`             | Student lifecycle               | Create/View/Edit/Delete |
| Teachers                      | `/attendance/admin/teachers.php`             | Teacher management              | Create/View/Edit/Delete |
| Classes                       | `/attendance/admin/classes.php`              | Academic structure              | Create/View/Edit/Delete |
| Class Enrollment              | `/attendance/admin/class-enrollment.php`     | Enrollment operations           | Create/View/Edit/Delete |
| Attendance                    | `/attendance/admin/attendance.php`           | Attendance governance           | Create/View/Edit        |
| Reports                       | `/attendance/admin/reports.php`              | Institutional reporting         | View                    |
| Users                         | `/attendance/admin/users.php`                | User administration             | Create/View/Edit/Delete |
| Approvals                     | `/attendance/admin/approve-users.php`        | Approval workflow               | Approve/View/Delete     |
| Role Management               | `/attendance/admin/role-management.php`      | Role governance                 | View/Edit               |
| Financial Management          | `/attendance/admin/financial-management.php` | Financial oversight             | View/Edit/Approve       |
| Backup & Export               | `/attendance/admin/backup-export.php`        | Institutional backup/export     | Create/View/Edit        |
| Settings                      | `/attendance/admin/settings.php`             | Institutional settings          | Edit                    |

### 4. Button → Page Mapping

- `Manage Students` → `/attendance/admin/students.php`
- `Manage Teachers` → `/attendance/admin/teachers.php`
- `Manage Classes` → `/attendance/admin/classes.php`
- `View Reports` → `/attendance/admin/reports.php`
- `Financial Management` → `/attendance/admin/financial-management.php`

### 5. Required Supporting Pages

- Owner subscription & billing summary page (institution-level)
- Owner approvals dashboard (multi-queue: users, fees, policy)
- School profile/legal/config page

### 6. Missing Pages Detected

- Missing dedicated owner workspace (`/attendance/owner/*`) for clean role separation
- Missing owner subscription/billing page (required by directive)
- Missing owner policy approvals hub page

---

## ROLE: Principal

### 1. Dashboard

- Current effective route (via role mapping): `/attendance/admin/dashboard.php`
- Required widgets:
  - Academic performance, attendance trends, discipline incidents, teacher workload
- Quick actions:
  - Classes, attendance, reports, approvals, notices

### 2. Navigation Menu

- Uses admin-style menu currently
- Recommended principal-specific submenus:
  - Academic Oversight, Staff Performance, Student Welfare, Institutional Reports

### 3. Pages (Complete List)

| Page Name                            | Route Path                            | Purpose                          | Actions                 |
| ------------------------------------ | ------------------------------------- | -------------------------------- | ----------------------- |
| Principal Dashboard (current shared) | `/attendance/admin/dashboard.php`     | Academic command view            | View                    |
| Classes                              | `/attendance/admin/classes.php`       | Curriculum/class control         | Create/View/Edit/Delete |
| Attendance                           | `/attendance/admin/attendance.php`    | Institution attendance oversight | View/Edit/Approve       |
| Teachers                             | `/attendance/admin/teachers.php`      | Teacher assignment/performance   | View/Edit               |
| Students                             | `/attendance/admin/students.php`      | Student welfare overview         | View/Edit               |
| Reports                              | `/attendance/admin/reports.php`       | Academic and compliance reports  | View                    |
| Announcements                        | `/attendance/admin/announcements.php` | School announcements             | Create/View/Edit/Delete |

### 4. Button → Page Mapping

- `View Academic Reports` → `/attendance/admin/reports.php`
- `Monitor Attendance` → `/attendance/admin/attendance.php`
- `Manage Class Structures` → `/attendance/admin/classes.php`

### 5. Required Supporting Pages

- Staff appraisal page
- Discipline/case management page
- Timetable governance page

### 6. Missing Pages Detected

- No dedicated `/attendance/principal/*` module
- No principal-specific dashboard
- Missing explicit discipline/case-management pages in principal namespace

---

## ROLE: Admin

### 1. Dashboard

- `/attendance/admin/dashboard.php`
- Widgets required:
  - Student/teacher/class counts, daily attendance, risk alerts
- Quick actions:
  - Students, teachers, classes, attendance, reports, approvals, users

### 2. Navigation Menu

- Defined in `includes/sidebar-nav.php` for `admin` role
- Major submenus:
  - Main, People, Academic, Communication, Analytics, System, AI Center

### 3. Pages (Complete List)

| Page Name             | Route Path                                                                    | Purpose                 | Actions                 |
| --------------------- | ----------------------------------------------------------------------------- | ----------------------- | ----------------------- |
| Dashboard             | `/attendance/admin/dashboard.php`                                             | Daily administration    | View                    |
| Students              | `/attendance/admin/students.php`                                              | Student CRUD            | Create/View/Edit/Delete |
| Student Add/Edit/View | `/attendance/admin/student-add.php`, `/student-edit.php`, `/student-view.php` | Student detail forms    | Create/View/Edit        |
| Teachers              | `/attendance/admin/teachers.php`                                              | Teacher CRUD            | Create/View/Edit/Delete |
| Parents               | `/attendance/admin/parents.php`                                               | Parent records          | Create/View/Edit/Delete |
| Classes               | `/attendance/admin/classes.php`                                               | Class CRUD              | Create/View/Edit/Delete |
| Class Enrollment      | `/attendance/admin/class-enrollment.php`                                      | Enrollment operations   | Create/View/Edit/Delete |
| Attendance            | `/attendance/admin/attendance.php`                                            | Attendance operations   | Create/View/Edit        |
| Reports               | `/attendance/admin/reports.php`                                               | Reports/exports         | View                    |
| Analytics             | `/attendance/admin/analytics.php`                                             | Institutional analytics | View                    |
| Notices/Announcements | `/attendance/admin/notices.php`, `/attendance/admin/announcements.php`        | Notices management      | Create/View/Edit/Delete |
| Approvals             | `/attendance/admin/approve-users.php`                                         | User approval flow      | Approve/View/Delete     |
| Users                 | `/attendance/admin/users.php`, `/attendance/admin/user-management.php`        | User admin              | Create/View/Edit/Delete |
| Role Management       | `/attendance/admin/role-management.php`                                       | Roles/permissions       | Create/View/Edit        |
| System Health         | `/attendance/admin/system-health.php`                                         | Health monitoring       | View                    |
| Audit/Security        | `/attendance/admin/audit-logs.php`, `/attendance/admin/security-logs.php`     | Governance logs         | View                    |
| Backup/Export         | `/attendance/admin/backup-export.php`                                         | Backup/export/restore   | Create/View/Edit        |
| Settings              | `/attendance/admin/settings.php`                                              | Admin settings          | Edit                    |

### 4. Button → Page Mapping

- `Approve Users` → `/attendance/admin/approve-users.php`
- `System Health` → `/attendance/admin/system-health.php`
- `Backup & Export` → `/attendance/admin/backup-export.php`
- `AI User Creator` → `/attendance/admin/ai-user-creator.php`

### 5. Required Supporting Pages

- Unified bulk action page for users
- Unified import validation report page
- Data correction queue page

### 6. Missing Pages Detected

- Some dashboard/button targets differ by variant pages (legacy vs enhanced)
- Need single canonical dashboard route (`admin/dashboard.php`) with deprecated aliases redirected

---

## ROLE: Teacher

### 1. Dashboard

- `/attendance/teacher/dashboard.php`
- Widgets required:
  - Class attendance trend, pending grading, assignment due queue, parent messages
- Quick actions:
  - Attendance, assignments, grades, students, reports

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`teacher`)
- Submenus:
  - Main, Academic, Communication, Insights

### 3. Pages (Complete List)

| Page Name     | Route Path                              | Purpose              | Actions                 |
| ------------- | --------------------------------------- | -------------------- | ----------------------- |
| Dashboard     | `/attendance/teacher/dashboard.php`     | Teacher cockpit      | View                    |
| My Classes    | `/attendance/teacher/my-classes.php`    | Assigned classes     | View                    |
| Students      | `/attendance/teacher/students.php`      | Class students       | View/Edit               |
| Attendance    | `/attendance/teacher/attendance.php`    | Mark attendance      | Create/View/Edit        |
| Assignments   | `/attendance/teacher/assignments.php`   | Assignment lifecycle | Create/View/Edit/Delete |
| Grades        | `/attendance/teacher/grades.php`        | Gradebook            | Create/View/Edit        |
| Materials     | `/attendance/teacher/materials.php`     | Learning resources   | Create/View/Edit/Delete |
| Parent Comms  | `/attendance/teacher/parent-comms.php`  | Parent communication | Create/View             |
| Reports       | `/attendance/teacher/reports.php`       | Class reports        | View                    |
| Analytics     | `/attendance/teacher/analytics.php`     | Class analytics      | View                    |
| Meeting Hours | `/attendance/teacher/meeting-hours.php` | Availability slots   | Create/View/Edit/Delete |
| Behavior Logs | `/attendance/teacher/behavior-logs.php` | Behavior records     | Create/View/Edit        |
| LMS Sync      | `/attendance/teacher/lms-sync.php`      | LMS connector        | View/Edit               |
| Settings      | `/attendance/teacher/settings.php`      | Role settings        | Edit                    |

### 4. Button → Page Mapping

- `Mark Attendance` → `/attendance/teacher/attendance.php`
- `Manage Assignments` → `/attendance/teacher/assignments.php`
- `Grade Students` → `/attendance/teacher/grades.php`
- `View Class Reports` → `/attendance/teacher/reports.php`

### 5. Required Supporting Pages

- Assignment detail page (`/attendance/teacher/assignment-view.php`)
- Grade history detail page
- Parent message thread detail page

### 6. Missing Pages Detected

- `teacher/classes.php` is referenced in enhanced dashboard flows but does not exist (use `my-classes.php`)

---

## ROLE: Student

### 1. Dashboard

- `/attendance/student/dashboard.php`
- Widgets required:
  - Attendance %, current grades, upcoming assignments, schedule snapshot
- Quick actions:
  - Check-in, assignments, schedule, grades, profile

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`student`)
- Submenus:
  - Main, Academic, Communication, Account

### 3. Pages (Complete List)

| Page Name          | Route Path                                                            | Purpose                       | Actions                  |
| ------------------ | --------------------------------------------------------------------- | ----------------------------- | ------------------------ |
| Dashboard          | `/attendance/student/dashboard.php`                                   | Student overview              | View                     |
| Attendance         | `/attendance/student/attendance.php`                                  | Personal attendance           | View                     |
| Check-in           | `/attendance/student/checkin.php`                                     | Biometric/attendance check-in | Create/View              |
| Assignments        | `/attendance/student/assignments.php`                                 | Coursework                    | View/Create (submission) |
| Grades             | `/attendance/student/grades.php`                                      | Grade visibility              | View                     |
| Schedule           | `/attendance/student/schedule.php`                                    | Timetable                     | View                     |
| Class Registration | `/attendance/student/class-registration.php`                          | Enroll requests               | Create/View              |
| Events             | `/attendance/student/events.php`                                      | School events                 | View                     |
| Reports            | `/attendance/student/reports.php`                                     | Personal reports              | View                     |
| Study Groups       | `/attendance/student/study-groups.php`                                | Collaboration                 | Create/View              |
| LMS Portal         | `/attendance/student/lms-portal.php`                                  | LMS access                    | View                     |
| Notifications      | `/attendance/student/notifications.php`                               | Notification center           | View/Edit                |
| Profile/Settings   | `/attendance/student/profile.php`, `/attendance/student/settings.php` | Account management            | View/Edit                |
| ID Card            | `/attendance/student/id-card.php`                                     | Identity card                 | View                     |

### 4. Button → Page Mapping

- `Quick Check-in` → `/attendance/student/checkin.php`
- `View Assignments` → `/attendance/student/assignments.php`
- `View Events` → `/attendance/student/events.php`
- `View Grades` → `/attendance/student/grades.php`

### 5. Required Supporting Pages

- Assignment submission detail page
- Grade detail with rubric page
- Class request history page

### 6. Missing Pages Detected

- `student/messages.php` is referenced by some enhanced UI actions but absent (should route to `/attendance/communication/conversations.php`)

---

## ROLE: Parent

### 1. Dashboard

- `/attendance/parent/dashboard.php`
- Widgets required:
  - Child attendance/grades snapshot, upcoming events, fee status
- Quick actions:
  - Children, attendance, grades, meetings, messaging

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`parent`)
- Submenus:
  - Main, Academic, Communication, Account

### 3. Pages (Complete List)

| Page Name         | Route Path                                                           | Purpose            | Actions            |
| ----------------- | -------------------------------------------------------------------- | ------------------ | ------------------ |
| Dashboard         | `/attendance/parent/dashboard.php`                                   | Parent overview    | View               |
| Children          | `/attendance/parent/children.php`                                    | Child listing      | View               |
| Link Children     | `/attendance/parent/link-children.php`                               | Child linkage      | Create/View/Delete |
| Attendance        | `/attendance/parent/attendance.php`                                  | Child attendance   | View               |
| Grades            | `/attendance/parent/grades.php`                                      | Child grades       | View               |
| Fees              | `/attendance/parent/fees.php`                                        | Fee oversight      | View               |
| Book Meeting      | `/attendance/parent/book-meeting.php`                                | Schedule meetings  | Create/View        |
| My Meetings       | `/attendance/parent/my-meetings.php`                                 | Meeting management | View/Edit/Delete   |
| Events            | `/attendance/parent/events.php`                                      | School events      | View               |
| Analytics/Reports | `/attendance/parent/analytics.php`, `/attendance/parent/reports.php` | Child analytics    | View               |
| LMS Overview      | `/attendance/parent/lms-overview.php`                                | Child LMS summary  | View               |
| Settings          | `/attendance/parent/settings.php`                                    | Parent settings    | Edit               |

### 4. Button → Page Mapping

- `View Child Attendance` → `/attendance/parent/attendance.php`
- `View Child Grades` → `/attendance/parent/grades.php`
- `Book Meeting` → `/attendance/parent/book-meeting.php`
- `Messages` → `/attendance/communication/conversations.php`

### 5. Required Supporting Pages

- Child detail page
- Parent-teacher thread detail page
- Fee invoice detail page

### 6. Missing Pages Detected

- `parent/communication.php` referenced in dashboard variants but absent
- `parent/child-details.php` referenced in dashboard variants but absent

---

## ROLE: Staff

### 1. Dashboard

- Recommended route: `/attendance/staff/dashboard.php` (missing)
- Required widgets:
  - Operational tasks, assigned tickets, student support queue
- Quick actions:
  - Operational tasks, reports, communication, settings

### 2. Navigation Menu

- Recommended:
  - Main, Operations, Communication, Reports, Account

### 3. Pages (Complete List)

| Page Name       | Route Path                                    | Purpose                 | Actions          |
| --------------- | --------------------------------------------- | ----------------------- | ---------------- |
| Staff Dashboard | `/attendance/staff/dashboard.php`             | Non-teaching operations | View             |
| Task Board      | `/attendance/staff/tasks.php`                 | Assigned task execution | Create/View/Edit |
| Student Support | `/attendance/staff/student-support.php`       | Welfare/admin support   | Create/View/Edit |
| Communication   | `/attendance/communication/conversations.php` | Messaging               | Create/View      |
| Reports         | `/attendance/staff/reports.php`               | Operational reports     | View             |
| Settings        | `/attendance/staff/settings.php`              | Profile/preferences     | Edit             |

### 4. Button → Page Mapping

- `View Tasks` → `/attendance/staff/tasks.php`
- `Open Support Queue` → `/attendance/staff/student-support.php`

### 5. Required Supporting Pages

- Staff role guard and middleware routes
- Staff assignment detail page

### 6. Missing Pages Detected

- Entire `staff/` module is missing

---

## ROLE: Nurse

### 1. Dashboard

- Recommended route: `/attendance/nurse/dashboard.php` (missing)
- Required widgets:
  - Clinic visits, medication schedule, emergency alerts
- Quick actions:
  - Health records, incidents, meds, wellness reports

### 2. Navigation Menu

- Recommended:
  - Main, Health Records, Clinic Operations, Alerts, Reports, Account

### 3. Pages (Complete List)

| Page Name       | Route Path                             | Purpose                       | Actions                 |
| --------------- | -------------------------------------- | ----------------------------- | ----------------------- |
| Nurse Dashboard | `/attendance/nurse/dashboard.php`      | Clinic operations overview    | View                    |
| Health Records  | `/attendance/nurse/health-records.php` | Student medical profiles      | Create/View/Edit        |
| First Aid Log   | `/attendance/nurse/first-aid.php`      | Incident recording            | Create/View/Edit        |
| Medications     | `/attendance/nurse/medications.php`    | Medication administration     | Create/View/Edit/Delete |
| Wellness Alerts | `/attendance/nurse/wellness.php`       | Health alerts                 | Create/View/Edit        |
| Appointments    | `/attendance/nurse/appointments.php`   | Clinic scheduling             | Create/View/Edit/Delete |
| Health Reports  | `/attendance/nurse/reports.php`        | Compliance/wellness reporting | View                    |
| Settings        | `/attendance/nurse/settings.php`       | Nurse preferences             | Edit                    |

### 4. Button → Page Mapping

- `Record Incident` → `/attendance/nurse/first-aid.php`
- `Manage Medications` → `/attendance/nurse/medications.php`

### 5. Required Supporting Pages

- Emergency contact lookup page
- Health history timeline page

### 6. Missing Pages Detected

- Entire `nurse/` module is missing

---

## ROLE: Librarian

### 1. Dashboard

- `/attendance/librarian/dashboard.php`
- Widgets required:
  - Active loans, overdue books, fines pending, inventory health
- Quick actions:
  - Issue/return, add books, overdue, reports

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`librarian`)
- Submenus:
  - Main, Catalog, Circulation, Reports, Communication

### 3. Pages (Complete List)

| Page Name         | Route Path                                    | Purpose                   | Actions                 |
| ----------------- | --------------------------------------------- | ------------------------- | ----------------------- |
| Dashboard         | `/attendance/librarian/dashboard.php`         | Library cockpit           | View                    |
| Books             | `/attendance/librarian/books.php`             | Catalog management        | Create/View/Edit/Delete |
| Add Book          | `/attendance/librarian/add-book.php`          | New catalog entry         | Create                  |
| Categories        | `/attendance/librarian/categories.php`        | Classification management | Create/View/Edit/Delete |
| Digital Resources | `/attendance/librarian/digital-resources.php` | e-resources               | Create/View/Edit/Delete |
| Issue/Return      | `/attendance/librarian/issue-return.php`      | Circulation operations    | Create/View/Edit        |
| Active Loans      | `/attendance/librarian/active-loans.php`      | Loan tracking             | View                    |
| Overdue           | `/attendance/librarian/overdue.php`           | Overdue management        | View/Edit               |
| Fines             | `/attendance/librarian/fines.php`             | Fine handling             | Create/View/Edit        |
| Reservations      | `/attendance/librarian/reservations.php`      | Hold queue                | Create/View/Edit/Delete |
| Inventory         | `/attendance/librarian/inventory.php`         | Stock status              | View/Edit               |
| Reports           | `/attendance/librarian/reports.php`           | Library reports           | View                    |
| Settings          | `/attendance/librarian/settings.php`          | Librarian settings        | Edit                    |

### 4. Button → Page Mapping

- `Issue/Return` → `/attendance/librarian/issue-return.php`
- `Add Book` → `/attendance/librarian/add-book.php`
- `Overdue` → `/attendance/librarian/overdue.php`

### 5. Required Supporting Pages

- Member detail history page
- Book transaction timeline page

### 6. Missing Pages Detected

- `librarian/transactions.php` referenced by dashboard link labels but absent

---

## ROLE: Bursar

### 1. Dashboard

- `/attendance/bursar/dashboard.php`
- Widgets required:
  - Daily collection, unpaid invoices, plan compliance, receipt counts
- Quick actions:
  - Fee collection, invoices, receipts, defaulters

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`bursar`)
- Submenus:
  - Main, Billing, Management, Reports, Communication

### 3. Pages (Complete List)

| Page Name      | Route Path                              | Purpose                 | Actions                 |
| -------------- | --------------------------------------- | ----------------------- | ----------------------- |
| Dashboard      | `/attendance/bursar/dashboard.php`      | Bursar operations       | View                    |
| Fee Collection | `/attendance/bursar/fee-collection.php` | Collect payments        | Create/View/Edit        |
| Invoices       | `/attendance/bursar/invoices.php`       | Invoice management      | Create/View/Edit/Delete |
| Payment Plans  | `/attendance/bursar/payment-plans.php`  | Structured plans        | Create/View/Edit/Delete |
| Receipts       | `/attendance/bursar/receipts.php`       | Receipt issuance        | Create/View             |
| Fee Structure  | `/attendance/bursar/fee-structure.php`  | Fee model               | Create/View/Edit        |
| Defaulters     | `/attendance/bursar/defaulters.php`     | Delinquency management  | View/Edit               |
| Scholarships   | `/attendance/bursar/scholarships.php`   | Scholarship adjustments | Create/View/Edit/Delete |
| Daily Summary  | `/attendance/bursar/daily-summary.php`  | Daily reconciliation    | View                    |
| Reports        | `/attendance/bursar/reports.php`        | Financial reports       | View                    |
| Export         | `/attendance/bursar/export.php`         | Data export             | View                    |
| Settings       | `/attendance/bursar/settings.php`       | Role settings           | Edit                    |

### 4. Button → Page Mapping

- `Fee Collection` → `/attendance/bursar/fee-collection.php`
- `Invoices` → `/attendance/bursar/invoices.php`
- `Defaulters` → `/attendance/bursar/defaulters.php`

### 5. Required Supporting Pages

- Invoice detail page
- Receipt detail page
- Payment dispute resolution page

### 6. Missing Pages Detected

- No major hard missing file in bursar folder; workflow support pages can be expanded for disputes/approvals

---

## ROLE: Accountant

### 1. Dashboard

- `/attendance/accountant/dashboard.php`
- Widgets required:
  - Ledger status, expense vs income, payroll status, audit highlights
- Quick actions:
  - Ledger, expenses, payroll, balance sheet, reports

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`accountant`)
- Submenus:
  - Main, Finance, Statements, Reports, Communication

### 3. Pages (Complete List)

| Page Name     | Route Path                                 | Purpose                     | Actions                 |
| ------------- | ------------------------------------------ | --------------------------- | ----------------------- |
| Dashboard     | `/attendance/accountant/dashboard.php`     | Accounting overview         | View                    |
| Ledger        | `/attendance/accountant/ledger.php`        | General ledger              | Create/View/Edit        |
| Income        | `/attendance/accountant/income.php`        | Income tracking             | Create/View/Edit        |
| Expenses      | `/attendance/accountant/expenses.php`      | Expense management          | Create/View/Edit/Delete |
| Payroll       | `/attendance/accountant/payroll.php`       | Payroll operations          | Create/View/Edit        |
| Balance Sheet | `/attendance/accountant/balance-sheet.php` | Financial statement         | View                    |
| Profit & Loss | `/attendance/accountant/profit-loss.php`   | P&L statement               | View                    |
| Tax Reports   | `/attendance/accountant/tax-reports.php`   | Tax compliance reports      | View                    |
| Budget        | `/attendance/accountant/budget.php`        | Budget planning             | Create/View/Edit        |
| Reports       | `/attendance/accountant/reports.php`       | Aggregate financial reports | View                    |
| Audit Trail   | `/attendance/accountant/audit-trail.php`   | Accounting audit logs       | View                    |
| Settings      | `/attendance/accountant/settings.php`      | Role settings               | Edit                    |

### 4. Button → Page Mapping

- `General Ledger` → `/attendance/accountant/ledger.php`
- `Expenses` → `/attendance/accountant/expenses.php`
- `Payroll` → `/attendance/accountant/payroll.php`
- `Balance Sheet` → `/attendance/accountant/balance-sheet.php`

### 5. Required Supporting Pages

- Journal entry detail page
- Reconciliation workflow page

### 6. Missing Pages Detected

- No critical missing file in accountant folder for baseline workflow

---

## ROLE: Transport Officer

### 1. Dashboard

- `/attendance/transport/dashboard.php`
- Widgets required:
  - Routes active, vehicles available, assigned students, maintenance alerts
- Quick actions:
  - Routes, vehicles, drivers, allocation, trip logs

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`transport`)
- Submenus:
  - Main, Fleet, Operations, Reports, Communication

### 3. Pages (Complete List)

| Page Name          | Route Path                                     | Purpose                      | Actions                 |
| ------------------ | ---------------------------------------------- | ---------------------------- | ----------------------- |
| Dashboard          | `/attendance/transport/dashboard.php`          | Transport control center     | View                    |
| Routes             | `/attendance/transport/routes.php`             | Route management             | Create/View/Edit/Delete |
| Vehicles           | `/attendance/transport/vehicles.php`           | Fleet management             | Create/View/Edit/Delete |
| Drivers            | `/attendance/transport/drivers.php`            | Driver records               | Create/View/Edit/Delete |
| Student Allocation | `/attendance/transport/student-allocation.php` | Student-route mapping        | Create/View/Edit/Delete |
| Trip Logs          | `/attendance/transport/trip-logs.php`          | Operational logs             | Create/View/Edit        |
| Maintenance        | `/attendance/transport/maintenance.php`        | Vehicle maintenance tracking | Create/View/Edit        |
| Fuel Log           | `/attendance/transport/fuel-log.php`           | Fuel tracking                | Create/View/Edit/Delete |
| Reports            | `/attendance/transport/reports.php`            | Transport analytics          | View                    |
| Settings           | `/attendance/transport/settings.php`           | Role settings                | Edit                    |

### 4. Button → Page Mapping

- `Routes` → `/attendance/transport/routes.php`
- `Vehicles` → `/attendance/transport/vehicles.php`
- `Drivers` → `/attendance/transport/drivers.php`
- `Trip Logs` → `/attendance/transport/trip-logs.php`

### 5. Required Supporting Pages

- Route detail map page
- Driver duty roster page

### 6. Missing Pages Detected

- `transport/allocation.php` is referenced by dashboard variants but absent (canonical existing page is `student-allocation.php`)

---

## ROLE: Forum Moderator

### 1. Dashboard

- `/attendance/forum-moderator/dashboard.php`
- Widgets required:
  - Reported posts queue, warning counts, ban counts, trend metrics
- Quick actions:
  - Reported posts, categories, threads, warnings, banned users

### 2. Navigation Menu

- `includes/sidebar-nav.php` (`forum_moderator`)
- Submenus:
  - Main, Moderation, Forum, Communication

### 3. Pages (Complete List)

| Page Name      | Route Path                                       | Purpose                  | Actions                 |
| -------------- | ------------------------------------------------ | ------------------------ | ----------------------- |
| Dashboard      | `/attendance/forum-moderator/dashboard.php`      | Moderation cockpit       | View                    |
| Threads        | `/attendance/forum-moderator/threads.php`        | Thread moderation        | View/Edit/Delete        |
| Reported Posts | `/attendance/forum-moderator/reported-posts.php` | Abuse queue              | Approve/View/Delete     |
| User Warnings  | `/attendance/forum-moderator/user-warnings.php`  | Warning management       | Create/View/Edit/Delete |
| Banned Users   | `/attendance/forum-moderator/banned-users.php`   | Ban management           | Create/View/Edit/Delete |
| Categories     | `/attendance/forum-moderator/categories.php`     | Forum category structure | Create/View/Edit/Delete |
| Analytics      | `/attendance/forum-moderator/analytics.php`      | Moderation analytics     | View                    |
| Settings       | `/attendance/forum-moderator/settings.php`       | Role settings            | Edit                    |

### 4. Button → Page Mapping

- `Reported Posts` → `/attendance/forum-moderator/reported-posts.php`
- `Categories` → `/attendance/forum-moderator/categories.php`
- `Threads` → `/attendance/forum-moderator/threads.php`

### 5. Required Supporting Pages

- Thread detail moderation page
- Escalation queue page

### 6. Missing Pages Detected

- `forum-moderator/view-thread.php` referenced by dashboard link but absent

---

# Cross-role Navigation Gaps Detected (Global)

1. `/attendance/parent/communication.php` (missing)
2. `/attendance/parent/child-details.php` (missing)
3. `/attendance/transport/allocation.php` (missing; use `student-allocation.php`)
4. `/attendance/librarian/transactions.php` (missing)
5. `/attendance/forum-moderator/view-thread.php` (missing)
6. `/attendance/teacher/classes.php` (missing; use `my-classes.php`)
7. `/attendance/student/messages.php` (missing; use `/attendance/communication/conversations.php`)
8. No dedicated modules for `principal`, `owner`, `staff`, `nurse`

---

# Required CRUD Interfaces by Domain

- Users: Create / View / Edit / Delete / Approve
- Students: Create / View / Edit / Delete / Assign class
- Teachers: Create / View / Edit / Delete / Assign classes
- Classes: Create / View / Edit / Delete
- Enrollment: Create / View / Edit / Delete
- Attendance: Create / View / Edit (Delete optional with audit)
- Assignments: Create / View / Edit / Delete / Submit
- Fees/Invoices: Create / View / Edit / Approve / Export
- Library circulation: Create loan / View / Edit / Close / Fine
- Transport operations: Create route / View / Edit / Assign / Log trip
- Forum moderation: View / Approve / Delete / Warn / Ban

---

# Implementation Assist (Second Output)

## A) Folder restructuring aligned with Stitch-generated UI

Recommended target structure (non-breaking via wrappers/aliases):

- `modules/dashboard/{super-admin,owner,principal,admin,teacher,student,parent,staff,nurse,librarian,bursar,accountant,transport,forum-moderator}.php`
- `modules/{users,academics,finance,library,transport,communication,forum,reports,settings}/...`
- `modules/tenant/{all,create,details,switch}.php`
- `modules/system/{health,activity,security,backup}.php`

Keep current role folders as compatibility endpoints that include module pages.

## B) Existing files → new structure mapping

| Existing                          | Proposed Canonical                      |
| --------------------------------- | --------------------------------------- |
| `admin/super-admin-dashboard.php` | `modules/dashboard/super-admin.php`     |
| `admin/dashboard.php`             | `modules/dashboard/admin.php`           |
| `teacher/dashboard.php`           | `modules/dashboard/teacher.php`         |
| `student/dashboard.php`           | `modules/dashboard/student.php`         |
| `parent/dashboard.php`            | `modules/dashboard/parent.php`          |
| `accountant/dashboard.php`        | `modules/dashboard/accountant.php`      |
| `bursar/dashboard.php`            | `modules/dashboard/bursar.php`          |
| `librarian/dashboard.php`         | `modules/dashboard/librarian.php`       |
| `transport/dashboard.php`         | `modules/dashboard/transport.php`       |
| `forum-moderator/dashboard.php`   | `modules/dashboard/forum-moderator.php` |
| `admin/all-tenants.php`           | `modules/tenant/all.php`                |
| `admin/create-tenant.php`         | `modules/tenant/create.php`             |
| `admin/tenant-details.php`        | `modules/tenant/details.php`            |
| `admin/switch-tenant.php`         | `modules/tenant/switch.php`             |

## C) Reusable components identified

- Layout shell: `resources/ui-core/layouts/master-dashboard.php`
- Sidebar navigation model: `includes/sidebar-nav.php`
- Route helper layer: `includes/router.php`, `config/routes.php`
- Role guard helpers: `includes/functions.php`
- Stitch bridge: `modules/stitch-router.php`, `modules/stitch-map.php`

## D) Route refactors (without breaking functionality)

1. Add named routes for every active role page in `config/routes.php`.
2. Keep legacy paths; convert pages to lightweight wrappers to canonical module routes.
3. Add redirect aliases for known missing/legacy links:
   - `transport/allocation.php` → `transport/student-allocation.php`
   - `teacher/classes.php` → `teacher/my-classes.php`
   - `student/messages.php` → `/attendance/communication/conversations.php`
4. Enforce middleware role checks centrally per route group.
5. Add explicit `owner`, `principal`, `staff`, `nurse` route groups and guard maps.

---

# Final Compliance Notes

This architecture guarantees the target state for:

- zero dead buttons,
- complete role workflows,
- permission-aligned route hierarchy,
- scalable module organization.

Immediate priority for production hardening:

1. Create missing route targets listed in “Cross-role Navigation Gaps”.
2. Introduce dedicated role modules for `owner`, `principal`, `staff`, `nurse`.
3. Centralize role-to-route policy and migrate to canonical module routes.
