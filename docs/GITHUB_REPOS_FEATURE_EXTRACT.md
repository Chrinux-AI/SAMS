# Extracted Features & Roles from GitHub Repositories

**Source Repositories Analyzed:**

1. Chrinux-AI/School_Management_System
2. Chrinux-AI/SMS (and duplicates)
3. projectmichris-dev/EDUSYNCH
4. Chrinux-AI/NEW_SMS

---

### 1. Unified Access Control & Role Management

The systems are partitioned using role-based access control (RBAC), ensuring that authenticated users only have access to modules pertinent to their responsibilities.

- **Dev / Super Admin / System Owner**
  - **Full System Configuration:** Manages overarching settings (academic year, term schedules, grading scales, institution profile).
  - **User Provisioning:** Approves, creates, and manages all roles (Admin, Teacher, Accountant, etc.) and handles account recovery.
  - **Audit Logging & Security:** Monitors user activity, manages backups, system updates, and database integrity.
- **Administrator (Principal / Site Admin)**
  - **Human Resources:** Manages staff profiles, department assignments, and payroll integration.
  - **Student Logistics:** Approves admissions, manages student promotions, and coordinates class/section assignments.
  - **Curriculum Mapping:** Assigns subjects to teachers, sets up timetables, and manages curriculum structures.
- **Accountant / Bursar**
  - **Fee Structure Management:** Defines fee categories (tuition, transport, boarding, extracurriculars).
  - **Invoicing & Receipting:** Generates student invoices, processes incoming payments, and tracks balances.
  - **Expense Tracking:** Manages school expenditures and generates financial reports (P&L, balance sheets, cash flow).
- **Teacher / Instructor**
  - **Academic Operations:** Enters subject marks/grades, creates lesson plans, and distributes study materials.
  - **Daily Management:** Records student attendance, assigns homework, and manages classroom discipline logs.
  - **Communication:** Interacts with parents/students via integrated messaging or notices regarding academic progression.
- **Student**
  - **Academic Dashboard:** Accesses grades, report cards (terminly/semesterly), and subject syllabuses.
  - **Interactive Learning:** Submits assignments, views timetables, reads announcements, and participates in forums or exams.
- **Parent / Guardian**
  - **Ward Monitoring:** Views academic progress, attendance records, and disciplinary events across multiple linked children.
  - **Financial Overview:** Views billing statements and payment histories securely.
- **Transport / Fleet Manager**
  - **Logistics:** Manages bus routes, driver assignments, vehicle maintenance logs, and schedules.
  - **Transport Fees:** Integrates route assignments with the accounting module for billing.
- **Librarian**
  - **Asset Management:** Manages book catalogs, serial numbers, conditions, and stock audits.
  - **Circulation:** Issues and receives books, calculates late fines, and issues clearance to graduating students.

### 2. Core Functional Modules & Workflows

#### A. Academic & Examination Management

- **Gradebook & Result Compilation:** Automatic aggregation of continuous assessments, mid-terms, and final exam grades based on customizable weighting.
- **Automated Report Cards:** Generates downloadable/printable report cards (PDFs) with cumulative GPAs, remarks, and teacher signatures.
- **Promotion Workflows:** Automated algorithmic promotion of students to the next academic level/class based on passing criteria.
- **Timetable Generation:** Scheduling grids to allocate teachers, subjects, and classrooms while avoiding overlap conflicts.

#### B. Student Information System (SIS)

- **Admission Lifecycle:** Digital onboarding forms, document uploading (birth certificates, prior transcripts), and unique ID (Admission Number) generation.
- **Attendance Tracking:** Daily or subject-specific attendance logging with automated SMS/Email notifications to parents for absentees.
- **Behavioral Tracking:** Logging of disciplinary infractions, detentions, or commendations.

#### C. Financial Management System

- **Tiered Fee Allocation:** Rules-based fee assignment based on student status (e.g., scholar vs. regular, boarding vs. day, specific transport routes).
- **Payment Gateways:** Integration endpoints for handling digital payments, standard bank transfers, and manual cash receipting.
- **Defaulter Management:** Generates lists of pending dues and places automated holds on report cards or exam access for unpaid accounts.

#### D. Human Resource & Payroll

- **Staff Profiles:** Comprehensive tracking of staff credentials, contracts, role history, and emergency contacts.
- **Leave Management:** Application/approval workflow for various leave types (sick, annual, maternity).
- **Payroll Processing:** Automated calculation of salaries accommodating bonuses, tax deductions, and unpaid leave penalties.

#### E. Assets & Infrastructure

- **Inventory:** Tracking of school assets (lab equipment, sports gear, IT hardware) with check-in/checkout capabilities.
- **Hostel/Dormitory Management:** Allocation of rooms, capacity planning, and warden assignments.

#### F. Communication & Collaboration

- **Noticeboard & Circulars:** Role-targeted global announcements (e.g., visible only to "Teachers" or "Parents").
- **Internal Messaging:** A closed-loop internal messaging system for parent-teacher or admin-staff communication without sharing personal contact strings.
- **SMS/Email Integration:** Broadcast alerts for emergencies, fee reminders, or term closings.

### 3. Systems & Architectural Capabilities

- **Multi-Tenancy / Dual Branding:** Capability to handle multiple wings (Primary vs. Secondary) under a unified overarching instance.
- **Extensible APIs:** Core routes (RESTful) mapping out the ingestion or exportation of crucial data (e.g., `GET /api/v1/students/results`, `POST /api/v1/attendance`).
- **Data Export/Import:** Features leveraging CSV/Excel parsing to bulk-upload students or batch-export financial reports.
- **Progressive Web Features:** Designed for offline resilience, caching, and cross-device responsiveness (PWA).
