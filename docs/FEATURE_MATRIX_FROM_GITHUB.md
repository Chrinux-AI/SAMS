# Feature Matrix From GitHub Analysis

This document expands the extracted feature set into an implementation-oriented matrix.
It remains **UI-free** and focuses only on **features, roles, data, workflows, and delivery priority**.

## Repository coverage

| Repository                            | Status                                | Feature confidence   |
| ------------------------------------- | ------------------------------------- | -------------------- |
| `Chrinux-AI/School_Management_System` | Not publicly readable during analysis | Low / not verifiable |
| `Chrinux-AI/SMS`                      | Returned `404` during analysis        | Low / not verifiable |
| `projectmichris-dev/EDUSYNCH`         | Returned `404` during analysis        | Low / not verifiable |
| `Chrinux-AI/NEW_SMS`                  | Returned `404` during analysis        | Low / not verifiable |
| `Chrinux-AI/SAMS`                     | Publicly readable and analyzed        | High                 |

---

## 1) Platform-level matrix

| Feature group              | What it covers                                                 | Primary roles                                 | Key data objects                                    | Priority |
| -------------------------- | -------------------------------------------------------------- | --------------------------------------------- | --------------------------------------------------- | -------- |
| Identity and access        | Login, logout, register, OTP, password reset, biometric auth   | All roles                                     | users, OTP/token tables, biometric_credentials      | Critical |
| Tenancy and scoping        | Tenant resolution, scoped data access, tenant switching        | super_admin, owner, admin                     | tenants, tenant_id fields                           | Critical |
| User provisioning          | Manual add-user flows, bulk import, AI user creation, approval | admin, super_admin                            | users, teacher/student/parent profiles, import logs | High     |
| Academic operations        | Classes, enrollment, attendance, records                       | teacher, admin, principal, student, parent    | classes, class_enrollments, attendance              | High     |
| Communication              | Chat, messages, notices, forum                                 | all role groups, moderator roles              | messages, notices, forum posts                      | High     |
| Reporting and analytics    | Academic, operational, and system reports                      | admin, principal, teacher, accountant, bursar | reporting queries, activity logs                    | Medium   |
| Operations and maintenance | Health checks, backup/export, migration, cache                 | admin, super_admin                            | logs, schema tables, backup artifacts               | Medium   |
| PWA/offline services       | Offline fallback, app installation, caching                    | all users                                     | sw.js, manifest, cached assets                      | Medium   |

---

## 2) Role-to-feature matrix

| Role              | Main responsibilities                                       | Allowed feature groups                                  |
| ----------------- | ----------------------------------------------------------- | ------------------------------------------------------- |
| `super_admin`     | Platform-wide governance, tenant control, global monitoring | Tenancy, user management, system admin, reporting       |
| `owner`           | Institution-level oversight and configuration               | Tenancy, admin settings, reporting                      |
| `principal`       | Academic leadership and monitoring                          | Academic, attendance, reporting, communication          |
| `admin`           | Daily operations and user administration                    | User provisioning, academic, communication, maintenance |
| `teacher`         | Classroom execution and student interaction                 | Attendance, class management, messaging, reporting      |
| `student`         | Personal academic access and communication                  | Attendance view, messages, account management           |
| `parent`          | Guardian visibility into student progress                   | Attendance view, grades/reports, notices, messaging     |
| `staff`           | Non-teaching institutional operations                       | Communication, notices, assigned admin workflows        |
| `librarian`       | Library-related operations                                  | Role-specific institution workflows, notices            |
| `bursar`          | Fees and payment-adjacent operations                        | Financial workflows, communication, reports             |
| `accountant`      | Accounting and financial record handling                    | Financial reports, records, summaries                   |
| `transport`       | Transportation workflow coordination                        | Role-specific operations, communication, notices        |
| `forum_moderator` | Community oversight and moderation                          | Forum, notices, moderation tools                        |

---

## 3) Detailed feature matrix

### 3.1 Authentication and account lifecycle

| Feature            | Description                        | Roles                                 | Data objects                     | Implementation targets                  | Priority |
| ------------------ | ---------------------------------- | ------------------------------------- | -------------------------------- | --------------------------------------- | -------- |
| Login              | Email/password sign-in             | All roles                             | users                            | login.php, auth service, session start  | Critical |
| Logout             | End session safely                 | All roles                             | sessions, activity logs          | logout.php, session destroy, audit log  | Critical |
| Registration       | Self registration where allowed    | Role-dependent                        | users, role assignment           | register.php, validation, status flags  | High     |
| Email verification | Verify email ownership             | New users                             | account activation records       | verify-email.php, confirmation tokens   | Critical |
| OTP activation     | Time-limited activation code flow  | New users, newly provisioned accounts | OTP/token tables                 | confirm-account.php, email helper       | Critical |
| Password reset     | Reset forgotten password via email | All roles                             | OTP/token tables, users          | forgot-password.php, reset-password.php | Critical |
| Biometric auth     | WebAuthn login/registration        | Supported devices                     | biometric_credentials, auth logs | api/biometric-auth.php, settings tabs   | High     |
| Session timeout    | Auto logout after inactivity       | All roles                             | session data                     | session middleware / guard              | Critical |

