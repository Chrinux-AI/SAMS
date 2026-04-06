# SAMS Site-Wide UI Migration — Stitch → PHP

## 1. Context & Objective

Apply the Stitch-generated "Academic Sentinel" design system site-wide across the SAMS system. You want to completely overhaul the visual appearance of all PHP pages to use the high-end Tailwind CSS UI components in the `stitch` module.

This plan details:
- **Missing Pages**: PHP pages that *do not* have a direct 1-1 mapped Stitch template and will require us to adapt the Stitch design system manually.
- **Duplicate/Variant Templates**: Stitch templates that offer multiple visual variations for a single page, showing which ones will be used as the primary choice.
- **Mapped Pages**: The core mapping of Stitch templates to existing PHP pages.
- **Execution Strategy**: How we will safely apply this site-wide.

---

## 2. ⚠️ Unmapped Pages (Requires Custom Adaptation)

The following pages currently exist in SAMS but do not have a direct 1-1 equivalent in the Stitch files. For these pages, we will need to utilize the common components (Cards, Tables, Modals, Forms) from the new Stitch design system (`sams-core.css` + `Tailwind`) to match the new visual guidelines.

### Admin & Core System
- **Advanced Operations**: `advanced-admin.php`, `biometric-scan.php`, `cloud-storage.php`, `create-tenant.php`, `switch-tenant.php`, `pwa-management.php`, `realtime-sync.php`, `system-management.php`, `system-monitor.php`, `timetable.php`
- **Dashboards (Variants)**: `dashboard-enhanced.php`, `enhanced-super-admin-dashboard.php`, `enhanced-analytics.php`
- **Management (Miscellaneous)**: `facilities.php`, `fee-management.php`, `financial-management.php`, `id-management.php`, `manage-ids.php`, `role-management.php`, `security-center.php`, `security-logs.php`
- **Users**: `student-add.php`, `student-edit.php`, `student-view.php`, `bulk-import.php`, `students-bulk-import.php`, `unapproved-users.php`

### AI & Intelligence
- **AI Administration**: `admin/ai-center/` (all pages, e.g., `anomaly-detection.php`, `automation.php`, `security-monitor.php`, `system-health.php`)
- **Public AI**: `public-ai/assistant.php`

### Student Portal
- **Features**: `checkin.php`, `checkin-enhanced.php`, `class-registration.php`, `emergency-alerts.php`, `events.php`, `id-card.php`, `lms-portal.php`, `notifications.php`, `schedule.php`, `study-groups.php`, `team-selection.php`
- **Variants**: `dashboard-enhanced.php`, `attendance-enhanced.php`

### Teacher Portal
- **Features**: `behavior-logs.php`, `emergency-alerts.php`, `lms-sync.php`, `materials.php`, `resources.php`, `resource-library.php`, `meeting-hours.php`, `my-classes.php`, `parent-comms.php`, `team-selection.php`
- **Variants**: `dashboard-enhanced.php`

### Parent Portal
- **Features**: `book-meeting.php`, `my-meetings.php`, `children.php`, `link-children.php`, `emergency-alerts.php`, `events.php`, `lms-overview.php`, `team-selection.php`

### Specialized Roles (Components need to be adapted)
- **Accountant**: `balance-sheet.php`, `budget.php`, `expenses.php`, `income.php`, `ledger.php`, `payroll.php`, `profit-loss.php`, `tax-reports.php`
- **Bursar**: `daily-summary.php`, `defaulters.php`, `export.php`, `fee-collection.php`, `fee-structure.php`, `payment-plans.php`, `receipts.php`, `scholarships.php`
- **Librarian**: `active-loans.php`, `add-book.php`, `categories.php`, `digital-resources.php`, `fines.php`, `inventory.php`, `issue-return.php`, `overdue.php`, `reservations.php`
- **Transport**: `drivers.php`, `fuel-log.php`, `maintenance.php`, `student-allocation.php`, `trip-logs.php`
- **Forum Moderator**: `banned-users.php`, `categories.php`, `reported-posts.php`, `threads.php`, `user-warnings.php`

> **Note on Strategy**: For these unmapped pages, we will create a reusable set of UI components (e.g., standard master layout, DataTables, forms) based on the Stitch core design, and wrap the existing PHP logic with these new components.

---

## 3. 📝 Duplicate / Variant Code Pages in Stitch

Stitch provided multiple variations for major interfaces. Below are the duplicated options and the selected primary choice:

### Landing Page
| Selected Variant | Unused Duplicates |
|---|---|
| `sams_the_academic_os_landing_page` | `sams_the_educational_os_landing_page`, `platform_overview_v1`, `platform_overview_v2`, `platform_overview_v3` |

