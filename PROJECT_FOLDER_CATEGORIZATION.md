# SAMS Project Folder Categorization (Full Documentation)

## 1) Purpose

This document provides a complete categorization of the **current split project structure** after reorganizing the repository into two parent folders:

- `frontend/` (presentation and user interaction layer)
- `backend/` (API, business logic, data, operations, and infrastructure layer)

It is intended to be the single source of truth for folder ownership, boundaries, and maintenance responsibilities.

---

## 2) Root Structure (Current)

At repository root:

- `.git/` — Git metadata
- `.vscode/` — editor/workspace settings
- `frontend/` — full frontend app surface and UI modules
- `backend/` — full backend service/API and platform internals
- `.gitignore` — ignore rules
- `MIGRATION_SPLIT_MAP.md` — split mapping reference
- `PROJECT_FOLDER_CATEGORIZATION.md` — this document

---

## 3) Frontend Categorization (`frontend/`)

### 3.1 Role-based UI Modules

These folders represent role portals/pages and user-facing module interfaces:

- `frontend/admin/`
- `frontend/accountant/`
- `frontend/bursar/`
- `frontend/forum/`
- `frontend/forum-moderator/`
- `frontend/librarian/`
- `frontend/nurse/`
- `frontend/owner/`
- `frontend/parent/`
- `frontend/principal/`
- `frontend/staff/`
- `frontend/student/`
- `frontend/teacher/`
- `frontend/transport/`

**Category:** Domain-specific presentation layer by user role.

### 3.2 Shared Presentation & Layout

- `frontend/assets/` — css/js/images/fonts/icons
- `frontend/layouts/` — shared page shells/layout templates
- `frontend/views/` — reusable view fragments/UI templates
- `frontend/resources/` — frontend resources/content assets
- `frontend/public/` — static public frontend assets

**Category:** Shared UI composition and reusable visual resources.

### 3.3 Frontend Application Scaffold

- `frontend/src/`
  - `frontend/src/app/`
  - `frontend/src/modules/`
  - `frontend/src/shared/`

**Category:** Target modern frontend app architecture scaffold.

### 3.4 Public/Auth Entry Pages

- `frontend/index.php`
- `frontend/login.php`
- `frontend/logout.php`
- `frontend/register.php`
- `frontend/forgot-password.php`
- `frontend/reset-password.php`
- `frontend/confirm-account.php`
- `frontend/activate-account.php`
- `frontend/verify-email.php`
- `frontend/verify-otp.php`
- `frontend/notices.php`
- `frontend/updates.php`
- `frontend/system-overview.php`

**Category:** Front door, authentication UX, and general user-facing entry points.

### 3.5 PWA & Device Presentation Assets

- `frontend/manifest.json`
- `frontend/sw.js`
- `frontend/offline.html`
- `frontend/browserconfig.xml`
- `frontend/apple-touch-icon.png`
- `frontend/favicon-16x16.png`
- `frontend/favicon-32x32.png`
- `frontend/icon-192.png`
- `frontend/icon-512.png`
- Branded logo/icon image files in `frontend/`

**Category:** Progressive web app shell + install/offline/branding assets.

### 3.6 Frontend Governance Files

- `frontend/README.md` — frontend overview and roadmap

**Category:** Frontend strategy and implementation guidance.

---

## 4) Backend Categorization (`backend/`)

### 4.1 API and Application Core

- `backend/api/` — HTTP API endpoints
- `backend/app/` — application internals/domain code
- `backend/routes/` — route definitions
- `backend/core/` — core framework/bootstrap utilities
- `backend/modules/` — functional backend modules
- `backend/src/` — shared source libraries/utilities
- `backend/public/` — backend web entry/public server-facing files

**Category:** API transport and core application runtime.

### 4.2 Security, Identity, and Middleware

- `backend/auth/` — authentication components
- `backend/includes/` — shared helpers (auth/csrf/db/functions)
- `backend/middleware/` — request middleware layers
- `backend/config/` — configuration and environment plumbing

