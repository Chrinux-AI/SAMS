# Antigravity Brief

Use this brief when continuing the current SAMS launch-hardening phase.

## Prompt

You are working inside `C:\\xampp\\htdocs\\attendance`.

Read `CURRENT_PHASE_STATUS.md` and `AGENTS.md` before you edit anything. Start by restating the current phase status in one short paragraph and listing the files you plan to touch.

### Scope freeze

Only work on:

1. backend/runtime hardening for existing features
2. multi-tenant safety
3. communication stability
4. launch-facing UI consistency
5. live validation using separate local sessions

Do not add features, redesign the UI, or refactor broadly.

### Required validation

For every touched PHP file:

1. run `php -l <file>`
2. if it is a launch-facing page, open it in a separate local session
3. confirm:
   - no generic error screen
   - no offline-shell fallback
   - no fatal/warning output

### Comprehensive UI System Baseline (Established April 28, 2026)

**Project-wide CSS injection system now stable:**

- ✅ **163+ pages** automatically receive `sams-core.css` and Material Symbols via `sidebar-nav.php` injection
- ✅ **49 pages** using modern `master-dashboard.php` pattern (already compliant)
- ✅ **138+ pages** using legacy `professional-ui.css` pattern (now receiving injected core CSS)
- ✅ **Universal head bootstrap** helper available: `frontend/includes/sams-head-bootstrap.php` for standalone pages
- ✅ **Dev accounts refreshed:** All current dev roles with password `DevPass@2026`
- ✅ **Dev-role enum repair in place:** `backend/scripts/utilities/ensure-dev-logins.php` now repairs missing `users.role` enum values for `bursar`, `staff`, and `forum_moderator` before refreshing accounts
- ✅ **Leadership dev accounts available:** `dev.owner@attendance.local` and `dev.principal@attendance.local` are provisioned for live checks

**Previous UI failure pattern (now systematically addressed):**

Legacy pages combined these issues (now resolved via injection system):

- ❌ missing `sams-core.css` → ✅ Now injected via sidebar or head bootstrap
- ❌ missing Material Symbols → ✅ Now injected via sidebar or head bootstrap
- ❌ stray empty `<div class="app-layout"></div>` → ✅ Already audited and resolved
- ❌ nested `app-layout` wrappers → ✅ Audit found no critical instances

**New approach:** Pages with `professional-ui.css` + `sidebar-nav.php` are now properly styled by the injection system. Watch for component-level or custom wrapper issues, but do not assume old CSS gaps remain. Fix edge cases conservatively.

### Local test setup

- Base URL: `http://127.0.0.1:8001/attendance`
- Dev password: `DevPass@2026`
- Refresh dev accounts: `php backend/scripts/utilities/ensure-dev-logins.php`
- Do not use the user's in-app browser session

### Recommended next focus

1. Continue role-by-role live UI validation after the current stabilized parent/teacher/student/admin/owner/principal batches.
2. Finance inner pages are the next highest-value runtime target:
   - already stabilized and live-checked: `frontend/bursar/fee-collection.php`, `frontend/bursar/invoices.php`, `frontend/bursar/reports.php`, `frontend/accountant/reports.php`, `frontend/accountant/expenses.php`
   - also stabilized and live-checked: `frontend/bursar/payment-plans.php`, `frontend/bursar/receipts.php`, `frontend/bursar/fee-structure.php`, `frontend/bursar/defaulters.php`, `frontend/bursar/scholarships.php`, `frontend/accountant/income.php`, `frontend/accountant/payroll.php`, `frontend/accountant/tax-reports.php`, `frontend/accountant/budget.php`
   - next candidates: librarian, transport, staff, nurse, and forum moderator utility pages
3. Prefer confirmed runtime/UI outliers over speculative cleanup.
4. Watch for:
   - duplicate shell wrappers
   - unreadable contrast on white cards
   - silent schema drift in finance/support pages
   - stale role/test account assumptions

### Stop condition

After each task, report:

1. files changed
2. what was fixed
3. how it was validated
4. what still looks risky
