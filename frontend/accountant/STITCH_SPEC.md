# ACCOUNTANT ROLE - COMPLETE FEATURE INVENTORY

**For Stitch UI/Code Generation**

Date: April 4, 2026
Module: SAMS Accountant System
Status: Ready for UI/Feature Implementation

---

## 📊 PAGES OVERVIEW

### Total Pages: 13

- **Core**: dashboard.php, team-selection.php, settings.php
- **Financial Operations**: expenses.php, income.php, payroll.php, ledger.php, budget.php
- **Financial Reporting**: reports.php, balance-sheet.php, profit-loss.php, tax-reports.php, audit-trail.php

---

## 🎯 DETAILED PAGE SPECIFICATIONS

### 1. **dashboard.php** - Main Financial Dashboard

**Status**: Core Structure Ready | UI/Features: 60% Complete

**Current Functions**:

- [ ] Display financial statistics (total income, expenses, payroll, ledger entries, pending approvals, budget items)
- [ ] Calculate net balance (income - expenses)
- [ ] Fetch recent fee payments (10 most recent)
- [ ] Calculate monthly revenue trend (6 months)
- [ ] AI Financial Advisor widget with fallback
- [ ] Financial health status indicator
- [ ] Payment trend analysis

**Missing/Needed Features**:

- [ ] Dashboard charts (revenue trends, expense breakdown by category)
- [ ] Performance metrics (KPIs)
- [ ] Quick action buttons (Record expense, Generate reports)
- [ ] Cash flow projections
- [ ] Overdue payments alert widget
- [ ] Budget vs. actual comparison card
- [ ] Monthly reconciliation status
- [ ] Tax liability preview
- [ ] Expense approval queue widget
- [ ] Real-time transaction notifications

**Database Tables Used**:

- fee_payments (total_income calculation)
- expenses (total_expenses calculation)
- payroll (payroll_total calculation)
- ledger_entries (count)
- expense_approvals (pending count)
- budget_items (count)

**Helper Functions Needed**:

```php
acc_sum($table, $column, $where, $params) // ✓ EXISTS
acc_count($table, $where, $params) // ✓ EXISTS
format_currency($amount) // ✗ NEEDS CREATION
get_financial_kpis($tenant_id) // ✗ NEEDS CREATION
get_cash_flow_projection($tenant_id, $months) // ✗ NEEDS CREATION
get_expense_breakdown($tenant_id) // ✗ NEEDS CREATION
```

**UI Components Needed**:

- Stat cards (4x) for key metrics
- AI Advisor widget
- Revenue trend chart (Chart.js)
- Expense category pie chart
- Recent transactions table
- Quick action cards
- Budget status bar
- Overdue accounts alert

**API Endpoints**:

- GET /api/accounting/dashboard (main data)
- GET /api/accounting/stats (financial metrics)
- GET /api/accounting/trends (revenue trends)
- GET /api/accounting/alerts (notifications)

---

### 2. **expenses.php** - Expense Management

**Status**: Basic Structure Ready | UI/Features: 40% Complete

**Current Functions**:

- [x] Record new expense via form
- [x] List all expenses (100 most recent)
- [x] Calculate total expenses
- [x] CSRF protection on form
- [x] Display expense categories
- [x] Track payment method

**Missing/Needed Features**:

- [ ] Edit expense form
- [ ] Delete expense functionality
- [ ] Expense approval workflow
- [ ] Expense search/filter
- [ ] Export to CSV/PDF
- [ ] Receipt file upload & storage
- [ ] Bulk expense import
- [ ] Expense notes/comments
- [ ] Budget category tracking
- [ ] Department/cost center assignment
- [ ] Recurring expense templates
- [ ] Expense trend analysis
- [ ] Vendor management
- [ ] Tax classification per expense
- [ ] Expense status (draft, submitted, approved, rejected)

**Database Tables Used**:

- expenses (main table)

**New Database Tables Needed**:

- expense_attachments (for receipts)
- expense_approvals (workflow)
- expense_categories (master list)
- vendors (vendor master)

**Helper Functions Needed**:

```php
create_expense($data) // ✗ NEEDS REFACTORING
edit_expense($id, $data) // ✗ NEEDS CREATION
delete_expense($id) // ✗ NEEDS CREATION
approve_expense($id, $approver_id) // ✗ NEEDS CREATION
get_expense_by_id($id) // ✗ NEEDS CREATION
search_expenses($filters) // ✗ NEEDS CREATION
export_expenses($format, $filters) // ✗ NEEDS CREATION
validate_expense($data) // ✗ NEEDS CREATION
```

**UI Components Needed**:

- Expense form (with all fields)
- Expense table with sorting/filtering
- Expense detail modal
- Receipt viewer
- Status badge (draft/submitted/approved)
- Approval queue for managers
- Bulk action checkboxes
- Export buttons
- Quick filters (category, date range, vendor)
- Search bar

**Form Fields**:

- Date (required)
- Category (select: supplies, utilities, maintenance, salaries, transport, events, other)
- Description (required)
- Amount $ (required, decimal)
- Vendor (optional)
- Payment Method (select: cash, bank transfer, cheque, mobile money)
- Receipt # / Reference (optional)
- Budget Line Item (select from active budget)
- Department (select: admin, teaching, support)
- Notes (textarea optional)
- Attachment (file upload)

**Validation Rules**:

- Amount must be > 0
- Date must be <= today
- Category must be from predefined list
- Description required and min 5 chars
- Amount max 999,999.99

**API Endpoints**:

- GET /api/accounting/expenses (list)
- GET /api/accounting/expenses/{id} (detail)
- POST /api/accounting/expenses (create)
- PUT /api/accounting/expenses/{id} (update)
- DELETE /api/accounting/expenses/{id}
- POST /api/accounting/expenses/{id}/approve
- POST /api/accounting/expenses/export
- GET /api/accounting/expense-categories

