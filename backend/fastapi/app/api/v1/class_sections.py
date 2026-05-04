from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.api.deps import get_current_user, require_roles
from app.core.database import get_db
from app.core.response import success_response
from app.models.user import User
from app.schemas.class_section import ClassSectionCreate
from app.services.class_section_service import ClassSectionService

router = APIRouter()


@router.get("")
def list_class_sections(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    rows = ClassSectionService.list_class_sections(db, current_user.school_id)
    data = [
        {
            "id": row.id,
            "school_id": row.school_id,
            "class_level_id": row.class_level_id,
            "name": row.name,
            "class_teacher_id": row.class_teacher_id,
            "capacity": row.capacity,
        }
        for row in rows
    ]
    return success_response("Class sections fetched successfully", data)


@router.post("")
def create_class_section(
    payload: ClassSectionCreate,
    current_user: User = Depends(require_roles("super_admin", "school_admin", "principal")),
    db: Session = Depends(get_db),
):
    if payload.school_id != current_user.school_id:
        payload = payload.model_copy(update={"school_id": current_user.school_id})
    class_section = ClassSectionService.create_class_section(db, payload)
    return success_response("Class section created successfully", {"id": class_section.id})
