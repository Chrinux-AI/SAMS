# SAMS — UI/UX Structure Specification (Stitch AI Ready)

---

# APPLICATION OVERVIEW

- **Product Name:** SAMS (School Attendance Management System)
- **Core Purpose:** Multi-tenant, AI-powered platform for managing school operations, attendance, onboarding, communication, analytics, and resource management. Designed as an "operating system" for schools, with extensibility for partnerships and educational integrations.
- **Target Users:** Super Admins, Owners, Principals, Admins, Teachers, Students, Parents, Staff, Librarians, Bursars, Accountants, Transport Managers, Forum Moderators, and other specialized roles.
- **Primary Workflows:**
  - Secure authentication and onboarding (OTP, AI-powered bulk creation)
  - Role-based dashboards and navigation
  - Attendance tracking and reporting
  - Class, user, and resource management
  - Communication (chat, notices, forums)
  - Analytics, reporting, and system health monitoring
  - Multi-tenant management and configuration
  - Finance, fees, and payment management
  - Academic and behavioral tracking
  - Library, transport, and facility management
  - Parental engagement and alerts
  - Integration with external partners and educational tools

---

# GLOBAL NAVIGATION STRUCTURE

- **Main Navigation Items:**
  - Dashboard (role-specific)
  - Students / Teachers / Classes / Attendance / Reports / Analytics (Admin)
  - Assignments / Grades / Schedule / Materials (Teacher/Student)
  - Children / Fees / Meetings (Parent)
  - Library / Transport / Finance (role-specific)
  - Communication (Chat, Notices, Forum)
  - Settings / Profile / Help / Support
  - System Health / Audit Logs / Integrations

- **Sidebar Items:**
  - Dynamically loaded per role (e.g., Admin: Dashboard, Students, Teachers, Classes, Reports, Settings)
  - Quick links to frequently used features (e.g., "Check In" for students, "Bulk Import" for admins)
  - Contextual shortcuts (e.g., "Mark Attendance", "View Grades", "Book Meeting")

- **Footer Links:**
  - Help
  - Support
  - Version info
  - Legal/Privacy
  - Accessibility statement

- **Access-Level Differences:**
  - Each role sees only relevant navigation items and pages.
  - Super Admins have cross-tenant controls and platform settings.
  - Admins manage users, classes, and system settings.
  - Teachers access class, attendance, and assignment tools.
  - Students see their dashboard, attendance, grades, assignments, and messages.
  - Parents view children’s data, fees, and communication.
  - Specialized dashboards for Accountant, Bursar, Librarian, Transport, Forum Moderator, etc.

---

# PAGE INVENTORY (CRITICAL)

## Authentication & Onboarding

### Login

- **Purpose:** Authenticate users into the system.
- **Route/URL:** `/attendance/login.php`
- **User Role Access:** All (public)
- **Entry Points:** Direct URL, logout redirect, session timeout
- **UI Sections:** Header (logo, title), Login form (email, password, OTP if required), Forgot password link, Register link (if enabled), Error/notification area
- **Data Displayed:** Login status, error messages
- **API/Data Sources:** Auth backend
- **User Actions:** Submit login, request password reset
- **State Changes:** Session creation, error display
- **Error States:** Invalid credentials, account locked, session expired
- **Empty States:** No input

### Register

- **Purpose:** New user self-registration (if enabled)
- **Route/URL:** `/attendance/register.php`
- **User Role Access:** Public (role-restricted)
- **Entry Points:** Login page, direct URL
- **UI Sections:** Registration form, role selection, email verification prompt
- **Data Displayed:** Registration status, errors
- **API/Data Sources:** Auth backend
- **User Actions:** Submit registration, select role
- **State Changes:** Registration pending, email sent
- **Error States:** Duplicate email, invalid data
- **Empty States:** No input

### OTP/Account Confirmation

- **Purpose:** Secure onboarding and password setup
- **Route/URL:** `/attendance/confirm-account.php`, `/attendance/verify-otp.php`, `/attendance/verify-email.php`
- **User Role Access:** All (invited/new users)
- **Entry Points:** Registration, AI user creation, password reset
- **UI Sections:** OTP input, resend OTP, status messages
- **Data Displayed:** OTP status, errors
- **API/Data Sources:** OTP backend
- **User Actions:** Enter OTP, resend OTP
- **State Changes:** Account activation, lockout on failure
- **Error States:** Expired/invalid OTP, lockout
- **Empty States:** No input