---

### 3. **income.php** - Income Management

**Status**: Basic Structure Ready | UI/Features: 35% Complete

**Current Functions**:

- [x] Calculate fee income (total fee payments)
- [x] Fetch other revenue from ledger
- [x] Display recent fee payments (20 most recent)
- [x] Calculate other income total
- [x] Calculate total income

**Missing/Needed Features**:

- [ ] Record other income transaction
- [ ] Income search/filter by student/class
- [ ] Payment status tracking (pending, partial, paid)
- [ ] Outstanding balances per student
- [ ] Payment plans tracking
- [ ] Invoice generation
- [ ] Payment schedule management
- [ ] Discount/refund recording
- [ ] Late payment penalties
- [ ] Receipt generation
- [ ] Payment reminders automation
- [ ] Income by source analysis
- [ ] Student payment history detail
- [ ] Class/Grade income summary
- [ ] Year-over-year income comparison

**Database Tables Used**:

- fee_payments
- ledger_entries
- users (for student names)

**New Database Tables Needed**:

- invoices (student fee invoices)
- payment_plans (for installment options)
- payment_schedules (when payments are due)
- refunds (refund tracking)

**Helper Functions Needed**:

```php
record_income($data) // ✗ NEEDS CREATION
get_student_balance($student_id) // ✗ NEEDS CREATION
get_outstanding_fees($tenant_id) // ✗ NEEDS CREATION
generate_invoice($student_id, $term) // ✗ NEEDS CREATION
get_income_by_class($class_id) // ✗ NEEDS CREATION
get_income_by_source() // ✗ NEEDS CREATION
send_payment_reminder($student_id) // ✗ NEEDS CREATION
record_payment($student_id, $amount, $method) // ✗ NEEDS CREATION
```

**UI Components Needed**:

- Summary cards (fee collections, other revenue, total income)
- Recent payments table
- Outstanding fees list
- Income form (to record other income)
- Student account status (with balance)
- Payment method breakdown
- Income source pie chart
- Class-wise income summary
- Month-over-month income trend
- Invoice generator button
- Payment plan editor

**Form Fields** (for other income):

- Date (required)
- Income Type (select: fee adjustment, donation, grant, service charge, other)
- Description (required)
- Amount $ (required)
- Related Student (optional select)
- Notes (textarea)
- Reference/Receipt # (optional)

**API Endpoints**:

- GET /api/accounting/income (summary)
- GET /api/accounting/income/payments (list)
- GET /api/accounting/income/outstanding (fees due)
- POST /api/accounting/income (record other income)
- GET /api/accounting/students/{id}/balance (student balance)
- GET /api/accounting/income/by-class/{id}
- GET /api/accounting/income/by-source
- POST /api/accounting/invoices/generate

---

### 4. **ledger.php** - General Ledger & Bookkeeping

**Status**: Structure Needed | UI/Features: 0% Complete

**Required Functions**:

- [ ] Display general ledger (double-entry bookkeeping)
- [ ] Create ledger entries (debit/credit)
- [ ] View ledger by account
- [ ] Calculate account balances
- [ ] Trial balance report
- [ ] Journal entry management
- [ ] Search ledger entries
- [ ] Filter by date, account, amount
- [ ] Reconcile entries
- [ ] Audit ledger changes
- [ ] Period/month reconciliation
- [ ] Accrual tracking

**Database Tables Needed**:

- ledger_entries (date, account, debit, credit, description, reference)
- chart_of_accounts (account definitions)
- account_balances (cached balances)

**Helper Functions Needed**:

```php
create_journal_entry($entries_array) // Batch create
get_ledger_entries($filters) // Search/filter
get_account_balance($account_id, $as_of_date) // Balance calculation
calculate_trial_balance($as_of_date) // Trial balance
reconcile_ledger($entries) // Mark as reconciled
get_chart_of_accounts() // List all accounts
validate_journal_entry($data) // Entry validation
post_ledger_entries($entries) // Batch posting
```

**UI Components Needed**:

- Ledger entry form (for manual entries)
- General ledger table (searchable, paginated)
- Chart of accounts tree view
- General ledger report by account
- Trial balance report
- Journal entry detail view
- Reconciliation interface
- Period closing checklist
- Entry search with filters

**Form Fields**:

