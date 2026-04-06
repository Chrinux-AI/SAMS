# SAMS Backend Overview & Roadmap

## Purpose

This document defines how to extract and harden the **backend** of SAMS into a dedicated API/service layer while preserving security, tenancy boundaries, and operational reliability.

---

## 1) Backend Scope (What belongs to backend)

### Current backend-heavy areas in this repository

- API endpoints: `api/`
- Core shared logic and infrastructure:
  - `includes/` (database, auth helpers, CSRF, mail helpers, shared functions)
  - `config/`, `core/`, `middleware/`, `modules/`, `src/`
- Data and operations:
  - `database/` (schema/migrations)
  - `cron/`, `scripts/`, `tools/`
  - `logs/`, `storage/`
- Security and identity flows (OTP, activation, account verification).

### Future backend responsibilities

- API endpoints (REST-first, versioned).
- Authentication/authorization and role policy enforcement.
- Multi-tenant isolation enforcement (`tenant_id` boundaries).
- Data access, business rules, audit logging, and integrations (email/AI).
- Operational endpoints (health, metrics, diagnostics).

### What backend should stop doing over time

- Rendering final UI HTML for business modules.
- Tight coupling of controller logic and view templates.

---

## 2) Recommended Target Backend Architecture

## Service-style modular monolith (recommended intermediate target)

- `backend/`
  - `public/index.php` (single entry)
  - `app/Http/Controllers/`
  - `app/Domain/` (attendance, classes, users, finance, library, transport)
  - `app/Infrastructure/` (db, mail, cache, queue)
  - `app/Security/` (auth, csrf, permissions)
  - `app/Tenancy/`
  - `routes/api_v1.php`
  - `database/migrations/`
  - `tests/` (unit, integration, contract)

Why this target:

- Keeps deployment simple while improving separation.
- Creates clean seams for later microservice extraction if needed.

---

## 3) API & Security Baseline

### API standards

- Versioned endpoints: `/api/v1/...`
- Stable response envelope for success/error.
- Idempotency for selected mutation endpoints where applicable.
- Pagination/filter/sort conventions.

### Security requirements

- Strict RBAC + permission checks on every protected endpoint.
- CSRF protection for cookie/session-based mutation flows.
- Input validation and normalization at boundary layer.
- Centralized audit logging for privileged actions.
- Rate limiting and abuse controls for auth-sensitive endpoints.

### Tenancy requirements

- Enforce tenant scoping in query layer by default.
- Explicit super-admin bypass only where allowed.
- Tenant-safe reporting/export paths.

---

## 4) Backend Roadmap (Phased)

### Phase B0 — Baseline & boundary mapping (1–2 weeks)

- Inventory all endpoints and classify by domain and risk.
- Document shared helpers in `includes/` and current dependencies.
- Add API contract matrix (endpoint, method, auth, role, input/output schema).

Deliverables:

- Endpoint inventory + ownership map.
- Security hotspot report.

### Phase B1 — Core platform hardening (2–4 weeks)

- Introduce centralized request validation and response helpers.
- Enforce method checks and CSRF/auth consistency everywhere.
- Add tenant scoping middleware defaults.
- Standardize error envelope and status codes.

Deliverables:

- Baseline security controls uniformly applied.
- Contract-consistent API behavior.

### Phase B2 — Domain modularization (4–8 weeks)

- Extract domain services:
  - User/Auth
  - Attendance
  - Academics (classes/enrollment)
  - Finance
  - Library
  - Transport
- Move duplicate logic from page-level scripts into domain services.

Deliverables:

- Shared business logic centralized.
- Lower coupling between endpoints and legacy pages.

### Phase B3 — Data/ops modernization (3–6 weeks)

- Improve migration discipline and schema checks.
- Add background job handling for email/notifications/heavy exports.
- Add observability: structured logs, metrics, health/readiness endpoints.

Deliverables:

- Safer releases and better runtime visibility.
- Reduced request latency for heavy operations.

### Phase B4 — Frontend-first backend cutover (2–4 weeks)

- Lock down deprecated view-coupled endpoints.
- Publish API docs for all frontend-consumed modules.
- Add compatibility layer or redirects for legacy integrations.

Deliverables:

- Backend consumed primarily by dedicated frontend.
- Legacy coupling minimized.

---

## 5) Backend Quality Gates

- 100% protected endpoints have explicit auth + role enforcement.
- Contract tests for critical endpoints (auth, attendance, reports, exports).
- No P0/P1 security findings open.
- Tenant data isolation validated with automated tests.
- Error handling and logging consistent across modules.

---

## 6) Backend Risks & Mitigations

- **Risk:** Hidden shared helper coupling causes regressions.
  - **Mitigation:** Service extraction with compatibility wrappers and staged migration.
- **Risk:** Inconsistent endpoint behavior blocks frontend teams.
  - **Mitigation:** API contract tests + strict endpoint checklist.
- **Risk:** Tenant leakage in reports/exports.
  - **Mitigation:** query-level tenant guards + super-admin exception policy tests.

---

## 7) “Definition of Done” for Backend Split

- Backend deployable and testable as an independent API-focused application.
- Domain logic centralized in services, not scattered in page scripts.
- Security/tenancy policies enforced uniformly.
- API documentation and contract tests cover all frontend-critical flows.
