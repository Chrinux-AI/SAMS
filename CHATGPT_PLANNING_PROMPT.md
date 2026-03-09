# SAMS — Copy This Entire File to ChatGPT for Project Planning

---

## WHO I AM

I'm building a School Attendance Management System (SAMS) — a PHP/MySQL multi-tenant school management platform. I need help planning and prioritizing work to take this to production quality. I can't type as much as before, so I need you to ask me focused questions and help me plan step by step.

---

## WHAT THE PROJECT IS

**Name**: SAMS (Student Attendance Management System)
**Stack**: PHP 8.0+, MySQL/MariaDB, Apache (XAMPP on Windows), PHPMailer, Composer
**Location**: `C:\xampp\htdocs\attendance\`
**Size**: ~240 PHP files, 374 total files (excluding vendor)
**Theme**: Nature-inspired UI (light organic design, earth tones)
**Architecture**: Multi-tenant with role-based access control

---

## WHAT'S BUILT AND WORKING

### Core System (Verified Working)

- PDO database singleton (`db()` function) with query/fetch/insert/update/delete
- Role-based authentication for 13 roles (super_admin, owner, principal, admin, teacher, student, parent, staff, librarian, bursar, accountant, transport, forum_moderator)
- Session-based login/logout with role-appropriate dashboard routing
- CSRF protection on forms
- Activity logging via `log_activity()`

### User Management (Verified — 59 integration tests pass)

- **Add teacher**: Admin form → creates user (is_active=0) + teacher profile + OTP token → sends email → teacher confirms OTP → sets password → account active
- **Bulk student import**: CSV upload → creates users (is_active=0) + student profiles + class enrollments + OTP tokens → sends emails
- **AI user creation**: Paste Google Forms data (JSON/CSV/key-value) → AI parses with 40+ field mappings → creates accounts with OTP → sends emails
- **OTP confirmation**: User clicks email link → enters 6-digit OTP → sets password (8+ chars, mixed case + number) → account activated

### Class Management (Working)

- Create/edit/delete classes with teacher assignment
- Class enrollment for students (class_enrollments table)
- Dynamic column detection (class_teacher_id vs teacher_id)

### Communication (Built)

- WhatsApp-style chat with threading, reactions, read receipts
- Role-based messaging system
- Contact management with favorites
- Community forum "The Quad"

### Other Features (Built)

- Progressive Web App (service worker, offline support, manifest)
- Multi-tenant architecture (tenant_id isolation, super admin dashboard)
- AI chatbot assistant
- Attendance tracking
- Grade/report viewing
- Fee management, library, transport dashboards (basic)

---

## DATABASE SCHEMA (KEY TABLES)

```
users: id, email, password, full_name, first_name, last_name, role (enum 13 values),
       is_active, phone, address, status, email_verified, approved, assigned_id,
       verification_token (VARCHAR 255), token_expiry (DATETIME), password_set_at

students: id, user_id, admission_number, roll_number, class_id, date_of_birth,
          gender, blood_group, admission_date, parent_id, assigned_student_id

teachers: id, user_id, employee_id, qualification, specialization,
          experience_years, date_joined, subjects_handled

classes: id, class_name, grade_level, section, capacity, current_students,
         class_teacher_id, room_number, academic_year, is_active

class_enrollments: id, class_id, student_id, enrolled_at, status, enrolled_by
google_form_submissions: id, form_response_id, raw_data (JSON), extracted_data (JSON),
                         processing_status, created_user_id
