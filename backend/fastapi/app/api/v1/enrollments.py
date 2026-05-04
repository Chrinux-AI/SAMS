from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.api.deps import require_roles
from app.core.database import get_db
from app.core.response import success_response
from app.models.user import User
from app.schemas.enrollment import EnrollmentCreate
from app.services.enrollment_service import EnrollmentService

router = APIRouter()


@router.post("")
def create_enrollment(
    payload: EnrollmentCreate,
    current_user: User = Depends(require_roles("super_admin", "school_admin", "principal")),
    db: Session = Depends(get_db),
):
    enrollment = EnrollmentService.create_enrollment(db, school_id=current_user.school_id, payload=payload)
    return success_response("Student assigned successfully", {"enrollment_id": enrollment.id})
