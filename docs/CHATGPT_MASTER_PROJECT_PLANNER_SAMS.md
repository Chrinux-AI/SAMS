# SAMS MASTER PROJECT PLANNING DOSSIER
## Execution-Grade Architecture & Delivery Planning Prompt

You are my **Principal Software Architect, Product Strategist, Security Lead, and Technical Program Manager** for a real-world school platform called **SAMS**.

Your task is to produce a **decision-complete modernization, stabilization, and delivery plan** for my **existing codebase**.

This plan must be **directly executable by a small engineering team**.

Do NOT give generic advice.
Do NOT propose rewriting the system.

Focus on **stabilizing and evolving the current system safely.**

------------------------------------------------------------

# 0) HOW YOU MUST WORK

## Planning Philosophy

Follow this strategic order:

Stabilize
→ Standardize
→ Secure
→ Consolidate
→ Scale

### Operational Assumptions

Assume the following conditions exist:

• Code quality is inconsistent
• Database schema drift exists
• Some modules work while others partially work
• Duplicate and backup files exist
• Authentication flows are fragile
• Documentation may be outdated

Your responsibility is to **stabilize the real system, not redesign an ideal system.**

------------------------------------------------------------

## Non-Negotiable Constraints

| Constraint | Requirement |
|-----------|-------------|
| Stack | PHP + MySQL + JavaScript + CSS must remain |
| Migration | Incremental in-place improvements only |
| Downtime | Minimal disruption to existing features |
| Team Size | Assume a solo developer or small team |
| Risk Tolerance | Low migration risk |
| UX Priority | Secure but simple for school staff |

------------------------------------------------------------

## Planning Quality Standard

For **every major recommendation**, include:

1. Why this matters
2. What to change
3. How to implement
4. Risks or tradeoffs
5. Fallback option
6. Acceptance criteria

If multiple solutions exist:

• Choose **one primary approach**
• Provide **one fallback option**
• Explain the reasoning

Do NOT leave undecided placeholders.

------------------------------------------------------------

# 1) PROJECT CONTEXT (CURRENT REALITY)

## Project Identity

| Field | Value |
|------|------|
| Name | School Attendance Management System (SAMS) |
| Nature | Multi-role school operations platform |
| Target | Multi-tenant SaaS platform |
| Backend | PHP (mostly procedural) |
| Database | MySQL / MariaDB |
| Frontend | HTML + CSS + JavaScript |
| Development Environment | Windows + XAMPP |
| Codebase Size | Approximately 3000 files |

------------------------------------------------------------

## Major Directory Structure

admin/
teacher/
student/
parent/
accountant/
bursar/
librarian/
transport/
forum/
forum-moderator/

api/
includes/
assets/
database/
scripts/
chatbot/
tests/
docs/

------------------------------------------------------------

## Important Root Files

index.php
login.php
register.php
forgot-password.php
reset-password.php
confirm-account.php
verify-email.php
system-overview.php
migrate.php
manifest.json
sw.js

------------------------------------------------------------

## Current Strategic Features

• Multi-tenant architecture (partially implemented)
• AI-assisted user creation
• OTP verification system
• Role dashboards
• Theme standardization effort
• Progressive Web App support
• Role-specific modules (admin, teacher, student, etc.)

------------------------------------------------------------

# 2) PRODUCT VISION

Transform SAMS into a **stable, production-grade, multi-tenant school platform**.

## Target Outcomes

### Stability

• No blank pages
• No fatal runtime errors
• Consistent navigation across modules

### Core Admin Operations

Admin must reliably be able to:

• Create teachers
• Bulk import students
• Create classes
• Manage roles

### Identity & Security

Reliable flows for:

• OTP verification
• Email verification
• Password reset
• Invite-based onboarding

### Intelligent Features

• AI-assisted account creation
• Chatbot navigation assistant

### Scalability

Support **multiple schools (multi-tenant)** safely.

------------------------------------------------------------

