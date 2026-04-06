# SAMS Migration Split Map (Monolith -> Frontend + Backend)

This map defines how current top-level areas should be assigned during migration.

## Target Parents

- `frontend/` -> UI application layer
- `backend/` -> API/business/data layer

## Mapping Guide

### Move/Refactor toward Frontend ownership

- Role UI directories:
  - `admin/`, `teacher/`, `student/`, `parent/`, `staff/`, `principal/`, `owner/`, `librarian/`, `bursar/`, `accountant/`, `transport/`, `forum/`, `forum-moderator/`
- Presentation and interaction assets:
  - `assets/`, `layouts/`, `views/`, `resources/`
- PWA UX files:
  - `manifest.json`, `sw.js`, `offline.html`, icons
- Public auth pages UX:
  - `index.php`, `login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `confirm-account.php`, `activate-account.php`

### Move/Refactor toward Backend ownership

- APIs and server logic:
  - `api/`, `includes/`, `middleware/`, `core/`, `modules/`, `src/`, `config/`
- Data and operational layers:
  - `database/`, `scripts/`, `cron/`, `tools/`, `logs/`, `storage/`
- Integration and service internals:
  - `ai/`, `chatbot/`, `chatbots/`, `communication/` (depending on whether UI or API concern)

## Migration Strategy

1. Keep legacy monolith paths active during transition.
2. Build new code under `frontend/` and `backend/` first.
3. Migrate domains in slices (Auth -> Dashboard -> Attendance -> Extended modules).
4. Deprecate legacy paths only after parity + testing + staged rollout.

## Note

This split map is intentionally non-destructive. It creates clear parent ownership now and supports controlled migration without breaking production workflows.

## Related Documentation

- `PROJECT_FOLDER_CATEGORIZATION.md` — Complete folder-by-folder categorization and ownership documentation for the entire split repository.
