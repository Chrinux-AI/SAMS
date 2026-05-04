# Master Task Tracker

Last updated: 2026-04-20

## Status legend

- `[x]` done
- `[~]` in progress
- `[ ]` not started

## Completed in this pass

- `[x]` Create an execution overview of the current repository state
- `[x]` Create a master implementation plan for frontend and backend completion
- `[x]` Create a master task tracker for ongoing delivery
- `[x]` Normalize the newly added bursar, librarian, nurse, transport, parent, and principal role managers onto the active PDO-backed database wrapper
- `[x]` Start a canonical admin backend foundation with `AdminManager` and an admin dashboard API
- `[x]` Create the first executable admin page/action inventory
- `[x]` Build the first canonical admin approvals and user lifecycle service layer
- `[x]` Add canonical admin approvals and user action APIs under `backend/api/admin`
- `[x]` Wire admin approvals and users pages onto the new admin backend service layer
- `[x]` Add a generic admin user detail page so user-list view actions resolve to a real page

## Epic 0: Foundation and architecture cleanup

- `[~]` Start DB abstraction normalization for newly added role managers
- `[ ]` Confirm canonical runtime ownership for root pages, `frontend/`, `backend/api/`, and mirrored `api/`
- `[~]` Create a page inventory for every role folder
- `[~]` Create a page-to-endpoint contract inventory for every visible action
- `[ ]` Standardize all role/domain managers on the active PDO wrapper from `backend/includes/database.php`
- `[ ]` Remove `mysqli_*`, `fetch_assoc`, `insert_id`, `affected_rows`, and `begin_transaction` assumptions from role managers
- `[ ]` Standardize JSON response format for all backend endpoints
- `[ ]` Standardize role guard and tenant guard behavior for all endpoints
- `[ ]` Audit duplicated helper/config logic between `frontend/includes/` and `backend/includes/`
- `[ ]` Decide whether top-level `api/` stays as proxy or becomes deprecated
- `[ ]` Document canonical role names and route names

## Epic 1: Shared UX system

- `[ ]` Standardize use of the master dashboard shell across all role pages
- `[ ]` Create a shared page header pattern with title, subtitle, primary action, and breadcrumbs where needed
- `[ ]` Create shared table, filter bar, modal, form, badge, toast, and empty-state patterns
- `[ ]` Reduce visual inconsistency across older and newer role pages
- `[ ]` Standardize mobile sidebar and responsive behavior
- `[ ]` Standardize success, error, loading, and empty states
- `[ ]` Add accessibility pass for focus states, keyboard paths, and readable contrast

## Epic 2: Admin role completion

### Frontend

- `[ ]` Audit all admin pages and group them into active workflows
- `[ ]` Finish dashboard and overview UX
- `[~]` Finish user management, approvals, registrations, and invites UX
- `[ ]` Finish student, teacher, and parent management UX
- `[ ]` Finish class, enrollment, and timetable UX
- `[ ]` Finish attendance and biometric UX
- `[ ]` Finish notices, announcements, and alerts UX
- `[ ]` Finish analytics, reports, audit logs, and system health UX
- `[ ]` Finish backup/export, advanced SAMS, merit rules, and merit board UX

### Backend

- `[x]` Build/normalize admin dashboard stats service
- `[~]` Build user lifecycle endpoints and handlers
- `[~]` Build approval and registration action handlers
- `[ ]` Build invite creation, resend, cancel, and acceptance backend flow
- `[ ]` Build class CRUD and enrollment backend flow
- `[ ]` Build timetable backend flow
- `[ ]` Build attendance and biometric action backend flow
- `[ ]` Build notices/events/alerts backend flow
- `[ ]` Build reporting/export backend flow
- `[ ]` Build admin audit/event logging coverage

## Epic 3: Owner and Principal completion

### Frontend

- `[ ]` Finish owner dashboard, overview, analytics, and operations pages
- `[ ]` Finish principal dashboard, overview, reports, and oversight pages

### Backend

- `[ ]` Replace owner dashboard placeholder stats with real tenant/school analytics
- `[ ]` Replace principal dashboard placeholder stats with real school analytics
- `[ ]` Build leadership oversight services for attendance, users, classes, reports, and events
- `[ ]` Build read-only and action-safe leadership endpoints

## Epic 4: Teacher completion

### Frontend

