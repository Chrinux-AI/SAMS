# Master Implementation Plan

Last updated: 2026-04-20

## Goal

Finish the platform as a complete, role-based school management system where:

- the frontend is calm, simple, neat, modern, and consistent
- the backend is structured, secure, tenant-aware, and fully functional
- every page and every button maps to a real workflow
- every role can complete its daily work without dead ends

## Delivery principles

### 1. Frontend-first experience definition

We will design and finalize the page experience for each role before calling that role complete.

That does not mean backend work waits until the very end. It means each frontend page must first declare:

- what the page is for
- who can use it
- what actions exist on the page
- what data is needed
- what success, empty, loading, and error states look like

### 2. No decorative controls

No button, filter, modal, quick action, bulk action, export, or card CTA should remain present without a real workflow behind it.

### 3. One shared UX language

The platform should feel like one product, not thirteen separate dashboards.

UX standards:

- low cognitive load
- clear hierarchy
- limited primary actions per screen
- stable layouts
- consistent table, form, modal, alert, and toast behavior
- predictable navigation
- tenant-safe context always visible where it matters

### 4. Backend contracts must be thin at the edge and strong in the middle

Target backend flow per feature:

`page -> endpoint -> role/domain manager -> database`

Within the current repo, that means:

- `frontend/<role>/*.php` stays as the UI layer
- `backend/api/<role>/*.php` becomes thin request handlers
- `backend/modules/<role>/*Manager.php` becomes the canonical domain layer for that role
- `backend/database/migrations/*.sql` remains the change log for schema evolution

### 5. Finish by role, not by random file

We will move through the product in a controlled role order so that each role becomes fully usable before the next cluster is called complete.

## Phase 0: Baseline and stabilization

Purpose: remove ambiguity before deep feature work.

### Deliverables

- choose canonical runtime ownership for:
  - root public/auth pages
  - frontend role pages
  - backend API endpoints
  - mirrored `api/` compatibility layer
- standardize database access around the active PDO wrapper
- remove `mysqli_*` assumptions from role managers
- create a page/action inventory for every role
- confirm active schema baseline and required migrations
- document canonical role names and route names

### Success criteria

- one documented path for every request type
- no new feature code uses mixed DB abstractions
- every role folder has an inventory of pages and actions

## Phase 1: Shared UX system

Purpose: make the platform feel clean, calm, and consistent before scaling more screens.

### Deliverables

- standardized dashboard shell usage for all roles
- shared page header pattern
- shared cards, tables, filters, bulk actions, forms, modals, toasts, empty states
- consistent search/filter/action bars
- consistent mobile behavior and sidebar behavior
- accessible color, spacing, keyboard, and focus treatment

### Success criteria

- any role page can be rebuilt without inventing a new local layout
- visual noise is reduced
- common interactions behave the same everywhere

## Phase 2: Admin completion

Purpose: make admin the first fully finished operating role.

### Frontend scope

- dashboard and overview
- users, approvals, registrations, invites
- students, teachers, parents
- classes, enrollment, timetable
- attendance and biometric entry surfaces
- notices, announcements, alerts
- reports, analytics, audit logs
- system health, backup/export, advanced SAMS, merit board/rules

### Backend scope

- admin dashboard stats service
- user lifecycle services
- registration approval flows
- invite lifecycle services
- class and enrollment CRUD
- attendance and biometric actions
- notices/events/emergency actions
- report/export handlers
- audit/event logging normalization

### Success criteria

- all admin pages load real data
- all admin buttons post to real handlers
- all admin workflows validate role and tenant scope

## Phase 3: Owner and Principal completion

Purpose: finish leadership oversight roles after admin.

### Frontend scope

- owner dashboard, oversight, reports, system health
- principal dashboard, oversight, class and staff monitoring

### Backend scope

- real leadership stats instead of placeholder dashboard payloads
- approval summaries
- school analytics
- role-safe read-only and action-limited oversight services

### Success criteria

- owner and principal dashboards are insight-driven
- no placeholder operational stats remain

## Phase 4: Teacher completion

Purpose: make the academic execution role production-usable.

### Frontend scope

- my classes
- students
- attendance
- materials
- assignments
- grades
- class enrollment
- parent communication
- behavior logs
- reports
- settings

