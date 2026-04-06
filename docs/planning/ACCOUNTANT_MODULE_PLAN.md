# Accountant Module Backend Implementation Plan

## Overview

This plan focuses entirely on the **Backend/API** implementation for the **Accountant** role. Per the user's instructions, **no UI** will be implemented in this phase. The goal is to build out the features necessary for financial record handling, purchases, reports, and controls as extracted from `docs/EXTRACTED_FEATURES_FROM_GITHUB.md`.

## Relational Database Tables Used

The following existing database tables will be the core of the Accountant Backend Module:

- `purchase_orders`
- `expenses` & `expense_approvals`
- `suppliers`
- `fee_invoices` & `fee_payments`
- `fee_structures` & `student_fees`
- `ledger_entries`
- `payroll` (read access or management depending on privilege)

## Planned API Endpoints

### 1. Purchase & Expenditure Management

- `POST /backend/api/accountant/purchase_orders.php` - Create a new purchase order.
- `GET /backend/api/accountant/purchase_orders.php` - View list of purchase orders (with filters like status, date range).
- `PUT /backend/api/accountant/purchase_orders.php` - Update purchase order status (Pending, Approved, Paid, Cancelled).
- `POST /backend/api/accountant/expenses.php` - Record a new operational expense.
- `GET /backend/api/accountant/expenses.php` - Retrieve expense records.

### 2. Supplier Management

- `POST /backend/api/accountant/suppliers.php` - Add a new supplier to the system.
- `GET /backend/api/accountant/suppliers.php` - Retrieve the list of active suppliers.

### 3. Student Fees & Income

- `GET /backend/api/accountant/fee_invoices.php` - List all student fee invoices.
- `POST /backend/api/accountant/fee_payments.php` - Record a manual fee payment from a student/parent.
- `GET /backend/api/accountant/fee_payments.php` - View history of all recorded fee payments.

### 4. General Ledger & Reporting

- `POST /backend/api/accountant/ledger_entries.php` - Add a manual entry into the general ledger.
- `GET /backend/api/accountant/dashboard_summary.php` - Retrieve calculated financial metrics: Total Revenue, Total Expenses, Pending Payouts, Outstanding Debts.
- `GET /backend/api/accountant/generate_report.php` - Generate a JSON/CSV financial summary report covering a specified date range.

## Core Backend Logic & Security

1. **Authentication & Authorization**: Every endpoint must verify that the user is authenticated and possesses the `accountant` (or `owner`/`super_admin`) role.
2. **Data Integrity**: Enforce constraints so amounts cannot be negative when they shouldn't be, and status updates follow a logical flow (e.g., you cannot "Approve" an already "Paid" invoice).
3. **Audit Logging**: Any write (POST/PUT) action must log which user/accountant made the modification to the database.

## Workflow Execution Steps

1. **Approval**: Wait for user to approve this `.md` plan.
2. **Scaffold APIs**: Build out the PHP endpoint files in `backend/api/accountant/`.
3. **Core Logic**: Write reusable database queries and logic (potentially in `backend/modules/finance/` or directly in the endpoints based on current conventions).
4. **Validation**: Test the API responses to ensure strict JSON output and correct role validation.
5. **Walkthrough**: Explain the finalized backend implementation to the user.
