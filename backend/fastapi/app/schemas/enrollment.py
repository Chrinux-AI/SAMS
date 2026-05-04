from pydantic import BaseModel, Field

from app.schemas.common import ORMBaseSchema


class EnrollmentCreate(BaseModel):
    student_id: int
    class_section_id: int
    academic_year_id: int
    roll_number: str | None = Field(default=None, max_length=30)


class EnrollmentOut(ORMBaseSchema):
    id: int
    school_id: int
    student_id: int
    class_section_id: int
    academic_year_id: int
    roll_number: str | None
    status: str
