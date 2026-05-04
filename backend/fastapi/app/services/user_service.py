from fastapi import HTTPException, status
from sqlalchemy.orm import Session

from app.core.security import hash_password
from app.models.user import User
from app.schemas.user import UserCreate, UserUpdate


class UserService:
    @staticmethod
    def list_users(db: Session, school_id: int) -> list[User]:
        return db.query(User).filter(User.school_id == school_id).order_by(User.id.desc()).all()

    @staticmethod
    def create_user(db: Session, payload: UserCreate) -> User:
        user = User(
            school_id=payload.school_id,
            role_id=payload.role_id,
            first_name=payload.first_name,
            last_name=payload.last_name,
            middle_name=payload.middle_name,
            email=payload.email,
            phone=payload.phone,
            username=payload.username,
            password_hash=hash_password(payload.password),
            is_active=True,
        )
        db.add(user)
        db.commit()
        db.refresh(user)
        return user

    @staticmethod
    def get_user(db: Session, user_id: int) -> User:
        user = db.query(User).filter(User.id == user_id).first()
        if not user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")
        return user

    @staticmethod
    def update_user(db: Session, user_id: int, payload: UserUpdate) -> User:
        user = UserService.get_user(db, user_id)
        updates = payload.model_dump(exclude_unset=True)
        for key, value in updates.items():
            setattr(user, key, value)
        db.commit()
        db.refresh(user)
        return user
