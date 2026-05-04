from fastapi import HTTPException, status
from sqlalchemy.orm import Session

from app.models.academics import AcademicYear, Enrollment
from app.models.finance import Invoice
from app.models.student import AttendanceRecord, AttendanceSession, Guardian, Student, StudentGuardian, TermResult
from app.schemas.student import StudentCreate, StudentGuardianCreate, StudentUpdate


class StudentService:
    @staticmethod
    def list_students(
        db: Session,
        school_id: int,
        search: str | None,
        class_section_id: int | None,
        status_filter: str | None,
        page: int,
        page_size: int,
    ) -> tuple[list[Student], int]:
        query = db.query(Student).filter(Student.school_id == school_id)

        if search:
            like = f"%{search}%"
            query = query.filter(
                (Student.first_name.ilike(like)) | (Student.last_name.ilike(like)) | (Student.admission_no.ilike(like))
            )

        if status_filter:
            query = query.filter(Student.status == status_filter)

        if class_section_id:
            query = query.join(Enrollment, Enrollment.student_id == Student.id).filter(
                Enrollment.class_section_id == class_section_id,
                Enrollment.status == "active",
            )

        total = query.count()
        students = query.order_by(Student.id.desc()).offset((page - 1) * page_size).limit(page_size).all()
        return students, total

    @staticmethod
    def create_student(db: Session, school_id: int, payload: StudentCreate) -> Student:
        existing = (
            db.query(Student)
            .filter(Student.school_id == school_id, Student.admission_no == payload.admission_no)
            .first()
        )
        if existing:
            raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Admission number already exists")

        student = Student(
            school_id=school_id,
            admission_no=payload.admission_no,
            first_name=payload.first_name,
            last_name=payload.last_name,
            middle_name=payload.middle_name,
            gender=payload.gender,
            date_of_birth=payload.date_of_birth,
            email=payload.email,
            phone=payload.phone,
            address=payload.address,
            admission_date=payload.admission_date,
            status="active",
        )
        db.add(student)
        db.flush()

        if payload.guardian:
            guardian = Guardian(
                school_id=school_id,
                first_name=payload.guardian.first_name,
                last_name=payload.guardian.last_name,
                email=payload.guardian.email,
                phone=payload.guardian.phone,
            )
            db.add(guardian)
            db.flush()
            db.add(
                StudentGuardian(
                    student_id=student.id,
                    guardian_id=guardian.id,
                    relationship=payload.guardian.relationship,
                    is_primary=True,
                )
            )

        current_year = (
            db.query(AcademicYear)
            .filter(AcademicYear.school_id == school_id, AcademicYear.is_current.is_(True))
            .first()
        )
        if not current_year:
            current_year = db.query(AcademicYear).filter(AcademicYear.school_id == school_id).order_by(AcademicYear.id.desc()).first()
        if not current_year:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="No academic year found for enrollment",
            )

        db.add(
            Enrollment(
                school_id=school_id,
                student_id=student.id,
                class_section_id=payload.class_section_id,
                academic_year_id=current_year.id,
                status="active",
            )
        )

        db.commit()
        db.refresh(student)
        return student

    @staticmethod
    def get_student(db: Session, school_id: int, student_id: int) -> Student:
        student = db.query(Student).filter(Student.id == student_id, Student.school_id == school_id).first()
        if not student:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Student not found")
        return student

    @staticmethod
    def update_student(db: Session, school_id: int, student_id: int, payload: StudentUpdate) -> Student:
        student = StudentService.get_student(db, school_id, student_id)
        updates = payload.model_dump(exclude_unset=True)
        for key, value in updates.items():
            setattr(student, key, value)
        db.commit()
        db.refresh(student)
        return student

    @staticmethod
    def add_guardian(db: Session, school_id: int, student_id: int, payload: StudentGuardianCreate) -> None:
        student = StudentService.get_student(db, school_id, student_id)
        guardian = Guardian(
            school_id=school_id,
            first_name=payload.first_name,
            last_name=payload.last_name,
            email=payload.email,
            phone=payload.phone,
        )
        db.add(guardian)
        db.flush()
        db.add(
            StudentGuardian(
                student_id=student.id,
                guardian_id=guardian.id,
                relationship=payload.relationship,
                is_primary=payload.is_primary,
            )
        )
        db.commit()

    @staticmethod
    def get_attendance(db: Session, school_id: int, student_id: int) -> list[dict]:
        StudentService.get_student(db, school_id, student_id)
        rows = (
            db.query(AttendanceRecord, AttendanceSession)
            .join(AttendanceSession, AttendanceSession.id == AttendanceRecord.attendance_session_id)
            .filter(AttendanceRecord.student_id == student_id, AttendanceSession.school_id == school_id)
            .order_by(AttendanceSession.attendance_date.desc())
            .all()
        )
        return [
            {
                "attendance_date": session.attendance_date,
                "status": record.status,
                "remarks": record.remarks,
                "class_section_id": session.class_section_id,
            }
            for record, session in rows
        ]

    @staticmethod
    def get_results(db: Session, school_id: int, student_id: int) -> list[TermResult]:
        StudentService.get_student(db, school_id, student_id)
        return (
            db.query(TermResult)
            .filter(TermResult.school_id == school_id, TermResult.student_id == student_id)
            .order_by(TermResult.id.desc())
            .all()
        )

    @staticmethod
    def get_invoices(db: Session, school_id: int, student_id: int) -> list[Invoice]:
        StudentService.get_student(db, school_id, student_id)
        return (
            db.query(Invoice)
            .filter(Invoice.school_id == school_id, Invoice.student_id == student_id)
            .order_by(Invoice.id.desc())
            .all()
        )
