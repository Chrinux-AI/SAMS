# ROLE INVENTORY REPORT

## Authoritative Sources

- `login.php`
- `includes/functions.php`
- `includes/sidebar-nav.php`
- `config/routes.php`
- `core/role_engine.php`
- `includes/SecurityMiddleware.php`
- `core/database.php`
- `owner/_owner_gate.php`
- `principal/_principal_gate.php`

## STEP 1 — ALL ROLES

### Runtime and dashboard roles detected

- `admin`
- `super_admin`
- `superadmin`
- `owner`
- `principal`
- `vice_principal`
- `admin_officer`
- `teacher`
- `class_teacher`
- `subject_coordinator`
- `student`
- `parent`
- `librarian`
- `bursar`
- `accountant`
- `transport`
- `forum_moderator`
- `nurse`
- `staff`
- `counselor`

### Legacy or alternate identifiers

- `moderator`
- `developer`
- `general`
- `guest`

## ROLE SUMMARY

### Admin

- Dashboard: `admin/dashboard.php`
- Key nav groups: Main, People, Academic, Communication, Analytics, System, AI Center
- Key dashboard actions:
  - `Manage Students -> students.php`
  - `Attendance -> attendance.php`
  - `Reports -> reports.php`
  - `Classes -> classes.php`
  - `View Full Activity Log -> audit-logs.php`
- Main pages:
  - `admin/students.php`
  - `admin/student-add.php`
  - `admin/student-view.php`
  - `admin/student-edit.php`
  - `admin/teachers.php`
  - `admin/parents.php`
  - `admin/classes.php`
  - `admin/class-enrollment.php`
  - `admin/attendance.php`
  - `admin/reports.php`
  - `admin/analytics.php`
  - `admin/users.php`
  - `admin/approve-users.php`
  - `admin/backup-export.php`
  - `admin/system-health.php`
  - `admin/settings.php`
  - `admin/role-management.php`
  - `admin/team-selection.php`
- Additional admin module pages:
  - `admin/activity-log.php`
  - `admin/activity-monitor.php`
  - `admin/advanced-admin.php`
  - `admin/ai-user-creator.php`
  - `admin/ai-user-management.php`
  - `admin/all-tenants.php`
  - `admin/announcements.php`
  - `admin/announcements-system.php`
  - `admin/attendance_new.php`
  - `admin/biometric-scan.php`
  - `admin/bulk-import.php`
  - `admin/class-management.php`
  - `admin/cloud-storage.php`
  - `admin/cognitive-center.php`
  - `admin/communication-hub.php`
  - `admin/create-tenant.php`
  - `admin/dashboard-enhanced.php`
  - `admin/emergency-alerts.php`
  - `admin/enhanced-analytics.php`
  - `admin/enhanced-super-admin-dashboard.php`
  - `admin/events.php`
  - `admin/export-logs.php`
  - `admin/facilities.php`
  - `admin/fee-management.php`
  - `admin/financial-management.php`
  - `admin/financial-reports.php`
  - `admin/get-role-data.php`
  - `admin/id-management.php`
  - `admin/intelligence-center.php`
  - `admin/library-management.php`
  - `admin/library-reports.php`
  - `admin/lms-settings.php`
  - `admin/manage-ids.php`
  - `admin/management.php`
  - `admin/mobile-api.php`
  - `admin/notices.php`
  - `admin/overview.php`
  - `admin/platform-analytics.php`
  - `admin/platform-settings.php`
  - `admin/pwa-management.php`
  - `admin/realtime-sync.php`
  - `admin/registrations.php`
  - `admin/reset-system.php`
  - `admin/security-center.php`
  - `admin/security-logs.php`
  - `admin/super-admin-dashboard.php`
  - `admin/switch-tenant.php`
  - `admin/system-management.php`
  - `admin/system-monitor.php`
  - `admin/tenant-details.php`
  - `admin/timetable.php`
  - `admin/transport-management.php`
  - `admin/transport-reports.php`
  - `admin/unapproved-users.php`
  - `admin/user-management.php`
