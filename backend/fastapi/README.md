# SAMS FastAPI Backend (Phase 1)

Production-oriented FastAPI implementation starter for:

- Authentication (JWT + refresh + reset)
- Users module
- Students module
- Class Sections module
- Enrollments module

## Folder Structure

```text
backend/fastapi/
  app/
    main.py
    core/
      config.py
      database.py
      response.py
      security.py
    middleware/
      rbac.py
    models/
      __init__.py
      school.py
      user.py
      auth.py
      academics.py
      student.py
      finance.py
    schemas/
      __init__.py
      common.py
      auth.py
      user.py
      student.py
      class_section.py
      enrollment.py
    services/
      __init__.py
      auth_service.py
      user_service.py
      student_service.py
      class_section_service.py
      enrollment_service.py
    api/
      deps.py
      v1/
        router.py
        auth.py
        users.py
        students.py
        class_sections.py
        enrollments.py
  alembic/
    env.py
    script.py.mako
    versions/
      0001_initial_phase1.py
  alembic.ini
  requirements.txt
  .env
  .env.example
```

## Run Locally

1. Use Python 3.14+ (or 3.12/3.13), create and activate a virtual env.
2. Install dependencies from `requirements.txt`.
3. Update `.env` with real values.
4. Run migration:
   - `alembic upgrade head`
5. Start API:
   - `uvicorn app.main:app --reload --host 0.0.0.0 --port 8000`

> Important: run `uvicorn` from the `backend/fastapi` directory so `.env` is loaded automatically.

## API Docs

- Swagger UI: `http://localhost:8000/docs`
- ReDoc: `http://localhost:8000/redoc`

## Implemented Endpoints

### Auth

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `GET /api/v1/auth/me`

### Users

- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{user_id}`
- `PUT /api/v1/users/{user_id}`

### Students

- `GET /api/v1/students`
- `POST /api/v1/students`
- `GET /api/v1/students/{student_id}`
- `PUT /api/v1/students/{student_id}`
- `POST /api/v1/students/{student_id}/guardians`
- `GET /api/v1/students/{student_id}/attendance`
- `GET /api/v1/students/{student_id}/results`
- `GET /api/v1/students/{student_id}/invoices`

### Class Sections

- `GET /api/v1/class-sections`
- `POST /api/v1/class-sections`

### Enrollments

- `POST /api/v1/enrollments`

## Notes

- Business logic is in `app/services/*`.
- Route handlers remain thin and focus on IO + response formatting.
- RBAC enforced via dependency + role middleware helper.
- Migration schema follows the blueprint entities required for this phase.
