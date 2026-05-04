from datetime import date

from pydantic import BaseModel, EmailStr, Field

from app.schemas.common import ORMBaseSchema


class GuardianPayload(BaseModel):
    first_name: str = Field(min_length=1, max_length=80)
    last_name: str = Field(min_length=1, max_length=80)
    email: EmailStr | None = None
    phone: str | None = Field(default=None, max_length=30)
    relationship: str = Field(min_length=1, max_length=30)


class StudentCreate(BaseModel):
    admission_no: str = Field(min_length=1, max_length=50)
    first_name: str = Field(min_length=1, max_length=80)
    last_name: str = Field(min_length=1, max_length=80)
    middle_name: str | None = Field(default=None, max_length=80)
    gender: str | None = Field(default=None, max_length=20)
    date_of_birth: date | None = None
    email: EmailStr | None = None
    phone: str | None = Field(default=None, max_length=30)
    address: str | None = None
    admission_date: date
    class_section_id: int
    guardian: GuardianPayload | None = None


class StudentUpdate(BaseModel):
    first_name: str | None = Field(default=None, min_length=1, max_length=80)
    last_name: str | None = Field(default=None, min_length=1, max_length=80)
    middle_name: str | None = Field(default=None, max_length=80)
    gender: str | None = Field(default=None, max_length=20)
    date_of_birth: date | None = None
    email: EmailStr | None = None
    phone: str | None = Field(default=None, max_length=30)
    address: str | None = None
    status: str | None = Field(default=None, max_length=30)


class StudentGuardianCreate(GuardianPayload):
    is_primary: bool = False


class StudentOut(ORMBaseSchema):
    id: int
    school_id: int
    admission_no: str
    first_name: str
    last_name: str
    middle_name: str | None
    gender: str | None
    date_of_birth: date | None
    email: str | None
    phone: str | None
    address: str | None
    admission_date: date
    status: str