- Date (required)
- Account (select from chart of accounts)
- Debit Amount (optional, decimal)
- Credit Amount (optional, decimal)
- Description (required)
- Reference (invoice #, check #, etc)
- Department (optional)
- Project/Cost Center (optional)

**Validation Rules**:

- Debits must equal credits (for each journal entry batch)
- Account must exist
- At least one of debit/credit must be > 0
- Description required and min 10 chars

**API Endpoints**:

- GET /api/accounting/ledger (general ledger)
- GET /api/accounting/ledger/accounts (chart of accounts)
- GET /api/accounting/ledger/account/{id}/balance
- POST /api/accounting/ledger/entries (create)
- GET /api/accounting/ledger/trial-balance
- GET /api/accounting/ledger/reconciliation
- PUT /api/accounting/ledger/entries/{id}/reconcile

---

### 5. **budget.php** - Budget Management

**Status**: Structure Needed | UI/Features: 5% Complete

**Required Functions**:

- [ ] Create annual budget
- [ ] Set budget line items
- [ ] Set budget by department
- [ ] Allocate budget amounts
- [ ] Track budget vs. actual
- [ ] Budget variance analysis
- [ ] Monthly budget breakdown
- [ ] Budget reallocation
- [ ] Budget approval workflow
- [ ] Overspend alerts
- [ ] Budget utilization dashboard
- [ ] Historical budget comparison
- [ ] Budget forecasting

**Database Tables Needed**:

- budgets (yearly budget master)
- budget_items (individual line items)
- budget_allocations (department/cost center allocation)
- budget_tracking (actual vs. budget records)

**Helper Functions Needed**:

```php
create_budget($budget_data) // Create year budget
add_budget_item($budget_id, $item) // Add line item
allocate_budget($budget_id, $department, $amount) // Allocation
get_budget_vs_actual($budget_id) // Comparison
get_budget_variance($budget_id) // Variance analysis
update_budget_item($id, $data) // Update
approve_budget($budget_id) // Workflow
calculate_budget_utilization($budget_id) // % used
get_overspend_items($budget_id) // Alerts
```

**UI Components Needed**:

- Budget creation form (date range, total amount)
- Budget line items table (add/edit/delete rows)
- Budget allocation grid (by department)
- Budget vs. actual comparison chart
- Variance analysis table
- Budget utilization gauge
- Monthly breakdown chart
- Department budget summary
- Overspend alerts widget
- Budget history comparison

**Form Fields** (Budget Master):

- Fiscal Year (select)
- Total Budget $ (required)
- Start Date (required)
- End Date (required)
- Status (draft/submitted/approved)
- Description (optional)
- Prepared By (auto-filled)
- Approved By (for workflow)

**Budget Item Sub-form**:

- Line Item Name (required)
- Category (select: salaries, utilities, supplies, maintenance, transport, events, other)
- Budgeted Amount $ (required)
- Department (select)
- Cost Center (select)
- Month-by-month allocation toggle

**Validation Rules**:

- Line items must sum <= total budget or allow overage flag
- Dates must be valid fiscal year
- Amounts must be positive decimals
- Category required for each item

**API Endpoints**:

- GET /api/accounting/budgets (list)
- GET /api/accounting/budgets/{id} (detail)
- POST /api/accounting/budgets (create)
- PUT /api/accounting/budgets/{id} (update)
- DELETE /api/accounting/budgets/{id}
- GET /api/accounting/budgets/{id}/actual (vs actual)
- GET /api/accounting/budgets/{id}/variance
- POST /api/accounting/budgets/{id}/approve

---

### 6. **payroll.php** - Staff Payroll Management

**Status**: Structure Needed | UI/Features: 10% Complete

**Required Functions**:

- [ ] List staff members
- [ ] Set staff salary/allowances
- [ ] Process monthly payroll
- [ ] Calculate deductions (tax, pension)
- [ ] Generate pay slips
- [ ] Track payroll history
- [ ] Salary advance management
- [ ] Tax calculation
- [ ] Pension contributions
- [ ] Payroll variance tracking
- [ ] Late payment tracking
- [ ] Payroll approval workflow
- [ ] Bulk payroll processing
- [ ] Export payroll to bank

**Database Tables Needed**:

- staff_payroll (staff salary records)
- payroll_runs (monthly payroll batches)
- payroll_items (salary components - base, allowances)
- deductions (tax, pension deductions)
- payslips (generated pay slips)
- salary_advances (advance tracking)

**Helper Functions Needed**:

```php
get_staff_list($filters) // Staff list
get_staff_salary($staff_id) // Current salary
calculate_gross_salary($staff_id, $period) // Calculation
calculate_taxes($gross, $bracket) // Tax calc
calculate_deductions($gross) // Deductions
generate_payslip($staff_id, $period) // Payslip generation
process_payroll_run($period, $staff_ids) // Batch processing
calculate_payroll_variance() // Variance
get_payroll_history($staff_id) // History
post_payroll_to_ledger($payroll_run_id) // Accounting entry
```

**UI Components Needed**:

- Staff list table (with salary info)
- Salary setup form
- Monthly payroll processing interface
- Payroll summary (total salaries, deductions)
- Pay slip viewer/generator
- Payroll run history
- Deduction management
- Salary advance approval queue
- Tax summary
- Export to bank format button
- Staff payroll detail view

**Form Fields** (Staff Salary Setup):

- Staff Member (select)
- Base Salary $ (required)
- Allowances (table: housing, transport, meal, other)
- Deductions (table: tax, pension, loan, other)
- Employment Type (select: monthly, hourly, contract)
- Effective Date (required)
- Status (active/inactive)

**Payroll Processing**:

- Pay Period (select: current month)
- Cutoff Date
- Process Payroll button (generates slips)
- Review payroll summary
- Approve button (workflow)
- Export to bank format

**Validation Rules**:

- Base salary must be > 0
- Allowances must be positive
- Deductions cannot exceed gross
- Tax must match statutory tables
- Staff must have valid employment record

**API Endpoints**:

- GET /api/accounting/payroll/staff (list)
- GET /api/accounting/payroll/staff/{id}/salary
- PUT /api/accounting/payroll/staff/{id}/salary (update)
- GET /api/accounting/payroll/runs (history)
- POST /api/accounting/payroll/runs (process)
- GET /api/accounting/payroll/runs/{id}/slips
- GET /api/accounting/payroll/slips/{id} (individual)
- POST /api/accounting/payroll/runs/{id}/approve
- GET /api/accounting/payroll/export/bank-format

---

### 7. **reports.php** - Financial Report Generation

**Status**: Basic Structure Ready | UI/Features: 40% Complete

**Current Functions**:

- [x] CSV export of financial records
- [x] Date range filtering (start/end dates)
- [x] Sample transaction data with fallback
- [x] Calculate total income/expenses
- [x] Calculate net profit
- [x] Transaction count

**Missing/Needed Features**:

- [ ] Monthly Income Statement
- [ ] Balance Sheet
- [ ] Cash Flow Statement
- [ ] Profit & Loss detailed
- [ ] Trial Balance
- [ ] Expense breakdown by category
- [ ] Income breakdown by source
- [ ] Revenue by class/grade
- [ ] Aging analysis (overdue fees)
- [ ] Variance analysis (budget vs actual)
- [ ] Comparative period reports
- [ ] PDF export
- [ ] Email report scheduling
- [ ] Report templates
- [ ] Custom report builder
- [ ] Visual charts/graphs
- [ ] Executive summary

**Database Tables Used**:

- financial_records (main transaction table)
- fee_payments
- expenses

**Helper Functions Needed**:

```php
generate_income_statement($start_date, $end_date) // Income Statement
generate_balance_sheet($as_of_date) // Balance Sheet
generate_cash_flow($start_date, $end_date) // Cash flow
generate_expense_breakdown($start_date, $end_date) // Expense detail
get_expense_by_category($start, $end) // Category breakdown
get_income_by_source($start, $end) // Income detail
get_aging_analysis() // Overdue fees aging
export_to_pdf($report_data, $format) // PDF generation
schedule_report($type, $recipients, $frequency) // Scheduling
generate_comparative_report($period1, $period2) // Comparison
```

**UI Components Needed**:

- Report type selector dropdown
- Date range picker (from/to dates)
- Department filter (optional)
- Report preview table
- Summary statistics boxes
- Detail tables
- Charts/graphs (line, bar, pie)
- Export buttons (CSV, PDF, Excel)
- Print button
- Email button
- Schedule report option
- Report history/archive

**Report Types**:

1. Income Statement (P&L)
2. Balance Sheet
3. Cash Flow Statement
4. Trial Balance
5. Expense Summary by Category
6. Income Summary by Source
7. Revenue by Class/Grade
8. Aging Analysis (Overdue)
9. Budget vs Actual
10. Comparative Period Report
11. Tax Summary
12. Staff Payroll Summary

**API Endpoints**:

- GET /api/accounting/reports/income-statement
- GET /api/accounting/reports/balance-sheet
- GET /api/accounting/reports/cash-flow
- GET /api/accounting/reports/trial-balance
- GET /api/accounting/reports/expenses-summary
- GET /api/accounting/reports/income-summary
- GET /api/accounting/reports/aging-analysis
- POST /api/accounting/reports/export
- POST /api/accounting/reports/schedule
- GET /api/accounting/reports/history

---

### 8. **balance-sheet.php** - Balance Sheet

**Status**: Structure Needed | UI/Features: 0% Complete

**Required Functions**:

- [ ] Display balance sheet (Assets = Liabilities + Equity)
- [ ] Calculate assets total
- [ ] Calculate liabilities total
- [ ] Calculate equity total
- [ ] Verify balance sheet equation
- [ ] Compare to prior period
- [ ] Show as of date
- [ ] Format currency properly
- [ ] Drill-down to detail accounts

**Database Tables Needed**:

- chart_of_accounts (with account type: asset, liability, equity)
- account_balances or calculated from ledger

**Helper Functions Needed**:

```php
generate_balance_sheet($as_of_date) // Main report
get_assets($as_of_date) // Assets section
get_liabilities($as_of_date) // Liabilities section
get_equity($as_of_date) // Equity section
calculate_total_liabilities_equity() // Check equation
get_prior_period_balance_sheet() // Comparison
```

**UI Components Needed**:

- Date selector (as of date)
- Balance sheet table (Assets | Liabilities & Equity)
- Section subtotals
- Grand totals
- Prior period comparison column
- Variance percentage column
- Print button
- PDF export
- Email option

**Report Format**:

```
ASSETS
  Current Assets
    Cash                    $X
    Accounts Receivable     $X
    Prepaid Expenses        $X
  Total Current Assets      $X

  Fixed Assets
    Equipment               $X
    Buildings               $X
  Total Fixed Assets        $X

TOTAL ASSETS                $X

LIABILITIES
  Current Liabilities
    Accounts Payable        $X
    Short-term Loans        $X
  Total Current Liab        $X

  Long-term Liabilities
    Long-term Debt          $X
  Total Long-term Liab      $X

TOTAL LIABILITIES           $X

EQUITY
  Retained Earnings         $X
  Capital                   $X

TOTAL LIABILITIES & EQUITY  $X
```

**API Endpoints**:

- GET /api/accounting/balance-sheet (as of today)
- GET /api/accounting/balance-sheet?date=YYYY-MM-DD

---

### 9. **profit-loss.php** - Profit & Loss Statement

**Status**: Structure Needed | UI/Features: 0% Complete

**Required Functions**:

- [ ] Display income statement/P&L
- [ ] Calculate total revenue
- [ ] Calculate total expenses
- [ ] Calculate gross profit
- [ ] Calculate operating income
- [ ] Calculate net income
- [ ] Show percentages (% of revenue)
- [ ] Compare to prior period
- [ ] Show monthly breakdown
- [ ] Variance analysis

**Database Tables Used**:

- ledger_entries (revenue/expense accounts)
- financial_records (transactions)

**Helper Functions Needed**:

```php
generate_profit_loss($start_date, $end_date) // Main report
get_revenue($start, $end) // Total revenue
get_cost_of_goods_sold($start, $end) // COGS
get_operating_expenses($start, $end) // Opex
get_gross_profit($start, $end) // Gross profit
get_operating_income($start, $end) // Operating income
get_net_income($start, $end) // Bottom line
get_expense_variance($period1, $period2) // Variance
get_monthly_breakdown($year) // Monthly detail
```

**UI Components Needed**:

- Date range picker
- Period comparison toggle
- P&L table (hierarchical)
- Section subtotals with % of revenue
- Monthly breakdown table
- Charts (stacked bar, line)
- Variance analysis
- Print/PDF/Email buttons

**Report Format**:

```
Revenue
  Fee Income              $X  100.0%
  Other Income            $X   X.X%
Total Revenue             $X  100.0%

Operating Expenses
  Salaries & Benefits     $X   XX.X%
  Utilities               $X    X.X%
  Maintenance             $X    X.X%
  Supplies                $X    X.X%
Total Operating Expenses  $X   XX.X%

Operating Income          $X   XX.X%

Other Income/Expense
  Interest Income         $X    X.X%
  Interest Expense       ($X)  (X.X%)
Total Other              ($X)  (X.X%)

Net Income                $X   XX.X%
```

**API Endpoints**:

- GET /api/accounting/profit-loss (current period)
- GET /api/accounting/profit-loss?start=YYYY-MM-DD&end=YYYY-MM-DD

---

### 10. **tax-reports.php** - Tax & Compliance Reporting

**Status**: Structure Needed | UI/Features: 0% Complete

**Required Functions**:

- [ ] Calculate taxable income
- [ ] Track tax deductions
- [ ] Generate tax summary
- [ ] Calculate tax liability
- [ ] VAT/GST tracking
- [ ] Withholding tax calculations
- [ ] Tax payment history
- [ ] Tax compliance checklist
- [ ] Deduction substantiation
- [ ] Tax planning recommendations

**Database Tables Needed**:

- tax_records (tax transactions)
- tax_payments (payment tracking)
- tax_deductions (eligible deductions)

**Helper Functions Needed**:

```php
calculate_taxable_income($period) // Taxable income
calculate_income_tax($income, $bracket_table) // Tax calc
calculate_vat($amount, $rate) // VAT
track_withholding_tax($amount) // Withholding
calculate_quarterly_tax() // Quarterly est
generate_tax_summary($year) // Annual summary
get_tax_payment_history() // Payment history
validate_deductions($items) // Deduction check
calculate_tax_liability() // Total liability
```

**UI Components Needed**:

- Tax summary dashboard
- Taxable income calculation
- Tax liability by type
- Tax payment tracking table
- Deduction substantiation checklist
- Quarterly tax estimate
- Tax rate configuration
- Compliance calendar
- Export to tax filing format
- Print/PDF option

**Data Tracked**:

- Gross Income
- Eligible Deductions
- Taxable Income
- Tax Rate
- Income Tax
- VAT/GST
- Withholding Tax
- Estimated Tax Payments
- Actual Tax Paid
- Tax Balance Due/Refund

**API Endpoints**:

- GET /api/accounting/tax/summary (year-to-date)
- GET /api/accounting/tax/liability
- GET /api/accounting/tax/payments (history)
- POST /api/accounting/tax/payments (record)
- GET /api/accounting/tax/deductions

---

### 11. **audit-trail.php** - Audit Trail & Transaction History

**Status**: Basic Structure Ready | UI/Features: 20% Complete

**Current Functions**:

- [x] Basic structure exists
- [x] Page header and layout

**Missing/Needed Features**:

- [ ] Log all financial transactions
- [ ] Track who made changes
- [ ] Track when changes made
- [ ] Track what was changed (before/after)
- [ ] Reason for changes/approvals
- [ ] Filter by user, date, type
- [ ] Search transactions
- [ ] View transaction detail
- [ ] Export audit log
- [ ] Prevent deletion of audit records
- [ ] Administrator access only
- [ ] Immutable transaction log
- [ ] Compliance reporting

**Database Tables Needed**:

- audit_logs (immutable transaction log)
- audit_log_details (change detail)

**Helper Functions Needed**:

```php
log_transaction($transaction_data, $action, $reason) // Create log
get_audit_trail($filters) // Search/filter
get_transaction_history($transaction_id) // Detail history
track_changes($before, $after) // Difference
verify_immutable_log() // Integrity check
export_audit_log($format, $date_range) // Export
get_user_activity($user_id, $date_range) // By user
```

**UI Components Needed**:

- Filter section (user, date range, transaction type, action)
- Audit log table (sortable, paginated)
  - Date/Time
  - User
  - Transaction Type
  - Transaction ID
  - Action (create/update/delete/approve)
  - Before/After values
  - Reason/Comment
- Detail view modal (full change history)
- Export buttons (CSV, PDF)
- Admin access indicator
- Integrity verification button

**Data Captured**:

- Transaction ID
- Transaction Type (expense, income, payroll, etc)
- Action (created, updated, deleted, approved)
- User ID / Name
- Date/Time (immutable)
- Before value
- After value
- Reason/Approval comment
- IP Address (if available)

**API Endpoints**:

- GET /api/accounting/audit-trail (list)
- GET /api/accounting/audit-trail/{transaction_id} (history)
- GET /api/accounting/audit-trail/user/{user_id} (by user)
- POST /api/accounting/audit-trail/verify (integrity check)
- GET /api/accounting/audit-trail/export

---

### 12. **team-selection.php** - Department/Team Context

**Status**: Structure Needed | UI/Features: 0% Complete

**Required Functions**:

- [ ] List available departments/teams
- [ ] Switch context to selected team
- [ ] Store selected team in session
- [ ] Show current team in header
- [ ] Filter data by selected team
- [ ] Track team membership
- [ ] Display team-specific dashboard
- [ ] Team budgets and limits
- [ ] Team member list
- [ ] Team performance dashboard

**Database Tables Needed**:

- departments/teams (team master)
- team_members (team membership)
- team_budgets (allocated budgets per team)
- team_settings (team configuration)

**Helper Functions Needed**:

```php
get_user_teams($user_id) // Available teams
set_active_team($user_id, $team_id) // Switch context
get_active_team($user_id) // Current team
get_team_info($team_id) // Team detail
get_team_members($team_id) // Team list
get_team_budget($team_id, $year) // Budget
get_team_dashboard($team_id) // Team stats
```

**UI Components Needed**:

- Team selector dropdown or grid
- Current team display (header)
- Team description
- Team members list
- Team budget summary
- Team financial metrics
- Switch team button/link
- Team-specific reports option

**Page Flow**:

1. User lands on team-selection.php
2. Shows list of teams user has access to
3. User clicks team name/button
4. Sets active team in session
5. Redirects to dashboard.php (filtered for team)

**API Endpoints**:

- GET /api/accounting/teams (user's teams)
- GET /api/accounting/teams/{id} (detail)
- POST /api/accounting/teams/select (switch context)
- GET /api/accounting/teams/{id}/members
- GET /api/accounting/teams/{id}/budget

---

### 13. **settings.php** - Role Settings & Preferences

**Status**: Basic Structure Ready | UI/Features: 30% Complete

**Current Functions**:

- [x] Page structure exists
- [x] PWA manifest link (fixed)
- [x] Apple touch icon (fixed)
- [x] Sidebar navigation

**Missing/Needed Features**:

- [ ] Financial settings
- [ ] Tax configuration
- [ ] Currency settings
- [ ] Fiscal year configuration
- [ ] Budget year defaults
- [ ] Approval workflow settings
- [ ] Notification preferences
- [ ] Report template defaults
- [ ] Expense categories customization
- [ ] Payment method configuration
- [ ] Vendor list management
- [ ] Cost center definitions
- [ ] Budget allocation rules
- [ ] Account reconciliation settings
- [ ] User permission configuration
- [ ] Export format preferences

**Settings Sections**:

#### Financial Settings

- Default Currency (select/text)
- Fiscal Year Start Month (select)
- Budget Year (select)
- Accounting Method (accrual / cash)
- Default Rounding (nearest cent/dollar)

#### Tax Settings

- Tax Rate % (decimal)
- VAT/GST Rate % (decimal)
- Tax Year (select)
- Withholding Tax Rate % (decimal)
- Deduction Categories (multi-select)

#### Workflow Settings

- Expense Approval Required (boolean)
- Expense Threshold for Approval $ (number)
- Budget Approval Required (boolean)
- Payroll Approval Required (boolean)
- Approval Chain (email list)

#### Notification Prefs

- Email alerts for:
  - Pending approvals
  - Budget threshold (% exceeded)
  - Payment overdue
  - Payroll processing
  - Reports generated
- Notification frequency (real-time, daily, weekly)

#### Report Settings

- Default report type (select)
- Default date range
- Auto-generate reports? (yes/no)
- Report recipients (email list)
- Report format (PDF, email, printed)

#### Vendor & Category Management

- Add/edit/delete vendors
- Add/edit/delete expense categories
- Add/edit/delete cost centers
- Add/edit/delete bank accounts

**Database Tables Needed**:

- accountant_settings (key-value store)
- vendors (vendor master)
- expense_categories (category master)
- cost_centers (cost center definitions)
- bank_accounts (school bank accounts)
- approval_workflows (custom approval rules)

**Helper Functions Needed**:

```php
get_accountant_setting($key) // Get setting
update_accountant_setting($key, $value) // Update
get_all_settings() // All settings
reset_to_defaults() // Reset
validate_settings($data) // Validation
get_vendors() // Vendor list
add_vendor($data) // Add vendor
delete_vendor($id) // Remove vendor
get_expense_categories() // Category list
get_cost_centers() // Cost center list
```

**UI Components Needed**:

- Tabbed interface (Financial, Tax, Workflow, Notifications)
- Form sections for each setting type
- Toggle switches (for boolean settings)
- Dropdown selects
- Text inputs (numbers, percentages)
- Email list textarea
- Save/Cancel buttons
- Reset to Defaults button
- Vendor/Category management tables
- Add/Edit/Delete modals

**Form Validation**:

- Currency code must be valid
- Tax rates must be 0-100%
- Email addresses must be valid
- Threshold amounts must be > 0
- Fiscal year months 1-12
- All required fields filled

**API Endpoints**:

- GET /api/accounting/settings (all)
- GET /api/accounting/settings/{key}
- PUT /api/accounting/settings/{key}
- POST /api/accounting/settings/restore-defaults
- GET /api/accounting/vendors
- POST /api/accounting/vendors
- DELETE /api/accounting/vendors/{id}
- GET /api/accounting/expense-categories
- POST /api/accounting/expense-categories

---

## 🔌 BACKEND API ENDPOINTS REQUIRED

### Base URL

`/api/accounting` or `/backend/api/accounting`

### Core Endpoints by Feature

**Dashboard**

- GET /dashboard (overview data)
- GET /stats (summary stats)
- GET /trends (revenue trends)

**Expenses**

- GET /expenses (list, with filters)
- POST /expenses (create)
- GET /expenses/{id} (detail)
- PUT /expenses/{id} (update)
- DELETE /expenses/{id} (delete)
- POST /expenses/{id}/approve (workflow)
- POST /expenses/export (CSV/PDF)
- GET /expense-categories (master list)

**Income**

- GET /income (summary)
- GET /income/payments (transaction list)
- GET /income/outstanding (overdue fees)
- POST /income (record other income)
- GET /students/{id}/balance (student account)
- GET /income/by-class/{id} (class income)
- GET /income/by-source (source breakdown)
- POST /invoices/generate (create invoice)

**Payroll**

- GET /payroll/staff (list)
- GET /payroll/staff/{id}/salary (get salary)
- PUT /payroll/staff/{id}/salary (update)
- GET /payroll/runs (history)
- POST /payroll/runs (process)
- GET /payroll/runs/{id}/slips (pay slips)
- GET /payroll/slips/{id} (individual slip)
- POST /payroll/runs/{id}/approve
- GET /payroll/export/bank-format

**Ledger**

- GET /ledger (general ledger)
- GET /ledger/accounts (chart of accounts)
- GET /ledger/account/{id}/balance
- POST /ledger/entries (create)
- GET /ledger/trial-balance
- GET /ledger/reconciliation
- PUT /ledger/entries/{id}/reconcile

**Budget**

- GET /budgets (list)
- POST /budgets (create)
- GET /budgets/{id} (detail)
- PUT /budgets/{id} (update)
- DELETE /budgets/{id} (delete)
- GET /budgets/{id}/actual (vs actual)
- GET /budgets/{id}/variance
- POST /budgets/{id}/approve

**Reports**

- GET /reports/income-statement (P&L)
- GET /reports/balance-sheet
- GET /reports/cash-flow
- GET /reports/trial-balance
- GET /reports/expenses-summary
- GET /reports/income-summary
- GET /reports/aging-analysis
- POST /reports/export (CSV/PDF/Email)
- POST /reports/schedule (recurring)
- GET /reports/history (archive)

**Audit Trail**

- GET /audit-trail (list)
- GET /audit-trail/{transaction_id} (history)
- GET /audit-trail/user/{user_id} (by user)
- POST /audit-trail/verify (integrity)
- GET /audit-trail/export

**Settings**

- GET /settings (all)
- GET /settings/{key}
- PUT /settings/{key}
- POST /settings/restore-defaults
- GET /vendors
- POST /vendors
- DELETE /vendors/{id}
- GET /expense-categories
- POST /expense-categories
- DELETE /expense-categories/{id}

---

## 📊 DATABASE SCHEMA ADDITIONS NEEDED

### Core Tables (Currently Required)

- fee_payments
- expenses
- payroll
- ledger_entries
- budget_items
- users
- class_enrollments
- classes

### New Tables to Create

```sql
-- Expense Management
CREATE TABLE expense_approvals (
  id INT PRIMARY KEY,
  expense_id INT,
  approver_id INT,
  status ENUM('pending','approved','rejected'),
  reason TEXT,
  created_at TIMESTAMP
);

CREATE TABLE expense_attachments (
  id INT PRIMARY KEY,
  expense_id INT,
  file_path VARCHAR(255),
  file_type VARCHAR(50),
  uploaded_at TIMESTAMP
);

CREATE TABLE vendors (
  id INT PRIMARY KEY,
  tenant_id INT,
  name VARCHAR(255),
  contact_email VARCHAR(255),
  phone VARCHAR(20),
  address TEXT,
  tax_id VARCHAR(50),
  payment_terms VARCHAR(100),
  active BOOLEAN,
  created_at TIMESTAMP
);

-- Income Management
CREATE TABLE invoices (
  id INT PRIMARY KEY,
  tenant_id INT,
  student_id INT,
  term VARCHAR(50),
  amount DECIMAL(10,2),
  due_date DATE,
  status ENUM('draft','sent','paid','overdue'),
  created_at TIMESTAMP
);

CREATE TABLE payment_plans (
  id INT PRIMARY KEY,
  student_id INT,
  total_amount DECIMAL(10,2),
  installments INT,
  due_dates JSON,
  status ENUM('active','completed','cancelled'),
  created_at TIMESTAMP
);

CREATE TABLE refunds (
  id INT PRIMARY KEY,
  payment_id INT,
  amount DECIMAL(10,2),
  reason TEXT,
  status ENUM('pending','approved','processed'),
  created_at TIMESTAMP
);

-- Ledger & Bookkeeping
CREATE TABLE chart_of_accounts (
  id INT PRIMARY KEY,
  account_code VARCHAR(20),
  account_name VARCHAR(255),
  account_type ENUM('asset','liability','equity','revenue','expense'),
  active BOOLEAN,
  created_at TIMESTAMP
);

CREATE TABLE account_balances (
  id INT PRIMARY KEY,
  account_id INT,
  period_date DATE,
  debit DECIMAL(12,2),
  credit DECIMAL(12,2),
  balance DECIMAL(12,2),
  updated_at TIMESTAMP
);

-- Budget Management
CREATE TABLE budgets (
  id INT PRIMARY KEY,
  tenant_id INT,
  fiscal_year INT,
  total_amount DECIMAL(12,2),
  start_date DATE,
  end_date DATE,
  status ENUM('draft','submitted','approved'),
  created_by INT,
  created_at TIMESTAMP
);

CREATE TABLE budget_items (
  id INT PRIMARY KEY,
  budget_id INT,
  line_item_name VARCHAR(255),
  category VARCHAR(100),
  budgeted_amount DECIMAL(10,2),
  department VARCHAR(100),
  cost_center VARCHAR(50)
);

CREATE TABLE budget_allocations (
  id INT PRIMARY KEY,
  budget_id INT,
  department VARCHAR(100),
  allocated_amount DECIMAL(10,2),
  month INT
);

-- Payroll
CREATE TABLE staff_payroll (
  id INT PRIMARY KEY,
  staff_id INT,
  base_salary DECIMAL(10,2),
  allowances JSON,
  deductions JSON,
  employment_type VARCHAR(50),
  effective_date DATE,
  active BOOLEAN
);

CREATE TABLE payroll_runs (
  id INT PRIMARY KEY,
  tenant_id INT,
  pay_period VARCHAR(50),
  total_gross DECIMAL(12,2),
  total_deductions DECIMAL(12,2),
  total_net DECIMAL(12,2),
  status ENUM('draft','processed','approved','paid'),
  created_at TIMESTAMP
);

CREATE TABLE payslips (
  id INT PRIMARY KEY,
  payroll_run_id INT,
  staff_id INT,
  gross_salary DECIMAL(10,2),
  deductions JSON (tax, pension, etc),
  net_salary DECIMAL(10,2),
  generated_at TIMESTAMP
);

CREATE TABLE salary_advances (
  id INT PRIMARY KEY,
  staff_id INT,
  amount DECIMAL(10,2),
  reason TEXT,
  status ENUM('requested','approved','rejected','paid'),
  created_at TIMESTAMP
);

-- Tax & Compliance
CREATE TABLE tax_records (
  id INT PRIMARY KEY,
  tenant_id INT,
  tax_type VARCHAR(50),
  period_start DATE,
  period_end DATE,
  taxable_income DECIMAL(12,2),
  tax_amount DECIMAL(10,2),
  status VARCHAR(50)
);

CREATE TABLE tax_payments (
  id INT PRIMARY KEY,
  tax_record_id INT,
  payment_date DATE,
  amount DECIMAL(10,2),
  receipt_number VARCHAR(50),
  payment_method VARCHAR(50)
);

-- Audit Trail
CREATE TABLE audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT,
  user_id INT,
  transaction_type VARCHAR(50),
  transaction_id INT,
  action VARCHAR(50),
  before_value JSON,
  after_value JSON,
  reason TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  INDEX idx_user (user_id),
  INDEX idx_date (created_at),
  INDEX idx_transaction (transaction_type, transaction_id),
  -- Immutable constraints
  CONSTRAINT no_delete CHECK (1=1) -- Prevent deletion
);

-- Settings
CREATE TABLE accountant_settings (
  id INT PRIMARY KEY,
  tenant_id INT,
  setting_key VARCHAR(100),
  setting_value TEXT,
  updated_at TIMESTAMP
);

CREATE TABLE cost_centers (
  id INT PRIMARY KEY,
  tenant_id INT,
  code VARCHAR(50),
  name VARCHAR(255),
  description TEXT,
  active BOOLEAN
);

CREATE TABLE bank_accounts (
  id INT PRIMARY KEY,
  tenant_id INT,
  account_name VARCHAR(255),
  account_number VARCHAR(50),
  bank_name VARCHAR(255),
  account_type VARCHAR(50),
  active BOOLEAN
);
```

---

## 🎨 UI/DESIGN REQUIREMENTS

### Common Components

- Consistent color scheme (emerald/green for positive, red for negative)
- Material Design icons (material-symbols)
- Responsive grid layout
- Dark mode support (theme-loader.js)
- Mobile-friendly tables
- Accessible forms (labels, validation)
- Loading states
- Error handling with user-friendly messages
- Breadcrumb navigation
- Date pickers (flatpickr or similar)
- Number formatters (currency, percentages)
- Charts library (Chart.js recommended)

### Layout Requirements

- Sidebar navigation (already implemented)
- Top page header with icon and title
- Content area with responsive grid
- Status badges for workflow states
- Action buttons consistent styling
- Modal dialogs for detail views
- Confirmation dialogs for destructive actions
- Toast notifications for feedback

---

## 📱 RESPONSIVE DESIGN

### Breakpoints

- Mobile: < 640px (single column)
- Tablet: 640px - 1024px (2 columns)
- Desktop: > 1024px (3-4 columns)
- Wide: > 1280px (4+ columns)

### Table Scrolling

- Horizontal scroll on mobile
- Full width on desktop
- Sticky headers
- Sticky first column (optional)

---

## 🔐 SECURITY REQUIREMENTS

### Authentication

- [x] Require login (require_login())
- [x] Role check (has_role('accountant'))
- [x] CSRF tokens on all forms
- [ ] Session timeout management
- [ ] IP address validation (possible)

### Authorization

- [ ] Function-level permissions
- [ ] Data isolation by tenant_id
- [ ] Role-based view filtering
- [ ] Approval workflow enforcement

### Data Protection

- [ ] Immutable audit log
- [ ] Encryption for sensitive data
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] CORS validation

---

## 🚀 IMPLEMENTATION PRIORITY

### Phase 1 (Core - Week 1)

1. Dashboard enhancements (charts, KPIs)
2. Expenses complete CRUD + approval
3. Income tracking with student balances
4. Basic Reports (P&L, Balance Sheet)

### Phase 2 (Financial Management - Week 2)

5. Ledger & double-entry bookkeeping
6. Budget management with tracking
7. Payroll system with pay slip generation
8. Tax calculations

### Phase 3 (Reporting & Compliance - Week 3)

9. Advanced reports (cash flow, aging analysis)
10. Audit trail & compliance
11. Settings & configuration
12. Team/department context

### Phase 4 (Polish & Integration - Week 4)

13. Mobile optimization
14. Performance optimization
15. Security hardening
16. Testing & QA

---

## 🔗 INTEGRATION POINTS

### With Frontend

- Use helper functions: base_url(), asset_url(), api_url()
- Include sidebar-nav.php for consistent navigation
- Respect session variables: user_id, tenant_id, role
- Use INCLUDES_PATH constant for includes

### With Backend

- API endpoint responses (JSON format)
- Database functions: db()->fetchAll(), db()->fetchOne()
- Error handling with try-catch blocks
- Tenant isolation (filter by tenant_id)

### With SAMS-AI

- Financial Bot integration (SAMS_FinancialBot class)
- Fallback when AI unavailable
- Caching of AI insights
- Error handling for AI service outages

---

## ✅ TESTING CHECKLIST

- [ ] All forms validate correctly
- [ ] All API endpoints return correct data
- [ ] Role authorization enforced
- [ ] CSRF protection working
- [ ] Responsive on mobile/tablet/desktop
- [ ] Charts render correctly
- [ ] PDF export works
- [ ] CSV export works
- [ ] Email export works
- [ ] Session timeout handled
- [ ] Error messages user-friendly
- [ ] Audit trail logging all changes
- [ ] Performance acceptable (< 2s load)
- [ ] Accessibility standards met
- [ ] Dark mode works
- [ ] Print layout optimized

---

**Status**: Ready for Stitch UI Generation
**Last Updated**: April 4, 2026
**Next Step**: Feed this spec to Stitch tool for UI/code generation