- Workflow examples:
  - `dashboard.php -> students.php -> student-add.php|student-view.php|student-edit.php`
  - `dashboard.php -> approve-users.php -> users.php`
  - `dashboard.php -> classes.php -> class-enrollment.php`
  - `dashboard.php -> create-tenant.php -> all-tenants.php -> tenant-details.php`
- Guards:
  - `require_admin()`
  - some pages/API checks use `has_role('admin')`
  - some pages also permit `super_admin` or `owner`

### Owner

- Dashboard wrapper: `owner/dashboard.php -> owner/_owner_gate.php -> ../admin/dashboard.php`
- Navigation pages:
  - `owner/overview.php`
  - `owner/students.php`
  - `owner/teachers.php`
  - `owner/parents.php`
  - `owner/users.php`
  - `owner/approve-users.php`
  - `owner/classes.php`
  - `owner/class-enrollment.php`
  - `owner/attendance.php`
  - `owner/reports.php`
  - `owner/notices.php`
  - `owner/events.php`
  - `owner/financial-management.php`
  - `owner/library-management.php`
  - `owner/transport-management.php`
  - `owner/activity-monitor.php`
  - `owner/backup-export.php`
  - `owner/analytics.php`
  - `owner/system-health.php`
  - `owner/role-management.php`
  - `owner/settings.php`
  - `owner/team-selection.php`
- Permission model:
  - requires login
  - exact role `owner`
  - non-owner redirected to `get_role_dashboard_path()`

### Principal

- Dashboard wrapper: `principal/dashboard.php -> principal/_principal_gate.php -> ../admin/dashboard.php`
- Navigation pages:
  - `principal/overview.php`
  - `principal/students.php`
  - `principal/teachers.php`
  - `principal/parents.php`
  - `principal/users.php`
  - `principal/approve-users.php`
  - `principal/classes.php`
  - `principal/class-enrollment.php`
  - `principal/attendance.php`
  - `principal/reports.php`
  - `principal/events.php`
  - `principal/notices.php`
  - `principal/analytics.php`
  - `principal/system-health.php`
  - `principal/activity-monitor.php`
  - `principal/role-management.php`
  - `principal/settings.php`
  - `principal/team-selection.php`
- Permission model:
  - exact roles `principal` or `vice_principal`
  - other roles redirected to mapped dashboard

### Staff

- Dashboard: `staff/dashboard.php`
- Widgets:
  - Tasks
  - Open Tasks
  - Support Cases
  - Resolved Cases
- Dashboard actions:
  - `Task Board -> tasks.php`
  - `Student Support -> student-support.php`
  - `Operational Reports -> reports.php`
- Pages:
  - `staff/tasks.php`
  - `staff/student-support.php`
  - `staff/reports.php`
  - `staff/settings.php`
  - `staff/team-selection.php`
- Guard:
  - `require_role('staff', '../login.php')`

### Nurse

- Dashboard: `nurse/dashboard.php`
- Widgets:
  - Health Records
  - First Aid Logs
  - Medication Plans
- Dashboard actions:
  - `Health Records -> health-records.php`
  - `First Aid -> first-aid.php`
  - `Medications -> medications.php`
- Pages:
  - `nurse/health-records.php`
  - `nurse/first-aid.php`
  - `nurse/medications.php`
  - `nurse/wellness.php`
  - `nurse/reports.php`
  - `nurse/settings.php`
  - `nurse/team-selection.php`
- Guard:
  - `require_role('nurse', '../login.php')`

### Teacher

- Dashboard: `teacher/dashboard.php`
- Navigation groups: Main, Academic, Communication, Insights
- Dashboard actions:
  - `Take Attendance -> attendance.php`
  - `Manage Assignments -> assignments.php`
  - `Manage Grades -> grades.php`
  - `My Students -> students.php`
  - `Messages -> ../communication/conversations.php`
  - `Class Reports -> reports.php`