- `[ ]` Finish teacher dashboard and my-classes flow
- `[ ]` Finish attendance, students, and class enrollment pages
- `[ ]` Finish assignments, materials, grades, and reports pages
- `[ ]` Finish parent communication, behavior logs, meeting hours, and settings pages

### Backend

- `[ ]` Build teacher roster and class assignment services
- `[ ]` Build attendance create/update/summary endpoints
- `[ ]` Build assignment and material lifecycle endpoints
- `[ ]` Build grade entry, update, and analytics endpoints
- `[ ]` Build behavior logging and parent notification backend flow
- `[ ]` Build teacher reporting endpoints

## Epic 5: Student completion

### Frontend

- `[ ]` Finish student dashboard
- `[ ]` Finish attendance, assignments, grades, schedule, and reports pages
- `[ ]` Finish check-in and ID card UX
- `[ ]` Finish study groups, messages, notifications, settings, and wallet pages

### Backend

- `[ ]` Build student summary/dashboard endpoint
- `[ ]` Build student attendance and academic history endpoints
- `[ ]` Build check-in status flow
- `[ ]` Build study-group data and participation endpoints
- `[ ]` Build wallet and merit visibility endpoints

## Epic 6: Parent completion

### Frontend

- `[ ]` Finish parent dashboard and children pages
- `[ ]` Finish attendance, grades, fees, communication, meetings, reports, and wallet pages

### Backend

- `[ ]` Replace mock ward overview logic with real parent-child resolution
- `[ ]` Build child-linking workflow
- `[ ]` Build ward attendance and grade history endpoints
- `[ ]` Build fee and wallet visibility endpoints
- `[ ]` Build meeting booking and communication endpoints

## Epic 7: Finance completion

### Bursar

- `[ ]` Finish fee collection, invoices, receipts, payment plans, scholarships, defaulters, reports, and settings pages
- `[ ]` Build invoice creation/update/status endpoints
- `[ ]` Build payment posting and receipt generation endpoints
- `[ ]` Build defaulter and scholarship reporting endpoints

### Accountant

- `[ ]` Finish dashboard, ledger, expenses, income, payroll, budget, balance sheet, profit/loss, tax reports, reports, settings, and wallets pages
- `[ ]` Harden accountant CRUD endpoints already in place
- `[ ]` Build ledger balancing and reporting services
- `[ ]` Build payroll processing flow
- `[ ]` Build budget planning and actual-vs-budget reporting
- `[ ]` Build finance audit trail coverage

## Epic 8: Support-role completion

### Librarian

- `[ ]` Finish catalog, loans, reservations, fines, digital resources, inventory, reports, and settings pages
- `[ ]` Replace stub librarian manager with real CRUD and loan workflow

### Nurse

- `[ ]` Finish health records, first aid, medications, wellness, reports, and settings pages
- `[ ]` Replace stub nurse manager with real incident, record, and medication workflow

### Transport

- `[ ]` Finish routes, vehicles, drivers, allocations, trip logs, fuel, maintenance, reports, and settings pages
- `[ ]` Replace stub transport manager with real fleet, route, and allocation workflow

### Forum Moderator

- `[ ]` Finish dashboard, reported posts, categories, warnings, bans, threads, and settings pages
- `[ ]` Build moderation review and enforcement backend flow

### Staff

- `[ ]` Finish dashboard, tasks, support, reports, and settings pages
- `[ ]` Build minimal but real staff task/support workflow

## Epic 9: Cross-cutting product hardening

- `[ ]` Build a universal notification/toast/event feed strategy
- `[ ]` Finish file upload validation and storage handling
- `[ ]` Replace remaining placeholder AI/biometric logic or explicitly feature-flag it
- `[ ]` Standardize exports/downloads
- `[ ]` Add tenant isolation verification to critical queries
- `[ ]` Add role-based smoke tests for login-to-core-workflow paths
- `[ ]` Add workflow verification checklist per role
- `[ ]` Add error logging and audit logging coverage to all critical actions
- `[ ]` Review performance hotspots for heavy dashboard/report pages
- `[ ]` Verify PWA/offline behavior only for pages that should support it

## Immediate next sprint

- `[~]` Build the full page/action inventory starting with admin
- `[ ]` Normalize all role manager DB access patterns
- `[~]` Complete admin backend contracts for the highest-traffic pages
- `[ ]` Begin shared UX cleanup on admin pages while wiring real actions
