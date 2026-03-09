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
-> Standardize  
-> Secure  
-> Consolidate  
-> Scale

### Operational Assumptions

Assume the following conditions exist:

- Code quality is inconsistent  
- Database schema drift exists  
- Some modules work while others partially work  
- Duplicate and backup files exist  
- Authentication flows are fragile  
- Documentation may be outdated  

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

- Choose **one primary approach**  
- Provide **one fallback option**  
- Explain the reasoning  

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

- Multi-tenant architecture (partially implemented)  
- AI-assisted user creation  
- OTP verification system  
- Role dashboards  
- Theme standardization effort  
- Progressive Web App support  
- Role-specific modules (admin, teacher, student, etc.)

------------------------------------------------------------

# 2) PRODUCT VISION

Transform SAMS into a **stable, production-grade, multi-tenant school platform**.

## Target Outcomes

### Stability

- No blank pages  
- No fatal runtime errors  
- Consistent navigation across modules  

### Core Admin Operations

Admin must reliably be able to:

- Create teachers  
- Bulk import students  
- Create classes  
- Manage roles  

### Identity & Security

Reliable flows for:

- OTP verification  
- Email verification  
- Password reset  
- Invite-based onboarding  

### Intelligent Features

- AI-assisted account creation  
- Chatbot navigation assistant  

### Scalability

Support **multiple schools (multi-tenant)** safely.

------------------------------------------------------------

# 3) KNOWN PAIN POINTS

### Runtime Reliability

- PHP errors appearing in UI  
- Blank pages from fatal runtime errors  

### Database Problems

- Table column mismatches  
- Schema drift between database and code  

### UI Inconsistency

- Mixed themes  
- Inconsistent icons  
- Different sidebar behavior across modules  

### Identity Issues

- OTP verification failures  
- Inconsistent password reset flow  

### Codebase Fragmentation

- Duplicate files  
- Legacy variants  
- Multiple implementation styles  

### Architectural Drift

- Features added rapidly without consolidation.

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

- Use tables where useful  
- Use checklists for implementation steps  
- Use sequence diagrams when helpful  
- Mark dependencies explicitly  

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

- request format  
- response format  
- error format  
- authentication strategy  

------------------------------------------------------------

## Naming Standards

Standardize naming for:

- database tables  
- services  
- helpers  
- modules  

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

- Ordered  
- Idempotent  
- Reversible  

------------------------------------------------------------

## Drift Detection

Define automated schema comparison strategy.

------------------------------------------------------------

## Compatibility Adapters

Design safe handling for:

- missing columns  
- renamed columns  
- legacy fields  

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

- tenant_id  
- user lookups  
- attendance queries  

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

- rate limiting  
- request throttling  
- suspicious activity logging  

------------------------------------------------------------

# 9) CORE WORKFLOW BLUEPRINTS

Provide **step-by-step flow specifications** for:

1. Admin adds Teacher  
2. Admin bulk imports Students (CSV)  
3. Admin creates/manages Classes  
4. AI/Form onboarding -> account creation  
5. Invite -> OTP -> password setup  
6. Forgot password -> OTP reset  
7. Role-based dashboard routing  

For each workflow include:

- inputs  
- validation rules  
- database operations  
- side effects (emails, logs, notifications)  
- error states and messages  
- retry behavior  
- monitoring hooks  

Example sequence diagram format:

Admin -> Form  
Form -> Validation  
Validation -> Database Write  
Database -> Email Service  
Email -> Invite Link  
User -> OTP Verification  

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

- Tenant onboarding flow  
- Tenant admin hierarchy  
- Superadmin control panel  
- Migration path from current single-tenant assumption  

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

- role-aware responses  
- logging for future training  
- safe fallback behavior  

------------------------------------------------------------

# 12) UI/UX UNIFICATION PLAN

Define global design system.

Design tokens:

- colors  
- spacing  
- typography  

Theme directory:

assets/theme/

Define:

- sidebar standards  
- icon standards  
- responsive design baseline  
- accessibility checklist  

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

- null checks  
- safe defaults  
- defensive programming  

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

- test data strategy  
- bug triage process  
- release gates  

------------------------------------------------------------

# 15) 90-DAY DELIVERY PLAN

Provide a **week-by-week implementation roadmap**.

Include:

| Week | Goal |

Define:

- dependencies  
- critical path  
- milestone demos  

------------------------------------------------------------

# 16) RISK REGISTER

Provide **15 major risks**.

Table format:

| Risk | Probability | Impact | Warning Signal | Mitigation |

------------------------------------------------------------

# 17) DEFINITION OF DONE

Define measurable completion criteria for each phase.

Include:

- technical quality  
- UX consistency  
- security standards  
- operational readiness  

------------------------------------------------------------

# 18) IMMEDIATE ACTION PLAN

Provide **10 high-impact actions I can execute immediately**.

These actions must be:

- clear  
- unambiguous  
- executable today  

------------------------------------------------------------

# 19) TECHNICAL ASSUMPTIONS

Unless proven otherwise assume:

- mixed database schema states  
- helper functions exist but are inconsistent  
- SMTP exists but needs verification  
- roles are not fully normalized  
- legacy and backup files coexist  
- development currently local

------------------------------------------------------------

# FINAL INSTRUCTION

Produce the **best practical modernization and stabilization plan** for transforming this SAMS codebase into a **stable, secure, multi-tenant school platform with reliable admin workflows, AI onboarding, chatbot assistance, and consistent cross-role UX - without rewriting the system.**

------------------------------------------------------------

OPTIONAL RULE

If missing details exist:

Infer reasonable defaults first.

Only ask clarification questions if absolutely necessary, and bundle them together.