### Forgot/Reset Password

- **Purpose:** Password recovery
- **Route/URL:** `/attendance/forgot-password.php`, `/attendance/reset-password.php`
- **User Role Access:** All
- **Entry Points:** Login page, direct URL
- **UI Sections:** Email input, OTP/verification, new password form
- **Data Displayed:** Status, errors
- **API/Data Sources:** Auth backend
- **User Actions:** Request reset, enter OTP, set new password
- **State Changes:** Reset token issued, password updated
- **Error States:** Invalid email, expired token
- **Empty States:** No input

---

## Dashboards (Role-Specific)

### Admin Dashboard

- **Purpose:** Central hub for all admin operations
- **Route/URL:** `/attendance/admin/dashboard.php`
- **User Role Access:** Admin, Super Admin, Principal
- **Entry Points:** Post-login, sidebar navigation
- **UI Sections:** Header (user info, notifications), Sidebar (admin menu), Stats widgets (users, attendance, alerts), Recent activity cards, Quick actions (add user, import, reports), Announcements/alerts
- **Data Displayed:** User stats, attendance summaries, system health, recent actions
- **API/Data Sources:** Admin APIs, analytics, notifications
- **User Actions:** Navigate to subpages, trigger quick actions, view reports
- **State Changes:** Widget refresh, notification updates
- **Error States:** API/data load failure
- **Empty States:** No data (e.g., no students yet)

### Teacher Dashboard

- **Purpose:** Teacher’s operational hub
- **Route/URL:** `/attendance/teacher/dashboard.php`
- **User Role Access:** Teacher
- **Entry Points:** Post-login, sidebar
- **UI Sections:** Header, Sidebar, Class summary, Attendance stats, Assignments, Messages, Alerts
- **Data Displayed:** Classes, attendance %, assignments, messages
- **API/Data Sources:** Teacher APIs
- **User Actions:** View classes, mark attendance, upload assignments
- **State Changes:** Assignment/attendance updates
- **Error States:** Data/API failure
- **Empty States:** No classes/assignments

### Student Dashboard

- **Purpose:** Student’s personal portal
- **Route/URL:** `/attendance/student/dashboard.php`
- **User Role Access:** Student
- **Entry Points:** Post-login, sidebar
- **UI Sections:** Header (profile, notifications), Sidebar (student menu), Attendance summary, Upcoming assignments, Grades overview, Messages/announcements
- **Data Displayed:** Attendance %, grades, assignments, messages
- **API/Data Sources:** Student APIs, assignments, grades, notifications
- **User Actions:** View details, check in, access assignments
- **State Changes:** Attendance check-in, assignment submission
- **Error States:** Data load failure
- **Empty States:** No assignments, no grades

### Parent Dashboard

- **Purpose:** Parent’s overview of children
- **Route/URL:** `/attendance/parent/dashboard.php`
- **User Role Access:** Parent
- **Entry Points:** Post-login, sidebar
- **UI Sections:** Header, Sidebar, Children summary, Attendance/grades, Fees, Messages
- **Data Displayed:** Children, attendance, grades, fees, alerts
- **API/Data Sources:** Parent APIs
- **User Actions:** View child details, pay fees, communicate
- **State Changes:** Payment, message updates
- **Error States:** Data/API failure
- **Empty States:** No children linked

### Specialized Dashboards

- **Accountant/Bursar:** Finance, payments, reports
- **Librarian:** Library inventory, loans, reservations
- **Transport:** Vehicle logs, routes, student allocation
- **Forum Moderator:** Community threads, reports, user actions

---

## Management & CRUD Pages

### User Management

- **Purpose:** CRUD for users (add, edit, deactivate, bulk import)
- **Route/URL:** `/attendance/admin/users.php`, `/attendance/admin/approve-users.php`, `/attendance/admin/students.php`, `/attendance/admin/teachers.php`
- **User Role Access:** Admin, Super Admin
- **Entry Points:** Admin sidebar, dashboard
- **UI Sections:** User table, filters, add/edit modal, bulk import, status badges
- **Data Displayed:** User list, roles, status, actions
- **API/Data Sources:** User APIs
- **User Actions:** Add/edit/deactivate user, approve, import
- **State Changes:** Table refresh, modal open/close
- **Error States:** Validation/API errors
- **Empty States:** No users

