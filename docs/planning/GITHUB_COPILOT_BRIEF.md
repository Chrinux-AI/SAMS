# GitHub Copilot Brief

Use this brief when continuing the current SAMS launch-hardening phase.

## Prompt

You are working inside `C:\\xampp\\htdocs\\attendance`.

Read `CURRENT_PHASE_STATUS.md` and `AGENTS.md` before you edit anything. Start by restating the current phase status in one short paragraph and listing the exact files you plan to touch.

### Scope freeze

Only work on:

1. backend/runtime hardening for existing features
2. multi-tenant safety
3. communication stability
4. launch-facing UI consistency
5. live validation using separate local sessions

Do not add features, redesign the product, or refactor broadly.

### Already validated in the current phase

- login redirects now land on `frontend/...` dashboards
- parent launch pages have been normalized for the shared shell
- teacher/student legacy pages in the current launch batch have been normalized
- the following runtime failures have already been fixed:
  - `frontend/parent/team-selection.php`
  - `frontend/teacher/analytics.php`
  - `frontend/student/reports.php`
  - `frontend/student/analytics.php`

Treat that as the current baseline and do not re-break it.

### Comprehensive UI System Baseline (Established April 28, 2026)

**Project-wide CSS injection system now stable:**

- ✅ **163+ pages** automatically receive `sams-core.css` and Material Symbols via `sidebar-nav.php` injection
- ✅ **49 pages** using modern `master-dashboard.php` pattern (already compliant)
- ✅ **138+ pages** using legacy `professional-ui.css` pattern (now receiving injected core CSS)
- ✅ **Universal head bootstrap** helper available: `frontend/includes/sams-head-bootstrap.php` for standalone pages
- ✅ **Dev accounts refreshed:** All current dev roles with password `DevPass@2026`
- ✅ **Dev-role enum repair in place:** `backend/scripts/utilities/ensure-dev-logins.php` now repairs missing `users.role` enum values for `bursar`, `staff`, and `forum_moderator` before refreshing accounts
- ✅ **Leadership dev accounts available:** `dev.owner@attendance.local` and `dev.principal@attendance.local` are provisioned for live checks

**The "Known UI failure pattern" below is now systematically addressed:**

- Missing `sams-core.css` → Now injected via sidebar or head bootstrap
- Missing Material Symbols → Now injected via sidebar or head bootstrap
- Stray/nested `app-layout` issues → Already audited; no critical issues found

**New baseline:** pages with `professional-ui.css` + `sidebar-nav.php` are now properly styled. Watch for edge cases (custom wrappers, component-level issues) but do not assume old CSS gaps remain.

### Known UI failure pattern

Legacy role pages often break when they combine:

- `professional-ui.css`
- `sidebar-nav.php`
- missing `sams-core.css`
- missing Material Symbols
- stray empty `<div class=\"app-layout\"></div>`
- nested `app-layout` wrappers used as content containers

Fix that pattern conservatively instead of redesigning the page.

### Validation required

For every touched PHP file:

1. run `php -l <file>`
2. if it is launch-facing, open it in a separate local session
3. confirm:
   - no generic error screen
   - no offline-shell fallback
   - no fatal/warning output

### Local setup

- Base URL: `http://127.0.0.1:8001/attendance`
- Dev password: `DevPass@2026`
- Refresh dev accounts: `php backend/scripts/utilities/ensure-dev-logins.php`
- Do not use the user's in-app browser session

### Stop condition

After each task, report:

1. files changed
2. what was fixed
3. how it was validated
4. what still looks risky
   - backend must enforce permissions
   - frontend checks are not sufficient

5. **Keep changes local**
   - patch the active API or service being hardened
   - avoid wide refactors
   - avoid touching already-dirty frontend files unless required

6. **Verify syntax**
   - after edits, run `php -l` on every touched PHP file

## Preferred working style

When continuing:

1. inspect current dirty changes first
2. identify the next launch-critical backend gap
3. patch the smallest live backend surface that reduces real risk
4. verify with `php -l`
5. summarize exactly what changed and what remains risky

## Recommended next targets

These are the next likely hardening targets after the current work:

1. remaining finance/support pages after the stabilized launch batch
   - already stabilized and live-checked: `frontend/bursar/fee-collection.php`, `frontend/bursar/invoices.php`, `frontend/bursar/reports.php`, `frontend/accountant/reports.php`, `frontend/accountant/expenses.php`
   - also stabilized and live-checked: `frontend/bursar/payment-plans.php`, `frontend/bursar/receipts.php`, `frontend/bursar/fee-structure.php`, `frontend/bursar/defaulters.php`, `frontend/bursar/scholarships.php`, `frontend/accountant/income.php`, `frontend/accountant/payroll.php`, `frontend/accountant/tax-reports.php`, `frontend/accountant/budget.php`
   - next candidates: librarian, transport, staff, nurse, and forum moderator utility pages
   - inspect for schema drift first; patch only where logs or live UI show a real mismatch

2. librarian / transport / staff utility pages
   - continue the same shell-consistency audit pattern role by role
   - look for legacy `professional-ui.css` pages that still conflict with shared sidebar or master-dashboard migration

3. remaining legacy attendance surfaces
   - inspect teacher/admin attendance pages and any related inline writes only if a real issue is still visible

4. communication-adjacent notification consumers
   - verify the frontend reads the tightened notification/SSE payloads without expecting legacy fields

5. remaining secondary role pages with direct unread-count queries
   - some utility pages may still query `message_recipients` directly and may need the same helper

## What not to do

- do not rewrite the attendance system
- do not replace messaging with a new architecture
- do not add "better" abstractions that are not needed for launch
- do not revert user or Copilot changes outside the exact files being worked on
- do not market the platform as an "operating system" in code or docs for launch work

## Output expectation

When you finish a slice:

- state which files changed
- state what was enforced
- state how it was verified
- state the next highest-risk area still remaining
