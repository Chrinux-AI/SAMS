from fastapi import HTTPException, status
from sqlalchemy.orm import Session

from app.models.academics import ClassSection
from app.schemas.class_section import ClassSectionCreate


class ClassSectionService:
    @staticmethod
    def list_class_sections(db: Session, school_id: int) -> list[ClassSection]:
        return db.query(ClassSection).filter(ClassSection.school_id == school_id).order_by(ClassSection.id.desc()).all()

    @staticmethod
    def create_class_section(db: Session, payload: ClassSectionCreate) -> ClassSection:
        existing = (
            db.query(ClassSection)
            .filter(
                ClassSection.school_id == payload.school_id,
                ClassSection.class_level_id == payload.class_level_id,
                ClassSection.name == payload.name,
            )
            .first()
        )
        if existing:
            raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Class section already exists")

        class_section = ClassSection(**payload.model_dump())
        db.add(class_section)
        db.commit()
        db.refresh(class_section)
        return class_section
