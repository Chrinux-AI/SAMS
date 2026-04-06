# SAMS Architecture & Technical Reference

## System Overview

**Student Attendance Management System (SAMS)** is a multi-tenant school management platform built with PHP/MySQL. It supports 13+ user roles, AI-powered user creation, OTP-based authentication, and progressive web app features.

**Stack**: PHP 8.0+ · MySQL/MariaDB · Apache · PHPMailer · Composer · PWA (Service Worker)

---

## Database Architecture

### Core Tables
| Table | Purpose |
|-------|---------|
| `users` | All user accounts (13+ roles), authentication, OTP tokens |
| `students` | Student profiles linked to users via `user_id` |
| `teachers` | Teacher profiles linked to users via `user_id` |
| `classes` | Class definitions with teacher assignment |
| `class_enrollments` | Student-to-class enrollment mapping |
| `attendance` | Daily attendance records |
| `messages` | Role-based messaging system |

### AI & Security Tables
| Table | Purpose |
|-------|---------|
| `google_form_submissions` | AI user creation audit trail |
| `account_activations` | OTP confirmation logs |
| `biometric_credentials` | WebAuthn authentication data |
| `activity_logs` | System-wide audit trail |
| `tenants` | Multi-tenant school instances |

### Key Columns (users table)
- `verification_token` — Stores `CONFIRM:XXXXXX:timestamp` for OTP flow
- `token_expiry` — DateTime expiry for OTP tokens
- `assigned_id` — System-generated ID (TCH-2026-001, STD-2026-001, etc.)
- `password` — Hashed password (set during OTP confirmation)
- `password_set_at` — Timestamp of password creation
- `is_active` — 0 until OTP confirmed, then 1
- `role` — Enum: super_admin, owner, principal, admin, teacher, student, parent, staff, librarian, bursar, accountant, transport, forum_moderator

---

## Directory Structure

```
attendance/
├── index.php, login.php, register.php    # Public entry points
├── confirm-account.php                    # OTP verification & password creation
├── setup-admin.php                        # First-time admin setup
├── migrate.php                            # Database schema migrations
├── admin/                                 # Admin portal (60+ pages)
│   ├── dashboard.php                      # Main admin dashboard
│   ├── teachers.php                       # Add teachers (OTP flow)
│   ├── students-bulk-import.php           # CSV bulk import (OTP flow)
│   ├── classes.php                        # Class CRUD
│   ├── ai-user-creator.php                # AI user creation API
│   └── ai-user-management.php             # AI user creation dashboard
├── student/                               # Student portal
├── teacher/                               # Teacher portal
├── parent/                                # Parent portal
├── api/                                   # REST API endpoints
├── includes/                              # Shared PHP components
│   ├── config.php                         # App configuration
│   ├── database.php                       # PDO singleton (db() function)
│   ├── functions.php                      # Helper functions
│   ├── email-helper.php                   # PHPMailer + send_account_otp_email()
│   ├── sidebar-nav.php                    # Unified navigation
│   └── multi-tenant-init.php              # Tenant initialization
├── assets/                                # CSS, JS, images, fonts, icons
├── database/                              # SQL schemas & migrations
├── docs/                                  # Documentation
├── scripts/                               # Utility scripts (organized by type)
├── config/                                # Environment configuration
├── tests/                                 # PHPUnit test suite
└── vendor/                                # Composer dependencies
```

---

## Authentication Flow

### OTP-Based Account Creation
1. Admin creates user → `is_active=0`, generates OTP token (`CONFIRM:XXXXXX:timestamp`)
2. System sends email via PHPMailer with OTP code and confirmation link
3. User visits `confirm-account.php?email=xxx`, enters OTP
4. System validates OTP + expiry → user sets password → `is_active=1`

### Key Functions
- `build_user_payload()` — Builds user insert array (note: must override `is_active=0` after call)
- `insert_flexible()` — Safe INSERT with column filtering
- `update_flexible()` — Safe UPDATE with column filtering
- `filter_data_for_table()` — Strips columns that don't exist in target table
- `send_account_otp_email()` — Sends styled OTP email with PHPMailer
- `db()` — PDO singleton with `query()`, `fetch()`, `insert()`, `update()`, `delete()`

---

## Multi-Tenant Architecture

- Tenant isolation via `tenant_id` column across tables
- `current_tenant_id()` returns active tenant from session
- `attach_user_to_tenant()` links new users to their school
- Super admin dashboard for cross-tenant management
- Each tenant can have its own branding, settings, and data

---

## AI User Creation System

### Supported Input Formats
- **JSON** — Google Forms export
- **CSV** — Comma-separated values with headers
- **Key-Value** — Plain text `Name: John, Email: john@...`

### Role Aliasing
`instructor` → teacher, `learner`/`pupil` → student, `guardian` → parent, `Position` field → role

### Flow
1. Admin pastes data into AI User Management dashboard
2. `ai-user-creator.php` API parses → normalizes → validates
3. Creates accounts with OTP tokens + sends emails
4. Logs to `google_form_submissions` table

---

## Security

- OTP tokens expire in 15 minutes
- Rate limiting on OTP requests (5/hour)
- Passwords: 8+ chars, mixed case + number required
- CSRF protection on all forms
- Session-based authentication with timeout
- Role-based access control (RBAC) on every page
- Activity logging via `log_activity()`
- All passwords hashed with `password_hash()`
