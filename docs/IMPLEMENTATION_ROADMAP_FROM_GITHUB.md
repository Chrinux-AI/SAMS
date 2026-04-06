# Implementation Roadmap From GitHub Feature Analysis

This roadmap is derived from the feature extraction work across the requested repositories.
It is intentionally **UI-free** and focuses on **features, roles, workflows, data, security, and implementation priorities**.

## Source repositories reviewed

| Repository                            | Status                                             |
| ------------------------------------- | -------------------------------------------------- |
| `Chrinux-AI/School_Management_System` | Not publicly readable during analysis              |
| `Chrinux-AI/SMS`                      | Returned `404` during analysis                     |
| `projectmichris-dev/EDUSYNCH`         | Returned `404` during analysis                     |
| `Chrinux-AI/NEW_SMS`                  | Returned `404` during analysis                     |
| `Chrinux-AI/SAMS`                     | Publicly readable and used as the feature baseline |

## Roadmap purpose

The goal is to rebuild or stabilize a school management platform using the extracted feature set in a controlled sequence.
This roadmap is organized so future implementation can be done in phases rather than as a single risky rewrite.

## Companion docs

- `docs/EXTRACTED_FEATURES_FROM_GITHUB.md` — feature extraction summary across all requested repositories
- `docs/FEATURE_MATRIX_FROM_GITHUB.md` — detailed feature, role, data, and priority matrix

---

## Phase 1 — Foundation and identity

### 1. Authentication core

**Features to implement**

- Login
- Logout
- Registration
- Email verification
- Password reset
- OTP account activation
- Session timeout handling
- Biometric authentication (WebAuthn/FIDO2)

**Roles affected**

- All authenticated roles

**Dependencies**

- User table
- OTP/token storage
- Email transport
- Session handling
- CSRF protection

**Implementation notes**

- Standardize all auth flows before building role modules.
- Ensure password hashing uses bcrypt.
- Ensure OTP expiry and retry limits are enforced consistently.
- Biometric login should store only public keys server-side.

**Priority**: Critical

---

### 2. Role and permission system

**Features to implement**

- Central role definitions
- Permission matrix
- Role-based authorization checks
- Role assignment to users
- Approval-based activation for new accounts

**Roles**

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

**Dependencies**

- Users table
- Roles/permissions structure
- Middleware or guard layer

**Implementation notes**

- Make authorization reusable across all pages and APIs.
- Keep tenant checks separate from role checks.

**Priority**: Critical

---

### 3. Multi-tenant foundation

**Features to implement**

- Tenant discovery
- Tenant isolation
- Tenant-scoped data access
- Tenant-specific configuration
- Super-admin tenant switching
- Cross-tenant monitoring

**Dependencies**

- Tenants table
- Session tenant context
- Query scoping helpers
- App bootstrap hooks

**Implementation notes**

- Every data query should either include tenant scope or explicitly opt out for super-admin functionality.
- Support future expansion to shared/separate/hybrid tenancy models.

**Priority**: Critical

---

## Phase 2 — User provisioning and onboarding

### 4. Manual user creation

**Features to implement**

- Add teacher
- Add staff
- Add parent
- Add student
- Add librarian
- Add bursar
- Add accountant
- Add transport user

**Dependencies**

- User model
- Role system
- Profile tables
- Validation helpers

**Implementation notes**

- Keep creation logic consistent for all roles.
- Generate assigned IDs where needed.

**Priority**: High

---

### 5. Bulk import workflows

**Features to implement**

- Student CSV import
- Data validation
- Duplicate detection
- Failed-row error export
- Class enrollment during import

**Dependencies**

- CSV parser
- Students table
- Enrollment tables
- Error logging/export

**Implementation notes**

- Preserve failed rows in a downloadable report.
- Make the import process resumable or retry-friendly.

**Priority**: High

---

### 6. AI-assisted user creation

**Features to implement**

- Accept JSON input
- Accept CSV input
- Accept key-value input
- Normalize role aliases
- Preview parsed users
- Bulk create accounts
- Emit OTP notifications
- Log AI import activity

**Dependencies**

- Parser/normalizer
- Validation layer
- Email/OTP service
- Audit logs

**Implementation notes**

- Validate before creation.
- Keep a traceable record of all AI-generated accounts.

**Priority**: High

---

### 7. User approval workflows

**Features to implement**

- Pending registration queue
- Account approval
- Account deactivation
- Activity review

**Dependencies**

- Users table
- Approval status fields
- Admin review actions

**Implementation notes**

- Separate account creation from activation.
- Make approval operations auditable.

**Priority**: High

---

## Phase 3 — Academic structure

### 8. Class management

**Features to implement**

- Class CRUD
- Academic year support
- Grade level support
- Teacher assignment
- Class listing and filtering

**Dependencies**

- Classes table
- Teacher references
- Tenant scope

**Implementation notes**

- Treat class creation as a core dependency for most academic modules.

**Priority**: High

---

### 9. Enrollment management

**Features to implement**

- Student-to-class enrollment
- Enrollment review
- Reassignment handling
- Bulk enrollment support

**Dependencies**

- Enrollment table
- Student/class relations

**Implementation notes**

- Prevent duplicate enrollments.
- Ensure enrollment changes are logged.