- Pages:
  - `teacher/my-classes.php`
  - `teacher/students.php`
  - `teacher/attendance.php`
  - `teacher/materials.php`
  - `teacher/assignments.php`
  - `teacher/grades.php`
  - `teacher/class-enrollment.php`
  - `teacher/parent-comms.php`
  - `teacher/resources.php`
  - `teacher/behavior-logs.php`
  - `teacher/meeting-hours.php`
  - `teacher/analytics.php`
  - `teacher/reports.php`
  - `teacher/lms-sync.php`
  - `teacher/settings.php`
  - `teacher/team-selection.php`
- Additional direct pages:
  - `teacher/classes.php`
  - `teacher/dashboard-enhanced.php`
  - `teacher/emergency-alerts.php`
  - `teacher/resource-library.php`
- Guard:
  - `require_teacher()`
  - `has_role('teacher')`

### Student

- Dashboard: `student/dashboard.php`
- Navigation groups: Main, Academic, Communication, Account
- Dashboard actions:
  - `View Assignments -> assignments.php`
  - `Class Schedule -> schedule.php`
  - `My Attendance -> attendance.php`
  - `Grades -> grades.php`
  - `Messages -> ../communication/conversations.php`
  - `Profile -> profile.php`
- Pages:
  - `student/schedule.php`
  - `student/attendance.php`
  - `student/checkin.php`
  - `student/class-registration.php`
  - `student/assignments.php`
  - `student/grades.php`
  - `student/events.php`
  - `student/lms-portal.php`
  - `student/study-groups.php`
  - `student/profile.php`
  - `student/id-card.php`
  - `student/settings.php`
  - `student/team-selection.php`
- Additional direct pages:
  - `student/analytics.php`
  - `student/attendance-enhanced.php`
  - `student/checkin-enhanced.php`
  - `student/dashboard-enhanced.php`
  - `student/emergency-alerts.php`
  - `student/messages.php`
  - `student/notifications.php`
  - `student/reports.php`
  - `student/study-group-view.php`
- Guard:
  - `require_student()`
  - `has_role('student')`
- Special cross-role note:
  - `student/class-registration.php` also allows `teacher` and `admin`

### Parent

- Dashboard: `parent/dashboard.php`
- Navigation groups: Main, Academic, Communication, Account
- Dashboard actions:
  - `Track Attendance -> attendance.php`
  - `Contact Teachers -> communication.php`
  - `View Full Profile -> child-details.php?student_id={id}`
  - `Messages -> ../communication/conversations.php`
  - `Settings -> settings.php`
- Pages:
  - `parent/children.php`
  - `parent/link-children.php`
  - `parent/attendance.php`
  - `parent/grades.php`
  - `parent/fees.php`
  - `parent/events.php`
  - `parent/lms-overview.php`
  - `parent/book-meeting.php`
  - `parent/my-meetings.php`
  - `parent/analytics.php`
  - `parent/reports.php`
  - `parent/settings.php`
  - `parent/team-selection.php`
- Additional direct pages:
  - `parent/communication.php`
  - `parent/child-details.php`
  - `parent/emergency-alerts.php`
- Guard:
  - `require_parent()`
  - `has_role('parent')`

### Librarian

- Dashboard: `librarian/dashboard.php`
- Navigation groups: Main, Catalog, Circulation, Reports, Communication
- Dashboard actions:
  - `Issue / Return -> issue-return.php`
  - `Add Book -> add-book.php`
  - `Overdue -> overdue.php`
  - `Books -> books.php`
  - `Fines -> fines.php`
  - `View All -> transactions.php`
- Pages:
  - `librarian/books.php`
  - `librarian/add-book.php`
  - `librarian/categories.php`
  - `librarian/digital-resources.php`
  - `librarian/issue-return.php`
  - `librarian/active-loans.php`
  - `librarian/overdue.php`
  - `librarian/fines.php`
  - `librarian/reservations.php`
  - `librarian/reports.php`
  - `librarian/inventory.php`
  - `librarian/settings.php`
  - `librarian/team-selection.php`
  - `librarian/transactions.php`
