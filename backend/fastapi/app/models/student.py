from datetime import date, datetime

from sqlalchemy import BIGINT, Boolean, Date, DateTime, ForeignKey, String, Text, UniqueConstraint, func
from sqlalchemy.orm import Mapped, mapped_column

from app.core.database import Base


class Student(Base):
    __tablename__ = "students"
    __table_args__ = (UniqueConstraint("school_id", "admission_no", name="uq_student_school_admission_no"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    user_id: Mapped[int | None] = mapped_column(BIGINT, ForeignKey("users.id"))
    admission_no: Mapped[str] = mapped_column(String(50), nullable=False)
    first_name: Mapped[str] = mapped_column(String(80), nullable=False)
    last_name: Mapped[str] = mapped_column(String(80), nullable=False)
    middle_name: Mapped[str | None] = mapped_column(String(80))
    gender: Mapped[str | None] = mapped_column(String(20))
    date_of_birth: Mapped[date | None] = mapped_column(Date)
    email: Mapped[str | None] = mapped_column(String(150))
    phone: Mapped[str | None] = mapped_column(String(30))
    address: Mapped[str | None] = mapped_column(Text)
    admission_date: Mapped[date] = mapped_column(Date, nullable=False)
    status: Mapped[str] = mapped_column(String(30), nullable=False, default="active")
    blood_group: Mapped[str | None] = mapped_column(String(10))
    medical_notes: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class Guardian(Base):
    __tablename__ = "guardians"

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    user_id: Mapped[int | None] = mapped_column(BIGINT, ForeignKey("users.id"))
    first_name: Mapped[str] = mapped_column(String(80), nullable=False)
    last_name: Mapped[str] = mapped_column(String(80), nullable=False)
    email: Mapped[str | None] = mapped_column(String(150))
    phone: Mapped[str | None] = mapped_column(String(30))
    address: Mapped[str | None] = mapped_column(Text)
    occupation: Mapped[str | None] = mapped_column(String(120))
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class StudentGuardian(Base):
    __tablename__ = "student_guardians"

    student_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("students.id", ondelete="CASCADE"), primary_key=True)
    guardian_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("guardians.id", ondelete="CASCADE"), primary_key=True)
    relationship: Mapped[str] = mapped_column(String(30), nullable=False)
    is_primary: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)


class AttendanceSession(Base):
    __tablename__ = "attendance_sessions"
    __table_args__ = (
        UniqueConstraint(
            "school_id",
            "class_section_id",
            "subject_offering_id",
            "attendance_date",
            "period_label",
            name="uq_attendance_session_key",
        ),
    )

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    class_section_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("class_sections.id"), nullable=False)
    subject_offering_id: Mapped[int | None] = mapped_column(BIGINT)
    attendance_date: Mapped[date] = mapped_column(Date, nullable=False)
    period_label: Mapped[str | None] = mapped_column(String(50))
    recorded_by: Mapped[int] = mapped_column(BIGINT, ForeignKey("users.id"), nullable=False)
    remarks: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())


class AttendanceRecord(Base):
    __tablename__ = "attendance_records"
    __table_args__ = (UniqueConstraint("attendance_session_id", "student_id", name="uq_attendance_record_session_student"),)

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    attendance_session_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("attendance_sessions.id", ondelete="CASCADE"), nullable=False)
    student_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("students.id"), nullable=False)
    status: Mapped[str] = mapped_column(String(20), nullable=False)
    remarks: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())


class TermResult(Base):
    __tablename__ = "term_results"

    id: Mapped[int] = mapped_column(BIGINT, primary_key=True, autoincrement=True)
    school_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("schools.id"), nullable=False)
    term_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("terms.id"), nullable=False)
    student_id: Mapped[int] = mapped_column(BIGINT, ForeignKey("students.id"), nullable=False)
    subject_offering_id: Mapped[int] = mapped_column(BIGINT, nullable=False)
    total_score: Mapped[float] = mapped_column(nullable=False)
    average_score: Mapped[float] = mapped_column(nullable=False)
    teacher_remark: Mapped[str | None] = mapped_column(Text)
    principal_remark: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, server_default=func.now(), onupdate=func.now())