### 3.2 Tenant and access control

| Feature                | Description                                  | Roles                     | Data objects             | Implementation targets | Priority |
| ---------------------- | -------------------------------------------- | ------------------------- | ------------------------ | ---------------------- | -------- |
| Tenant discovery       | Resolve tenant from domain/subdomain/session | super_admin, owner, admin | tenants                  | bootstrap/init helpers | Critical |
| Tenant isolation       | Prevent cross-tenant data leaks              | All roles                 | tenant_id columns        | query scoping helpers  | Critical |
| Tenant-specific config | Branding and settings per institution        | super_admin, owner, admin | tenants, config settings | tenant config services | High     |
| Super-admin switching  | Jump between tenant contexts                 | super_admin               | session tenant context   | admin switch controls  | High     |
| RBAC enforcement       | Enforce role permissions per action          | All roles                 | users, permissions       | guard/middleware layer | Critical |

### 3.3 User provisioning

| Feature                 | Description                             | Roles              | Data objects                | Implementation targets         | Priority |
| ----------------------- | --------------------------------------- | ------------------ | --------------------------- | ------------------------------ | -------- |
| Manual teacher creation | Create teacher accounts and profiles    | admin, super_admin | users, teachers             | admin/teachers.php             | High     |
| Manual staff creation   | Create non-teaching staff               | admin, super_admin | users, staff profiles       | user creation workflow         | High     |
| Manual parent creation  | Create guardian accounts                | admin, super_admin | users, parent profiles      | user creation workflow         | High     |
| Manual student creation | Create learner accounts                 | admin, super_admin | users, students             | user creation workflow         | High     |
| Bulk student import     | CSV import for many students            | admin, super_admin | students, enrollments, logs | admin/students-bulk-import.php | High     |
| AI user creation        | Parse structured input and create users | admin, super_admin | users, AI logs, OTP tables  | admin/ai-user-creator.php      | High     |
| Approval queue          | Review and activate pending users       | admin, super_admin | users, approval flags       | admin/approve-users.php        | High     |
| Role assignment         | Map users to specific roles             | admin, super_admin | users, roles/permissions    | role-management pages          | High     |
| Duplicate detection     | Stop repeated imports/creates           | admin, super_admin | users, import logs          | validation utilities           | Medium   |

### 3.4 Academic structure

| Feature                 | Description                           | Roles                               | Data objects          | Implementation targets    | Priority |
| ----------------------- | ------------------------------------- | ----------------------------------- | --------------------- | ------------------------- | -------- |
| Class CRUD              | Create/update/delete class records    | admin, principal                    | classes               | admin/classes.php         | High     |
| Grade level support     | Organize classes by level             | admin, principal                    | classes               | class metadata            | High     |
| Academic year support   | Track classes by year                 | admin, principal                    | classes               | class metadata            | High     |
| Teacher assignment      | Assign teachers to classes            | admin, principal                    | classes, teachers     | class assignment workflow | High     |
| Enrollment management   | Add students to classes               | admin, teacher                      | class_enrollments     | enrollment workflow       | High     |
| Class listing/filtering | Search and filter class records       | admin, teacher, principal           | classes               | listing endpoints         | Medium   |
| Attendance capture      | Mark present/absent/late              | admin, teacher                      | attendance            | attendance pages/APIs     | High     |
| Attendance review       | View attendance by date/class/student | admin, teacher, parent, student     | attendance            | reporting endpoints       | High     |
| Student check-in        | Self check-in flow for students       | student                             | attendance            | check-in endpoint         | Medium   |
| Grade/report view       | Read academic summaries and results   | student, parent, teacher, principal | grades/report queries | reports module            | Medium   |

### 3.5 Communication

| Feature           | Description                     | Roles                                      | Data objects           | Implementation targets     | Priority |
| ----------------- | ------------------------------- | ------------------------------------------ | ---------------------- | -------------------------- | -------- |
| Chat              | Conversational messaging        | all users (role-scoped)                    | chat threads, messages | chat.php, API handlers     | High     |
| Direct messages   | Role-based private messaging    | all users                                  | messages, recipients   | messages.php, API handlers | High     |
| Notices           | Broadcast announcements         | all users, role-targeting supported        | notices                | notices.php                | High     |
| Pinned notices    | Keep critical notices visible   | admin, super_admin                         | notices                | notice metadata            | High     |
| Priority notices  | Mark urgent/high-priority posts | admin, super_admin                         | notices                | notice metadata            | High     |
| Forum discussions | Community thread discussions    | forum_moderator, students, staff, teachers | forum posts/replies    | forum/                     | Medium   |
| Moderation        | Control community behavior      | forum_moderator, admin                     | forum reports/logs     | moderation tools           | Medium   |