### Backend scope

- class roster loading
- attendance write flows
- grade entry and update flows
- assignment/material lifecycle
- behavior logs with notification hooks
- parent communication history

### Success criteria

- a teacher can run a day of work without switching to admin tools

## Phase 5: Student and Parent completion

Purpose: finish learner and guardian experience with clean, low-stress flows.

### Student frontend scope

- dashboard
- attendance
- grades
- assignments
- schedule
- events
- check-in
- ID card
- study groups
- wallet
- notifications/settings

### Parent frontend scope

- dashboard
- children
- attendance
- grades
- fees
- communication
- meetings
- reports
- wallet

### Backend scope

- ward resolution and child linking
- student academic summary
- parent ward-status APIs
- attendance/grade history APIs
- meeting booking workflow
- fee and wallet visibility flows

### Success criteria

- students and parents see only relevant, current, role-scoped data
- no mock ward or wallet content remains

## Phase 6: Finance completion

Purpose: finish bursar and accountant flows together so finance behaves as one coherent domain.

### Bursar frontend scope

- fee collection
- invoices
- receipts
- payment plans
- scholarships
- defaulters
- daily summary
- reports

### Accountant frontend scope

- dashboard
- ledger
- income
- expenses
- payroll
- budget
- balance sheet
- profit/loss
- tax reports
- audit trail
- wallets
- reports/settings

### Backend scope

- invoice creation and status transitions
- payment posting and reconciliation
- ledger entries
- expense CRUD
- payroll runs
- budget categories and targets
- finance reporting queries
- wallet and allowance integration

### Success criteria

- all finance pages are backed by real tables and balanced workflows
- invoice, payment, ledger, and reporting data are internally consistent

## Phase 7: Support-role completion

Purpose: finish operational roles so the platform is truly end-to-end.

### Librarian

- books, categories, active loans, issue/return, reservations, fines, digital resources, reports

### Nurse

- health records, first aid, medications, wellness, reports

### Transport

- fleet, drivers, routes, allocation, trip logs, fuel log, maintenance, reports

### Forum Moderator

- reported posts, user warnings, categories, bans, thread review

### Staff

- tasks, support, reports, settings

### Backend scope

- replace stub managers with real domain logic
- align tables and CRUD actions
- build history/audit actions where needed

### Success criteria

- these roles stop being scaffold surfaces and become working operational modules

## Phase 8: Cross-cutting completion

Purpose: close the platform gaps that affect all roles.

### Deliverables

- notification center
- file upload policy and storage strategy
- export/download handlers
- AI placeholder replacement strategy
- biometric hardening
- audit coverage
- reporting consistency
- role-based automated smoke tests
- tenant isolation verification
- performance and cache review

## Target backend structure

We should keep the current repo shape, but enforce clear responsibilities:

### `frontend/`

- render UI
- handle page-level validation and state display
- call backend endpoints only through known contracts

### `backend/api/<role>/`

- validate request method
- validate auth and role
- validate input
- call role/domain manager
- return consistent JSON payloads

### `backend/modules/<role>/`

- hold business rules
- perform tenant-scoped queries
- own workflow transitions
- avoid direct HTML concerns

### `backend/includes/`

- shared config
- shared DB wrapper
- shared auth/session/security helpers
- cross-domain utility functions only

### `backend/database/migrations/`

- every schema change for feature completion

## Definition of done for a page

A page is done only when all of the following are true:

1. it loads real data
2. it enforces the correct role
3. it scopes data to the correct tenant/school
4. every visible action works
5. empty/loading/error states are handled
6. success/failure feedback is visible
7. it survives a manual workflow test

## Recommended build order from here

1. Phase 0 baseline
2. Phase 1 shared UX system
3. Phase 2 admin end-to-end completion
4. Phase 3 owner/principal
5. Phase 4 teacher
6. Phase 5 student/parent
7. Phase 6 bursar/accountant
8. Phase 7 librarian/nurse/transport/forum moderator/staff
9. Phase 8 cross-cutting hardening

## Immediate next move

Start with:

1. page/action inventory
2. DB abstraction normalization
3. admin workflow completion

That sequence gives us the fastest path to turning the platform from broad-but-uneven into reliably complete.
