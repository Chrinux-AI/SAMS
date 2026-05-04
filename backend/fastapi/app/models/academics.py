from datetime import date, datetime

from sqlalchemy import BIGINT, Date, DateTime, ForeignKey, Integer, String, UniqueConstraint, func
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base


class AcademicYear(Base):
    __tablename__ = "academic_years"
    __table_args__ = (UniqueConstraint("school_id", "name", name="uq_academic_year_school_name"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    name: Mapped[str] = mapped_column(String(50), nullable=False)
    start_date: Mapped[date] = mapped_column(Date, nullable=False)
    end_date: Mapped[date] = mapped_column(Date, nullable=False)
    is_current: Mapped[bool] = mapped_column(nullable=False, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class Term(Base):
    __tablename__ = "terms"
    __table_args__ = (UniqueConstraint("school_id", "academic_year_id", "name", name="uq_terms_school_year_name"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    academic_year_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("academic_years.id"), nullable=False)
    name: Mapped[str] = mapped_column(String(50), nullable=False)
    start_date: Mapped[date] = mapped_column(Date, nullable=False)
    end_date: Mapped[date] = mapped_column(Date, nullable=False)
    is_current: Mapped[bool] = mapped_column(nullable=False, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class ClassLevel(Base):
    __tablename__ = "class_levels"
    __table_args__ = (UniqueConstraint("school_id", "name", name="uq_class_level_school_name"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    name: Mapped[str] = mapped_column(String(60), nullable=False)
    rank_order: Mapped[int] = mapped_column(Integer, nullable=False, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class ClassSection(Base):
    __tablename__ = "class_sections"
    __table_args__ = (UniqueConstraint("school_id", "class_level_id", "name", name="uq_class_section_school_level_name"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    class_level_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("class_levels.id"), nullable=False)
    name: Mapped[str] = mapped_column(String(60), nullable=False)
    class_teacher_id: Mapped[int | None] = mapped_column(BIGINT)
    capacity: Mapped[int | None] = mapped_column(Integer)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class Enrollment(Base):
    __tablename__ = "enrollments"
    __table_args__ = (UniqueConstraint("school_id", "student_id", "academic_year_id", name="uq_enrollment_school_student_year"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    student_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("students.id"), nullable=False)
    class_section_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("class_sections.id"), nullable=False)
    academic_year_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("academic_years.id"), nullable=False)
    roll_number: Mapped[str | None] = mapped_column(String(30))
    status: Mapped[str] = mapped_column(String(30), nullable=False, default="active")
    enrolled_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
