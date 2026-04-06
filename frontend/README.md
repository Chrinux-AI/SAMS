# Frontend Parent Folder — Overview & Roadmap

## Overview

This `frontend/` folder is the dedicated parent for the SAMS user interface layer.
Its purpose is to host all client-facing application code while consuming backend APIs from `backend/` (or the current monolith APIs during migration).

### Frontend Mission

- Deliver role-based UI/UX for Admin, Teacher, Student, Parent, and other roles.
- Use API-driven architecture instead of embedding business/data logic in pages.
- Provide consistent responsive and accessible experience across modules.
- Maintain PWA capability (manifest, service worker, offline shell).

### Scope

Frontend should own:

- Route rendering and page composition.
- Forms, validation UX, state management.
- API client calls, auth/session handling, CSRF propagation.
- UI components/design system and interaction behavior.

Frontend should NOT own:

- Database access.
- Final authorization decisions.
- Email/OTP security validation.
- Tenant data scoping logic enforcement.

---

## Suggested Structure

- `src/app/` — app shell, bootstrap, router wiring
- `src/modules/` — role/domain modules (admin, teacher, student, finance, transport, etc.)
- `src/shared/` — reusable components, hooks, utils, api client
- `public/` — static assets, manifest, icons, service worker registration

---

## Frontend Roadmap

### F0: Discovery & Baseline (1–2 weeks)

- Inventory all current UI pages and map to domains.
- Capture role navigation maps and critical journeys.
- Build component inventory from legacy assets/layouts.

### F1: Foundation (2–3 weeks)

- Bootstrap app shell in `frontend/`.
- Add routing, auth/session context, API client abstraction.
- Introduce core design-system primitives (button/table/form/modal/toast).

### F2: Core Workflow Migration (4–8 weeks)

- Migrate high-impact journeys first:
  1. Authentication lifecycle.
  2. Admin dashboard and activity views.
  3. Attendance management/reporting.
  4. Student/teacher/class management.

### F3: Extended Domain Migration (4–8 weeks)

- Migrate finance, transport, library, communications, notices, forum.
- Align PWA/offline behavior with existing capabilities.
- Complete accessibility and mobile-first hardening pass.

### F4: Cutover & Optimization (2–4 weeks)

- Shift production traffic role-by-role.
- Remove legacy page dependencies for migrated flows.
- Optimize bundles, caching, and frontend observability.

---

## Frontend Quality Gates

- API-only data access for migrated modules.
- End-to-end tests for top role journeys.
- Accessibility targets met (keyboard nav, ARIA semantics, contrast).
- No critical UX regressions vs legacy workflows.

---

## Immediate Next Actions

1. Pick frontend framework/runtime and lock standards.
2. Build API client + route guard layer.
3. Start with Auth + Admin Dashboard + Attendance.
4. Run side-by-side rollout before full cutover.

## Cross-Project Documentation

- See `../PROJECT_FOLDER_CATEGORIZATION.md` for complete repository-wide folder categorization and ownership.

## Split Compatibility Layer (Current)

- A compatibility layer is in place so legacy frontend pages can continue resolving shared dependencies after the split:
  - `frontend/includes` -> points to backend shared includes
  - `frontend/core` -> points to backend core bootstrap/utilities
  - `frontend/views/core` -> compatibility path for layout includes

- Root URL compatibility is also enabled for existing absolute frontend paths:
  - root `assets` -> frontend assets
  - root `api` -> backend API
  - root public wrappers for legacy entry URLs (`/attendance/login.php`, `/attendance/index.php`, etc.)

This is transitional. Long term, frontend pages should be progressively refactored away from direct shared-include coupling and hardcoded absolute paths.
