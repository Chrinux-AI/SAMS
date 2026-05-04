# Advanced SAMS Merge Blueprint

## Main Project

The main runtime project is `attendance`.

This folder is the only application that should keep evolving. The other folders are donors:

- `attendance-2`
- `SAMS 2`

They are reference sources for logic, schema ideas, workflows, and selected features. They are not separate runtime branches anymore.

## Merge Intent

We are consolidating the best parts of the donor projects into `attendance` while keeping the native `attendance` UI, layout system, routing style, and role dashboards.

That means:

- keep `attendance` as the operational app shell
- extract useful backend logic from both donors
- adapt donor features to `attendance` naming and tenant model
- avoid copying donor UI styles into the main app

## Donor Contributions

### From `attendance-2`

- advanced merit-economy logic
- class points and private points ledger model
- monthly allowance run model
- special exam and enforcement model
- invite registration flow
- school-first registration flow

### From `SAMS 2`

- stronger tenant-context thinking
- school-first lifecycle discipline
- invite-only staff membership pattern
- cleaner separation of tenant membership from user identity

## Operational Direction

The merged system should behave like an advanced School Management System inspired by the S-System idea:

- class-arms are ranked by class points
- students receive private points from monthly class-point snapshots
- all point-affecting actions are audit-backed
- high-risk sanctions are soft deactivation only
- school-first onboarding remains the tenant contract

## Core Goals

1. One main project only: `attendance`
2. Multi-school isolation with strict tenant scoping
3. Native Nigerian school support
4. Role-based dashboards with clear permissions
5. Ledger-backed merit economy
6. Auditable enforcement and restoration
7. Invite-based staff onboarding
8. Keep the UI consistent with `attendance`

## Tenant and Onboarding Model

The merged onboarding contract is:

1. A school is registered first
2. The first school admin is created with that school
3. Staff and managed roles enter through school-issued invites
4. Students and parents operate inside an existing school context
5. All operational records remain tenant-scoped

## Merit-Economy Features

The merged project now targets these core constructs:

- `class_point_accounts`
- `class_point_ledger`
- `private_point_accounts`
- `private_point_ledger`
- `monthly_allowance_runs`
- `merit_rules`
- `merit_events`
- `special_exams`
- `special_exam_rules`
- `special_exam_participants`
- `special_exam_outcomes`
- `enforcement_actions`

### Merit Rules

- class points belong to class-arms
- private points belong to students
- monthly allowance is class points times 100
- internal wallet currency is NGN
- balances are derived from immutable ledgers
- expulsion is soft deactivation, never deletion

## Roles in the Overall Project

The current overall role set for `attendance` should include the existing operational roles plus the merged merit/onboarding behavior:

- Super Admin
- Owner
- Principal
- Vice Principal
- Admin
- Admin Officer
- Teacher
- Class Teacher
- Subject Coordinator
- Student
- Parent
- Librarian
- Bursar
- Accountant
- Transport
- Counselor
- Nurse
- Staff
- Forum Moderator

## Responsibilities by Role

### Platform Roles

- Super Admin: platform-wide control, tenant oversight
- Owner: institution-wide strategic oversight

### School Leadership

- Principal: school policy, academic leadership, special-exam approvals
- Vice Principal: delegated leadership and operations
- Admin: school administration, approvals, invites, merit controls
- Admin Officer: school operations support

### Academic Roles

- Teacher: attendance, assignments, results, classroom events
- Class Teacher: class-arm coordination and student monitoring
- Subject Coordinator: academic oversight within subject domains

### Student and Family

- Student: attendance, academics, messages, private-point wallet
- Parent: children visibility, reports, read-only wallet visibility

### Operational Support

- Accountant: wallet reconciliation, allowance runs, financial views
- Bursar: fee collection and receivables
- Librarian: library operations
- Transport: route and fleet operations
- Counselor: student support
- Nurse: health and wellness workflows
- Staff: support operations
- Forum Moderator: community moderation

## Merged Feature Set

### Existing `attendance` Strengths to Keep

- richer dashboard shell
- broad role coverage
- analytics and AI modules
- finance, attendance, class, and communication modules
- better frontend layout consistency

### Donor Features Now Being Folded In

- school registration
- invite redemption
- invite management
- merit overview board
- student private-point wallet
- parent wallet visibility
- accountant wallet page
- backend merit and lifecycle APIs

## Implemented Merge State

The main `attendance` project now contains these merged building blocks from the donor folders:

- advanced merit-economy migration set
- school-first registration page
- invite redemption page
- admin invite management page
- admin merit overview board
- admin Advanced SAMS setup page
- accountant private-points wallet page
- student private-points wallet page
- parent wallet visibility page
- merit and school-lifecycle backend APIs
- login enforcement for soft deactivation and tenant-bound access

## Live Tracking Now Active

The merge is no longer only structural. These live write paths now feed the merged merit layer:

- attendance entry from admin attendance flow
- attendance entry from teacher attendance flow
- biometric quick scan attendance flow
- biometric verification attendance flow
- teacher behavior logs
- teacher grade entry

These flows now create tenant-scoped merit events and, where applicable, class-point ledger activity.

## Current Merge Principle

When two implementations overlap:

- prefer `attendance` UI and routing
- prefer donor logic only when it adds clear value
- adapt donor code to `attendance` schema helpers and tenant patterns
- do not create a parallel app inside the main folder

## What “Done” Looks Like

The merge is successful when:

1. `attendance` contains the strongest features from both donor projects
2. users no longer need to run `attendance-2` or `SAMS 2` to access those features
3. the UI still feels like one `attendance` product
4. onboarding, merit, wallets, and enforcement all work inside the main project

## Immediate Implementation Priorities

1. connect more academic and discipline modules into the same ledger-backed merit pipeline
2. add more admin operations for special exams and enforcement review
3. deepen accountant reconciliation between wallet flows and finance views
4. harden role restrictions and tenant isolation everywhere
5. keep removing donor-runtime dependence by pulling remaining useful logic into `attendance`

## Long-Term Product Direction

This project should become an Advanced SAMS:

- school-first
- ledger-backed
- role-strict
- tenant-safe
- academically aware
- financially traceable
- operationally auditable

The main rule remains simple:

`attendance` is the product. The donor folders are the source material.
