# Complete School Management System Blueprint

This document defines a practical, production-grade School Management System that can be implemented from scratch or used as the target architecture for the current SAMS repository.

The goal is not to be academically perfect. The goal is to give you a clear buildable system.

## 1. System Overview

### Purpose

A School Management System centralizes daily school operations into one platform so administrators, teachers, students, parents, and finance staff can work from the same source of truth.

In simple terms:

- admins manage the school
- teachers manage classes, attendance, and marks
- students view schedules, attendance, and results
- parents monitor children and payments
- finance staff manage invoices and payments

### Target Users

#### `super_admin`

- manages multiple schools if the platform is multi-tenant
- provisions schools and platform settings

#### `school_admin`

- manages users, classes, subjects, terms, and system settings for one school

#### `principal`

- monitors academic and operational performance
- approves critical records and reports

#### `teacher`

- takes attendance
- enters scores and remarks
- views assigned classes and students

#### `student`

- views timetable, attendance, grades, invoices, and notifications

#### `parent`

- views child attendance, grades, announcements, and fee balance

#### `accountant` / `bursar`

- creates invoices
- records payments
- tracks outstanding balances

### Core Features

- school and academic session setup
- student admission and enrollment
- teacher and staff management
- class, section, subject, and timetable management
- attendance capture and reporting
- exam, assessment, and grading workflows
- fees, invoicing, discounts, and payment tracking
- role-based authentication and authorization
- email and SMS notifications
- dashboards and reports
- audit logs for sensitive actions

## 2. Assumptions Used For This Design

Because no detailed requirements were provided, these assumptions are used:

1. The system supports one or more schools, so the design is tenant-aware using `school_id`.
2. A student can have one or more parents or guardians.
3. A teacher can teach multiple subjects and multiple class sections.
4. Attendance is recorded per class session, usually once per day per subject.
5. Grading is term-based and can combine multiple assessments into a final result.
6. Fees can be generated per term, class, or custom invoice.
7. Notifications can be sent by email, SMS, and in-app messages.
8. Parents only see their linked children.
9. Admins should be able to operate even if students and teachers have limited portal access at first.
10. The preferred future backend is FastAPI, but the design can coexist with the current PHP project during migration.

## 3. Feature Breakdown

### Student Management

- admission and registration
- student profile
- guardian linking
- class enrollment history
- student status tracking: active, graduated, suspended, transferred
- medical notes and emergency contacts
- student documents upload

### Teacher Management

- teacher profile and employment data
- department assignment
- subject assignment
- class assignment
- workload summary

### Course / Class Management

- academic years and terms
- classes and sections
- subjects
- subject offerings per class section
- class teacher assignment
- timetable support

### Attendance System

- create attendance session
- mark present, absent, late, excused
- prevent duplicate marking for same session
- class attendance summary
- student attendance history
- parent alert on absence

### Result / Grading System

- assessments: test, quiz, exam, assignment
- score entry per student
- grade scale definition
- result publication by term
- report card generation

### Fees / Payment Tracking

- fee structure by class or term
- invoice generation
- payment recording
- discounts and scholarships
- outstanding balance reporting
- receipt history

### Authentication and RBAC

- login by email or username
- JWT-based API auth
- refresh tokens
- password reset
- role-based access checks
- tenant isolation by `school_id`

### Notifications

- email notifications
- SMS alerts
- in-app notifications
- event-based triggers:
  - student admitted
  - invoice created
  - payment received
  - attendance absence
  - result published

## 4. Recommended Product Scope

If you want a strong first version, build in this order:

1. authentication + RBAC
2. school setup + academic sessions
3. students + guardians + teachers
4. classes + subjects + enrollments
5. attendance
6. grading/results
7. fees and payments
8. notifications and reports

## 5. Database Design

### Design Notes

- PostgreSQL is recommended for production.
- Every core business table includes `school_id`.
- Use soft deletes only where needed. Most operational tables should prefer status flags over actual deletion.
- Use `created_at` and `updated_at` on all important tables.

### Relationship Summary

- one school has many users, students, teachers, classes, terms, invoices
- one student belongs to one school and can have many guardians
- one class section has many students and many subject offerings
- one attendance session belongs to one class section and one subject
- one assessment belongs to one subject offering and term
- one invoice belongs to one student
- one payment can settle one or more invoice items

### SQL Schema

