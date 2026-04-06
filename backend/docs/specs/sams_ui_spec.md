# SAMS — UI/UX Structure Specification (Stitch AI Ready)

---

# APPLICATION OVERVIEW

- Product name: SAMS (School Attendance Management System)
- Core purpose: A multi-tenant, AI-powered school operations platform designed like a school “operating system” for smartphone and web use. It unifies attendance, academic management, communication, finance, analytics, safety/alerts, and partner integrations for educational organizations.
- Target users: Super Admin, Owner, Principal, Admin, Teacher, Student, Parent, Staff, Librarian, Bursar, Accountant, Transport Manager, Forum Moderator, Developer/Platform Operator.
- Primary workflows:
- Secure authentication and onboarding with OTP, email verification, and optional biometric login.
- Role-based dashboards and navigation with scoped access and tenant context switching.
- Attendance marking, monitoring, and analytics for staff, teachers, students, and parents.
- Academic management: classes, enrollments, assignments, grades, schedules, resources.
- Communication: messaging, notices, alerts, and community forum.
- Finance: fees, invoices, payments, payroll, and reporting.
- Operations: library, transport, inventory, facilities.
- AI/Automation: AI-assisted bulk user creation, documentation, analytics, anomaly detection, and self-healing tooling.
- Multi-tenant configuration and system health governance.

---

# GLOBAL NAVIGATION STRUCTURE

- Main navigation items:
- Dashboard (role-specific)
- People management (students, teachers, parents, users)
- Academic (classes, attendance, assignments, grades, timetable, reports)
- Communication (messages, notices, forum)
- Analytics and reports
- Finance (fees, invoices, payments, payroll, ledgers)
- Operations (library, transport, facilities)
- Settings and profile
- System health, logs, and admin/dev tools

- Sidebar items:
- Dynamically loaded per role.
- Admin sections: Main, People, Academic, Communication, Analytics, System, AI Center, Developer.
- Teacher sections: Main, Academic, Communication, Insights.
- Student sections: Main, Academic, Communication, Account.
- Parent sections: Main, Academic, Communication, Account.
- Librarian sections: Main, Catalog, Circulation, Reports, Communication.
- Bursar sections: Main, Billing, Management, Reports, Communication.
- Accountant sections: Main, Finance, Statements, Reports, Communication.
- Transport sections: Main, Fleet, Operations, Reports, Communication.
- Forum Moderator sections: Main, Moderation, Forum, Communication.

- Footer links:
- Help
- FAQ
- Support
- Version info
- Legal/Privacy
- Accessibility statement

- Access-level differences:
- Super Admin: cross-tenant access, platform controls, tenant creation.
- Admin: full tenant admin, user/class management, settings, AI tools.
- Teacher: class rosters, attendance, grades, assignments, parent comms.
- Student: attendance, check-in, schedule, assignments, grades, profile.
- Parent: child monitoring, meetings, fees, communications.
- Accountant/Bursar: finance operations and reports.
- Librarian: catalog, circulation, fines, inventory.
- Transport: routes, vehicles, drivers, allocations, logs.
- Forum Moderator: moderation dashboards and controls.
- Developer: system health, AI governance, diagnostics, self-healing tools.

---

# PAGE INVENTORY (CRITICAL)

## Page Name: Landing / Home

### Purpose:
Public landing page, product overview, entry to login/register.

### Route/URL:
`/index.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Direct URL, marketing links

### UI Sections:
- Header
- Hero
- Feature highlights
- CTA buttons
- Footer

### Data Displayed:
Static product info, feature highlights

### API/Data Sources:
Optional landing content service

### User Actions:
Navigate to login/register

### State Changes:
None

### Error States:
None

### Empty States:
None

---

## Page Name: Login

### Purpose:
Authenticate users with email/password and optional biometric.

### Route/URL:
`/login.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Landing CTA, session timeout redirect, direct URL

### UI Sections:
- Header
- Login card
- Biometric login button
- Email/password form
- Alerts
- Footer links