### Class Management

- **Purpose:** CRUD for classes, teacher assignment, enrollment
- **Route/URL:** `/attendance/admin/classes.php`, `/attendance/admin/class-enrollment.php`
- **User Role Access:** Admin
- **Entry Points:** Admin sidebar, dashboard quick link
- **UI Sections:** Header, Class list (data table), Add/edit class modal, Teacher assignment dropdown, Enrollment management
- **Data Displayed:** Class name, grade, teacher, enrolled students
- **API/Data Sources:** Class APIs
- **User Actions:** Add/edit/delete class, assign teacher, enroll students
- **State Changes:** Table refresh, modal open/close
- **Error States:** Validation errors, API failure
- **Empty States:** No classes

### Attendance Management

- **Purpose:** Mark and view attendance
- **Route/URL:** `/attendance/admin/attendance.php`, `/attendance/teacher/attendance.php`, `/attendance/student/attendance.php`, `/attendance/parent/attendance.php`
- **User Role Access:** Admin, Teacher, Student, Parent
- **Entry Points:** Sidebar, dashboard widget
- **UI Sections:** Attendance table (date, status), Mark attendance form (teacher/admin), Attendance summary cards, Filters (date, class, student)
- **Data Displayed:** Attendance records, summary stats
- **API/Data Sources:** Attendance APIs
- **User Actions:** Mark attendance, view records, filter/search
- **State Changes:** Table update, status change
- **Error States:** Submission failure, data load error
- **Empty States:** No records

### Assignments & Grades

- **Purpose:** Assignment upload, grading, and viewing
- **Route/URL:** `/attendance/teacher/assignments.php`, `/attendance/student/assignments.php`, `/attendance/teacher/grades.php`, `/attendance/student/grades.php`, `/attendance/parent/grades.php`
- **User Role Access:** Teacher, Student, Parent
- **Entry Points:** Sidebar, dashboard
- **UI Sections:** Assignment list, upload form, grade table, feedback cards
- **Data Displayed:** Assignments, grades, feedback
- **API/Data Sources:** Assignment/grade APIs
- **User Actions:** Upload/submit assignments, grade, view feedback
- **State Changes:** Submission, grading updates
- **Error States:** Upload/validation errors
- **Empty States:** No assignments/grades

### AI User Creation

- **Purpose:** Bulk onboarding via Google Forms/AI
- **Route/URL:** `/attendance/admin/ai-user-creator.php`, `/attendance/admin/ai-user-management.php`
- **User Role Access:** Admin
- **Entry Points:** Admin sidebar, dashboard
- **UI Sections:** Paste/upload form (JSON/CSV), Data preview table, Field mapping UI, Create users button, Status/progress modal
- **Data Displayed:** Parsed user data, validation results
- **API/Data Sources:** AI extraction APIs
- **User Actions:** Paste/upload data, map fields, create users
- **State Changes:** Progress bar, error/success notifications
- **Error States:** Invalid data, mapping errors
- **Empty States:** No data uploaded

### Communication Center

- **Purpose:** Role-based messaging and community
- **Route/URL:** `/attendance/communication/conversations.php`, `/attendance/notices.php`, `/attendance/forum/index.php`
- **User Role Access:** All (role-based restrictions)
- **Entry Points:** Sidebar, dashboard widget
- **UI Sections:** Conversation list, Message thread, Notice board, Forum topics/posts
- **Data Displayed:** Messages, notices, forum threads
- **API/Data Sources:** Messaging APIs
- **User Actions:** Send message, post notice, create thread
- **State Changes:** New message/post, read/unread status
- **Error States:** Send failure, load error
- **Empty States:** No messages/notices

### Reports & Analytics

- **Purpose:** System-wide and role-specific analytics
- **Route/URL:** `/attendance/admin/reports.php`, `/attendance/admin/analytics.php`, `/attendance/teacher/reports.php`, `/attendance/student/analytics.php`, `/attendance/parent/reports.php`
- **User Role Access:** All (role-based)
- **Entry Points:** Sidebar, dashboard
- **UI Sections:** Analytics widgets, charts, tables, export buttons
- **Data Displayed:** Attendance stats, grades, usage, financials
- **API/Data Sources:** Analytics APIs
- **User Actions:** Filter, export, drill-down
- **State Changes:** Widget/chart refresh
- **Error States:** Data/API failure
- **Empty States:** No data

