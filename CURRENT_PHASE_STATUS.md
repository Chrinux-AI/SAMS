# Current Phase Status

Read this file before editing anything.

## Current Phase

Launch hardening and UI consistency.

This is a scope-freeze phase. We are finishing what already exists so the launch build is stable, consistent, and safe. We are not adding product ideas in code during this phase.

## Read Order For Any AI Agent

1. `CURRENT_PHASE_STATUS.md`
2. `AGENTS.md`
3. `docs/planning/GITHUB_COPILOT_BRIEF.md` or `docs/planning/ANTIGRAVITY_BRIEF.md`

Before making changes, always restate the current phase status in one short paragraph.

## What Is In Scope

1. backend rule completion for existing flows
2. multi-tenant enforcement
3. communication stability
4. launch-facing UI cleanup and runtime fixes
5. live validation using separate local sessions

## What Is Out Of Scope

- new features
- new role modules
- AI feature expansion
- broad redesigns
- large refactors for style only
- using the user's in-app browser session

## Runtime Rule

Use `attendance` only. Do not switch active work into donor folders or temp repos.

## Validation Rule

For every touched PHP file:

1. run `php -l <file>`
2. if the page is launch-facing, verify it live with a separate local session
3. confirm there is no generic error screen, offline shell fallback, fatal output, or warning output

## Completed In This Phase So Far

### Backend and runtime hardening

- messaging service and messaging API hardened
- approvals API hardened
- attendance, biometric, mobile sync, and notifications hardened
- SSE conversation participation checks added
- tenant-safe unread message helpers rolled out
- admin and teacher attendance pages hardened

### Launch UI stabilization already validated

- login redirects now land on `frontend/...` role dashboards
- parent dashboard and parent communication surfaces stabilized
- parent legacy shell pages normalized:
  - `frontend/parent/children.php`
  - `frontend/parent/attendance.php`
  - `frontend/parent/reports.php`
  - `frontend/parent/analytics.php`
  - `frontend/parent/fees.php`
  - `frontend/parent/grades.php`
  - `frontend/parent/link-children.php`
  - `frontend/parent/lms-overview.php`
  - `frontend/parent/settings.php`
  - `frontend/parent/team-selection.php`
- teacher/student legacy shell pages normalized:
  - `frontend/teacher/reports.php`
  - `frontend/teacher/students.php`
  - `frontend/teacher/analytics.php`
  - `frontend/student/attendance.php`
  - `frontend/student/reports.php`
  - `frontend/student/analytics.php`
- admin launch pages stabilized through live validation:
  - `frontend/admin/system-health.php`
  - `frontend/admin/approve-users.php`
- owner/principal wrapper pages now safely reuse admin pages after session handoff fixes:
  - `frontend/owner/_owner_gate.php`
  - `frontend/principal/_principal_gate.php`
- finance launch pages stabilized against current schema:
  - `frontend/bursar/dashboard.php`
  - `frontend/bursar/daily-summary.php`
  - `frontend/bursar/fee-collection.php`
  - `frontend/bursar/invoices.php`
  - `frontend/bursar/reports.php`
  - `frontend/bursar/payment-plans.php`
  - `frontend/bursar/receipts.php`
  - `frontend/bursar/fee-structure.php`
  - `frontend/bursar/defaulters.php`
  - `frontend/bursar/scholarships.php`
  - `frontend/accountant/dashboard.php`
  - `frontend/accountant/balance-sheet.php`
  - `frontend/accountant/profit-loss.php`
  - `frontend/accountant/reports.php`
  - `frontend/accountant/expenses.php`
  - `frontend/accountant/income.php`
  - `frontend/accountant/payroll.php`
  - `frontend/accountant/tax-reports.php`
  - `frontend/accountant/budget.php`