### Data Displayed:
Login status, error messages

### API/Data Sources:
Auth service, rate limiter, users table

### User Actions:
Login, biometric login, forgot password, register

### State Changes:
Session created, login logged

### Error States:
Invalid credentials, locked account, email unverified, pending approval

### Empty States:
None

---

## Page Name: Register

### Purpose:
Self-registration for eligible roles.

### Route/URL:
`/register.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Login page link

### UI Sections:
- Registration form
- Role selection
- Verification notices

### Data Displayed:
Registration status

### API/Data Sources:
Users, account activations

### User Actions:
Submit registration

### State Changes:
Account created, OTP sent

### Error States:
Validation errors, duplicate email

### Empty States:
None

---

## Page Name: Forgot Password

### Purpose:
Request password reset

### Route/URL:
`/forgot-password.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Login “Forgot Password”

### UI Sections:
- Email form
- Status messages

### Data Displayed:
Reset status

### API/Data Sources:
Password reset service

### User Actions:
Submit email

### State Changes:
Reset token created

### Error States:
Invalid email, throttling

### Empty States:
None

---

## Page Name: Reset Password

### Purpose:
Set new password with token

### Route/URL:
`/reset-password.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Email reset link

### UI Sections:
- New password form
- Confirmation

### Data Displayed:
Token validity, status

### API/Data Sources:
Password reset service

### User Actions:
Submit new password

### State Changes:
Password updated

### Error States:
Invalid/expired token

### Empty States:
None

---

## Page Name: Verify Email

### Purpose:
Confirm email ownership

### Route/URL:
`/verify-email.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Verification link

### UI Sections:
- Verification status
- Resend link

### Data Displayed:
Email verification status

### API/Data Sources:
Email verification service

### User Actions:
Confirm, resend

### State Changes:
Email verified

### Error States:
Invalid or expired link

### Empty States:
None

---

## Page Name: Verify OTP

### Purpose:
OTP verification for activation

