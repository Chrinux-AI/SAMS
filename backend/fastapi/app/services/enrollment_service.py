from fastapi import HTTPException, status
from sqlalchemy.orm import Session

from app.models.academics import Enrollment
from app.schemas.enrollment import EnrollmentCreate


class EnrollmentService:
    @staticmethod
    def create_enrollment(db: Session, school_id: int, payload: EnrollmentCreate) -> Enrollment:
        existing = (
            db.query(Enrollment)
            .filter(
                Enrollment.school_id == school_id,
                Enrollment.student_id == payload.student_id,
                Enrollment.academic_year_id == payload.academic_year_id,
            )
            .first()
        )
        if existing:
            raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Student already enrolled this year")

        enrollment = Enrollment(school_id=school_id, status="active", **payload.model_dump())
        db.add(enrollment)
        db.commit()
        db.refresh(enrollment)
        return enrollment
