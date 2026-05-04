from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session

from app.api.deps import get_current_user, require_roles
from app.core.database import get_db
from app.core.response import success_response
from app.models.user import User
from app.schemas.student import StudentCreate, StudentGuardianCreate, StudentUpdate
from app.services.student_service import StudentService

router = APIRouter()


@router.get("")
def list_students(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
    search: str | None = Query(default=None),
    class_section_id: int | None = Query(default=None, ge=1),
    status: str | None = Query(default=None),
    page: int = Query(default=1, ge=1),
    page_size: int = Query(default=20, ge=1, le=100),
):
    students, total = StudentService.list_students(
        db,
        school_id=current_user.school_id,
        search=search,
        class_section_id=class_section_id,
        status_filter=status,
        page=page,
        page_size=page_size,
    )
    data = [
        {
            "id": s.id,
            "admission_no": s.admission_no,
            "full_name": f"{s.first_name} {s.last_name}",
            "status": s.status,
        }
        for s in students
    ]
    return success_response("Students fetched successfully", data, meta={"total": total, "page": page, "page_size": page_size})


@router.post("")
def create_student(
    payload: StudentCreate,
    current_user: User = Depends(require_roles("super_admin", "school_admin", "principal")),
    db: Session = Depends(get_db),
):
    student = StudentService.create_student(db, school_id=current_user.school_id, payload=payload)
    return success_response(
        "Student created successfully",
        {
            "id": student.id,
            "admission_no": student.admission_no,
            "full_name": f"{student.first_name} {student.last_name}",
            "class_section_id": payload.class_section_id,
            "status": student.status,
        },
    )


@router.get("/{student_id}")
def get_student(
    student_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    student = StudentService.get_student(db, school_id=current_user.school_id, student_id=student_id)
    return success_response(
        "Student fetched successfully",
        {
            "id": student.id,
            "admission_no": student.admission_no,
            "first_name": student.first_name,
            "last_name": student.last_name,
            "middle_name": student.middle_name,
            "gender": student.gender,
            "date_of_birth": student.date_of_birth,
            "email": student.email,
            "phone": student.phone,
            "address": student.address,
            "admission_date": student.admission_date,
            "status": student.status,
        },
    )


@router.put("/{student_id}")
def update_student(
    student_id: int,
    payload: StudentUpdate,
    current_user: User = Depends(require_roles("super_admin", "school_admin", "principal")),
    db: Session = Depends(get_db),
):
    student = StudentService.update_student(db, school_id=current_user.school_id, student_id=student_id, payload=payload)
    return success_response("Student updated successfully", {"id": student.id})


@router.post("/{student_id}/guardians")
def add_guardian(
    student_id: int,
    payload: StudentGuardianCreate,
    current_user: User = Depends(require_roles("super_admin", "school_admin", "principal")),
    db: Session = Depends(get_db),
):
    StudentService.add_guardian(db, school_id=current_user.school_id, student_id=student_id, payload=payload)
    return success_response("Guardian linked successfully")


@router.get("/{student_id}/attendance")
def get_student_attendance(
    student_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    data = StudentService.get_attendance(db, school_id=current_user.school_id, student_id=student_id)
    return success_response("Student attendance fetched successfully", data)


@router.get("/{student_id}/results")
def get_student_results(
    student_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    rows = StudentService.get_results(db, school_id=current_user.school_id, student_id=student_id)
    data = [
        {
            "id": row.id,
            "term_id": row.term_id,
            "subject_offering_id": row.subject_offering_id,
            "total_score": float(row.total_score),
            "average_score": float(row.average_score),
            "teacher_remark": row.teacher_remark,
            "principal_remark": row.principal_remark,
        }
        for row in rows
    ]
    return success_response("Student results fetched successfully", data)


@router.get("/{student_id}/invoices")
def get_student_invoices(
    student_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    rows = StudentService.get_invoices(db, school_id=current_user.school_id, student_id=student_id)
    data = [
        {
            "id": row.id,
            "invoice_no": row.invoice_no,
            "status": row.status,
            "issue_date": row.issue_date,
            "due_date": row.due_date,
            "total_amount": float(row.total_amount),
            "amount_paid": float(row.amount_paid),
            "balance_due": float(row.balance_due),
        }
        for row in rows
    ]
    return success_response("Student invoices fetched successfully", data)
