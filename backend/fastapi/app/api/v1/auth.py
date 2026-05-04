from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.api.deps import get_current_user
from app.core.database import get_db
from app.core.response import success_response
from app.models.school import Role
from app.models.user import User
from app.schemas.auth import ForgotPasswordRequest, LoginRequest, RefreshRequest, ResetPasswordRequest
from app.services.auth_service import AuthService

router = APIRouter()


@router.post("/login")
def login(payload: LoginRequest, db: Session = Depends(get_db)):
    data = AuthService.login(db, email=payload.email, password=payload.password)
    return success_response("Login successful", data)


@router.post("/refresh")
def refresh(payload: RefreshRequest, db: Session = Depends(get_db)):
    data = AuthService.refresh(db, refresh_token=payload.refresh_token)
    return success_response("Token refreshed", data)


@router.post("/forgot-password")
def forgot_password(payload: ForgotPasswordRequest, db: Session = Depends(get_db)):
    data = AuthService.forgot_password(db, email=payload.email)
    return success_response("If account exists, reset instructions have been generated", data)


@router.post("/reset-password")
def reset_password(payload: ResetPasswordRequest, db: Session = Depends(get_db)):
    AuthService.reset_password(db, token=payload.token, new_password=payload.new_password)
    return success_response("Password reset successful")


@router.get("/me")
def me(current_user: User = Depends(get_current_user), db: Session = Depends(get_db)):
    role = db.query(Role).filter(Role.id == current_user.role_id).first()
    return success_response(
        "Current user fetched",
        {
            "id": current_user.id,
            "school_id": current_user.school_id,
            "role": role.name if role else "unknown",
            "first_name": current_user.first_name,
            "last_name": current_user.last_name,
            "email": current_user.email,
        },
    )