```sql
CREATE TABLE schools (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    timezone VARCHAR(64) DEFAULT 'Africa/Lagos',
    currency_code VARCHAR(10) DEFAULT 'USD',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE academic_years (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, name)
);

CREATE TABLE terms (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    academic_year_id BIGINT NOT NULL REFERENCES academic_years(id),
    name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, academic_year_id, name)
);

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(100) UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    role_id BIGINT NOT NULL REFERENCES roles(id),
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80),
    email VARCHAR(150),
    phone VARCHAR(30),
    username VARCHAR(80),
    password_hash TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, email),
    UNIQUE (school_id, username)
);

CREATE TABLE password_reset_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE departments (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    name VARCHAR(120) NOT NULL,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, name)
);

CREATE TABLE guardians (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    user_id BIGINT REFERENCES users(id),
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    occupation VARCHAR(120),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    user_id BIGINT REFERENCES users(id),
    admission_no VARCHAR(50) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80),
    gender VARCHAR(20),
    date_of_birth DATE,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    admission_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    blood_group VARCHAR(10),
    medical_notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, admission_no)
);

CREATE TABLE student_guardians (
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    guardian_id BIGINT NOT NULL REFERENCES guardians(id) ON DELETE CASCADE,
    relationship VARCHAR(30) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (student_id, guardian_id)
);

CREATE TABLE teachers (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    user_id BIGINT REFERENCES users(id),
    employee_no VARCHAR(50) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    hire_date DATE,
    department_id BIGINT REFERENCES departments(id),
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, employee_no)
);

CREATE TABLE class_levels (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    name VARCHAR(60) NOT NULL,
    rank_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, name)
);

CREATE TABLE class_sections (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    class_level_id BIGINT NOT NULL REFERENCES class_levels(id),
    name VARCHAR(60) NOT NULL,
    class_teacher_id BIGINT REFERENCES teachers(id),
    capacity INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, class_level_id, name)
);

CREATE TABLE subjects (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    code VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, code),
    UNIQUE (school_id, name)
);

CREATE TABLE subject_offerings (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    class_section_id BIGINT NOT NULL REFERENCES class_sections(id),
    subject_id BIGINT NOT NULL REFERENCES subjects(id),
    term_id BIGINT NOT NULL REFERENCES terms(id),
    teacher_id BIGINT REFERENCES teachers(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, class_section_id, subject_id, term_id)
);

CREATE TABLE enrollments (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    student_id BIGINT NOT NULL REFERENCES students(id),
    class_section_id BIGINT NOT NULL REFERENCES class_sections(id),
    academic_year_id BIGINT NOT NULL REFERENCES academic_years(id),
    roll_number VARCHAR(30),
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, student_id, academic_year_id)
);

CREATE TABLE attendance_sessions (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    class_section_id BIGINT NOT NULL REFERENCES class_sections(id),
    subject_offering_id BIGINT REFERENCES subject_offerings(id),
    attendance_date DATE NOT NULL,
    period_label VARCHAR(50),
    recorded_by BIGINT NOT NULL REFERENCES users(id),
    remarks TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, class_section_id, subject_offering_id, attendance_date, period_label)
);

CREATE TABLE attendance_records (
    id BIGSERIAL PRIMARY KEY,
    attendance_session_id BIGINT NOT NULL REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id),
    status VARCHAR(20) NOT NULL CHECK (status IN ('present', 'absent', 'late', 'excused')),
    remarks TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (attendance_session_id, student_id)
);

CREATE TABLE assessments (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    term_id BIGINT NOT NULL REFERENCES terms(id),
    subject_offering_id BIGINT NOT NULL REFERENCES subject_offerings(id),
    title VARCHAR(120) NOT NULL,
    assessment_type VARCHAR(30) NOT NULL,
    total_marks NUMERIC(8,2) NOT NULL,
    weight_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
    assessment_date DATE,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assessment_scores (
    id BIGSERIAL PRIMARY KEY,
    assessment_id BIGINT NOT NULL REFERENCES assessments(id) ON DELETE CASCADE,
    student_id BIGINT NOT NULL REFERENCES students(id),
    score NUMERIC(8,2) NOT NULL,
    remark TEXT,
    entered_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (assessment_id, student_id)
);

CREATE TABLE grade_scales (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    name VARCHAR(80) NOT NULL,
    min_score NUMERIC(5,2) NOT NULL,
    max_score NUMERIC(5,2) NOT NULL,
    letter_grade VARCHAR(5) NOT NULL,
    remark VARCHAR(80),
    grade_point NUMERIC(4,2),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, name, min_score, max_score)
);

CREATE TABLE term_results (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    term_id BIGINT NOT NULL REFERENCES terms(id),
    student_id BIGINT NOT NULL REFERENCES students(id),
    subject_offering_id BIGINT NOT NULL REFERENCES subject_offerings(id),
    total_score NUMERIC(8,2) NOT NULL,
    average_score NUMERIC(8,2) NOT NULL,
    grade_scale_id BIGINT REFERENCES grade_scales(id),
    teacher_remark TEXT,
    principal_remark TEXT,
    published_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, term_id, student_id, subject_offering_id)
);

CREATE TABLE fee_structures (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    term_id BIGINT REFERENCES terms(id),
    class_level_id BIGINT REFERENCES class_levels(id),
    name VARCHAR(120) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fee_structure_items (
    id BIGSERIAL PRIMARY KEY,
    fee_structure_id BIGINT NOT NULL REFERENCES fee_structures(id) ON DELETE CASCADE,
    item_name VARCHAR(120) NOT NULL,
    amount NUMERIC(12,2) NOT NULL,
    is_optional BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    student_id BIGINT NOT NULL REFERENCES students(id),
    term_id BIGINT REFERENCES terms(id),
    invoice_no VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE,
    status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
    subtotal NUMERIC(12,2) NOT NULL DEFAULT 0,
    discount_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
    amount_paid NUMERIC(12,2) NOT NULL DEFAULT 0,
    balance_due NUMERIC(12,2) NOT NULL DEFAULT 0,
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, invoice_no)
);

CREATE TABLE invoice_items (
    id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_amount NUMERIC(12,2) NOT NULL,
    total_amount NUMERIC(12,2) NOT NULL
);

CREATE TABLE payments (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    student_id BIGINT NOT NULL REFERENCES students(id),
    payment_reference VARCHAR(80) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    payment_date TIMESTAMP NOT NULL,
    amount NUMERIC(12,2) NOT NULL,
    received_by BIGINT REFERENCES users(id),
    note TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (school_id, payment_reference)
);

CREATE TABLE payment_allocations (
    id BIGSERIAL PRIMARY KEY,
    payment_id BIGINT NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
    invoice_id BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    amount_applied NUMERIC(12,2) NOT NULL
);

CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT NOT NULL REFERENCES schools(id),
    user_id BIGINT REFERENCES users(id),
    channel VARCHAR(20) NOT NULL CHECK (channel IN ('email', 'sms', 'in_app')),
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    scheduled_at TIMESTAMP,
    sent_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    school_id BIGINT REFERENCES schools(id),
    user_id BIGINT REFERENCES users(id),
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

## 6. API Design

### API Style

- style: REST
- transport: JSON over HTTPS
- auth: Bearer access token
- versioning: `/api/v1`

### Standard Response Shape

```json
{
  "success": true,
  "message": "Student created successfully",
  "data": {},
  "meta": {}
}
```

### Authentication Endpoints

#### `POST /api/v1/auth/login`

Request:

```json
{
  "email": "admin@greenfieldschool.edu",
  "password": "StrongPassword123!"
}
```

Response:

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "access_token": "jwt-access-token",
    "refresh_token": "jwt-refresh-token",
    "token_type": "bearer",
    "user": {
      "id": 12,
      "school_id": 1,
      "role": "school_admin",
      "first_name": "Ada",
      "last_name": "Ibrahim"
    }
  }
}
```