- Guards:
  - `require_role('librarian')`
  - several pages also allow `admin`

### Bursar

- Dashboard: `bursar/dashboard.php`
- Navigation groups: Main, Billing, Management, Reports, Communication
- Dashboard actions:
  - `Fee Collection -> fee-collection.php`
  - `Invoices -> invoices.php`
  - `Defaulters -> defaulters.php`
  - `Daily Summary -> daily-summary.php`
  - `Receipts -> receipts.php`
- Pages:
  - `bursar/fee-collection.php`
  - `bursar/invoices.php`
  - `bursar/payment-plans.php`
  - `bursar/receipts.php`
  - `bursar/fee-structure.php`
  - `bursar/defaulters.php`
  - `bursar/scholarships.php`
  - `bursar/daily-summary.php`
  - `bursar/reports.php`
  - `bursar/export.php`
  - `bursar/settings.php`
  - `bursar/team-selection.php`
- Guards:
  - `require_role('bursar')`
  - several pages also allow `admin`

### Accountant

- Dashboard: `accountant/dashboard.php`
- Navigation groups: Main, Finance, Statements, Reports, Communication
- Dashboard actions:
  - `Expenses -> expenses.php`
  - `Reports -> reports.php`
  - `General Ledger -> ledger.php`
  - `Payroll -> payroll.php`
  - `Balance Sheet -> balance-sheet.php`
  - `Profit & Loss -> profit-loss.php`
- Pages:
  - `accountant/ledger.php`
  - `accountant/expenses.php`
  - `accountant/income.php`
  - `accountant/payroll.php`
  - `accountant/balance-sheet.php`
  - `accountant/profit-loss.php`
  - `accountant/tax-reports.php`
  - `accountant/budget.php`
  - `accountant/reports.php`
  - `accountant/audit-trail.php`
  - `accountant/settings.php`
  - `accountant/team-selection.php`
- Guards:
  - `require_role('accountant')`
  - several pages also allow `admin`

### Transport

- Dashboard: `transport/dashboard.php`
- Navigation groups: Main, Fleet, Operations, Reports, Communication
- Dashboard actions:
  - `Routes -> routes.php`
  - `Allocation -> allocation.php`
  - `Maintenance -> maintenance.php`
  - `Vehicles -> vehicles.php`
  - `Drivers -> drivers.php`
  - `Trip Logs -> trip-logs.php`
- Pages:
  - `transport/routes.php`
  - `transport/vehicles.php`
  - `transport/drivers.php`
  - `transport/student-allocation.php`
  - `transport/trip-logs.php`
  - `transport/maintenance.php`
  - `transport/fuel-log.php`
  - `transport/reports.php`
  - `transport/settings.php`
  - `transport/team-selection.php`
  - `transport/allocation.php`
- Guards:
  - `require_role('transport')`
  - dashboard also allows `admin`

### Forum Moderator

- Dashboard: `forum-moderator/dashboard.php`
- Navigation groups: Main, Moderation, Forum, Communication
- Dashboard actions:
  - `Reported Posts -> reported-posts.php`
  - `Categories -> categories.php`
  - `All Threads -> threads.php`
  - `User Warnings -> user-warnings.php`
  - `Banned Users -> banned-users.php`
  - `Thread Title -> view-thread.php?id={id}`
- Pages:
  - `forum-moderator/threads.php`
  - `forum-moderator/reported-posts.php`
  - `forum-moderator/user-warnings.php`
  - `forum-moderator/banned-users.php`
  - `forum-moderator/categories.php`
  - `forum-moderator/analytics.php`
  - `forum-moderator/settings.php`
  - `forum-moderator/team-selection.php`
  - `forum-moderator/view-thread.php`
