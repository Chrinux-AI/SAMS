# Accountant Frontend & AI Visibility Implementation Plan

## 1. Overview

The backend APIs (CRUD + AI Insights) for the Accountant module have been successfully implemented and committed. This phase focuses on building the visible UI (Frontend) for the Accountant to seamlessly consume these features without any demo data, strictly pulling from our factual, real-time backend.

## 2. Directory Structure

All frontend files for the accountant will be strictly isolated in `frontend/accountant/` following the strict frontend/backend separation rules.

## 3. Core Milestones

### Phase A: Core Dashboard & AI Visibility

- **File**: `frontend/accountant/index.php` (or `dashboard.php`)
- **Features**:
  - Implement standard UI layout (Header, Sidebar, Main Content Area) enforcing the Accountant role visually.
  - **AI Insights Widget**: A prominent block at the top of the dashboard fetching real-time data from `backend/api/accountant/ai_insights.php` via AJAX/Fetch API.
  - **Stats Overview**: Simple metric cards displaying current Revenue, Expenses, Receivables, and Payables (pulled directly from the AI endpoint's raw JSON data so we don't have to make redundant queries).

### Phase B: Financial Management UIs (CRUD)

- **Files**:
  - `frontend/accountant/purchase-orders.php`
  - `frontend/accountant/expenses.php`
  - `frontend/accountant/suppliers.php`
  - `frontend/accountant/fee-invoices.php`
  - `frontend/accountant/fee-payments.php`
  - `frontend/accountant/ledger.php`
- **Features**:
  - Each file will feature Datatables or standard HTML tables populated via AJAX making GET requests to their respective backend API endpoints.
  - Add/Edit/Delete modals submitting POST/PUT/DELETE requests via Javascript to our robust backend.

### Phase C: Admin Configuration (Optional but Recommended)

- **File**: Modify an existing Admin settings view (e.g., `frontend/admin/platform-settings.php` or similar if it exists).
- **Feature**: A UI specifically for `dev` / `dev` to securely input and update the `GEMINI_API_KEY` directly from the browser, saving it to global settings rather than relying entirely on `config.php` environment variables over time if preferred.

## 4. Work Flow Execution

Upon approval of this plan, we will translate Phase A into immediate To-Do items and begin building the Accountant Dashboard with the live AI Insights Widget immediately.