# 3) KNOWN PAIN POINTS

### Runtime Reliability

• PHP errors appearing in UI
• Blank pages from fatal runtime errors

### Database Problems

• Table column mismatches
• Schema drift between database and code

### UI Inconsistency

• Mixed themes
• Inconsistent icons
• Different sidebar behavior across modules

### Identity Issues

• OTP verification failures
• Inconsistent password reset flow

### Codebase Fragmentation

• Duplicate files
• Legacy variants
• Multiple implementation styles

### Architectural Drift

• Features added rapidly without consolidation.

------------------------------------------------------------

# 4) REQUIRED DELIVERABLE

Produce a **complete architecture and delivery plan** for stabilizing and scaling the system.

The plan must be **implementation-ready**.

------------------------------------------------------------

# 5) REQUIRED OUTPUT STRUCTURE

Your response must use **exactly these sections**:

## Executive Summary
## Phase Plan
## Detailed Architecture
## Data & Security
## Workflow Specs
## QA & Operations
## 90-Day Timeline
## Risks
## Immediate Next 10 Actions

Inside sections:

• Use tables where useful
• Use checklists for implementation steps
• Use sequence diagrams when helpful
• Mark dependencies explicitly

------------------------------------------------------------

# 6) ARCHITECTURE REQUIREMENTS

## Application Structure Standard

modules/
services/
helpers/
layouts/

------------------------------------------------------------

## Service Layer (inside includes/)

Examples:

AuthService
UserService
StudentService
ClassService
ImportService
ChatbotService

------------------------------------------------------------

## API Conventions (api/)

Define:

• request format
• response format
• error format
• authentication strategy

------------------------------------------------------------

## Naming Standards

Standardize naming for:

• database tables
• services
• helpers
• modules

------------------------------------------------------------

## Backward Compatibility Policy

Design refactoring so that **existing pages continue functioning during migration.**

------------------------------------------------------------

# 7) DATA & SCHEMA GOVERNANCE

## Source of Truth Schema

database/schema.sql

------------------------------------------------------------

## Migration System

database/migrations/

001_init.sql
002_add_classes.sql

Migration rules:

• Ordered
• Idempotent
• Reversible

------------------------------------------------------------

## Drift Detection

Define automated schema comparison strategy.

------------------------------------------------------------

## Compatibility Adapters

Design safe handling for:

• missing columns
• renamed columns
• legacy fields

------------------------------------------------------------

## Multi-Tenant Model

Evaluate:

Shared database with tenant_id column
vs
Separate database per tenant

Recommend **one primary approach**.

------------------------------------------------------------

## Indexing Strategy

Focus indexing on:

• tenant_id
• user lookups
• attendance queries

------------------------------------------------------------

# 8) SECURITY & IDENTITY PLAN

## Authentication Hardening

Improve session handling and validation.

------------------------------------------------------------

## OTP Lifecycle Policy

Define:

| Policy | Value |
|------|------|
| Expiry | |
| Cooldown | |
| Attempt limit | |
| Daily request limit | |

------------------------------------------------------------

## Password Setup Strategy

Use **invite-based password creation**.

------------------------------------------------------------

## Email Verification

Ensure consistent verification flow.

------------------------------------------------------------

## Audit Logging

Log critical events:

LOGIN_FAILED
PASSWORD_RESET
OTP_REQUEST
ADMIN_ACTION

------------------------------------------------------------

## Abuse Protection

Implement:

• rate limiting
• request throttling
• suspicious activity logging

------------------------------------------------------------

# 9) CORE WORKFLOW BLUEPRINTS

Provide **step-by-step flow specifications** for:

1. Admin adds Teacher
2. Admin bulk imports Students (CSV)
3. Admin creates/manages Classes
4. AI/Form onboarding → account creation
5. Invite → OTP → password setup
6. Forgot password → OTP reset
7. Role-based dashboard routing

For each workflow include:

