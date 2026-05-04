# Accountant Portal Enhancement Plan

## 1. Objective

Enhance the SAMS Accountant portal by fixing existing alignment/theme issues, adding global currency selection, incorporating financial tools (currency converter, calculator), and implementing full accountant and financial management features as extracted from typical robust school management systems (GitHub repo features).

## 2. Phase 1: Theme & Layout Alignment

- **Task:** Standardize `frontend/accountant/dashboard.php` and sub-pages (`payroll.php`, `budget.php`, etc.) using the uniform layout shell (`partials/atlas-shell.php` or `master-dashboard.php`, whichever is the canonical one for the new UX).
- **Task:** Ensure the theme toggle switch aligns correctly in the top navigation or sidebar alongside the rest of the application.

## 3. Phase 2: Global Currency Management

- **Task:** Implement a global currency drop-down selector (e.g., USD, EUR, GBP, NGN, KES) within the top navigation bar or settings sidebar.
- **Task:** Save selected currency in `$_SESSION['preferred_currency']` via an AJAX endpoint (`api/set_currency.php`).
- **Task:** Create a central PHP formater function `format_currency($amount)` in `includes/functions.php` to format numbers dynamically based on the session's active currency.
- **Task:** Update all financial tables and data grids in the accountant portal to pass numeric values through `format_currency()`.

## 4. Phase 3: Dashboard Financial Tools

- **Task:** Embed a fast, interactive Calculator widget into the Accountant Dashboard.
- **Task:** Build a Currency Converter widget in the dashboard that uses real-time (or cached/static mocked) exchange rates to help accountants compute values instantly.

## 5. Phase 4: Full Features Implementation (Based on Extracted Data)

Implement modules mapped to `accountant` & `bursar` roles:

- **Income vs Expense Management:** (e.g., `budget.php`, `expenses.php`) Tracking operational costs.
- **Payroll Management:** Enhance `payroll.php` to handle Staff Salaries, Deductions, and Allowances.
- **Fee Management (Invoicing):** Views to see uncollected/collected tuition fees, generate payment receipts, and record offline payments.
- **Financial Reporting & Analytics:** Visual charts showing the cash flow over time on the dashboard.

## Process Workflow

1. Approval of this plan.
2. Generate Todos.
3. Iterative Implementation starting with Phase 1 (UI/Theme).
4. Phase 2 (Currency logic).
5. Phase 3 (Tools).
6. Phase 4 (Full System Features).
7. Final Review & Walkthrough.
