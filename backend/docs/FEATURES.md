# SAMS Feature Reference

A quick reference for all major features and where to find them.

---

## User Management

| Feature | File(s) | Notes |
|---------|---------|-------|
| Add teacher | `admin/teachers.php` | OTP flow, creates user + teacher profile |
| Bulk import students | `admin/students-bulk-import.php` | CSV upload, OTP emails, class enrollment |
| AI user creation | `admin/ai-user-creator.php` + `admin/ai-user-management.php` | Paste JSON/CSV/KV, preview, create |
| Manage users | `admin/users.php` | List, edit, deactivate users |
| Approve users | `admin/approve-users.php` | Approve pending registrations |
| Role management | `admin/role-management.php` | Manage role permissions |

## Class Management

| Feature | File(s) | Notes |
|---------|---------|-------|
| CRUD classes | `admin/classes.php` | Create/edit/delete, teacher assignment |
| Class enrollment | `admin/class-enrollment.php` | Enroll students in classes |
| Student classes | `teacher/my-classes.php` | Teacher view of their classes |

## Attendance

| Feature | File(s) | Notes |
|---------|---------|-------|
| Take attendance | `admin/attendance.php`, `teacher/attendance.php` | Mark present/absent/late |
| Student check-in | `student/checkin.php` | Self check-in (enhanced) |
| View attendance | `student/attendance.php`, `parent/attendance.php` | View records by date |

## Communication

| Feature | File(s) | Notes |
|---------|---------|-------|
| Chat | `chat.php`, `api/chat.php` | WhatsApp-style messaging |
| Messages | `messages.php`, `api/messages.php` | Role-based messaging |
| Notices | `notices.php` | Notice board |
| Forum | `forum/` | Community forum "The Quad" |

## AI Features

| Feature | File(s) | Notes |
|---------|---------|-------|
| AI chatbot | `includes/sams-ai-chatbot.php` | Context-aware assistant |
| AI user creator | `admin/ai-user-creator.php` | Google Forms parser |
| AI chat handler | `includes/ai-chat-handler.php` | API endpoint for chat |

## Authentication

| Feature | File(s) | Notes |
|---------|---------|-------|
| Login | `login.php` | Email + password |
| Register | `register.php` | Self-registration (role-restricted) |
| OTP confirm | `confirm-account.php` | Email OTP → set password |
| Password reset | `forgot-password.php` → `reset-password.php` | Email-based reset |
| Email verify | `verify-email.php` | Email verification |
| Biometric | `api/biometric-auth.php` | WebAuthn support |

## Reports & Analytics

| Feature | File(s) | Notes |
|---------|---------|-------|
| Admin reports | `admin/reports.php`, `admin/analytics.php` | System-wide analytics |
| Teacher reports | `teacher/reports.php` | Class-level reports |
| Student grades | `student/grades.php`, `parent/grades.php` | Grade viewing |

## System Administration

| Feature | File(s) | Notes |
|---------|---------|-------|
| System health | `admin/system-health.php`, `verify-system.php` | Health checks |
| Audit logs | `admin/audit-logs.php` | Activity trail |
| Backup/export | `admin/backup-export.php` | Database export |
| Settings | `admin/settings.php` | System configuration |
| Cache management | `scripts/utilities/clear_cache.php` | Clear app cache |
| Database migration | `migrate.php` | Schema updates |
| First-time setup | `setup-admin.php` | Create initial admin |

## PWA

| Feature | File(s) | Notes |
|---------|---------|-------|
| Service worker | `sw.js` | Offline support, caching |
| Manifest | `manifest.json` | App metadata, icons |
| Offline page | `offline.html` | Fallback when offline |
