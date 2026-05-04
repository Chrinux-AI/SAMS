# SAMS Fixed Launch Plan

**Status:** Active
**Scope:** Finish-only launch plan
**Last updated:** 2026-04-23

## Purpose

This plan locks SAMS to the features already in progress and finishes them in a controlled order before any new ideas are added.

The launch goal is a stable, role-aware, tenant-safe school workspace with reliable communication and a simpler user experience.

## Scope Freeze

Do **not** add new modules, new role concepts, new AI expansion, or new visual experiments until launch stability is confirmed.

Finish only what already exists:

- backend rules and enforcement
- tenant isolation and data protection
- communication platform completion
- launch UI/UX simplification
- launch hardening and verification

## Launch Principles

1. Backend rules come first.
2. Tenant safety is mandatory everywhere.
3. Communication must be reliable before launch.
4. UI should simplify confirmed behavior, not invent new behavior.
5. One shared app shell should serve all roles.
6. Anime inspiration stays as a mood/reference only, not a literal visual direction.

## Workstream 1: Backend Rule Completion

Finish the missing behavior for existing modules and endpoints.

### Required outcomes

- role/action permission matrix for existing roles
- validation on create, update, delete, and approval actions
- ownership checks for records tied to users, classes, or tenants
- standard API success/error responses where launch-critical
- audit logging for sensitive operations
- clear rejection behavior when permissions are missing

### Definition of done

- no launch-critical page depends on frontend-only hiding
- restricted actions are blocked at the backend
- current pages and APIs behave consistently

## Workstream 2: Multi-Tenant Enforcement

Treat tenant isolation as non-negotiable.

### Required outcomes

- tenant resolution on every request path that reads or writes data
- tenant-scoped queries and writes for launch-critical modules
- tenant-aware messaging, attendance, notices, reports, and finance-facing data
- audit trail for privileged tenant switching or cross-tenant actions
- no silent cross-tenant access

### Definition of done

- launch paths cannot leak or mix tenant data
- all key records are verified tenant-safe

## Workstream 3: Communication Platform Enablement

Finish communication as one reliable platform instead of scattered pieces.

### Required outcomes

- direct messages
- conversation threads
- notices
- forum access and moderation where already present
- unread/read tracking
- tenant-safe recipient filtering
- role-safe message publishing rules

### Definition of done

- messages and notices work consistently for allowed role pairs
- forum access is stable or reduced to a safe minimal mode
- unread state and history are reliable

## Workstream 4: UI/UX Simplification

Do not redesign the whole product. Simplify the interaction model for launch.

### UI rules

- one shared app shell across roles
- one consistent header
- one simplified sidebar
- fewer choices per screen
- one primary action per page
- dashboards should show: attention now, today’s status, next action
- communication should live in one predictable place

### UX goals

- users should complete core tasks without sidebar hunting
- common workflows should feel calmer and faster
- the product should feel consistent even when role content differs

## Suggested Launch UI Patterns

Keep the launch interface focused on a small set of repeatable screen types:

- dashboard
- list with filters and actions
- detail view
- create/edit form
- inbox/thread view
- approval queue
- settings/more page

## Role Focus for Launch

Keep the role surfaces aligned to the existing product:

- **Admin:** approvals, registrations, attendance oversight, notices, communication, core operations
- **Teacher:** attendance, assignments, grades, parent communication
- **Student:** schedule, attendance, assignments, grades, notices
- **Parent:** child summary, attendance alerts, fees, communication
- **Principal/Owner:** exceptions, trends, school-wide oversight
- **Accountant/Bursar/Librarian/Transport/Staff/Nurse:** only their current operational paths

## Launch Hard Stops

Do not launch if any of these remain unstable:

- missing tenant scoping
- broken role restrictions
- unreliable communication flow
- inconsistent approval behavior
- confusing navigation on common tasks

## Execution Order

1. Backend rule completion
2. Tenant isolation review
3. Communication completion
4. UI/UX simplification
5. Launch hardening and validation

## Working Rule

Backend behavior must be finished before frontend polish is finalized.
Frontend should simplify around confirmed backend rules, not work around missing ones.

## Current Focus

The immediate focus is on the remaining backend rules and launch-critical flows, with the UI only simplified where it helps users finish work faster.

## Notes

- Anime influence is accepted only as inspiration for energy, structure, and identity.
- The final product should still feel trustworthy, calm, and school-appropriate.
- No new scope should be added until the current launch checklist is complete.
