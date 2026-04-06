# SAMS Frontend Overview & Roadmap

## Purpose

This document defines how to extract and evolve the **frontend** of SAMS from the current PHP monolith into a dedicated frontend application with clear boundaries, API contracts, and phased delivery.

---

## 1) Frontend Scope (What belongs to frontend)

### Current UI sources in this repository

- Role portals/pages:
  - `admin/`, `student/`, `teacher/`, `parent/`, `staff/`, `principal/`, `owner/`, `librarian/`, `bursar/`, `accountant/`, `transport/`, `forum/`, `forum-moderator/`
- Shared visual/layout assets:
  - `assets/` (CSS, JS, images, icons)
  - `layouts/`, `views/`, `resources/`
- Public/auth entry pages:
  - `index.php`, `login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `confirm-account.php`, `activate-account.php`
- PWA/static UX files:
  - `sw.js`, `manifest.json`, `offline.html`, browser icons and PWA assets

### Future frontend responsibilities

- Route rendering and UI composition (role-aware dashboard shells).
- Form UX + validation + optimistic updates.
- Session/token handling and secure API calls.
- Accessibility, responsive behavior, and internationalization-ready components.
- Error handling UX (toasts, retry prompts, empty/loading/error states).

### What must move out of frontend

- SQL/data access and database logic.
- Permission enforcement decisions (frontend can hide, backend must enforce).
- Email/OTP generation, security token verification, and audit persistence.

---

## 2) Recommended Target Frontend Architecture

## Option A (Recommended): Single frontend app + role modules

- `frontend/`
  - `src/app/` (shell, router, bootstrapping)
  - `src/modules/` (admin, teacher, student, etc.)
  - `src/shared/` (design system, api client, auth hooks, utilities)
  - `src/features/` (attendance, classes, finance, transport, library)
  - `public/` (manifest, icons, service worker registration)

Benefits:

- Shared components and UX consistency.
- Lower maintenance than many separate role apps.
- Easier PWA and release management.

## Option B: Multiple frontend apps by domain

- `frontend-admin/`, `frontend-teacher/`, `frontend-student/`, etc.

Tradeoff:

- Better isolated deployments but much higher complexity and duplication.

---

## 3) Frontend API Contract Requirements

Frontend must consume backend through versioned endpoints only:

- Use `/api/v1/...` naming.
- Standard response envelope:
  - success: `{ "success": true, "data": ..., "meta": ... }`
  - failure: `{ "success": false, "error": { "code": "...", "message": "..." } }`
- CSRF for mutating requests.
- Correlation/request IDs for troubleshooting.

### Required frontend infrastructure

- Typed API client wrapper (timeouts, retries for safe GETs, auth/CSRF headers).
- Centralized error mapper (HTTP/network/business errors).
- Route guards by role and auth state.

---

## 4) Frontend Roadmap (Phased)

### Phase F0 — Discovery & baseline (1–2 weeks)

- Inventory all pages/components and map them to domains.
- Capture current navigation trees for each role.
- Identify top 20 most-used user journeys.
- Create UI component inventory from `assets/` + `layouts/`.

Deliverables:

- Page-domain map.
- UX baseline snapshots.
- Frontend architecture decision record.

### Phase F1 — Foundation (2–3 weeks)

- Bootstrap `frontend/` app.
- Set up router, auth/session layer, API client, env config.
- Build design primitives (buttons, forms, cards, tables, modal, toast).
- Implement global states: loading, notifications, user context.

Deliverables:

- Running frontend shell.
- Login flow wired to backend auth endpoints.
- Shared component library v1.

### Phase F2 — Core migration (4–8 weeks)

Migrate high-value modules first:

1. Auth & account lifecycle (login, OTP/activation flows).
2. Admin dashboard + activity/audit views.
3. Attendance management and reporting.
4. Class/student/teacher management.

Deliverables:

- Feature-parity for critical workflows.
- Side-by-side availability with current PHP pages.

### Phase F3 — Extended modules (4–8 weeks)

- Finance, library, transport, communication, notices, forum modules.
- PWA UX parity (offline shell, sync queue where feasible).
- Accessibility pass (keyboard nav, contrast, ARIA).

Deliverables:

- 80–90% role workflows on new frontend.
- PWA behavior validated.

### Phase F4 — Cutover & hardening (2–4 weeks)

- Traffic shifting by role/domain.
- Performance profiling and bundle optimizations.
- Remove legacy frontend coupling from PHP views.

Deliverables:

- New frontend is default UI.
- Legacy pages retired or routed to compatibility mode.

---

## 5) Frontend Quality Gates

- Lighthouse scores targets: Performance >= 80, Accessibility >= 90.
- Zero critical accessibility blockers.
- E2E test coverage for top journeys by role.
- No direct DB-linked logic in frontend code.
- Error budget and client-side logging dashboard active.

---

## 6) Frontend Risks & Mitigations

- **Risk:** Role complexity creates duplicated UI logic.
  - **Mitigation:** Domain-driven modules + shared permission-aware components.
- **Risk:** API inconsistency slows migration.
  - **Mitigation:** Enforce backend response contract before module migration.
- **Risk:** PWA regressions.
  - **Mitigation:** Dedicated offline acceptance tests and staged rollout.

---

## 7) “Definition of Done” for Frontend Split

- Frontend deployable independently from PHP backend.
- All critical role journeys run via API calls, not server-rendered PHP pages.
- Visual parity and UX improvements validated with stakeholders.
- Monitoring/analytics and error tracking operational.
