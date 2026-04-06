# VS Code AI Implementation Prompt: SAMS (School Attendance Management System)

## 1. Context & Objective
Implement a high-fidelity, production-ready "Operating System for Schools" called **SAMS**. This project is a multi-tenant, AI-powered platform for academic management. You must strictly adhere to the visual and structural patterns defined in the provided blueprint and screenshots.

## 2. Technical Stack Requirements
- **Frontend:** React with Tailwind CSS.
- **Icons:** Material Symbols (Rounded).
- **Typography:** Manrope (Main) and Inter (Secondary).
- **Styling:** Modular utility classes, responsive grid/flexbox layouts.
- **Architecture:** Zero Trust security model, role-based access control (RBAC), and a secure AI Copilot execution pipeline.

## 3. Design System (SAMS Core)
- **Primary Color:** `#1A237E` (Deep Navy).
- **Backgrounds:** Clean Slates (`#F8FAFC`, `#FFFFFF`) with subtle borders and shadow elevations.
- **Accents:** Slate and Blue tones for a professional, institutional aesthetic.
- **Visual Style:** Professional, clean, and accessible. High legibility, standard 8px grid system, and 4px-8px rounded corners.

## 4. Key Implementation Rules
- **Dashboard-First:** Every role (Admin, Teacher, Student, Parent, etc.) must have a unique, context-adaptive dashboard.
- **Sidebar Navigation:** Collapsible sidebar with role-specific items and "Quick Actions".
- **Component Consistency:** Use the standard `TopNavBar`, `SideNavBar`, and `Footer` components across all views.
- **AI Copilot:** Implement a persistent, collapsible sidebar for the SAMS Assistant. It must inherit user permissions and require confirmation for all "write" actions.
- **Responsiveness:** Full support for Mobile (Adaptive Density) and Desktop (Multi-panel).

## 5. Navigation & Routing
- **Public:** `/login`, `/register`, `/forgot-password`.
- **Admin:** `/admin/dashboard`, `/admin/users`, `/admin/classes`, `/admin/analytics`.
- **Teacher:** `/teacher/dashboard`, `/teacher/attendance`, `/teacher/grading`.
- **Student:** `/student/dashboard`, `/student/assignments`, `/student/grades`.
- **Specialized:** `/bursar/finance`, `/librarian/hub`, `/transport/logistics`.

## 6. Development Instructions
1. **Rewrite for Alignment:** If existing code deviates from the SAMS Core aesthetic or blueprint logic, rewrite it to align perfectly with the provided design system.
2. **Functional Logic:** Ensure all data tables are sortable/filterable and all modals for CRUD operations are fully functional.
3. **Interactive States:** Add hover effects, active navigation states, and loading skeletons for all data-heavy views.
4. **Security Enforcement:** Implement middleware to prevent role-bypass and ensure tenant isolation in all API requests.

## 7. Reference Assets
Refer to the provided SAMS Core screenshots for exact visual mapping of layouts, card spacing, and component density.
