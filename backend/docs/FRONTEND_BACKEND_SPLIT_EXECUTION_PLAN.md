# SAMS Frontend + Backend Split Execution Plan

## Executive Overview

SAMS is currently a feature-rich PHP monolith with role-based portals and APIs living in one repository. The practical split is:

1. **Frontend track**: a dedicated UI application consuming versioned APIs.
2. **Backend track**: an API-first, modular backend with strict security and tenancy enforcement.

This split should be done incrementally (strangler pattern), not by a one-time big-bang rewrite.

---

## Current-to-Target Mapping

### Frontend-owned today

- UI pages in role directories (`admin/`, `teacher/`, `student/`, etc.)
- Presentation assets (`assets/`, `layouts/`, `views/`)
- PWA shell files (`sw.js`, `manifest.json`, `offline.html`)

### Backend-owned today

- API logic (`api/`)
- Shared logic/security/db (`includes/`, `middleware/`, `core/`, `config/`)
- DB migrations and scripts (`database/`, `scripts/`, `cron/`, `tools/`)

### Target split

- `frontend/` (new app)
- `backend/` (API app; can remain in same repo initially)

---

## Timeline (Suggested)

- **Month 1**: discovery, contracts, foundations (F0/F1 + B0/B1)
- **Months 2–3**: migrate core workflows (F2 + B2)
- **Months 4–5**: migrate extended modules and harden operations (F3 + B3)
- **Month 6**: production cutover and legacy decommissioning (F4 + B4)

---

## Program Workstreams

1. **Architecture & Governance**
   - ADRs for frontend framework, backend modularization style, API standards.
2. **Security & Compliance**
   - CSRF/auth/rate limit/tenant-isolation controls and audits.
3. **API Platform**
   - Endpoint normalization, versioning, contract test suite.
4. **Frontend Migration**
   - Role-by-role feature migration with staged rollout.
5. **Quality Engineering**
   - Contract, integration, and E2E test automation.
6. **Release & Operations**
   - Monitoring, logging, rollback plans, migration playbooks.

---

## Milestone Acceptance (Cross-Team)

- Critical user journeys (auth, attendance, reports, core admin) pass in new frontend.
- API contracts stable and versioned for migrated domains.
- Security checks and tenant isolation pass in CI.
- Observability dashboards cover errors, latency, and key business flows.
- Legacy page traffic reduced to near-zero before final retirement.

---

## Immediate Next Steps (Start This Week)

1. Freeze and publish API contract conventions (`/api/v1`, envelopes, auth headers).
2. Build frontend skeleton and backend contract test harness.
3. Select first migration slice: **Auth + Admin dashboard + Attendance**.
4. Set up a staged environment for side-by-side old/new experience.
5. Track progress per domain with explicit parity checklist.