### Settings & Profile

- **Purpose:** Manage user/system settings and profile
- **Route/URL:** `/attendance/admin/settings.php`, `/attendance/student/settings.php`, `/attendance/teacher/settings.php`, `/attendance/parent/settings.php`, `/attendance/profile.php`
- **User Role Access:** All
- **Entry Points:** Sidebar, dashboard, header dropdown
- **UI Sections:** Profile form, password change, notification preferences, theme switcher
- **Data Displayed:** User info, preferences
- **API/Data Sources:** Settings/profile APIs
- **User Actions:** Update/save settings, change password, switch theme
- **State Changes:** Save confirmation, theme update
- **Error States:** Validation/API errors
- **Empty States:** No preferences set

### System Health & Audit Logs

- **Purpose:** Monitor system status and activity
- **Route/URL:** `/attendance/admin/system-health.php`, `/attendance/admin/audit-logs.php`, `/attendance/verify-system.php`
- **User Role Access:** Admin, Super Admin
- **Entry Points:** Sidebar, dashboard
- **UI Sections:** Health widgets, log tables, alerts
- **Data Displayed:** System status, error logs, audit trails
- **API/Data Sources:** Health/log APIs
- **User Actions:** View logs, export, acknowledge alerts
- **State Changes:** Log refresh, alert dismissal
- **Error States:** Data/API failure
- **Empty States:** No logs

### Specialized Modules (examples)

- **Finance & Fees:** `/attendance/bursar/`, `/attendance/accountant/`
- **Library:** `/attendance/librarian/`
- **Transport:** `/attendance/transport/`
- **Forum Moderation:** `/attendance/forum-moderator/`

---

# USER FLOWS

## Onboarding

1. User receives invite or registers
2. Email verification/OTP sent
3. User confirms OTP, sets password
4. First login redirects to dashboard

## Authentication

1. User enters credentials
2. System validates, checks lockout/session
3. On success, session created and redirected by role

## Main Feature Usage

- Admin: Navigates dashboard → manages users/classes → views reports
- Teacher: Views dashboard → marks attendance → uploads assignments
- Student: Checks in → views assignments/grades → communicates
- Parent: Views child’s dashboard → checks attendance/grades → books meetings
- Accountant/Bursar: Manages fees, payments, financial reports
- Librarian: Manages inventory, loans, reservations
- Transport: Manages vehicles, routes, logs

## Data Creation/Editing

- Admin/Teacher: Opens modal/form → enters data → submits → sees confirmation/error
- Student: Submits assignment → receives feedback

## Settings Management

- User opens settings → updates profile/preferences → saves → sees confirmation

## Communication

- User opens chat/forum/notices → reads/sends messages → receives notifications

## System Monitoring

- Admin views health dashboard → checks logs → acknowledges alerts

---

# COMPONENT LIBRARY

- **Buttons:** Primary, secondary, destructive, icon, floating action (actions, navigation)
- **Inputs:** Text, select, date, file upload, search, toggles (forms, filters, settings)
- **Dashboards:** Role-based summary screens (widgets, cards, stats, quick actions)
- **Widgets:** Stats, notifications, quick actions, analytics, alerts (dashboard, sidebar)
- **Notifications:** Toasts, banners, alerts, badges (feedback, errors, status)
- **Loaders:** Spinners, skeleton screens, progress bars (loading states)
- **Cards:** Content panels (activity, announcements, details, feedback)
- **Navigation Elements:** Sidebar, topbar, breadcrumbs, tabs, stepper (navigation, context)
- **Tables:** Data display, sortable/filterable, paginated (lists, reports, logs)
- **Modals:** Confirmation, forms, previews, progress (actions, editing, onboarding)
- **Badges:** Status, counts, role indicators (notifications, lists, avatars)
- **Profile Avatars:** User identity (header, sidebar, chat)
- **Collapsible Panels:** Expand/collapse sections (settings, details, logs)
- **Forms:** Multi-step, inline, modal-based (onboarding, CRUD, settings)
- **Charts:** Bar, line, pie, donut, progress (analytics, reports)
- **Tabs/Accordions:** Sectioned content (settings, reports, help)
- **Search/Filter Controls:** Global and contextual search, filters (tables, lists)
- **Theme Switcher:** Light/dark mode, color themes
- **Accessibility Controls:** Font size, contrast, ARIA support