• inputs
• validation rules
• database operations
• side effects (emails, logs, notifications)
• error states and messages
• retry behavior
• monitoring hooks

Example sequence diagram format:

Admin → Form
Form → Validation
Validation → Database Write
Database → Email Service
Email → Invite Link
User → OTP Verification

------------------------------------------------------------

# 10) MULTI-TENANT ROADMAP

Include:

Tenant model decision
Tenant isolation boundaries

| Area | Isolation |
|-----|-----------|
| Data | tenant_id |
| Config | tenant configuration |
| Branding | theme overrides |

Define:

• Tenant onboarding flow
• Tenant admin hierarchy
• Superadmin control panel
• Migration path from current single-tenant assumption

------------------------------------------------------------

# 11) CHATBOT FOUNDATION

Define first working chatbot version.

Scope:

Navigation assistant
Help assistant
Basic FAQ

Intent categories:

| Intent | Example |
|------|------|
| Navigation | open attendance |
| Help | how to add student |
| Information | today's classes |

Add:

• role-aware responses
• logging for future training
• safe fallback behavior

------------------------------------------------------------

# 12) UI/UX UNIFICATION PLAN

Define global design system.

Design tokens:

• colors
• spacing
• typography

Theme directory:

assets/theme/

Define:

• sidebar standards
• icon standards
• responsive design baseline
• accessibility checklist

------------------------------------------------------------

# 13) ERROR ELIMINATION PLAN

### Fatal Error Prevention

Create guardrails against runtime crashes.

------------------------------------------------------------

### Syntax Sweep

Use PHP lint:

php -l

------------------------------------------------------------

### Runtime Guardrails

Add:

• null checks
• safe defaults
• defensive programming

------------------------------------------------------------

### Observability

| Tool | Purpose |
|------|--------|
| logs | error tracking |
| health checks | system status |

Production must **never display raw PHP errors to users**.

------------------------------------------------------------

# 14) TESTING & QA STRATEGY

Testing layers:

1. Static checks
2. Unit tests
3. Integration tests
4. End-to-end tests
5. Role-based UAT scripts

Also define:

• test data strategy
• bug triage process
• release gates

------------------------------------------------------------

# 15) 90-DAY DELIVERY PLAN

Provide a **week-by-week implementation roadmap**.

Include:

| Week | Goal |

Define:

• dependencies
• critical path
• milestone demos

------------------------------------------------------------

# 16) RISK REGISTER

Provide **15 major risks**.

Table format:

| Risk | Probability | Impact | Warning Signal | Mitigation |

------------------------------------------------------------

# 17) DEFINITION OF DONE

Define measurable completion criteria for each phase.

Include:

• technical quality
• UX consistency
• security standards
• operational readiness

------------------------------------------------------------

# 18) IMMEDIATE ACTION PLAN

Provide **10 high-impact actions I can execute immediately**.

These actions must be:

• clear
• unambiguous
• executable today

------------------------------------------------------------

# 19) TECHNICAL ASSUMPTIONS

Unless proven otherwise assume:

• mixed database schema states
• helper functions exist but are inconsistent
• SMTP exists but needs verification
• roles are not fully normalized
• legacy and backup files coexist
• development currently local

------------------------------------------------------------

# FINAL INSTRUCTION

Produce the **best practical modernization and stabilization plan** for transforming this SAMS codebase into a **stable, secure, multi-tenant school platform with reliable admin workflows, AI onboarding, chatbot assistance, and consistent cross-role UX — without rewriting the system.**

------------------------------------------------------------

OPTIONAL RULE

If missing details exist:

Infer reasonable defaults first.

Only ask clarification questions if absolutely necessary, and bundle them together.

You are my **Principal Software Architect, Product Strategist, Security Lead, and Technical Program Manager** for a real-world school platform called **SAMS**.

Your task is to produce a **decision-complete modernization, stabilization, and delivery plan** for my **existing codebase**.

This plan must be **directly executable by a small engineering team**.

Do **not** give generic advice.
Do **not** propose rewriting the system.

