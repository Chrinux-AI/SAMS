from pydantic import BaseModel, EmailStr, Field

from app.schemas.common import ORMBaseSchema


class UserBase(BaseModel):
    first_name: str = Field(min_length=1, max_length=80)
    last_name: str = Field(min_length=1, max_length=80)
    middle_name: str | None = Field(default=None, max_length=80)
    email: EmailStr | None = None
    phone: str | None = Field(default=None, max_length=30)
    username: str | None = Field(default=None, max_length=80)
    role_id: int


class UserCreate(UserBase):
    school_id: int
    password: str = Field(min_length=8, max_length=128)


class UserUpdate(BaseModel):
    first_name: str | None = Field(default=None, min_length=1, max_length=80)
    last_name: str | None = Field(default=None, min_length=1, max_length=80)
    middle_name: str | None = Field(default=None, max_length=80)
    email: EmailStr | None = None
    phone: str | None = Field(default=None, max_length=30)
    username: str | None = Field(default=None, max_length=80)
    role_id: int | None = None
    is_active: bool | None = None


class UserOut(ORMBaseSchema):
    id: int
    school_id: int
    role_id: int
    first_name: str
    last_name: str
    middle_name: str | None
    email: str | None
    phone: str | None
    username: str | None
    is_active: bool
