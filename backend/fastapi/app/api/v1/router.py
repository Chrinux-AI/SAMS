from fastapi import APIRouter

from app.api.v1 import auth, class_sections, enrollments, students, users

api_router = APIRouter()
api_router.include_router(auth.router, prefix="/auth", tags=["auth"])
api_router.include_router(users.router, prefix="/users", tags=["users"])
api_router.include_router(students.router, prefix="/students", tags=["students"])
api_router.include_router(class_sections.router, prefix="/class-sections", tags=["class-sections"])
api_router.include_router(enrollments.router, prefix="/enrollments", tags=["enrollments"])
