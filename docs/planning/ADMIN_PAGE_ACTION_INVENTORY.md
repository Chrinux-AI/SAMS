# Admin Page Action Inventory

Last updated: 2026-04-20
Scope: first executable admin inventory for the current `attendance` project

## Why this exists

Admin is the first role we should complete end to end. The admin folder is large and mixes:

- core daily operations
- platform/super-admin operations
- legacy variants
- pages with direct DB writes
- pages with same-page POST handling
- pages that already call JSON APIs

This inventory identifies the pages that matter most first, the current interaction style, and the backend contract direction we should standardize.

## Current admin backend reality

There is not yet a dedicated admin backend module folder and endpoint family equivalent to the other newer role clusters.

Existing reusable backend/admin foundation now started:

- [AdminManager.php](C:/xampp/htdocs/attendance/backend/modules/admin/AdminManager.php)
- [dashboard.php](C:/xampp/htdocs/attendance/backend/api/admin/dashboard.php)

Existing legacy/shared APIs already used by admin pages:

- [class.php](C:/xampp/htdocs/attendance/api/class.php)
- [class-schedules.php](C:/xampp/htdocs/attendance/api/class-schedules.php)
- [delete-user.php](C:/xampp/htdocs/attendance/api/delete-user.php)
- [resend-verification.php](C:/xampp/htdocs/attendance/api/resend-verification.php)
- [biometric-quick-scan.php](C:/xampp/htdocs/attendance/api/biometric-quick-scan.php)
- [push.php](C:/xampp/htdocs/attendance/api/push.php)
- [pwa-admin.php](C:/xampp/htdocs/attendance/api/pwa-admin.php)

## Tier 1: Core operational pages

These are the first admin pages that should become fully complete and standardized.

| Page | Purpose | Current interaction style | Existing backend contract | Immediate target |
| --- | --- | --- | --- | --- |
| `dashboard.php` | school-wide operational overview | server-rendered | direct queries, now wrapped through `AdminManager` | keep as reference admin stats contract |
| `approve-users.php` | approve/reject pending users, resend verification | same-page POST + fetch | `api/resend-verification.php` plus inline approval logic | extract approval actions into `backend/api/admin/approvals.php` |
| `users.php` | admin user list, delete, bulk delete pending | list + fetch deletes | `api/delete-user.php` | consolidate into canonical admin users contract |
| `user-management.php` | super-admin platform user controls | same-page POST | inline DB writes | normalize into admin users service layer |
| `students.php` | student list and drill-down | server-rendered list | direct page queries | move summary/list queries into admin student contract |
| `student-add.php` / `student-edit.php` | create/update student | same-page POST | inline writes | canonical admin students create/update contract |
| `teachers.php` | teacher creation, deletion, class assignment | same-page POST | inline writes | canonical admin teachers contract |
| `classes.php` | class list, add/edit/delete, schedule fetch | mixed form + fetch | `api/class.php`, `api/class-schedules.php`, `ClassController` | keep, then standardize under admin class contract |
| `class-management.php` | single and bulk class creation | fetch-to-self | inline handling | merge behavior into one class contract path |
| `class-enrollment.php` | enroll/unenroll/bulk enroll | same-page POST | inline DB writes | extract into enrollment backend contract |
| `attendance.php` | mark attendance, quick biometric scan | same-page POST + fetch | `api/biometric-quick-scan.php`, inline writes | extract attendance write contract and clean page structure |
| `invites.php` | staff invite creation and invite list | same-page POST | `AdvancedSAMS::createInvite()` | wrap in canonical admin invites endpoint/service |
| `notices.php` | create/update/delete/archive/pin notices | same-page POST | direct queries | extract into admin notices contract |
| `reports.php` | generate attendance and summary reports | server-rendered GET filter | direct report queries | move to admin reporting query service |
| `settings.php` | profile/security/notifications/appearance | same-page POST | inline writes | move to admin settings/profile service |

## Tier 2: Platform and governance pages

These matter, but they come after the core operational cluster above.

| Page | Purpose | Current interaction style | Immediate target |
| --- | --- | --- | --- |
| `all-tenants.php` | tenant list and access switching | GET filter + fetch | consolidate around tenant admin contract |
| `create-tenant.php` | tenant creation | same-page POST | normalize into tenant lifecycle backend flow |
| `tenant-details.php` | tenant edit, switch, delete controls | POST + fetch | normalize into tenant detail contract |
| `role-management.php` | role definitions and editing | POST + fetch | extract into admin roles contract |
| `system-health.php` | runtime/system overview | server-rendered | unify with backend system tools |
| `audit-logs.php` | searchable logs | GET filter | move to log query service |
| `backup-export.php` | backup/export/restore scheduling | POST-heavy | move critical actions behind dedicated backup service |
| `pwa-management.php` | push/PWA admin | fetch + direct queries | keep shared APIs but formalize contract ownership |
| `cloud-storage.php` | backup/cloud operations | JS actions | formalize storage operations contract |
| `platform-analytics.php` | platform metrics | GET filter | reporting service |
| `platform-settings.php` | platform-wide config | same-page POST | platform settings service |

## Tier 3: Special, experimental, or duplicate pages

These should not be ignored, but they should not drive the first completion wave.

| Page cluster | Notes | Action |
| --- | --- | --- |
| `attendance_new.php` | appears to be an older/newer duplicate of attendance flow | decide one canonical attendance page |
| `dashboard-enhanced.php`, `super-admin-dashboard.php`, `enhanced-super-admin-dashboard.php` | overlapping dashboard variants | choose canonical dashboard per admin scope |
| `advanced-admin.php`, `advanced-sams-setup.php` | mixed control-center style pages | keep only if tied to actual workflow ownership |
| `announcements.php` vs `announcements-system.php` | likely overlapping notice/announcement surfaces | consolidate with notices strategy |
| `management.php`, `students.php`, `teachers.php`, `users.php` | overlapping people-management surfaces | define canonical page per entity |
| AI centers, cognitive/intelligence center pages | broad experimental/admin tooling surface | feature-flag or defer until core admin workflows are complete |

## Canonical backend direction for admin

Admin should follow the same backend shape we are now starting:

- `backend/modules/admin/`
- `backend/api/admin/`

Recommended first endpoint family:

- `backend/api/admin/dashboard.php`
- `backend/api/admin/approvals.php`
- `backend/api/admin/users.php`
- `backend/api/admin/students.php`
- `backend/api/admin/teachers.php`
- `backend/api/admin/classes.php`
- `backend/api/admin/enrollment.php`
- `backend/api/admin/attendance.php`
- `backend/api/admin/invites.php`
- `backend/api/admin/notices.php`
- `backend/api/admin/reports.php`
- `backend/api/admin/settings.php`

## Execution order for admin

### Wave 1

- dashboard
- approve users
- users
- students
- teachers
- classes
- class enrollment
- attendance

### Wave 2

- invites
- notices
- reports
- settings

### Wave 3

- tenant/platform/governance pages
- PWA/cloud/storage/system tooling
- duplicate and experimental pages consolidation

## Immediate implementation notes

1. Do not add more inline DB logic to admin pages.
2. New admin backend work should go into `backend/modules/admin` and `backend/api/admin`.
3. Existing shared APIs can remain temporarily, but should be treated as legacy compatibility seams.
4. Duplicate admin pages should be resolved only after the Wave 1 contracts are stable.