- Guards:
  - `require_role('forum_moderator')`
  - dashboard also allows `admin`

### Developer

- Dashboard path via helper: `developer/index.php`
- Pages:
  - `developer/aci-center.php`
  - `developer/aic-center.php`
  - `developer/ai-center.php`
  - `developer/ai-training.php`
  - `developer/autofix-center.php`
  - `developer/database-monitor.php`
  - `developer/debug-overlay.php`
  - `developer/devops-center.php`
  - `developer/ecosystem-center.php`
  - `developer/healing-center.php`
  - `developer/index.php`
  - `developer/intelligence-center.php`
  - `developer/logs.php`
  - `developer/modules.php`
  - `developer/os-center.php`
  - `developer/performance.php`
  - `developer/security-center.php`
  - `developer/settings.php`
  - `developer/system-health.php`
  - `developer/system-monitor.php`
  - `developer/themes.php`
  - `developer/master-control/index.php`
- Notes:
  - no canonical sidebar role block
  - `get_role_dashboard_path()` does not map `developer`

## STEP 3 — GLOBAL ROUTING MAP

### Missing route targets

- `admin.payroll -> admin/payroll.php`
- `admin.grades -> admin/grades.php`
- `admin.departments -> admin/departments.php`
- `teacher.schedule -> teacher/schedule.php`
- `teacher.profile -> teacher/profile.php`
- `parent.profile -> parent/profile.php`
- `comm.compose -> communication/compose.php`
- `comm.notices -> communication/notices.php`
- `forum.topics -> forum/topics.php`

### No duplicate route paths

- no duplicates detected in `config/routes.php`

### Real files missing from route map

- `forum-moderator/*`
- most `team-selection.php` pages
- many enhanced dashboards
- `student/messages.php`
- `parent/communication.php`
- `parent/child-details.php`
- `librarian/transactions.php`
- `transport/routes.php`
- `bursar/invoices.php`

## STEP 4 — MODULE STRUCTURE

```text
/admin/
/owner/
/principal/
/staff/
/nurse/
/teacher/
/student/
  /api/
/parent/
/accountant/
/bursar/
/librarian/
/transport/
/forum-moderator/
/communication/
  /api/
/forum/
/modules/
  /ai-copilot/
  /attendance/
  /auth/
  /chat/
  /classes/
  /communication/
  /dashboard/
  /reports/
  /settings/
  /users/
/developer/
  /master-control/
  /tabs/
```

## STEP 5 — GAP DETECTION

### Buttons without targets

- `admin/dashboard.php` `MTD`
- `admin/dashboard.php` `YTD`

### Roles sharing folders incorrectly

- `owner/*` wrappers load `../admin/*`
- `principal/*` wrappers load `../admin/*`

### Inconsistent naming

- `forum_moderator` role vs `forum-moderator` folder
- `moderator` legacy role vs `forum_moderator` runtime role
- `super_admin` vs `superadmin`
- `attendance.php` vs `attendance_new.php`
- `dashboard.php` vs `dashboard-enhanced.php`
- transport has `allocation.php` and `student-allocation.php`

### Role definition gaps

- `core/database.php` users enum includes only:
  - `admin`
  - `teacher`
  - `student`
  - `parent`
  - `accountant`
  - `librarian`
  - `transport`
  - `moderator`
- runtime mapping additionally includes:
  - `owner`
  - `principal`
  - `vice_principal`
  - `staff`
  - `nurse`
  - `bursar`
  - `forum_moderator`
  - `super_admin`
  - `superadmin`
  - `developer`
  - `counselor`
  - `admin_officer`
  - `class_teacher`
  - `subject_coordinator`

### Route helper inconsistencies

- `includes/functions.php:get_role_dashboard_path()` supports more roles than `app/helpers/url.php:role_dashboard()`
- `app/helpers/url.php` lacks explicit handling for:
  - `owner`
  - `principal`
  - `staff`
  - `nurse`
  - `super_admin`
  - `superadmin`