#### `POST /api/v1/auth/refresh`

#### `POST /api/v1/auth/forgot-password`

#### `POST /api/v1/auth/reset-password`

#### `GET /api/v1/auth/me`

### Student Management Endpoints

#### `GET /api/v1/students`

Query params:

- `search`
- `class_section_id`
- `status`
- `page`
- `page_size`

#### `POST /api/v1/students`

Request:

```json
{
  "admission_no": "ADM-2026-0012",
  "first_name": "David",
  "last_name": "Okoro",
  "gender": "male",
  "date_of_birth": "2012-05-11",
  "admission_date": "2026-04-24",
  "class_section_id": 4,
  "guardian": {
    "first_name": "Grace",
    "last_name": "Okoro",
    "email": "grace@example.com",
    "phone": "+15551230000",
    "relationship": "mother"
  }
}
```

Response:

```json
{
  "success": true,
  "message": "Student created successfully",
  "data": {
    "id": 101,
    "admission_no": "ADM-2026-0012",
    "full_name": "David Okoro",
    "class_section_id": 4,
    "status": "active"
  }
}
```

#### `GET /api/v1/students/{student_id}`

#### `PUT /api/v1/students/{student_id}`

#### `POST /api/v1/students/{student_id}/guardians`

#### `GET /api/v1/students/{student_id}/attendance`