- shared sidebar now self-injects `sams-core.css` and Material Symbols so legacy pages that include `sidebar-nav.php` render with the intended shell assets even if their head section is incomplete
- audit complete: all surveyed legacy role pages (transport, bursar, librarian, accountant) follow consistent, correct shell structure with proper app-layout single wrapper and sidebar includes; now eligible for sidebar-based asset healing
  - transport: 12 pages scanned, 1 uses master-dashboard (dashboard), 11 legacy pattern eligible for sidebar injection
  - bursar: 13 pages scanned, 1 uses master-dashboard (dashboard), 12 legacy pattern eligible for sidebar injection
  - librarian: 15 pages scanned, 1 uses master-dashboard (dashboard), 14 legacy pattern eligible for sidebar injection
  - accountant: ~8 pages scanned, 1 uses master-dashboard (wallets), remaining eligible for sidebar injection
  - admin: `master-dashboard.php` is the dominant pattern, but page-by-page live validation is still required; `frontend/admin/approve-users.php` was a real duplicate-shell exception fixed on April 29
  - no broken nested app-layout wrappers or structural shell errors remain in the surveyed non-admin role batches
- **Comprehensive Project UI System Baseline (Established)**
  - global CSS asset injection working via sidebar-nav.php for all 163+ pages that include sidebar
  - universal head bootstrap helper created: `frontend/includes/sams-head-bootstrap.php` for pages without sidebar
  - all pages with `professional-ui.css` pattern now automatically benefit from core CSS and Material Symbols
  - **Legacy page adoption matrix:**
    - 49 pages use modern `master-dashboard.php` layout (fully compliant)
    - 138+ pages use `professional-ui.css` pattern (legacy but now stabilized via sidebar injection)
    - 163+ pages include `sidebar-nav.php` (now receive injected core CSS automatically)
  - **Session fully refreshed:** All current dev roles reset with password `DevPass@2026`
  - **Dev role repair added:** `backend/scripts/utilities/ensure-dev-logins.php` now repairs missing `users.role` enum values for `bursar`, `staff`, and `forum_moderator` before refreshing accounts
  - **Leadership dev accounts added:** `dev.owner@attendance.local` and `dev.principal@attendance.local` are now provisioned for live role validation
  - **.gitignore expanded:** Added modern dev artifacts, temp files, UI audit directories, agent debugging artifacts

### Runtime errors fixed during live verification

- `frontend/parent/team-selection.php` no longer calls the database wrapper like raw PDO
- `frontend/teacher/analytics.php` now tolerates missing grade-sync tables and mixed teacher identifiers
- `frontend/student/reports.php` and `frontend/student/analytics.php` now tolerate mixed class joins and mixed attendance date fields
- `frontend/admin/system-health.php` no longer hides metric values behind transparent gradient text and no longer carries stray legacy shell closers into the master dashboard layout
- `frontend/admin/approve-users.php` no longer nests a full legacy sidebar/app-layout shell inside `master-dashboard.php`
- `backend/scripts/utilities/ensure-dev-logins.php` now repairs unsupported role enum gaps so bursar, staff, and forum moderator dev accounts keep valid roles during live testing
- `frontend/owner/_owner_gate.php` and `frontend/principal/_principal_gate.php` now hand off admin page reuse without crashing on duplicate `session_start()` warnings
- `frontend/bursar/dashboard.php` now reads invoice/payment data from the current schema and no longer leaks raw PHP text into the page
- `frontend/bursar/daily-summary.php` now uses the live payment/invoice columns (`amount_paid`, `payment_reference`, fallback joins) and renders a readable transaction table
- `frontend/bursar/fee-collection.php` now records payments with current `fee_payments` columns, links to open invoices when possible, keeps invoice balances in sync, and renders polished form controls/table spacing
- `frontend/bursar/invoices.php` now uses flexible invoice inserts, tenant-scoped student lists, and polished form/table controls
- `frontend/bursar/reports.php` now uses `amount_paid` and current payment status/method fields instead of obsolete `amount`/`payment_type` assumptions
- `frontend/bursar/payment-plans.php`, `frontend/bursar/receipts.php`, `frontend/bursar/fee-structure.php`, `frontend/bursar/defaulters.php`, and `frontend/bursar/scholarships.php` now have consistent finance table/form polish, current payment field aliases, and valid scholarship enum values
- `frontend/accountant/dashboard.php`, `frontend/accountant/balance-sheet.php`, and `frontend/accountant/profit-loss.php` now use `ledger_entries.type + amount` and current finance column names instead of obsolete debit/credit/category assumptions
- `frontend/accountant/reports.php` now reads `financial_records.record_date`, `record_type`, and `reference_number` through aliases instead of obsolete `date`, `type`, and `reference` columns
- `frontend/accountant/expenses.php` now enforces accountant/admin access consistently, stores valid expense statuses/payment methods, displays live `expense_category` and `vendor_name`, and removes the duplicate page heading
- `frontend/accountant/income.php`, `frontend/accountant/payroll.php`, and `frontend/accountant/tax-reports.php` now use current `fee_payments.amount_paid`, `ledger_entries.category/type/amount`, and `payroll.user_id/status` schema shapes; income duplicate heading and mislabeled action were cleaned up
- `frontend/accountant/budget.php` remains live-checked and clean with safe demo fallback when no budget records are available