### 3.6 Reporting and analytics

| Feature                  | Description                           | Roles              | Data objects            | Implementation targets | Priority |
| ------------------------ | ------------------------------------- | ------------------ | ----------------------- | ---------------------- | -------- |
| Admin analytics          | Platform-wide reports and summaries   | admin, super_admin | reporting queries, logs | admin reports          | Medium   |
| Teacher reports          | Class/learner-level reports           | teacher, principal | attendance, grades      | teacher reports        | Medium   |
| Parent reports           | Guardian-facing academic visibility   | parent             | grades, attendance      | parent reports         | Medium   |
| Student reports          | Self-service progress views           | student            | grades, attendance      | student reports        | Medium   |
| Financial reports        | Accounting summaries and totals       | accountant, bursar | financial data objects  | accounting pages       | Medium   |
| Health/status monitoring | Verify system health and availability | admin, super_admin | health checks, logs     | verify-system.php      | High     |

### 3.7 Operations and maintenance

| Feature           | Description                    | Roles              | Data objects       | Implementation targets        | Priority |
| ----------------- | ------------------------------ | ------------------ | ------------------ | ----------------------------- | -------- |
| Audit logging     | Capture important actions      | admin, super_admin | activity_logs      | logging helper                | High     |
| Backup/export     | Export database or records     | admin, super_admin | backup artifacts   | export scripts                | Medium   |
| Migration scripts | Update schema safely           | super_admin, admin | schema files       | migrate.php                   | High     |
| Cache clearing    | Refresh app caches             | admin, super_admin | cache              | utility scripts               | Medium   |
| Bootstrap setup   | First-time system setup        | super_admin        | config, admin user | setup-admin.php               | High     |
| Health endpoint   | Machine-readable system status | admin, dev ops     | health data        | api/health.php style endpoint | High     |

### 3.8 PWA and offline support

| Feature            | Description                          | Roles     | Data objects                            | Implementation targets | Priority |
| ------------------ | ------------------------------------ | --------- | --------------------------------------- | ---------------------- | -------- |
| Service worker     | Offline caching and request handling | all users | cached assets                           | sw.js                  | Medium   |
| Manifest           | Installable web app metadata         | all users | app metadata                            | manifest.json          | Medium   |
| Offline fallback   | Graceful offline page                | all users | static assets                           | offline.html           | Medium   |
| Push notifications | Delivery of alerts and updates       | all users | notification preferences, push payloads | push integration       | Medium   |

### 3.9 AI and automation

| Feature                  | Description                            | Roles                   | Data objects             | Implementation targets | Priority |
| ------------------------ | -------------------------------------- | ----------------------- | ------------------------ | ---------------------- | -------- |
| JSON import parsing      | Parse structured data                  | admin, super_admin      | AI logs, import payloads | AI user creator        | High     |
| CSV import parsing       | Parse spreadsheet-like input           | admin, super_admin      | AI logs, import payloads | AI user creator        | High     |
| Key-value parsing        | Parse semi-structured text             | admin, super_admin      | AI logs, import payloads | AI user creator        | High     |
| Role alias normalization | Map variants to canonical roles        | admin, super_admin      | role mapping rules       | parser normalizer      | High     |
| OTP issuance             | Send confirmation codes after creation | all newly created users | OTP/token tables         | mail/OTP service       | Critical |
| AI audit logging         | Store AI-assisted operation traces     | admin, super_admin      | AI logs                  | logging pipeline       | High     |

---

## 4) Repository-specific implementation notes

### `Chrinux-AI/SAMS`

This repository provides the strongest documented baseline and should be treated as the canonical feature reference for the school platform.

### `Chrinux-AI/School_Management_System`

Public documentation could not be extracted reliably. Re-check repo access later.

### `Chrinux-AI/SMS`, `Chrinux-AI/NEW_SMS`, `projectmichris-dev/EDUSYNCH`

These returned `404` during analysis. They are listed here only for traceability.

---

## 5) Build sequencing recommendation

1. Authentication and tenancy
2. Roles and permissions
3. Manual provisioning
4. Bulk import and AI import
5. Classes and enrollment
6. Attendance
7. Messages and notices
8. Reports and analytics
9. System administration and audits
10. PWA/offline and notifications
11. Forum/chat expansion

---

## 6) Risk notes

- Avoid implementing UI before the data and access model are stable.
- Keep tenant scoping mandatory in all queries.
- Log all import and approval actions.
- Validate all OTP and biometric flows on the target browser/device set.
- Handle missing/invalid repository sources as unknown instead of inferred.

---

## 7) Reference docs

- `docs/EXTRACTED_FEATURES_FROM_GITHUB.md`
- `docs/IMPLEMENTATION_ROADMAP_FROM_GITHUB.md`