#### `GET /api/v1/students/{student_id}/results`

#### `GET /api/v1/students/{student_id}/invoices`

### Teacher Management Endpoints

#### `GET /api/v1/teachers`

#### `POST /api/v1/teachers`

#### `GET /api/v1/teachers/{teacher_id}`

#### `PUT /api/v1/teachers/{teacher_id}`

#### `POST /api/v1/teachers/{teacher_id}/assignments`

Request:

```json
{
  "subject_offering_ids": [12, 13, 14]
}
```

### Class / Subject Endpoints

#### `GET /api/v1/class-levels`

#### `POST /api/v1/class-levels`

#### `GET /api/v1/class-sections`

#### `POST /api/v1/class-sections`

#### `GET /api/v1/subjects`

#### `POST /api/v1/subjects`

#### `POST /api/v1/subject-offerings`

Request:

```json
{
  "class_section_id": 4,
  "subject_id": 7,
  "term_id": 3,
  "teacher_id": 22
}
```

#### `POST /api/v1/enrollments`

Request:

```json
{
  "student_id": 101,
  "class_section_id": 4,
  "academic_year_id": 2,
  "roll_number": "14"
}
```

### Attendance Endpoints

#### `POST /api/v1/attendance/sessions`

Request:

```json
{
  "class_section_id": 4,
  "subject_offering_id": 12,
  "attendance_date": "2026-04-24",
  "period_label": "Period 1",
  "remarks": "Morning Mathematics"
}
```

Response:

```json
{
  "success": true,
  "message": "Attendance session created",
  "data": {
    "id": 501,
    "attendance_date": "2026-04-24"
  }
}
```

#### `POST /api/v1/attendance/sessions/{session_id}/records`

Request:

```json
{
  "records": [
    { "student_id": 101, "status": "present" },
    { "student_id": 102, "status": "late" },
    { "student_id": 103, "status": "absent", "remarks": "Sick leave" }
  ]
}
```

#### `GET /api/v1/attendance/sessions/{session_id}`

#### `GET /api/v1/attendance/reports/class/{class_section_id}`

Query params:

- `from_date`
- `to_date`

### Results / Grading Endpoints

#### `POST /api/v1/assessments`

Request:

```json
{
  "term_id": 3,
  "subject_offering_id": 12,
  "title": "Midterm Test",
  "assessment_type": "test",
  "total_marks": 40,
  "weight_percent": 20,
  "assessment_date": "2026-05-10"
}
```

#### `POST /api/v1/assessments/{assessment_id}/scores`

Request:

```json
{
  "scores": [
    { "student_id": 101, "score": 33 },
    { "student_id": 102, "score": 28 }
  ]
}
```

#### `POST /api/v1/results/compute`

Request:

```json
{
  "term_id": 3,
  "class_section_id": 4
}
```

#### `GET /api/v1/results/students/{student_id}?term_id=3`

#### `POST /api/v1/results/publish`

### Fees / Payment Endpoints

#### `POST /api/v1/fee-structures`

#### `POST /api/v1/invoices/generate`

Request:

```json
{
  "student_ids": [101, 102, 103],
  "term_id": 3,
  "fee_structure_id": 9,
  "due_date": "2026-05-31"
}
```

#### `GET /api/v1/invoices`

#### `GET /api/v1/invoices/{invoice_id}`

#### `POST /api/v1/payments`

Request:

```json
{
  "student_id": 101,
  "payment_reference": "PAY-2026-0001",
  "payment_method": "cash",
  "payment_date": "2026-04-24T10:00:00",
  "amount": 150.00,
  "invoice_allocations": [
    { "invoice_id": 701, "amount_applied": 150.00 }
  ]
}
```

### Notifications Endpoints

#### `POST /api/v1/notifications`

Request:

```json
{
  "user_id": 88,
  "channel": "email",
  "title": "Attendance Alert",
  "message": "Your child was marked absent today."
}
```