## Agent Briefs Updated (April 28)

✅ **Copilot brief** (`docs/planning/GITHUB_COPILOT_BRIEF.md`) updated with UI system baseline
✅ **Antigravity brief** (`docs/planning/ANTIGRAVITY_BRIEF.md`) updated with UI system baseline

Both briefs now document:

- 163+ pages with CSS injection guarantees via sidebar-nav.php
- Universal head bootstrap helper availability for edge cases
- Previous "UI failure pattern" is now systematically addressed via injection system
- Clear guidance to watch for component-level/custom wrapper edge cases, not old CSS gaps

## Previous UI Failure Pattern (Now Systematically Addressed)

Legacy pages **previously** broke when they combined:

- ~~`professional-ui.css` + missing `sams-core.css`~~ → **Now injected via sidebar**
- ~~missing Material Symbols font~~ → **Now injected via sidebar + head bootstrap**
- ~~stray/nested `app-layout` wrappers~~ → **Mostly addressed, but still possible on partial master-dashboard migrations; validate live**

**Current guidance:** Pages with `professional-ui.css` + `sidebar-nav.php` are now properly styled. Watch for edge cases (custom component wrappers, inline styles that override) but do not assume old CSS gaps remain.

## Structural Validation Complete (April 28)

✅ **Code-level validation of key pages:**

- `frontend/admin/overview.php` — modern `master-dashboard.php` pattern, theme-loader, proper head structure
- `frontend/teacher/attendance.php` — legacy pattern with professional-ui.css + sidebar-nav.php include (CSS injection eligible)
- `frontend/student/grades.php` — legacy pattern with professional-ui.css + sidebar-nav.php include (CSS injection eligible)

✅ **Verified injection system:**

- **sidebar-nav.php** function `sidebar_nav_emit_core_assets()` confirmed:
  - Static `$emitted` flag prevents duplicate link emissions
  - Emits `sams-core.css` via site_url() helper (safe URL resolution)
  - Emits Material Symbols Outlined from Google Fonts
  - Function called immediately on include (line 28)
  - Available for all 163+ pages that include sidebar-nav.php

✅ **Head structure verified:**

- All sampled pages have proper `<!DOCTYPE html>` declaration
- All have `<head>` section with meta tags
- professional-ui.css linked in all legacy pattern pages
- Theme loader script present for dark mode support
- App-layout wrapper present and single-nested (no structural issues)

## Current Work Queue

1. **Prioritized next:** continue live validation and targeted UI/runtime polish through librarian, transport, staff, nurse, and forum moderator utility pages
2. **Monitor in production:** watch for any CSS gaps or visual issues after deployment
3. **Targeted fixes:** address obvious contrast/readability issues and duplicate-shell regressions only where found live
4. **Avoid:** touching already-dirty unrelated files during stabilization

## Testing Setup

- Base URL: `http://127.0.0.1:8001/attendance`
- Dev password: `DevPass@2026`
- Refresh dev accounts:
  - `php backend/scripts/utilities/ensure-dev-logins.php`
- Use separate local automation only; do not consume the user's ChatGPT/Codex session

## Ownership Rule

If a file is already modified and your task does not require it, leave it alone.