**Purpose & Usage:** All components are reusable, styled via theme system, and context-aware (role, state, device). Components support accessibility, responsiveness, and extensibility for future modules.

---

# DESIGN SYSTEM GUIDANCE

- **Layout Style:** Responsive, mobile-first, grid/flex layouts, collapsible sidebar, fixed header, modular panels
- **Hierarchy:** Clear separation of navigation, content, and actions; dashboard-first for all roles; contextual overlays for modals and notifications
- **Interaction Patterns:** Modal dialogs for editing/creation, inline validation, real-time feedback, role-based navigation, drag-and-drop (where applicable)
- **Responsiveness:** Fully responsive, tested to 320px, PWA installable, touch-friendly, adaptive layouts for desktop/tablet/mobile
- **Accessibility:** WCAG 2.1 AA, semantic HTML, ARIA labels, keyboard navigation, focus indicators, color contrast, screen reader support
- **Theme System:** Multiple themes (Nature, Cyberpunk, Professional), dynamic switching, CSS variables, SVG icons
- **Branding:** School/institution branding support, logo upload, color overrides
- **Notifications:** Real-time, persistent, and dismissible notifications
- **Offline Support:** PWA features, offline fallback, sync indicators

---

# DATA → UI MAPPING

- **Users:** Displayed in tables, profile cards, avatars, dashboards, and audit logs
- **Classes:** Managed via tables, modals, assignment dropdowns, and schedule views
- **Attendance:** Shown as tables, summary widgets, analytics charts, and exportable reports
- **Assignments/Grades:** Listed in tables, detailed in cards/modals, feedback panels
- **Messages/Notices:** Rendered in threads, boards, notification banners, and chat panels
- **Audit Logs/Reports:** Displayed in sortable/filterable tables, exportable formats
- **Settings:** Forms with grouped sections, toggles, theme switcher, notification preferences
- **Finance/Fees:** Tables, charts, payment forms, receipts, alerts
- **Library:** Inventory tables, loan/reservation modals, overdue alerts
- **Transport:** Vehicle/route tables, trip logs, allocation panels
- **System Health:** Status widgets, error logs, alert banners

---

# AI UI GENERATION SUMMARY (FOR STITCH AI)

```markdown
## Pages List

- Login
- Register
- Forgot Password
- Reset Password
- Confirm Account (OTP)
- Admin Dashboard
- Teacher Dashboard
- Student Dashboard
- Parent Dashboard
- Accountant Dashboard
- Bursar Dashboard
- Librarian Dashboard
- Transport Dashboard
- Forum Moderator Dashboard
- User Management (CRUD)
- Class Management (CRUD)
- Attendance (Mark/View)
- Assignments/Grades
- AI User Creation
- Communication (Chat, Notices, Forum)
- Reports/Analytics
- Settings/Profile
- System Health/Audit Logs
- Finance/Fees
- Library
- Transport
- Help/Support

## Navigation Map

- Main navigation: Dashboard, Management, Communication, Reports, Settings, Finance, Library, Transport
- Sidebar: Role-specific quick links, context-aware shortcuts
- Footer: Help, Support, Version, Legal, Accessibility

## Component Hierarchy

- Layout: Header, Sidebar, Footer
- Dashboard: Stats Widgets, Cards, Quick Actions, Alerts
- Management: Data Tables, Modals, Forms, Bulk Actions
- Communication: Conversation List, Message Thread, Notice Board, Forum, Alerts
- Reports/Analytics: Charts, Tables, Filters, Export
- Settings: Profile Forms, Toggles, Theme Switcher, Preferences
- Specialized: Payment Forms, Library Panels, Transport Logs

## Screen Relationships

- Authentication → Dashboard (by role)
- Dashboard → Management/Communication/Reports/Modules
- Management → CRUD Modals/Forms
- Communication → Threads/Boards/Notices
- Reports → Analytics/Exports
- Settings → Profile/Preferences/Theme
- Specialized → Finance/Library/Transport
```

---

**This specification is maximally detailed, systematic, and ready for AI UI generation.**