#### `POST /api/v1/notifications/bulk`

#### `GET /api/v1/notifications`

#### `PATCH /api/v1/notifications/{notification_id}/read`

### Admin / Reporting Endpoints

#### `GET /api/v1/dashboard/admin`

#### `GET /api/v1/reports/attendance-summary`

#### `GET /api/v1/reports/result-summary`

#### `GET /api/v1/reports/finance-summary`

#### `GET /api/v1/audit-logs`

## 7. Backend Architecture

### Recommended Stack

Preferred stack:

- Python 3.12
- FastAPI
- SQLAlchemy 2.x
- Alembic for migrations
- PostgreSQL
- Redis for caching and background queues
- Celery or RQ for background jobs
- Pydantic for request and response validation
- PyJWT or `python-jose` for tokens
- `passlib` for password hashing
- pytest for testing

Why this stack is practical:

- FastAPI gives clean API structure and automatic docs
- SQLAlchemy handles relational data well
- PostgreSQL is strong for reporting and transactional data
- Redis helps with OTPs, caching, and queued notifications

### Suggested Folder Structure

```text
backend/
  app/
    main.py
    core/
      config.py
      security.py
      database.py
      exceptions.py
    models/
      school.py
      user.py
      student.py
      teacher.py
      academics.py
      attendance.py
      grading.py
      finance.py
      notification.py
    schemas/
      auth.py
      student.py
      teacher.py
      academics.py
      attendance.py
      grading.py
      finance.py
      notification.py
    api/
      deps.py
      v1/
        router.py
        auth.py
        students.py
        teachers.py
        classes.py
        attendance.py
        results.py
        finance.py
        notifications.py
        reports.py
    services/
      auth_service.py
      student_service.py
      enrollment_service.py
      attendance_service.py
      grading_service.py
      invoice_service.py
      payment_service.py
      notification_service.py
    repositories/
      base.py
      student_repo.py
      teacher_repo.py
      attendance_repo.py
    workers/
      email_tasks.py
      sms_tasks.py
      report_tasks.py
    tests/
      test_auth.py
      test_students.py
      test_attendance.py
      test_results.py
  alembic/
  requirements.txt
  .env
```

### Key Service Responsibilities

#### `auth_service`

- validates credentials
- issues access and refresh tokens
- handles reset tokens

#### `student_service`

- creates student record
- optionally creates portal user
- links guardians
- triggers welcome notifications

#### `enrollment_service`

- assigns student to class section and academic year
- enforces one active enrollment per year

#### `attendance_service`

- creates attendance session
- validates teacher access to subject offering
- stores attendance records
- triggers absence alerts

#### `grading_service`

- stores assessments and scores
- computes weighted totals
- maps score to grade scale
- publishes results

#### `invoice_service`

- creates invoices from fee structures
- recalculates totals and balances

#### `payment_service`

- records payment
- allocates payment to invoices
- updates invoice balance and status

### Logic Flow Example: Attendance

1. teacher logs in
2. teacher opens assigned class and subject
3. frontend calls `POST /attendance/sessions`
4. backend verifies teacher owns that subject offering
5. backend creates one session for that date and period
6. frontend submits student statuses
7. backend stores `attendance_records`
8. backend may queue notifications for absentees

## 8. Frontend Structure

### Recommended Frontend Stack

Preferred option:

- React
- TypeScript
- Vite
- React Router
- TanStack Query
- React Hook Form
- Tailwind CSS or a simple component system

If you want a simpler first step:

- server-rendered PHP pages can remain for now
- new modules can call the FastAPI backend
- gradually migrate high-value pages to React

### Main Pages

#### Public

- login
- forgot password
- reset password

#### Admin Portal

- dashboard
- students list
- add student
- teachers list
- classes and sections
- subjects and assignments
- attendance reports
- assessments and results
- invoices and payments
- settings

#### Teacher Portal

- dashboard
- my classes
- take attendance
- enter scores
- class report

#### Student Portal

- dashboard
- my profile
- timetable
- attendance history
- results
- invoices
- notifications

#### Parent Portal

- dashboard
- my children
- child attendance
- child results
- fee balance
- announcements

### Suggested Frontend Structure

