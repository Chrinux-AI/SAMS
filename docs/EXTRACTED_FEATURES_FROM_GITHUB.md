# Multi-Project Feature Documentation (GitHub Analysis)

This document captures the **features, roles, capabilities, and platform behaviors** found in the linked projects. UI/layout details are intentionally excluded.

## Analyzed repositories

| URL                                                      | Result                                             |
| -------------------------------------------------------- | -------------------------------------------------- |
| `https://github.com/Chrinux-AI/School_Management_System` | Not publicly readable during analysis              |
| `https://github.com/Chrinux-AI/SMS`                      | Returned `404` during analysis                     |
| `https://github.com/projectmichris-dev/EDUSYNCH`         | Returned `404` during analysis                     |
| `https://github.com/Chrinux-AI/NEW_SMS`                  | Returned `404` during analysis                     |
| `https://github.com/Chrinux-AI/SMS`                      | Duplicate of above; returned `404` during analysis |
| `https://github.com/Chrinux-AI/SAMS`                     | Publicly readable and used as the feature source   |

## Scope and method

- Only **features, roles, modules, security, data model, AI, platform ops, and architecture** are documented.
- UI, visuals, color systems, and page layouts are intentionally omitted.
- For repositories that returned `404` or no meaningful public content, this document records the analysis result instead of guessing.

## Repository analysis summary

### 1) `Chrinux-AI/SAMS`

This is the only repository that was publicly accessible and had enough documentation to extract a meaningful feature set.

#### Platform summary

- Multi-tenant PHP/MySQL school management platform.
- Role-based access control with 13+ roles.
- AI-assisted bulk user onboarding.
- OTP and biometric authentication.
- Communication, reports, analytics, system admin, and PWA support.

#### Supported roles

- `super_admin`
- `owner`
- `principal`
- `admin`
- `teacher`
- `student`
- `parent`
- `staff`
- `librarian`
- `bursar`
- `accountant`
- `transport`
- `forum_moderator`

#### Role-to-capability map

The source docs explicitly list the roles above and describe a shared school-platform feature set. The detailed responsibilities below are the implementation-oriented role map implied by the documented modules.

**Super admin**

- Platform-wide administration.
- Create and manage tenants / institutions.
- Cross-tenant monitoring and reporting.
- Global settings and governance.

**Owner**

- Institution-level oversight.
- School configuration and strategic controls.
- High-level access to operational data.

**Principal**

- Academic oversight.
- Class and student supervision.
- Monitoring of attendance, reports, and staff activity.

**Admin**

- Day-to-day system administration.
- User approvals, class creation, assignments, and records management.
- Access to operational reports and maintenance tools.

**Teacher**

- Classroom management.
- Attendance marking.
- Student and class views.
- Communication with students and parents.

**Student**

- Personal attendance and academic record visibility.
- Messaging/communication access.
- Password and account management.

**Parent**

- View attendance and grade-related information.
- Receive notices and communication from school staff.

**Staff**

- Non-teaching administrative operations.
- Access to assigned operational modules and notices.

**Librarian**

- Library-related access and record management.
- Institution-specific operational workflows.

**Bursar**

- Fee and payment-adjacent operational access.
- Financial workflow participation.

**Accountant**

- Financial record handling.
- Accounting-related reports, summaries, and controls.

**Transport**

- Transportation-related student/staff workflows.
- Operational communication and attendance-related coordination.

**Forum moderator**

- Community oversight.
- Moderation of forum-style discussions and notices.

#### Core modules

**User management**

- Add teacher accounts through OTP setup.
- Bulk import students using CSV.
- AI user creation from JSON, CSV, and key-value text.
- User listing, editing, deactivation, and approval workflows.
- Role and permission management.

**Academic and class management**

- Class CRUD.
- Teacher assignment to classes.
- Student enrollment into classes.
- Teacher class views.

**Attendance**

- Attendance capture for admin and teacher.
- Student self check-in.
- Attendance viewing for students and parents.

**Communication**

- Chat.
- Messages.
- Notices.
- Forum/community features.

**Authentication**

- Login and registration.
- OTP confirmation.
- Email verification.
- Forgot/reset password flow.
- Biometric login using WebAuthn/FIDO2.

**Reports and analytics**

- Admin analytics and reports.
- Teacher reports.
- Student/parent grade visibility.
- Health/status monitoring.

**System administration**

- Health checks.
- Audit logging.
- Backup/export operations.
- Migration scripts.
- Setup/admin bootstrap.
- Maintenance scripts and cache handling.

**PWA and offline support**

- Service worker support.
- Web app manifest.
- Offline fallback page.

#### Feature-by-feature implementation targets

Below is the feature inventory that should be preserved when re-implementing the system in a cleaner project.

**Authentication and account lifecycle**

- User registration and login.
- Email verification workflow.
- OTP-based activation.
- Password reset via email.
- Biometric registration and login.
- Logout and session expiration handling.

**User provisioning**

- Manual teacher creation.
- Student bulk import.
- AI-assisted user generation.
- User approval queue.
- Role assignment and permission enforcement.

**Academic records**

- Class CRUD.
- Enrollment management.
- Attendance capture.
- Attendance review.
- Grade/report viewing.

**Communication layer**

- Chat.
- Private or role-based messages.
- Notice board.
- Forum/community discussion area.

**Administration and maintenance**