Focus on **stabilizing and evolving the current system safely.**

---

# 0) HOW YOU MUST WORK

## Planning Philosophy

You must follow this strategic order:

```
Stabilize
→ Standardize
→ Secure
→ Consolidate
→ Scale
```

### Operational assumptions

Assume:

• Code quality is inconsistent
• Schema drift exists
• Some modules work, some partially work
• Some files are duplicates/backups
• Authentication flows are fragile
• Documentation may be outdated

Your job is to **stabilize reality, not rebuild fantasy architecture.**

---

## Non-Negotiable Constraints

The plan **must respect** the following:

| Constraint     | Requirement                            |
| -------------- | -------------------------------------- |
| Stack          | PHP + MySQL + JS + CSS must remain     |
| Migration      | Incremental in-place improvements only |
| Downtime       | Minimal disruption to working features |
| Team size      | Assume solo developer or small team    |
| Risk tolerance | Low migration risk                     |
| UX priority    | Secure but simple for school staff     |

---

## Planning Quality Standard

For **every major recommendation**, include:

1. **Why this matters**
2. **What to change**
3. **How to implement**
4. **Risks / tradeoffs**
5. **Fallback option**
6. **Acceptance criteria**

If multiple solutions exist:

* Choose **one primary approach**
* Provide **one fallback**
* Explain **why**

Do **not** leave undecided placeholders.

---

# 1) PROJECT CONTEXT (CURRENT REALITY)

## Project Identity

| Field           | Value                                      |
| --------------- | ------------------------------------------ |
| Name            | School Attendance Management System (SAMS) |
| Nature          | Multi-role school operations platform      |
| Target          | Multi-tenant SaaS platform                 |
| Backend         | PHP (mostly procedural)                    |
| Database        | MySQL / MariaDB                            |
| Frontend        | HTML + CSS + JavaScript                    |
| Dev Environment | Windows + XAMPP                            |
| Codebase size   | ~3000 files                                |

---

## Major Directory Structure (Observed)

```
admin/
teacher/
student/
parent/
accountant/
bursar/
librarian/
transport/
forum/
forum-moderator/

api/
includes/
assets/
database/
scripts/
chatbot/
tests/
docs/
```

---

## Important Root Files

```
index.php
login.php
register.php
forgot-password.php
reset-password.php
confirm-account.php
verify-email.php
system-overview.php
migrate.php
manifest.json
sw.js
```

---

## Current Strategic Features (Partially Implemented)

• Multi-tenant architecture
• AI-assisted user creation
• OTP verification system
• Role dashboards
• Theme standardization effort
• Progressive Web App support
• Role-specific modules (admin/teacher/student/etc)

---

# 2) PRODUCT VISION

The goal is to evolve SAMS into a **professional, production-ready, multi-tenant school platform.**

## Target Outcomes

### Stability

* No blank pages
* No fatal runtime errors
* Consistent navigation across modules

### Operations

Admin must reliably be able to:

• create teachers
• bulk import students
• create classes
• manage roles

### Identity & Security

Reliable:

• OTP verification
• email verification
• password reset
• invite-based onboarding

### Intelligent Features

• AI-assisted account creation
• chatbot navigation assistant

### Scalability

Support **multiple schools** safely.

---

# 3) KNOWN PAIN POINTS

### Runtime reliability

• PHP errors appearing in UI
• Blank pages from fatal errors

### Schema issues

• Table column mismatches
• Drift between code expectations and DB structure

### UI inconsistency

• Mixed themes
• inconsistent icons
• different sidebar behavior across modules

### Identity problems

• OTP verification failures
• inconsistent password reset flow

### Codebase fragmentation

• duplicate files
• legacy variants
• multiple implementation styles

### Architecture drift

• features added rapidly without consolidation

---

# 4) REQUIRED DELIVERABLE

Produce a **complete architecture + delivery plan** using the sections below.

This plan must be **implementation-ready**.

---