```text
frontend/
  src/
    app/
      router.tsx
      providers.tsx
      layouts/
    modules/
      auth/
      admin/
      students/
      teachers/
      classes/
      attendance/
      results/
      finance/
      parent/
      student/
    shared/
      api/
      components/
      hooks/
      utils/
      types/
    pages/
      LoginPage.tsx
      DashboardPage.tsx
    styles/
  public/
```

### UI Rules

- keep forms short and validated
- always show current school, term, and user role
- use tables for operational screens
- use cards for summary screens
- make attendance entry very fast on mobile and desktop

## 9. Authentication Flow

### Login

1. user submits email and password
2. backend validates credentials
3. backend returns access token and refresh token
4. frontend stores access token safely
5. frontend loads `/auth/me`
6. user is redirected based on role

### Registration

Recommended rule:

- users are usually created by admin
- self-registration should only be allowed for limited roles and should require school approval

Practical approach:

- admin creates student, teacher, or parent
- system sends activation email
- user sets password
- account becomes active

### Password Reset

1. user requests reset
2. backend creates reset token
3. email is sent with reset link
4. user submits new password
5. token is marked used

### Role Handling

Roles should be enforced at two levels:

1. frontend route guards for user experience
2. backend permission checks for actual security

Example:

- teacher can open attendance page only for assigned classes
- parent can only read records for linked children
- accountant can manage invoices and payments but not academic grading

## 10. Minimal Sample Code

The examples below are intentionally small. They show the right direction without overwhelming you.

### Example A: Create a Student

```python
# app/api/v1/students.py
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, EmailStr
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.models.student import Student

router = APIRouter(prefix="/api/v1/students", tags=["students"])


class StudentCreate(BaseModel):
    school_id: int
    admission_no: str
    first_name: str
    last_name: str
    email: EmailStr | None = None
    admission_date: str


@router.post("")
def create_student(payload: StudentCreate, db: Session = Depends(get_db)):
    existing = (
        db.query(Student)
        .filter(
            Student.school_id == payload.school_id,
            Student.admission_no == payload.admission_no,
        )
        .first()
    )
    if existing:
        raise HTTPException(status_code=409, detail="Admission number already exists")

    student = Student(
        school_id=payload.school_id,
        admission_no=payload.admission_no,
        first_name=payload.first_name,
        last_name=payload.last_name,
        email=payload.email,
        admission_date=payload.admission_date,
        status="active",
    )
    db.add(student)
    db.commit()
    db.refresh(student)

    return {
        "success": True,
        "message": "Student created successfully",
        "data": {
            "id": student.id,
            "admission_no": student.admission_no,
            "full_name": f"{student.first_name} {student.last_name}",
        },
    }
```

### Example B: Assign a Student to a Class

```python
# app/api/v1/enrollments.py
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.models.academics import Enrollment

router = APIRouter(prefix="/api/v1/enrollments", tags=["enrollments"])


class EnrollmentCreate(BaseModel):
    school_id: int
    student_id: int
    class_section_id: int
    academic_year_id: int
    roll_number: str | None = None


@router.post("")
def assign_course(payload: EnrollmentCreate, db: Session = Depends(get_db)):
    existing = (
        db.query(Enrollment)
        .filter(
            Enrollment.school_id == payload.school_id,
            Enrollment.student_id == payload.student_id,
            Enrollment.academic_year_id == payload.academic_year_id,
        )
        .first()
    )
    if existing:
        raise HTTPException(status_code=409, detail="Student already enrolled this year")

    enrollment = Enrollment(**payload.model_dump(), status="active")
    db.add(enrollment)
    db.commit()
    db.refresh(enrollment)

    return {
        "success": True,
        "message": "Student assigned successfully",
        "data": {"enrollment_id": enrollment.id},
    }
```

### Example C: Record Attendance