### Login Page
| Selected Variant | Unused Duplicates |
|---|---|
| `sams_secure_login_1` | `sams_secure_login_2`, `sams_secure_administrative_login` |

### Admin Dashboard
| Selected Variant | Unused Duplicates |
|---|---|
| `admin_dashboard_sams_overview_1` | `admin_dashboard_sams_overview_2`, `admin_dashboard_adaptive_overview`, `sams_admin_dashboard_overview`, `admin_overview_dashboard` |

### Student Dashboard
| Selected Variant | Unused Duplicates |
|---|---|
| `sams_student_learning_hub` | `student_portal_adaptive_learning_hub`, `student_portal_ai_enhanced_academic_hub`, `student_portal_my_learning_hub_1`, `student_portal_my_learning_hub_2` |

### Teacher Dashboard
| Selected Variant | Unused Duplicates |
|---|---|
| `sams_teacher_hub_dashboard` | `teacher_dashboard_academic_hub`, `teacher_dashboard_adaptive_hub`, `teacher_dashboard_my_classes_tasks` |

### Parent Dashboard
| Selected Variant | Unused Duplicates |
|---|---|
| `parent_portal_family_overview_1` | `parent_portal_family_overview_2` |

### Analytics / Reports
| Selected Variant | Unused Duplicates |
|---|---|
| `strategic_insights_v1` | `strategic_insights_v2`, `strategic_insights_v3` |

---

## 4. 🔗 Core Page Mapping (Direct Stitch Replacement)

These pages have a direct 1-1 Stitch component mapping and will be replaced directly with the new UI.

### Public & Auth
- `login.php` → `sams_secure_login_1`
- `register.php` → `sams_register_institution`
- `forgot-password.php` → `sams_forgot_password`
- `reset-password.php` → `sams_reset_password`
- `confirm-account.php` (and OTP flows) → `sams_confirm_account_otp`
- `index.php` (Landing) → `sams_the_academic_os_landing_page`

### Dashboards
- `admin/dashboard.php` → `admin_dashboard_sams_overview_1`
- `admin/super-admin-dashboard.php` → `super_admin_dashboard_platform_control`
- `teacher/dashboard.php` → `sams_teacher_hub_dashboard`
- `student/dashboard.php` → `sams_student_learning_hub`
- `parent/dashboard.php` → `parent_portal_family_overview_1`
- Specialized Dashboards: Accountant, Bursar, Librarian, Transport, Forum Moderator (direct mappings via respective Stitch folders).

### Management & Core Data
- `admin/users.php` → `sams_user_management_directory`
- `admin/approve-users.php` → `user_management_directory`
- `admin/classes.php` → `class_management_academic_structure`
- `admin/ai-user-creator.php` → `ai_onboarding_bulk_user_creator`
- `admin/attendance.php` → `attendance_tracker_daily_records`
- `student/assignments.php`, `teacher/assignments.php` → `student_portal_assignments_grades`, `teacher_hub_assignments_grading`
- `communication/conversations.php` → `communication_hub_conversations`
- `notices.php` / `forum/index.php` → `sams_communication_center`
- Settings Pages (`admin/settings.php`, etc) → `account_settings_user_profile`
- `admin/system-health.php`, `admin/audit-logs.php` → `system_health_audit_logs`, `audit_logger_security_forensic_hub`

---

## 5. Execution Strategy

Because we are changing the UI **site-wide**, including hundreds of custom PHP pages, we will follow this structured approach:

**Step 1: Universal Layout Foundation (Most Critical)**
1. Create a universal `master-dashboard.php` layout in `views/layouts/` (or similar) using the Stitch "Academic Sentinel Navigation Flow".
2. Inject the Tailwind CSS config and core typography (Manrope / Inter) into this universal header.
3. This guarantees that **even unmapped pages** inherit the correct fonts, sidebar, topbar, and color themes.

**Step 2: Core Authenticated & Landing Flows**
1. Migrate `login.php`, `register.php`, and password reset flows using their exact Stitch mappings.
2. Migrate `index.php` (landing page).

**Step 3: Primary Dashboards**
1. Port all primary role dashboards (Admin, Teacher, Student, Parent, Specialized).

**Step 4: Adaptation of Unmapped Pages**
1. Identify the core components inside unmapped pages (e.g., DataTables, CRUD Modals, Forms).
2. Create reusable PHP view components (or snippets) styled with the Stitch UI.
3. Batch update the unmapped PHP files to wrap their existing PHP loops and data arrays inside the new Stitch UI structure.

## Feedback Please

- Are you comfortable with proceeding with **Step 1 and Step 2** to establish the base layout and authentication flow first?
- Should we use Tailwind CSS via CDN for the migration, or do you have a build process (like `npm run dev`) that we should integrate it with?