- System health check.
- Audit logging.
- Backup/export.
- Database migration.
- Setup/bootstrap utilities.
- Cache/maintenance scripts.

**Platform services**

- Multi-tenant isolation.
- Tenant-specific branding.
- Offline/PWA support.
- Notifications support.

#### AI capabilities

- Google Forms-style intake parsing.
- Supported input formats:
  - JSON
  - CSV
  - key-value text
- Field normalization and role alias handling.
- Bulk account creation with OTP provisioning.
- AI operation logging.
- Tenant-aware AI guidance and cross-tenant comparison concepts.

#### Multi-tenant capabilities

- Tenant isolation with `tenant_id` scoping.
- Tenant resolution by subdomain, custom domain, session, and dev fallback.
- Tenant-specific branding and configuration.
- Super-admin cross-tenant visibility.
- Shared / separate / hybrid database tenancy models described in docs.

#### Security capabilities

- OTP expiry and throttling.
- CSRF protection.
- RBAC enforcement.
- Rate limiting / brute-force protection.
- Session timeout handling.
- Bcrypt password hashing.
- Activity/audit logging.
- WebAuthn biometric flow with public-key storage only on server.

#### Data model highlights

- `tenants`
- `users`
- `teachers`
- `students`
- `classes`
- `class_enrollments`
- `attendance`
- `messages`
- `account_activations`
- `google_form_submissions`
- `biometric_credentials`
- audit/activity log tables

#### Documentation-mapped files

- Auth/public entry: `login.php`, `register.php`, `confirm-account.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php`, `verify-otp.php`, `activate-account.php`
- AI onboarding: `admin/ai-user-creator.php`, `admin/ai-user-management.php`, `api/process-form-submission.php`
- Admin/academic core: `admin/teachers.php`, `admin/classes.php`, `admin/students-bulk-import.php`, `admin/users.php`, `admin/role-management.php`
- Communication: `chat.php`, `messages.php`, `notices.php`, `forum/`
- Security/biometric: `api/biometric-auth.php`
- PWA: `sw.js`, `manifest.json`, `offline.html`
- Operations: `setup-admin.php`, `migrate.php`, `verify-system.php`, `scripts/*`

### 2) `Chrinux-AI/School_Management_System`

- Repository page was not publicly extractable during analysis.
- No reliable feature list could be confirmed from the available public fetches.
- Marked as **unavailable for feature extraction**.

### 3) `Chrinux-AI/SMS`

- GitHub API returned `404` during analysis.
- Marked as **unavailable for feature extraction**.

### 4) `projectmichris-dev/EDUSYNCH`

- GitHub API returned `404` during analysis.
- Marked as **unavailable for feature extraction**.

### 5) `Chrinux-AI/NEW_SMS`

- GitHub API returned `404` during analysis.
- Marked as **unavailable for feature extraction**.

## Consolidated feature catalog

This section captures the common school-system features worth documenting for later implementation work.

### Identity and access

- Login / logout
- Registration
- Email verification
- OTP account activation
- Password reset
- Biometric authentication
- Role-based authorization

### Academic operations

- Student records
- Teacher records
- Class management
- Enrollment management
- Attendance tracking
- Grade/report viewing

### Communication

- Chat
- Messaging
- Notices / announcements
- Forum/community discussions

### Administration

- User approval
- Role management
- Settings/configuration
- Audit logs
- System health checks
- Backup/export
- Database migration

### Automation / AI

- Bulk user creation from external form data
- Parse structured and semi-structured imports
- Account provisioning with OTP
- Audit trail for AI-assisted actions

### Platform services

- Multi-tenant isolation
- Tenant-specific branding/configuration
- PWA/offline capability
- Notifications support

## Detailed build backlog for later implementation

This backlog is written so the extracted documentation can be used directly when the project is re-built or stabilized.

### Authentication track

- Standard email/password login.
- OTP confirmation/activation.
- Forgot-password reset flow.
- Email verification link handling.
- Biometric login support using WebAuthn.
- Session timeout and logout flows.

### Role management track

- Centralized role definitions.
- Permission matrix per role.
- Admin-controlled role assignment.
- Approval-based user activation.
- Tenant-aware access checks.

### Academic track

- Class creation and maintenance.
- Teacher assignment to classes.
- Student enrollment records.
- Attendance recording and review.
- Student/parent visibility into academic status.

### Communication track

- Chat endpoints.
- Message inbox/outbox.
- Notice board publishing.
- Forum moderation workflows.

### AI onboarding track

- Parse pasted data from forms or spreadsheets.
- Validate fields and normalize roles.
- Preview before account creation.
- Generate account activations and OTP messages.
- Store AI import logs for traceability.

### Operations track

- Health endpoint.
- Schema drift checking.
- Backup/export automation.
- Migration scripts.
- Cache clearing utilities.

### Multi-tenant track

- Tenant discovery.
- Tenant-specific branding.
- Tenant-scoped data access.
- Super-admin tenant switching.
- Cross-tenant analytics.

## Implementation notes

- This document is a **feature-level reference**, not a design/UI guide.
- Some capabilities are documented as roadmap or architecture goals; those should be validated before implementation.
- The inaccessible repos should be revisited later if the repository names, owners, or visibility settings change.

## Next step

If you want, I can turn this into a stricter implementation roadmap with columns like:

- Feature
- Target repository
- Role(s)
- Required database tables
- Required endpoints/pages
- Implementation priority
- Known gaps / errors