```python
# app/api/v1/attendance.py
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.core.database import get_db
from app.models.attendance import AttendanceSession, AttendanceRecord

router = APIRouter(prefix="/api/v1/attendance", tags=["attendance"])


class AttendanceSessionCreate(BaseModel):
    school_id: int
    class_section_id: int
    subject_offering_id: int | None = None
    attendance_date: str
    period_label: str | None = None
    recorded_by: int


class AttendanceLine(BaseModel):
    student_id: int
    status: str
    remarks: str | None = None


class AttendanceBulkCreate(BaseModel):
    records: list[AttendanceLine]


@router.post("/sessions")
def create_session(payload: AttendanceSessionCreate, db: Session = Depends(get_db)):
    existing = (
        db.query(AttendanceSession)
        .filter(
            AttendanceSession.school_id == payload.school_id,
            AttendanceSession.class_section_id == payload.class_section_id,
            AttendanceSession.subject_offering_id == payload.subject_offering_id,
            AttendanceSession.attendance_date == payload.attendance_date,
            AttendanceSession.period_label == payload.period_label,
        )
        .first()
    )
    if existing:
        raise HTTPException(status_code=409, detail="Attendance session already exists")

    session = AttendanceSession(**payload.model_dump())
    db.add(session)
    db.commit()
    db.refresh(session)
    return {"success": True, "data": {"session_id": session.id}}


@router.post("/sessions/{session_id}/records")
def save_records(session_id: int, payload: AttendanceBulkCreate, db: Session = Depends(get_db)):
    session = db.query(AttendanceSession).filter(AttendanceSession.id == session_id).first()
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")

    for item in payload.records:
        record = AttendanceRecord(
            attendance_session_id=session_id,
            student_id=item.student_id,
            status=item.status,
            remarks=item.remarks,
        )
        db.add(record)

    db.commit()
    return {"success": True, "message": "Attendance saved"}
```

## 11. Deployment Plan

### Local Development

Recommended local setup:

- backend runs with FastAPI on `http://localhost:8000`
- frontend runs on `http://localhost:5173`
- PostgreSQL runs locally or in Docker
- Redis runs locally or in Docker

Example development stack using Docker:

```yaml
version: "3.9"
services:
  db:
    image: postgres:16
    environment:
      POSTGRES_DB: school_mgmt
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports:
      - "5432:5432"

  redis:
    image: redis:7
    ports:
      - "6379:6379"
```

Local steps:

1. create virtual environment
2. install backend dependencies
3. configure `.env`
4. run migrations
5. start FastAPI
6. start frontend

Example backend commands:

```bash
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
alembic upgrade head
uvicorn app.main:app --reload
```

### Production Suggestions

Recommended production setup:

- frontend on Vercel, Netlify, or Nginx static hosting
- backend on Docker container
- PostgreSQL managed database
- Redis managed instance
- background worker for email and SMS
- object storage for documents
- reverse proxy with Nginx
- HTTPS with TLS

Good hosting options:

- VPS with Docker Compose for small schools
- Azure App Service / Azure Container Apps
- AWS ECS / Lightsail
- DigitalOcean App Platform

### Production Checklist

- enable HTTPS
- use environment variables, not hardcoded secrets
- enable database backups
- enable audit logging
- enable monitoring and error tracking
- rate limit auth endpoints
- rotate credentials
- use separate dev, staging, and prod databases

## 12. Simple Explanation of How the Whole System Works

Here is the system in plain language:

1. the school is created
2. admins define academic years, terms, classes, and subjects
3. admins create teachers and students
4. students are assigned to class sections
5. teachers are assigned to subject offerings
6. teachers take attendance and enter scores
7. the system calculates results
8. finance staff generate invoices and record payments
9. parents and students view updates through their portals
10. notifications keep everyone informed

That is the backbone of the product.

## 13. Practical Implementation Advice

If you are building this with limited time, avoid trying to build every advanced feature at once.

Build this first:

- auth
- users and roles
- students
- teachers
- classes
- enrollments
- attendance
- invoicing

Then add:

- grading
- parent portal
- notifications
- dashboards

Then add:

- timetable
- document uploads
- advanced reports
- SMS integrations

## 14. Migration Advice For This Current Repository

Because this repository already contains a large PHP-based SAMS implementation, the safest path is not a total rewrite.

Use this plan:

1. keep existing PHP pages working
2. build the FastAPI backend as the clean service layer
3. move high-value workflows to API-first modules
4. gradually migrate old pages to React or cleaner frontend modules
5. retire legacy direct-database pages after the API is stable

Best migration order:

1. auth
2. student management
3. teacher management
4. attendance
5. result processing
6. finance

## 15. Recommended Next Deliverables

After this blueprint, the next useful implementation documents are:

1. ERD diagram
2. OpenAPI spec
3. Alembic migration files
4. seed data script
5. frontend wireframes
6. permission matrix
7. test plan

## 16. Final Recommendation

If you want one practical implementation direction, use:

- FastAPI for backend
- PostgreSQL for database
- React for frontend
- Redis for background work and OTPs

This gives you a modern architecture that is still simple enough to execute with an intermediate team.