# 5) REQUIRED OUTPUT STRUCTURE

Your output **must use exactly these sections.**

---

# A) Executive Summary

Limit to ~1 page.

Include:

• Current system diagnosis
• Top architectural risks
• Strategic roadmap summary
• Stabilization priorities

---

# B) System Reality Assessment

Provide a realistic analysis of the system.

### Likely Working Areas

Based on architecture patterns.

### Likely Fragile Areas

Based on typical legacy PHP behavior.

### Highest Architecture Drift Areas

Examples:

• authentication
• schema assumptions
• duplicated UI

### Top 20 Technical Debt Hotspots

Categorize them, e.g.:

| Category           | Example                       |
| ------------------ | ----------------------------- |
| DB drift           | inconsistent columns          |
| UI duplication     | navbars copied across modules |
| error handling     | missing guards                |
| auth fragmentation | multiple login logic          |

---

# C) Target Architecture (Incremental)

Design the **future structure without breaking existing code.**

Include:

### Application structure standard

```
modules/
services/
helpers/
layouts/
```

### Shared layout/component strategy

Standard header / sidebar / footer.

### Module boundaries

Separate:

```
admin
teacher
student
parent
```

### Service layer design (`includes/`)

Examples:

```
AuthService
UserService
StudentService
ClassService
ImportService
ChatbotService
```

### API conventions

Inside:

```
api/
```

Define:

• request format
• response format
• error structure

### Naming standards

• tables
• services
• helpers

### Backward compatibility policy

How old pages keep working during refactor.

---

# D) Data & Schema Governance Plan

Include:

### Source-of-truth schema

```
database/schema.sql
```

### Migration governance

```
database/migrations/
001_init.sql
002_add_classes.sql
```

Rules:

• ordered
• idempotent
• reversible

### Drift detection

Automated schema diff process.

### Compatibility adapters

Handling missing columns safely.

### Multi-tenant model

Choose between:

| Model       | Option           |
| ----------- | ---------------- |
| shared DB   | tenant_id column |
| isolated DB | per tenant       |

Recommend one.

### Indexing strategy

Focus on:

• tenant_id
• user lookups
• attendance queries

---

# E) Security & Identity Plan

### Authentication hardening

Session model improvements.

### OTP lifecycle

Define:

| Policy        | Value |
| ------------- | ----- |
| expiry        |       |
| cooldown      |       |
| attempt limit |       |
| daily limit   |       |

### Password setup/reset safety

Invite-first password creation.

### Email verification

Unified flow.

### Audit logging

Critical events:

```
LOGIN_FAILED
PASSWORD_RESET
OTP_REQUEST
ADMIN_ACTION
```

### Abuse protection

Rate limiting strategy.

### Usability balance

Avoid over-restrictive security.

---

# F) Core Workflow Blueprints

Provide **detailed step-by-step system flows**.

For each include:

• inputs
• validation
• DB operations
• side effects
• error handling
• retry behavior
• monitoring hooks

---

### Required workflows

1️⃣ Admin adds Teacher

2️⃣ Admin bulk imports Students (CSV)

3️⃣ Admin creates/manages Classes

4️⃣ AI/Form onboarding → account creation

5️⃣ Invite → OTP → password setup

6️⃣ Forgot password → OTP reset

7️⃣ Role-based dashboard routing

Use **text sequence diagrams**.

Example format:

```
Admin → Form
Form → Validation
Validation → DB write
DB → Email service
Email → Invite link
User → OTP verification
```

---

# G) Multi-Tenant Roadmap

Include:

### Tenant model decision

Shared DB with tenant key recommended unless strong reason otherwise.

### Isolation boundaries

| Area     | Isolation       |
| -------- | --------------- |
| data     | tenant_id       |
| config   | tenant config   |
| branding | theme overrides |

### Tenant onboarding flow

### Tenant admin hierarchy

### Superadmin controls

### Migration path from single-instance system

---

# H) Chatbot Foundation Plan

