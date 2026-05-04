# Project Execution Overview

Last updated: 2026-04-20
Working project root: `C:\xampp\htdocs\attendance`

## 1. What this repository is right now

This is no longer just an attendance tracker. It is a multi-role school management platform with attendance as one of the core workflows.

The current repository already contains:

- public landing and auth entry pages at the project root
- a split `frontend/` application layer for role portals and UI
- a split `backend/` layer for APIs, modules, migrations, services, tooling, and storage
- a mirrored top-level `api/` surface for runtime compatibility
- a large MySQL schema with academic, finance, communication, tenant, biometric, merit, and support-role tables

This means the project is in a transitional state between:

- a legacy monolithic PHP application
- a more structured split frontend/backend platform

## 2. Runtime topology

### Root layer

The project root still contains the active public/auth pages:

- `index.php`
- `login.php`
- `register.php`
- `forgot-password.php`
- `reset-password.php`
- `verify-email.php`
- `verify-otp.php`
- `activate-account.php`
- `confirm-account.php`
- `school-register.php`
- `invite-register.php`

These files already depend on `frontend/includes/*` and backend helpers, so the root layer is currently acting as the public entry shell.

### Frontend layer

`frontend/` contains the role-facing application pages, shared layouts, CSS, and PWA assets.

Observed role folders and approximate page counts:

| Role | Frontend PHP pages |
| --- | ---: |
| admin | 101 |
| owner | 24 |
| principal | 20 |
| teacher | 21 |
| student | 28 |
| parent | 18 |
| accountant | 21 |
| bursar | 13 |
| librarian | 15 |
| nurse | 8 |
| transport | 12 |
| staff | 6 |
| forum-moderator | 10 |

The frontend already has a real dashboard shell through `frontend/resources/ui-core/layouts/master-dashboard.php`, but the UI language and interaction model are not yet fully consistent across roles.

### Backend layer

`backend/` contains:

- `api/` thin HTTP endpoints
- `modules/` role/domain logic
- `includes/` config, database, helpers, auth/session utilities
- `database/` schema snapshots and migrations
- `app/app/` service-loader and middleware infrastructure
- `tools/`, `scripts/`, `storage/`, `tests/`, `vendor/`

There is already a strong intention toward a modular backend, but completion is uneven by role.

### API duplication

Both of these exist:

- `api/`
- `backend/api/`

That duplication is a compatibility bridge, not a finished architecture. We need to choose and document the canonical runtime contract, then keep the other layer as a thin proxy only if still required.

## 3. What is already strong

The codebase already has several strong foundations:

1. Multi-role scope is real, not aspirational.
2. Multi-tenant and school-first onboarding are already present.
3. Merit economy and wallet foundations have already been merged.
4. Frontend role folders already exist for the major school personas.
5. The database already covers a broad institutional footprint.
6. Auth, session timeout, role mapping, PWA assets, and shared includes are already wired.
7. The platform has enough surface area to move into structured completion rather than blank-slate design.

## 4. What is incomplete or risky

### A. Backend depth is uneven across roles

Some roles already have meaningful API work, especially around accountant flows. Others only have minimal dashboard endpoints or thin stubs.

Examples:

- accountant: several CRUD-style endpoints already exist
- parent/principal/transport/librarian/nurse/bursar: some endpoints exist, but many are only dashboard-level or action stubs

### B. Database access patterns are inconsistent

`backend/includes/database.php` exposes a PDO-based wrapper, but several role manager classes still use `mysqli_*` style assumptions.

This creates a real runtime risk because classes can appear wired while still being incompatible with the active database abstraction.

### C. Frontend page count is much larger than backend coverage

There are many role pages already present in `frontend/`, but there is not yet a one-to-one contract map showing:

- page purpose
- buttons/actions
- required tables
- API endpoint(s)
- permissions
- empty/error states

Until that exists, many pages will continue to be partially static or operationally incomplete.

### D. UX is not yet unified

The frontend contains a modern shell, but several pages still reflect mixed design generations:

- some use the newer design tokens and dashboard shell
- some still carry older visual styles and local patterns
- action density is uneven
- page behavior is not yet standardized across roles

### E. Duplicated config and helper surface

Both `frontend/includes/*` and `backend/includes/*` carry overlapping configuration/helper logic. That works today, but it increases drift risk unless we intentionally standardize ownership.

### F. Tests and workflow verification are still thin

The repo has a `backend/tests/` folder, but completion is not yet matched by a role-by-role functional verification suite.

## 5. Current role surface by product domain

### Leadership and administration

- Admin
- Owner
- Principal

These roles already cover dashboards, user control, notices, analytics, reports, school operations, and system configuration.

### Academic operations

- Teacher
- Student
- Parent

These already cover attendance, grades, assignments, schedules, reports, meetings, and role-linked communication.

### Finance

- Bursar
- Accountant

These already have visible page structure for invoicing, receipts, fee collection, ledger, expenses, payroll, reports, and wallets.

### Support operations

- Librarian
- Nurse
- Transport
- Staff
- Forum Moderator

These are present in the UI, but backend completion depth varies a lot and will need structured finishing.

## 6. Database state

The repository contains:

- a full schema snapshot in `backend/database/database/schema.sql`
- migration files for merit economy and grade support
- role-extension schema files for librarian, nurse, bursar, transport, and forum moderation

This means the database design is already broad enough to support a full completion pass. The next job is not to invent every table from scratch, but to normalize, finish missing tables, and align code to the active schema.

## 7. Recommended execution stance

We should treat this repository as:

- one active runtime project
- one active frontend
- one active backend
- one active database baseline

And we should finish it by moving role-by-role through:

1. UX and page-contract definition
2. backend service/API completion
3. page-to-endpoint wiring
4. button/action verification
5. tenant/role/security hardening
6. acceptance testing

## 8. Immediate engineering conclusions

The most important near-term truths are:

1. We do not need a rewrite.
2. We do need a strict completion plan.
3. Admin should be the first full role we harden end to end.
4. The backend must be normalized before we trust all role modules.
5. Every page needs an explicit action contract so every button becomes real work, not UI decoration.

## 9. Target outcome

The target state is:

- every role has a clean, low-stress, consistent UI
- every page has a clear purpose and real data
- every button triggers a validated backend action
- every role action respects tenant and permission boundaries
- every workflow is testable from login to completion

This repo is already far enough along that the right move is disciplined completion, not starting over.