### Route/URL:
`/verify-otp.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Activation flow

### UI Sections:
- OTP input
- Status messages

### Data Displayed:
OTP status

### API/Data Sources:
OTP service

### User Actions:
Submit OTP

### State Changes:
Account activated

### Error States:
Invalid/expired OTP

### Empty States:
None

---

## Page Name: Confirm Account

### Purpose:
OTP confirmation and password setup

### Route/URL:
`/confirm-account.php`

### User Role Access:
Public

### Entry Points (how user reaches page):
Activation email

### UI Sections:
- OTP input
- Password setup
- Status messages

### Data Displayed:
Account activation status

### API/Data Sources:
Account activation service

### User Actions:
Confirm OTP, set password

### State Changes:
Account activated

### Error States:
Invalid/expired OTP

### Empty States:
None

---

## Page Name: Setup Admin

### Purpose:
First-time admin setup

### Route/URL:
`/setup-admin.php`

### User Role Access:
Public until setup complete

### Entry Points (how user reaches page):
Initial install

### UI Sections:
- Setup wizard
- Admin profile form
- Tenant settings

### Data Displayed:
Setup progress

### API/Data Sources:
Users, tenants

### User Actions:
Create admin

### State Changes:
Admin + tenant created

### Error States:
Validation or DB errors

### Empty States:
None

---

## Page Name: Notices (Public)

### Purpose:
Notice board for public or logged-in users

### Route/URL:
`/notices.php`

### User Role Access:
Public or authenticated

### Entry Points (how user reaches page):
Sidebar, direct link

### UI Sections:
- Notice list
- Filters
- Notice detail

### Data Displayed:
Notices

### API/Data Sources:
Notices service

### User Actions:
View notices

### State Changes:
Read tracking

### Error States:
Load failure

### Empty States:
No notices

---

## Page Name: Admin Dashboard

### Purpose:
Admin control center

### Route/URL:
`/admin/dashboard.php`

### User Role Access:
Admin

### Entry Points (how user reaches page):
Login redirect, sidebar

### UI Sections:
- Header
- Sidebar
- KPI cards
- Quick actions
- Charts
- Recent activity

### Data Displayed:
Users, attendance, system health

### API/Data Sources:
Admin stats, analytics, notifications

### User Actions:
Navigate modules, trigger actions

### State Changes:
Widgets refresh

### Error States:
Widget load failure

### Empty States:
No data

---

## Page Name: Teacher Dashboard

### Purpose:
Teacher operations hub

### Route/URL:
`/teacher/dashboard.php`

### User Role Access:
Teacher

### Entry Points (how user reaches page):
Login redirect

### UI Sections:
- KPI cards
- Class list
- Attendance summary

### Data Displayed:
Classes, attendance, assignments

### API/Data Sources:
Teacher data services

### User Actions:
Navigate to attendance, grades

### State Changes:
None

### Error States:
Load failure

### Empty States:
No classes

---

## Page Name: Student Dashboard

### Purpose:
Student overview portal

### Route/URL:
`/student/dashboard.php`

### User Role Access:
Student

### Entry Points (how user reaches page):
Login redirect

### UI Sections:
- Attendance summary
- Upcoming assignments
- Grades overview
- Notifications

### Data Displayed:
Attendance, grades, assignments

### API/Data Sources:
Student services

### User Actions:
Open assignments, check in

### State Changes:
None

### Error States:
Load failure

### Empty States:
No assignments

---

## Page Name: Parent Dashboard

### Purpose:
Parent overview of children

### Route/URL:
`/parent/dashboard.php`

### User Role Access:
Parent

### Entry Points (how user reaches page):
Login redirect

### UI Sections:
- Child cards
- Attendance, grades, fees summary

### Data Displayed:
Child performance

### API/Data Sources:
Parent services

### User Actions:
Select child, view details

### State Changes:
None

### Error States:
Load failure

### Empty States:
No linked children

---

## Page Name: Admin Students

### Purpose:
Manage student records

### Route/URL:
`/admin/students.php`

### User Role Access:
Admin

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Filters
- Student table
- Add/edit actions

### Data Displayed:
Student roster, status, classes

### API/Data Sources:
Students, users, enrollments

### User Actions:
Create, edit, deactivate

### State Changes:
Records updated

### Error States:
Validation errors

### Empty States:
No students

---

## Page Name: Admin Teachers

### Purpose:
Manage teacher records

### Route/URL:
`/admin/teachers.php`

### User Role Access:
Admin

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Teacher table
- Add/edit actions

### Data Displayed:
Teachers, assigned classes

### API/Data Sources:
Teachers, users

### User Actions:
Create, edit, assign

### State Changes:
Records updated

### Error States:
Validation errors

### Empty States:
No teachers

---

## Page Name: Admin Classes

### Purpose:
Class CRUD and assignment

### Route/URL:
`/admin/classes.php`

### User Role Access:
Admin

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Class list
- Create/edit modal

### Data Displayed:
Class details

### API/Data Sources:
Classes, teachers

### User Actions:
Create/edit/delete

### State Changes:
Class updated

### Error States:
Validation errors

### Empty States:
No classes

---

## Page Name: Admin Class Enrollment

### Purpose:
Enroll students in classes

### Route/URL:
`/admin/class-enrollment.php`

### User Role Access:
Admin

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Class selector
- Student picker
- Enrollment table

### Data Displayed:
Enrollment mappings

### API/Data Sources:
Enrollments, students, classes

### User Actions:
Enroll/unenroll

### State Changes:
Enrollment updated

### Error States:
Save failure

### Empty States:
No students

---

## Page Name: Attendance

### Purpose:
Mark and view attendance

### Route/URL:
Admin `/admin/attendance.php`
Teacher `/teacher/attendance.php`
Student `/student/attendance.php`
Parent `/parent/attendance.php`

### User Role Access:
Admin, Teacher, Student, Parent

### Entry Points (how user reaches page):
Sidebar, dashboard widget

### UI Sections:
- Filters
- Attendance table
- Summary cards

### Data Displayed:
Attendance records

### API/Data Sources:
Attendance services

### User Actions:
Mark attendance, filter, export

### State Changes:
Attendance saved

### Error States:
Save failure

### Empty States:
No records

---

## Page Name: Assignments

### Purpose:
Manage and submit assignments

### Route/URL:
Teacher `/teacher/assignments.php`
Student `/student/assignments.php`

### User Role Access:
Teacher, Student

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Assignment list
- Upload/submit panel

### Data Displayed:
Assignments, due dates

### API/Data Sources:
Resources, submissions

### User Actions:
Create, submit, view

### State Changes:
Submission stored

### Error States:
Upload failure

### Empty States:
No assignments

---

## Page Name: Grades

### Purpose:
Enter and view grades

### Route/URL:
Teacher `/teacher/grades.php`
Student `/student/grades.php`
Parent `/parent/grades.php`

### User Role Access:
Teacher, Student, Parent

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Grade table
- Filters

### Data Displayed:
Grades by term

### API/Data Sources:
Grades service

### User Actions:
Enter grades, filter

### State Changes:
Grades saved

### Error States:
Validation errors

### Empty States:
No grades

---

## Page Name: Schedule

### Purpose:
View schedules

### Route/URL:
Student `/student/schedule.php`
Admin `/admin/timetable.php`

### User Role Access:
Student, Admin

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Timetable grid
- Filters

### Data Displayed:
Class schedule

### API/Data Sources:
Schedule service

### User Actions:
Filter view

### State Changes:
None

### Error States:
Load failure

### Empty States:
No schedule

---

## Page Name: Communication - Conversations

### Purpose:
Role-based messaging

### Route/URL:
`/communication/conversations.php`

### User Role Access:
All authenticated roles

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Conversation list
- Message thread
- Composer

### Data Displayed:
Messages, participants

### API/Data Sources:
Messaging services

### User Actions:
Send message, attach files

### State Changes:
Message sent

### Error States:
Send failure

### Empty States:
No conversations

---

## Page Name: Forum

### Purpose:
Community discussion

### Route/URL:
`/forum/index.php`, `/forum/category.php`, `/forum/thread.php`, `/forum/create-thread.php`

### User Role Access:
Authenticated roles

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Categories
- Thread list
- Post editor

### Data Displayed:
Threads, posts

### API/Data Sources:
Forum services

### User Actions:
Create thread, reply, report

### State Changes:
Post created

### Error States:
Post failure

### Empty States:
No threads

---

## Page Name: Finance (Accountant/Bursar)

### Purpose:
Financial operations

### Route/URL:
`/accountant/*`, `/bursar/*`

### User Role Access:
Accountant, Bursar

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Finance dashboards
- Ledgers, invoices, payments
- Reports and exports

### Data Displayed:
Fees, invoices, payments, payroll

### API/Data Sources:
Finance services

### User Actions:
Create invoice, record payment, generate reports

### State Changes:
Records updated

### Error States:
Save failure

### Empty States:
No finance data

---

## Page Name: Library

### Purpose:
Library management

### Route/URL:
`/librarian/*`

### User Role Access:
Librarian

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Catalog
- Issue/return
- Fines and reservations

### Data Displayed:
Books, loans, fines

### API/Data Sources:
Library services

### User Actions:
Add books, issue, return, manage fines

### State Changes:
Loan records updated

### Error States:
Save failure

### Empty States:
No books

---

## Page Name: Transport

### Purpose:
Transport management

### Route/URL:
`/transport/*`

### User Role Access:
Transport

### Entry Points (how user reaches page):
Sidebar

### UI Sections:
- Routes
- Vehicles
- Drivers
- Trip logs

### Data Displayed:
Routes, vehicles, allocations

### API/Data Sources:
Transport services

### User Actions:
Create routes, assign vehicles

### State Changes:
Transport data updated

### Error States:
Save failure

### Empty States:
No transport data

---

## Page Name: Settings

### Purpose:
User and system settings

### Route/URL:
Role-specific settings pages

### User Role Access:
All

### Entry Points (how user reaches page):
Sidebar, user menu

### UI Sections:
- Profile form
- Password change
- Notification preferences
- Theme switcher

### Data Displayed:
User preferences

### API/Data Sources:
Settings services

### User Actions:
Update settings

### State Changes:
Preferences saved

### Error States:
Save failure

### Empty States:
None

---

# USER FLOWS

- Onboarding:
- User receives invite or registers
- Email verification/OTP sent
- User confirms OTP, sets password
- First login redirects to dashboard

- Authentication:
- User enters credentials
- System validates, checks lockout/session
- On success, session created and redirected by role

- Main feature usage:
- Admin: manage users/classes, monitor attendance and analytics
- Teacher: mark attendance, manage assignments and grades
- Student: check-in, view grades and assignments
- Parent: monitor child, book meetings, review fees
- Finance: manage invoices, payments, reports
- Library: manage catalog and circulation
- Transport: manage routes, vehicles, allocations

- Data creation/editing:
- Open form or modal
- Enter data
- Submit
- Receive confirmation or validation errors

- Settings management:
- Open settings
- Update profile/preferences
- Save and confirm

---

# COMPONENT LIBRARY

- Buttons: primary, secondary, destructive, icon, floating action
- Inputs: text, select, date, file upload, search, toggles
- Dashboards: KPI cards, stats widgets, quick actions
- Widgets: notifications, alerts, summary panels
- Notifications: toasts, banners, badges
- Loaders: spinners, skeletons, progress bars
- Cards: content panels, summary tiles
- Navigation: sidebar, topbar, tabs, breadcrumbs
- Tables: sortable, filterable, paginated
- Modals: confirmation, forms, previews
- Avatars: user identity
- Charts: bar, line, pie
- Search/filter controls
- Theme switcher

---

# DESIGN SYSTEM GUIDANCE

- Layout style: Responsive dashboard layout with sidebar and topbar
- Hierarchy: KPI cards first, tables and charts below
- Interaction patterns: modal-based CRUD, inline validation, role-based access
- Responsiveness: mobile-first, collapsible sidebar, PWA ready
- Accessibility: WCAG AA, keyboard navigation, ARIA labels, contrast compliance

---

# DATA ? UI MAPPING

- Users: tables, profile cards, dashboards
- Classes: class tables, assignment dropdowns, schedules
- Attendance: tables, summary widgets, charts
- Assignments/Grades: lists, tables, feedback panels
- Messages/Notices: threads, boards, alerts
- Finance: invoices, payment tables, reports
- Library: catalog tables, loan panels
- Transport: route and vehicle tables
- System Health: status widgets and logs

---

# AI UI GENERATION SUMMARY (FOR STITCH AI)

```markdown
## Pages list
- Auth: Login, Register, Forgot Password, Reset Password, OTP Verify
- Role dashboards: Admin, Teacher, Student, Parent, Accountant, Bursar, Librarian, Transport, Moderator
- Management: Users, Students, Teachers, Classes, Enrollment, Attendance
- Academic: Assignments, Grades, Schedule, Reports
- Communication: Conversations, Notices, Forum
- Operations: Library, Transport, Facilities
- Finance: Invoices, Payments, Ledgers, Payroll
- System: Settings, Health, Audit Logs, AI Center, Developer Tools

## Navigation map
- Role-specific sidebar with sections for Main, Academic, Communication, Analytics, System
- Topbar with page title, user menu, notifications
- Footer with Help, Support, Version

## Component hierarchy
- App shell: Sidebar + Topbar + Content
- Content: KPI cards ? filters ? tables/charts ? modals/forms
- Communication: conversation list + chat composer
- Settings: tabbed cards for profile, notifications, theme

## Screen relationships
- Auth ? Role dashboard
- Dashboard ? module pages
- Module pages ? CRUD modals/forms
- Reports ? analytics and exports
```
```