account_activations: id, user_id, activation_method, activated_at, ip_address
tenants: id, subdomain, name, settings, created_at
```

---

## PROJECT STRUCTURE (AFTER CLEANUP)

```
attendance/
├── Root: 16 PHP files (login, register, confirm-account, migrate, etc.)
├── admin/ .............. 62 PHP files (dashboards, CRUD, AI tools)
├── student/ ............ 26 PHP files (portal pages)
├── teacher/ ............ 19 PHP files (portal pages)
├── parent/ ............. 15 PHP files (portal pages)
├── api/ ................ 27 PHP files (REST endpoints)
├── includes/ ........... 29 PHP files (shared components)
├── assets/ ............. CSS, JS, images, fonts, icons
├── database/ ........... SQL schemas + migrations/ + setup/
├── docs/ ............... 10 focused documentation files
├── scripts/
│   ├── setup/ ......... Setup scripts, migration SQL, test files
│   ├── utilities/ ..... Cache clearing, dev logins, link checker
│   ├── fixes/ ......... Historical fix scripts (archived)
│   ├── generators/ .... Page/icon generators (archived)
│   └── theme-conversion/ Theme switch scripts (archived)
├── backups/ ............ 20 old/backup PHP files (archived)
├── tests/ .............. PHPUnit structure (e2e, integration, unit)
├── forum/ .............. 4 PHP files
├── chatbot/ ............ Chatbot docs
├── accountant/, bursar/, librarian/, transport/ ... Single dashboard each
└── vendor/ ............. Composer dependencies
```

---

## WHAT'S NOT DONE / NEEDS WORK

### Known Issues

1. **Inconsistent pages**: Some role pages (accountant, bursar, librarian, transport) only have a dashboard.php with no real functionality
2. **Schema drift**: Not all PHP files match the current database schema perfectly
3. **Test coverage**: Only 1 integration test file exists (59 tests), no unit tests
4. **API documentation**: docs/api/ folder is empty
5. **Error handling**: Inconsistent across pages — some have try/catch, some don't
6. **Grade levels**: Changed from 1-12 to 100-500 system, may not be consistent everywhere
7. **Multi-tenant isolation**: Partially implemented — not all queries filter by tenant_id
8. **Attendance feature**: The core attendance marking/tracking needs thorough testing
9. **Reporting**: Report pages exist but may not have complete data queries
10. **Mobile responsiveness**: Most pages are responsive but haven't been thoroughly tested

### Never Built

- Actual Google Forms webhook integration (currently manual paste only)
- Real-time notifications (push notification infrastructure exists but not connected)
- Payment/fee processing integration
- Automated scheduled tasks (cron jobs)
- Proper CI/CD pipeline
- Docker deployment setup
- Comprehensive test suite

---

## TECH DETAILS FOR CONTEXT

### Email

- PHPMailer via Gmail SMTP
- `send_account_otp_email($email, $name, $otp, $assigned_id, $role)` in includes/email-helper.php
- OTP format: `CONFIRM:XXXXXX:unix_timestamp` stored in verification_token column
- Token expiry: 15 minutes, stored in token_expiry column

### Key Helper Functions

- `db()` — PDO singleton
- `build_user_payload()` — Builds user insert data (IMPORTANT: must override is_active=0 after)
- `insert_flexible($table, $data)` — Safe INSERT with column filtering
- `update_flexible($table, $data, $where)` — Safe UPDATE with column filtering
- `filter_data_for_table($table, $data)` — Strips non-existent columns
- `table_has_column($table, $col)` — Runtime column check
- `current_tenant_id()` — Get active tenant from session
- `attach_user_to_tenant($userId)` — Link user to tenant
- `log_activity($data)` — Audit logging

### Config

- DB: localhost, root, no password, database "attendance_system"
- APP_URL defined in includes/config.php
- Session-based auth, no JWT

---

## WHAT I NEED FROM YOU (ChatGPT)

1. **Review this brief** and ask me clarifying questions about priorities
2. **Help me create a prioritized task list** — what should I work on next?
3. **Consider these priorities**:
   - The core admin workflows MUST be bulletproof (add teachers, bulk students, classes)
   - The OTP onboarding flow MUST be reliable
   - The student/teacher/parent portals should work well
   - Multi-tenant can wait — single-school mode is fine for now
   - The attendance feature (the project's namesake) should actually work properly
   - I want to deploy this to a real server eventually
4. **Give me actionable tasks**, not vague suggestions
5. **Keep responses concise** — I'll tell you when I need more detail
6. **Track what's done** as we go through tasks together

---

## DOCUMENTATION AVAILABLE

If you need details on any area, ask me and I can paste from:

- `docs/ARCHITECTURE.md` — Database schema, auth flow, directory structure
- `docs/FEATURES.md` — Feature reference with file locations
- `docs/CHANGELOG.md` — All changes by date
- `docs/THEME_AND_UI.md` — UI design system
- `docs/MULTI_TENANT_PLATFORM_GUIDE.md` — Multi-tenant details
- `docs/PROJECT_PLANNING_MASTER_BRIEF.md` — Detailed 6-phase roadmap

---

_Generated: March 9, 2026_
