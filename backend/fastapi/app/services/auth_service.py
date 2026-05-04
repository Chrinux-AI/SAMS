from datetime import UTC, datetime, timedelta
import secrets

from fastapi import HTTPException, status
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.security import create_access_token, create_refresh_token, decode_token, hash_password, verify_password
from app.models.auth import PasswordResetToken
from app.models.school import Role
from app.models.user import User


class AuthService:
    @staticmethod
    def login(db: Session, email: str, password: str) -> dict:
        user = db.query(User).filter(User.email == email).first()
        if not user or not verify_password(password, user.password_hash):
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid credentials")
        if not user.is_active:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Account is inactive")

        role = db.query(Role).filter(Role.id == user.role_id).first()
        role_name = role.name if role else "unknown"

        user.last_login_at = datetime.now(UTC)
        db.commit()

        claims = {"school_id": user.school_id, "role": role_name}
        access_token = create_access_token(subject=str(user.id), extra_claims=claims)
        refresh_token = create_refresh_token(subject=str(user.id), extra_claims=claims)

        return {
            "access_token": access_token,
            "refresh_token": refresh_token,
            "token_type": "bearer",
            "user": {
                "id": user.id,
                "school_id": user.school_id,
                "role": role_name,
                "first_name": user.first_name,
                "last_name": user.last_name,
            },
        }

    @staticmethod
    def refresh(db: Session, refresh_token: str) -> dict:
        payload = decode_token(refresh_token)
        if payload.get("type") != "refresh":
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid refresh token")

        user = db.query(User).filter(User.id == int(payload["sub"])).first()
        if not user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")

        role = db.query(Role).filter(Role.id == user.role_id).first()
        role_name = role.name if role else "unknown"
        claims = {"school_id": user.school_id, "role": role_name}

        return {
            "access_token": create_access_token(subject=str(user.id), extra_claims=claims),
            "refresh_token": create_refresh_token(subject=str(user.id), extra_claims=claims),
            "token_type": "bearer",
        }

    @staticmethod
    def forgot_password(db: Session, email: str) -> dict:
        user = db.query(User).filter(User.email == email).first()
        if not user:
            return {"reset_token": None}

        token = secrets.token_urlsafe(48)
        expires_at = datetime.now(UTC) + timedelta(minutes=settings.password_reset_token_expire_minutes)
        db.add(PasswordResetToken(user_id=user.id, token=token, expires_at=expires_at))
        db.commit()
        return {"reset_token": token}

    @staticmethod
    def reset_password(db: Session, token: str, new_password: str) -> None:
        reset_token = db.query(PasswordResetToken).filter(PasswordResetToken.token == token).first()
        if not reset_token:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Reset token not found")
        if reset_token.used_at is not None:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Reset token already used")
        if reset_token.expires_at < datetime.now(UTC).replace(tzinfo=None):
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Reset token expired")

        user = db.query(User).filter(User.id == reset_token.user_id).first()
        if not user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")

        user.password_hash = hash_password(new_password)
        reset_token.used_at = datetime.now(UTC).replace(tzinfo=None)
        db.commit()