Define **practical first version**.

### Immediate scope

Navigation + help assistant.

### Intent types

| Intent     | Example            |
| ---------- | ------------------ |
| navigation | open attendance    |
| help       | how to add student |
| info       | today's classes    |

### Role-aware responses

Students cannot see admin actions.

### Feedback logging

Future training dataset.

### Safe fallback

“I couldn't understand your request.”

---

# I) UI/UX Unification Plan

Define global design system.

### Design tokens

Colors / spacing / typography.

### Theme governance

Inside:

```
assets/theme/
```

### Sidebar standards

Collapse behavior rules.

### Icon consistency

FontAwesome / icon pack standard.

### Responsive standards

Desktop-first but mobile safe.

### Accessibility baseline

Checklist.

---

# J) Error Elimination & Reliability

### Fatal error prevention checklist

### PHP syntax sweep

Example:

```
php -l
```

### Runtime guardrails

Null checks / safe defaults.

### Standard exception handling

Centralized error handler.

### Observability

| Tool          | Purpose        |
| ------------- | -------------- |
| logs          | error tracking |
| health checks | system state   |

### Production error display policy

Users never see raw PHP errors.

---

# K) Testing & QA Strategy

Define test levels.

### Static checks

lint / syntax.

### Unit tests

Service layer.

### Integration tests

Auth + import flows.

### End-to-end tests

Critical paths.

### UAT scripts

Role-based manual testing.

Also define:

• test data strategy
• bug triage process
• release gates

---

# L) 90-Day Delivery Plan

Week-by-week milestone plan.

Include:

| Week | Goal |
| ---- | ---- |

Identify:

• critical path
• dependencies
• demo checkpoints

---

# M) Risk Register

Top **15 risks**.

Include table:

| Risk | Probability | Impact | Signal | Mitigation |

---

# N) Definition of Done

Define measurable criteria per phase.

Categories:

• technical
• UX
• security
• operations

---

# O) Immediate Action Plan

Give **10 specific actions to start immediately**.

These must be:

• high impact
• low ambiguity
• executable today

---

# 6) TECHNICAL ASSUMPTIONS

Unless justified otherwise assume:

• mixed DB schema states
• helper functions exist but inconsistently used
• SMTP configured but not fully verified
• roles not fully normalized
• legacy + backup files coexist
• development currently local

---

# 7) OUTPUT FORMAT REQUIREMENTS

Use these **top-level sections exactly**:

```
## Executive Summary
## Phase Plan
## Detailed Architecture
## Data & Security
## Workflow Specs
## QA & Operations
## 90-Day Timeline
## Risks
## Immediate Next 10 Actions
```

Inside sections:

• use tables
• use checklists
• use sequence diagrams
• mark dependencies

---

# 8) DECISION RULES

If multiple approaches exist:

• choose one
• provide fallback
• explain reasoning

Never leave undecided options.

---

# 9) SPECIAL REQUEST

The user prefers **structured output that can be pasted into task trackers.**

Therefore:

• use clean headings
• avoid long paragraphs
• prefer bullet lists and tables

---

# 10) FINAL INSTRUCTION

Produce the **best practical modernization and stabilization plan** for transforming this existing SAMS codebase into a **stable, secure, multi-tenant school platform with reliable admin workflows, AI onboarding, chatbot assistance, and consistent cross-role UX — without rewriting the system.**

---

## Optional Add-On

If additional details are required:

```
Infer reasonable defaults first.
Only ask clarification questions when absolutely necessary.
Bundle all questions into one list.
```

---

✅ **Recommendation**

Use this planner with **GPT-5-thinking or GPT-5-3** models.
Those models produce the most **architecturally rigorous planning output**.

---

**Companion Prompts Available:**
- `CHATGPT_CODEBASE_ANALYZER_SAMS.md` - For scanning the 3000-file project
- `CHATGPT_BUG_ELIMINATION_SAMS.md` - For removing blank pages across the system
