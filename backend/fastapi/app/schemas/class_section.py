from pydantic import BaseModel, Field

from app.schemas.common import ORMBaseSchema


class ClassSectionCreate(BaseModel):
    school_id: int
    class_level_id: int
    name: str = Field(min_length=1, max_length=60)
    class_teacher_id: int | None = None
    capacity: int | None = Field(default=None, ge=1)


class ClassSectionOut(ORMBaseSchema):
    id: int
    school_id: int
    class_level_id: int
    name: str
    class_teacher_id: int | None
    capacity: int | None
