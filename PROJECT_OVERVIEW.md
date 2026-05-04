# SAMS Project Overview

## What This Project Is

SAMS, short for **School Attendance Management System**, is a large PHP-based school operations platform designed to run an institution's day-to-day academic, administrative, financial, communication, and support workflows from one system.

At its core, the project combines:

- attendance tracking
- role-based portals
- school administration tools
- finance and records management
- internal communication
- PWA/mobile-friendly delivery
- AI-assisted onboarding and automation
- multi-tenant support for more than one school or institution

This repository is not a small single-purpose attendance app. It is closer to a **full school management platform** with attendance as one of the most visible anchors.

---

## Main Goal of the System

The project aims to give a school a unified operating system where different users see different tools based on their role.

Examples:

- admins manage users, classes, enrollment, approvals, notices, reports, system settings
- teachers manage classes, attendance, assignments, grades, parent communication
- students check attendance, assignments, grades, schedules, messages, study groups
- parents monitor children, fees, grades, attendance, events, teacher meetings
- bursars and accountants manage fee collection, invoices, expenses, reports, statements
- librarians manage books, loans, fines, reservations, digital resources
- nurses manage health records, wellness, medications, first aid logs
- transport officers manage routes, drivers, vehicles, trip logs, fuel, maintenance
- owners and principals monitor institution-wide performance and operations

So the real objective is:

> centralize school operations into one role-aware, secure, scalable platform.

---

## Project Scope

Based on the current repository, the platform covers these broad domains:

### 1. Identity and Access

- login, logout, registration
- account activation and verification
- OTP-based flows
- password reset
- role-based access control
- tenant-aware access control
- optional biometric or advanced auth-related support

### 2. Academic Management

- class management
- class enrollment
- teacher assignment
- attendance capture and review
- assignments and grading
- schedules and student academic views
- report generation

### 3. School Administration

- user creation and approval
- registrations
- notices and events
- audit logs
- system monitoring
- ID management
- role and permission management

### 4. Finance

- fee collection
- invoices
- receipts
- payment plans
- scholarships
- expenses
- income tracking
- payroll
- budgets
- balance sheet and profit/loss style reporting
- audit trail and tax report pages

### 5. Operations and Support

- library workflows
- health and nurse workflows
- transport workflows
- staff support workflows
- platform maintenance and exports

### 6. Communication and Community

- messaging/conversations
- notices
- forum/community pages
- moderation tools

### 7. Platform and Product Features

- multi-tenant architecture
- PWA support
- offline shell
- service worker
- AI-assisted user creation/import
- health checks and schema validation tooling

---

## Current Repository Structure

The repository has already been split conceptually into **frontend** and **backend** layers.

### Frontend

The `frontend/` folder contains the user-facing application layer:

- role portals
- dashboards
- visual assets
- layouts
- PWA files
- auth entry pages

Active role/module folders currently present in `frontend/`:

- `admin/` with 76 PHP files
- `owner/` with 24 PHP files
- `principal/` with 20 PHP files
- `teacher/` with 21 PHP files
- `student/` with 23 PHP files
- `parent/` with 17 PHP files
- `accountant/` with 19 PHP files
- `bursar/` with 14 PHP files
- `librarian/` with 16 PHP files
- `nurse/` with 8 PHP files
- `transport/` with 13 PHP files
- `staff/` with 6 PHP files
- `forum-moderator/` with 11 PHP files

This confirms the project already supports a wide role surface and is not limited to just admin, teacher, and student.

### Backend

The `backend/` folder contains the service and infrastructure side:

- `api/`
- `app/`
- `auth/`
- `config/`
- `core/`
- `database/`
- `includes/`
- `middleware/`
- `modules/`
- `routes/`
- `scripts/`
- `tests/`
- `tools/`
- `ai/`
- `communication/`
- `public-ai/`
- storage, logs, backups, uploads, cache, updates, vendor, and more

This means the intended architecture is:

- `frontend/` for UX and presentation
- `backend/` for business logic, security, data, integrations, and platform operations

---

## Architectural Direction

The project is currently in a **transitional architecture state**.

### What exists now

- many legacy PHP pages still perform direct rendering with shared includes
- there is already an emerging split between frontend and backend ownership
- compatibility layers are still present so older pages keep working
- multiple dashboards and modules already exist in production-style structure

### What the docs say the project is moving toward

- API-driven frontend modules
- clearer separation between UI and server logic
- stronger ownership boundaries between frontend and backend
- progressive refactoring instead of a destructive rewrite

So this project is best described as:

> a mature but still consolidating school platform, evolving from a monolithic PHP app into a more structured split frontend/backend architecture.

---

## Core Product Characteristics

### Multi-Role

Different user groups get different navigation, permissions, and data views. The sidebar configuration in `frontend/includes/sidebar-nav.php` is one strong indicator of this role-specific application design.

### Multi-Tenant

The system is built to support more than one school or institutional tenant, with tenant-aware session and query scoping.

### Operationally Broad

It handles far more than attendance:

- academics
- finance
- transport
- health
- library
- communication
- reporting
- governance

### Security-Aware

The codebase and docs emphasize:

- CSRF protection
- session validation
- role validation
- approval flows
- audit logging
- tenant integrity

### Progressive Web App

The frontend includes:

- `manifest.json`
- `sw.js`
- `offline.html`
- app icons and browser configuration

That means the project is intended to behave like an installable web app with offline-oriented behavior.

### AI-Enabled

The repo and docs reference AI-oriented features such as:

- AI-assisted user creation
- structured form/import parsing
- chatbot or public AI surfaces
- role-specific assistant concepts

AI here is not the whole product. It is an enhancement layer on top of school operations.

---

## Role Overview

