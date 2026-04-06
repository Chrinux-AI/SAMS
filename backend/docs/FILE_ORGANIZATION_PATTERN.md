# File Organization Pattern (SAMS)

## Root directory: keep only critical app-entry and infrastructure files

Keep in root:

- Web entry/auth endpoints (e.g., `index.php`, `login.php`, `logout.php`, `register.php`, password/account verification pages)
- Core web app files (`manifest.json`, `sw.js`, `offline.html`, `.htaccess`)
- Dependency/config files (`composer.json`, `composer.lock`, `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.php`)
- Main project readme (`README.md`)
- Top-level module directories (`admin/`, `teacher/`, `student/`, etc.)

Move out of root:

- Planning docs, implementation docs, reports, role references, specs (`*.md`) → `docs/` subfolders
- One-off maintenance/migration scripts should prefer `tools/` or `scripts/` unless they are required as public web endpoints

## Current root keep-set (SAMS)

Keep these root PHP files (runtime/sign-in/platform-critical):

- `index.php`
- `login.php`, `logout.php`, `register.php`
- `forgot-password.php`, `reset-password.php`
- `activate-account.php`, `confirm-account.php`, `verify-email.php`, `verify-otp.php`
- `notices.php`
- `updates.php`
- `setup-admin.php` (initialization)
- `verify-system.php` (health verification)
- `migrate.php`, `migrate-*.php` (migration compatibility entrypoints)

Legacy/non-runtime helpers should not remain in root; place under:

- `tools/legacy/` for legacy snapshots/examples
- `tools/maintenance/` for ad-hoc repair scripts
- `scripts/` for automated CLI workflows

### Compatibility rule for legacy URLs

If a legacy root endpoint is still referenced (UI buttons, cache route maps, or external bookmarks):

1. Move full implementation to `tools/maintenance/`.
2. Leave a minimal root wrapper that only `require_once` includes the moved file.
3. Keep wrappers temporary and remove after all references are migrated.

## Documentation placement

- `docs/implementation/` → implementation notes and system implementation docs
- `docs/planning/` → plans, prompts, roadmaps, checklists, deployment notes
- `docs/references/` → role references and owner/admin guides
- `docs/reports/` → completion and verification reports
- `docs/specs/` → UI/specification documents

## New-file rule

When creating new files:

1. Do **not** place documentation files in root.
2. Put docs into the correct `docs/*` category.
3. Keep root minimal and production-focused.
4. Place utility scripts in `tools/` or `scripts/` unless root exposure is intentionally required.
