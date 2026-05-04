from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session

from app.api.deps import require_roles
from app.core.database import get_db
from app.core.response import success_response
from app.models.user import User
from app.schemas.user import UserCreate, UserUpdate
from app.services.user_service import UserService

router = APIRouter()


@router.get("")
def list_users(
    db: Session = Depends(get_db),
    school_id: int = Query(..., ge=1),
    _: User = Depends(require_roles("super_admin", "school_admin", "principal")),
):
    users = UserService.list_users(db, school_id=school_id)
    return success_response("Users fetched successfully", [
        {
            "id": user.id,
            "school_id": user.school_id,
            "role_id": user.role_id,
            "first_name": user.first_name,
            "last_name": user.last_name,
            "middle_name": user.middle_name,
            "email": user.email,
            "phone": user.phone,
            "username": user.username,
            "is_active": user.is_active,
        }
        for user in users
    ])


@router.post("")
def create_user(
    payload: UserCreate,
    _: User = Depends(require_roles("super_admin", "school_admin")),
    db: Session = Depends(get_db),
):
    user = UserService.create_user(db, payload)
    return success_response("User created successfully", {"id": user.id})


@router.get("/{user_id}")
def get_user(
    user_id: int,
    _: User = Depends(require_roles("super_admin", "school_admin", "principal")),
    db: Session = Depends(get_db),
):
    user = UserService.get_user(db, user_id)
    return success_response("User fetched successfully", {
        "id": user.id,
        "school_id": user.school_id,
        "role_id": user.role_id,
        "first_name": user.first_name,
        "last_name": user.last_name,
        "middle_name": user.middle_name,
        "email": user.email,
        "phone": user.phone,
        "username": user.username,
        "is_active": user.is_active,
    })


@router.put("/{user_id}")
def update_user(
    user_id: int,
    payload: UserUpdate,
    _: User = Depends(require_roles("super_admin", "school_admin")),
    db: Session = Depends(get_db),
):
    user = UserService.update_user(db, user_id, payload)
    return success_response("User updated successfully", {"id": user.id})