### Admin

The admin role appears to be the operational center of the platform. It covers users, classes, attendance, events, notices, approvals, reports, analytics, audit logs, backups, and system health.

### Owner

The owner role is an institution-wide oversight role. It combines academic visibility with finance, library, transport, analytics, backups, and high-level system controls.

### Principal

The principal role is focused on school leadership: students, teachers, classes, attendance, reports, events, notices, analytics, and monitoring.

### Teacher

Teachers manage teaching workflows:

- classes
- students
- attendance
- assignments
- grades
- learning materials
- parent communication
- analytics and reports

### Student

Students have self-service academic access:

- dashboard
- class registration
- schedule
- attendance
- check-in
- assignments
- grades
- events
- messages
- profile and ID card
- study group participation

### Parent

Parents use the platform to monitor and support their children:

- children records
- attendance
- grades
- fees
- LMS overview
- meetings
- communication
- reports

### Bursar

The bursar role handles direct fee-related workflows:

- fee collection
- invoices
- receipts
- payment plans
- fee structure
- defaulters
- scholarships
- daily summaries
- exports

### Accountant

The accountant role focuses on broader accounting and financial analysis:

- ledger
- expenses
- income
- payroll
- balance sheet
- profit and loss
- tax reports
- budget
- audit trail

### Librarian

The librarian role covers catalog and circulation:

- books
- categories
- digital resources
- issue/return
- active loans
- overdue
- fines
- reservations
- inventory
- reports

### Nurse

The nurse role handles health and wellbeing operations:

- health records
- first aid
- medications
- wellness
- reports

### Transport

The transport role manages movement logistics:

- routes
- vehicles
- drivers
- student allocation
- trip logs
- maintenance
- fuel logs
- reports

### Staff

The staff role appears smaller and more operationally focused, covering tasks, student support, reports, and communication.

### Forum Moderator

This role manages community oversight:

- reported posts
- user warnings
- banned users
- categories
- analytics

---

## Major Functional Workflows

From the code and docs, the main workflows appear to be:

### School Onboarding

1. institution or tenant is created
2. admin-like users are provisioned
3. users are added manually or through bulk import / AI-assisted flows
4. users activate accounts through verification and OTP-related steps

### Daily Academic Operations

1. admins/principals create classes and enrollments
2. teachers manage class rosters and attendance
3. students access schedules, assignments, and grades
4. parents monitor academic and attendance performance

### Financial Operations

1. bursar handles fee-facing transactions
2. accountant handles analysis, expenses, budgeting, and reporting
3. leadership roles review financial and institutional performance

### Operational Support

1. librarian manages resources and circulation
2. nurse tracks health matters
3. transport coordinates routes and mobility
4. staff handle support tasks

### Governance and Monitoring

1. admins approve users and monitor activity
2. audit logs and system health pages support oversight
3. backups, exports, and maintenance scripts support reliability

---

## Technical Composition

The project is mainly built with:

- PHP
- MySQL or MariaDB
- server-rendered HTML
- shared PHP include architecture
- JavaScript for interactivity
- Tailwind usage in some modernized pages
- PWA web assets

It also includes:

- Composer-managed backend dependencies
- PHPUnit and QA configuration
- scripts for schema checks, smoke checks, exports, migrations, and fixes

This suggests a platform that is both:

- application-heavy
- operations-heavy

not just a visual frontend project.

---

## Current State of the Codebase

The current state looks like a blend of:

- active production features
- modernization work
- migration planning
- compatibility bridges for older code paths
- documentation-driven cleanup and restructuring

There are clear signs of real feature breadth:

- large number of role pages
- backend operational tooling
- docs for roadmap and categorization
- split-folder architecture
- multiple domains beyond attendance

There are also signs the platform is still being stabilized and reorganized:

- legacy and modern styles coexist
- some docs describe target-state architecture rather than only present-state architecture
- compatibility mappings still matter

So the correct mental model is:

> this is a broad, evolving enterprise-style school platform under active consolidation, not a finished greenfield app with one single architectural style.

---

## Why the Project Is Significant

This project is significant because it tries to solve a real institutional problem end-to-end.

Instead of separate tools for:

- attendance
- messaging
- grading
- fees
- library
- transport
- health
- reporting

it brings them into one role-aware platform.

That makes it complex, but it also makes it strategically valuable. The more complete the role coverage becomes, the more it acts like a school ERP or campus operating system.

---

## Best One-Paragraph Summary

SAMS is a multi-tenant, role-based school management platform built primarily in PHP, with a split frontend/backend structure, covering attendance, academics, administration, finance, health, library, transport, communication, analytics, and operational governance. It supports many institutional roles through dedicated dashboards and workflows, includes PWA and AI-related capabilities, and is currently in a transition from a legacy monolithic structure toward a more modular, API-oriented architecture.

---

## Recommended Reading Inside This Repo

If someone wants to understand the project further, these are the most useful starting points:

- `PROJECT_FOLDER_CATEGORIZATION.md`
- `MIGRATION_SPLIT_MAP.md`
- `frontend/README.md`
- `backend/README.md`
- `docs/FEATURE_MATRIX_FROM_GITHUB.md`
- `docs/IMPLEMENTATION_ROADMAP_FROM_GITHUB.md`
- `frontend/includes/sidebar-nav.php`

---

## Final Assessment

This project entails:

- a school ERP-like platform
- deep role-based workflow coverage
- attendance as a central but not exclusive feature
- strong operational and governance concerns
- a live transition from legacy structure to cleaner architecture
- a large enough scope that documentation, modularization, and consistent role design are critical for long-term maintainability

In practical terms, if you are working on this codebase, you are working on a **full institutional management product**, not just an attendance dashboard.
