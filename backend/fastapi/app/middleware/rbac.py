from fastapi import HTTPException, status

from app.models.school import Role
from app.models.user import User


def enforce_roles(user: User, role: Role | None, allowed_roles: set[str]) -> None:
    role_name = role.name if role else ""
    if role_name not in allowed_roles:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You are not allowed to perform this action",
        )