**Priority**: High

---

### 10. Attendance system

**Features to implement**

- Teacher-marked attendance
- Admin-marked attendance
- Student self check-in
- Attendance review by date/class
- Parent attendance visibility
- Attendance status summaries

**Dependencies**

- Attendance table
- Student/class relations
- Role permissions

**Implementation notes**

- Standardize attendance states such as present, absent, late, excused.
- Keep attendance operations tenant-scoped.

**Priority**: High

---

### 11. Academic records and reporting

**Features to implement**

- Grade/report viewing
- Class performance summaries
- Student attendance history
- Teacher reporting access
- Parent reporting access

**Dependencies**

- Reporting queries
- Attendance/grade-related tables
- Permission checks

**Implementation notes**

- Prefer read-only reporting endpoints.
- Build around reusable reporting services.

**Priority**: Medium

---

## Phase 4 — Communication and collaboration

### 12. Notices and announcements

**Features to implement**

- Notice creation
- Notice listing
- Pinned notices
- Priority notices
- Role-targeted notices

**Dependencies**

- Notices table
- Role targeting logic

**Implementation notes**

- Use notices as a primary broadcast channel for school operations.

**Priority**: High

---

### 13. Messaging system

**Features to implement**

- Direct messages
- Role-based messaging
- Inbox/outbox handling
- Read/unread tracking

**Dependencies**

- Messages table
- Message recipients table

**Implementation notes**

- Keep message access limited by role and tenant.

**Priority**: Medium

---

### 14. Chat system

**Features to implement**

- Real-time or near-real-time chat
- Participant-based conversations
- Message history
- Moderation hooks

**Dependencies**

- Chat tables or threads model
- Messaging infrastructure

**Implementation notes**

- Can be phased after notices and direct messages.

**Priority**: Medium

---

### 15. Forum/community module

**Features to implement**

- Forum threads
- Replies
- Moderation
- Topic categorization
- Role-based oversight

**Dependencies**

- Forum tables
- Moderator permissions

**Implementation notes**

- Useful for community and school engagement, but not required for core academic operations.

**Priority**: Low to Medium

---

## Phase 5 — Operations and administration

### 16. Settings and preferences

**Features to implement**

- Profile settings
- Notification preferences
- Password changes
- Role-aware settings access
- Tenant-aware branding/configuration

**Dependencies**

- Users table
- Tenant configuration table
- Notification preference fields

**Priority**: Medium

---

### 17. Audit and monitoring

**Features to implement**

- Activity logging
- Admin audit trail
- Login history
- AI action history
- Sensitive action logging

**Dependencies**

- Activity log table
- Central logging helper

**Implementation notes**

- Every important state change should be traceable.

**Priority**: High

---

### 18. Health and maintenance utilities

**Features to implement**

- System health checks
- Schema drift detection
- Cache clearing
- Backup/export
- Database migration scripts
- Setup/bootstrap tooling

**Dependencies**

- Database access
- Script execution environment
- Admin-level permissions

**Priority**: Medium

---

## Phase 6 — Platform services

### 19. PWA and offline support

**Features to implement**

- Service worker
- Manifest
- Offline fallback page
- Installable app behavior
- Cached assets strategy

**Dependencies**

- Frontend static asset strategy
- Service worker registration

**Priority**: Medium

---

### 20. Notifications

**Features to implement**

- Email notifications
- SMS notifications
- Push notifications
- Notification preference toggles

**Dependencies**

- Email transport
- SMS gateway or provider integration
- Push service support

**Priority**: Medium

---

## Data model checklist

These data objects were repeatedly implied by the documented features and should exist in some form during implementation.

- Tenants
- Users
- Roles / permissions
- Teacher profiles
- Student profiles
- Parent profiles
- Staff profiles
- Class records
- Enrollment records
- Attendance records
- Messages
- Notices
- Forum posts / replies
- OTP / activation tokens
- Biometric credentials
- Activity logs
- AI import logs
- Notification preferences

---

## Role coverage checklist

Use this as a quick validation list when implementing permissions.

- [ ] Super admin
- [ ] Owner
- [ ] Principal
- [ ] Admin
- [ ] Teacher
- [ ] Student
- [ ] Parent
- [ ] Staff
- [ ] Librarian
- [ ] Bursar
- [ ] Accountant
- [ ] Transport
- [ ] Forum moderator

---

## Suggested implementation order

1. Authentication and tenant isolation
2. Role and permission system
3. Manual user provisioning
4. Bulk import and AI onboarding
5. Class and enrollment management
6. Attendance capture
7. Notices and messaging
8. Reporting and monitoring
9. PWA/offline support
10. Forum/chat expansion
11. Optional enhancements and integrations

---

## Risks to address early

- Inconsistent role checks across pages
- Missing tenant scoping in queries
- Fragile relative links and asset paths
- OTP expiry / resend bugs
- Duplicate user imports
- Incomplete audit logging
- Partial notification delivery
- Biometric fallback behavior on unsupported devices

---

## Reuse guidance

When the implementation phase begins:

- keep the feature map above as the source of truth,
- add one file or module per phase,
- validate authentication and tenant scope first,
- do not couple UI work to backend feature completion,
- log all imported or AI-generated records.