**Category:** Security policy enforcement and request lifecycle controls.

### 4.3 Data, Persistence, and State

- `backend/database/` — schema, migrations, setup SQL
- `backend/data/` — system data bundles/resources
- `backend/storage/` — runtime storage
- `backend/cache/` — cache layer outputs
- `backend/logs/` — operational logs
- `backend/backups/` — backup artifacts
- `backend/uploads/` — uploaded assets/files

**Category:** Persistent storage and state management.

### 4.4 Platform Operations & Automation

- `backend/scripts/` — utility and automation scripts
- `backend/cron/` — scheduled job handlers
- `backend/tools/` — operational/dev tools
- `backend/tests/` — test suites
- `backend/updates/` — update/upgrade process resources
- `backend/stitch/` — stitching/build tooling assets

**Category:** DevOps, automation, verification, and maintenance.

### 4.5 Integrations, AI, and Communication

- `backend/ai/` — AI engines/services
- `backend/chatbot/` and `backend/chatbots/` — chatbot implementations
- `backend/communication/` — communication subsystems
- `backend/public-ai/` — externally exposed AI-facing interfaces

**Category:** Intelligent services and external interaction channels.

### 4.6 Documentation, Planning, and Ecosystem

- `backend/docs/` — architecture/features/plans/reports documentation
- `backend/developer/` — developer-specific resources
- `backend/ecosystem/` — ecosystem/platform extension context
- `backend/general/` — generalized shared content
- `backend/reports/` — generated/manual reports

**Category:** Documentation, ecosystem context, and governance collateral.

### 4.7 Dependency & Build Configuration

- `backend/vendor/` — Composer dependencies
- `backend/composer.json`
- `backend/composer.lock`
- `backend/composer.enhanced.json`
- `backend/phpstan.neon`
- `backend/phpunit.xml`
- `backend/.php-cs-fixer.php`
- `backend/.htaccess`

**Category:** Dependency management, QA config, and runtime web config.

### 4.8 Migration/Fix/Setup Executables (Backend-owned)

- `backend/migrate.php`
- `backend/migrate-notices.php`
- `backend/migrate-phase2.php`
- `backend/migrate-phase3.php`
- `backend/migrate-profile.php`
- `backend/setup-admin.php`
- `backend/verify-system.php`
- `backend/universal-role-fix.php`
- `backend/fix-code-problems.php`
- `backend/fix-dashboard.php`
- `backend/fix-database-session.php`
- `backend/fix-database.php`
- `backend/fix-entire-system.php`

**Category:** One-off/maintenance/admin backend scripts.

### 4.9 Backend Governance File

- `backend/README.md` — backend overview and roadmap

**Category:** Backend strategy and implementation guidance.

---

## 5) Ownership Rules (Authoritative)

### Frontend owns

- UI rendering, interaction logic, route-level UX, client-side validation, and PWA shell behavior.

### Backend owns

- Security decisions, permission enforcement, data access, business rules, integrations, and state persistence.

### Shared principle

- Frontend may **display** role restrictions; backend must **enforce** them.

---

## 6) Change Placement Guide (Where new work should go)

- New page/component UX -> `frontend/`
- New API endpoint/service -> `backend/api/` + domain internals in `backend/app/` or `backend/modules/`
- New DB migration/schema -> `backend/database/`
- New automation/check script -> `backend/scripts/` or `backend/tools/`
- New architecture/reference docs -> `backend/docs/` (or root if cross-cutting)

---

## 7) Migration Status

- Physical split into `frontend/` and `backend/`: **Completed**
- Legacy monolith flattened at root: **Completed**
- Runtime path harmonization and compatibility refactor: **Pending next step**

---

## 8) Recommended Next Actions

1. Add compatibility bootstrap/routing so moved files resolve includes reliably.
2. Standardize `frontend -> backend/api` request base paths.
3. Add CI checks to prevent accidental cross-layer leakage.
4. Start incremental module modernization inside each parent without changing ownership boundaries.
