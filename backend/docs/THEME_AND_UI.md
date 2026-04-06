# SAMS UI & Theme Guide

## Current Theme: Nature

The system uses a nature-inspired design with earth tones, organic shapes, and clean typography.

### Color Palette

| Token          | Value     | Usage                    |
| -------------- | --------- | ------------------------ |
| Primary        | `#2d5016` | Headers, primary buttons |
| Secondary      | `#4a7c59` | Hover states, accents    |
| Background     | `#f5f0e8` | Page background          |
| Surface        | `#ffffff` | Cards, panels            |
| Text Primary   | `#2c3e50` | Body text                |
| Text Secondary | `#6b7c6e` | Muted text               |
| Success        | `#27ae60` | Positive actions         |
| Warning        | `#f39c12` | Alerts                   |
| Danger         | `#e74c3c` | Destructive actions      |
| Info           | `#3498db` | Informational            |

### Typography

- **Headings**: 'Playfair Display', serif
- **Body**: 'Inter', sans-serif
- **Monospace**: 'Fira Code', monospace

### CSS Files

| File                               | Purpose               | Size       |
| ---------------------------------- | --------------------- | ---------- |
| `assets/css/nature-theme.css`      | Main theme stylesheet | ~825 lines |
| `assets/css/nature-components.css` | Component library     | ~850 lines |

### Components

Cards, tables, buttons, forms, modals, badges, alerts, navigation, and sidebar are all styled via CSS classes. See the CSS files for exact class names.

---

## Page Structure by Role

### Public Pages

- `index.php` — Landing page
- `login.php` — Authentication
- `register.php` — New user registration
- `forgot-password.php` — Password recovery
- `reset-password.php` — Password reset
- `confirm-account.php` — OTP verification

### Admin (admin/)

Dashboard, students, teachers, classes, attendance, reports, analytics, announcements, events, settings, users, timetable, communication, facilities, fee management, library, transport, security logs, audit logs, system health, AI user management

### Student (student/)

Dashboard, attendance, assignments, grades, schedule, events, messages, notifications, profile, settings, study groups, LMS portal, ID card

### Teacher (teacher/)

Dashboard, attendance, assignments, grades, classes, materials, reports, messages, settings, parent communications, meeting hours, resource library

### Parent (parent/)

Dashboard, children, attendance, grades, fees, events, communication, reports, settings, book meetings

### Other Roles

Accountant, bursar, librarian, transport — each has a dashboard at `{role}/dashboard.php`

---

## Previous Theme: Cyberpunk (Archived)

The original cyberpunk theme used dark backgrounds, neon effects, and futuristic typography. It was replaced by the nature theme in November 2025. The cyberpunk CSS files remain in `assets/css/` for reference but are no longer active. Theme conversion scripts are in `scripts/theme-conversion/`.

---

## Accessibility

- WCAG 2.1 AA compliant color contrast
- Semantic HTML5 elements
- ARIA labels on interactive elements
- Keyboard navigation support
- Focus indicators on all interactive elements
- Responsive: mobile-first, tested down to 320px
