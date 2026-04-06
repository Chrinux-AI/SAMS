# Accountant Module

The accountant role manages all financial operations, accounting records, and financial reporting for the school.

## Pages & Features

### Dashboard & Core

- **dashboard.php** - Main financial overview with AI insights, key metrics, and quick actions
- **team-selection.php** - Select which team/department accounting context to work in
- **settings.php** - Role-specific settings and preferences

### Financial Management

- **income.php** - Track and manage income sources and fee payments
- **expenses.php** - Record, approve, and manage school expenses
- **payroll.php** - Manage staff payroll, deductions, and salary processing
- **budget.php** - Create, track, and manage annual/departmental budgets
- **ledger.php** - Double-entry bookkeeping ledger with balance tracking

### Financial Reports

- **reports.php** - Generate comprehensive financial reports (income, expenses, aging analysis)
- **balance-sheet.php** - Balance sheet reporting with assets, liabilities, equity
- **profit-loss.php** - Profit and loss statements and financial performance analysis
- **tax-reports.php** - Tax-related reporting and compliance documentation
- **audit-trail.php** - Complete audit history of all financial transactions

## Authorization

All accountant pages require:

1. User to be logged in
2. User role to be either `accountant` or `admin`
3. Proper session setup with tenant_id and user_id

## Database Tables

The accountant module interacts with:

- `fee_payments` - Student payment records
- `expenses` - School expense records
- `expense_approvals` - Expense approval workflow
- `payroll` - Staff salary and payment records
- `ledger_entries` - General ledger bookkeeping entries
- `budget_items` - Budget tracking and forecasts
- `invoices` - Student fee invoices and billing

## URL Routing

All accountant pages use centralized base URL helper:

- `base_url()` - Generates full URL paths relative to app root
- `api_url()` - Generates backend API endpoint URLs
- `asset_url()` - Generates asset file URLs (CSS, JS, images)

Use these helpers instead of hardcoded paths for maintainability.

## Setup Checklist

- [x] All pages validate user role authorization
- [x] Dashboard loads financial metrics and AI insights
- [x] Links use centralized base_url() helpers
- [x] Proper error handling for missing database tables
- [x] Session guards prevent timeout lockouts
- [x] CSRF tokens protect form submissions

## Known Files with Hardcoded Paths

Update these if you change the app base URL:

- `settings.php` - PWA manifest and icon links (fixed: now uses asset_url())

## Module Initialization

When a user with accountant role logs in:

1. User is redirected to `accountant/dashboard.php` via get_role_dashboard_path()
2. Dashboard loads financial statistics and recent transactions
3. AI Financial Bot provides insights and recommendations
4. User can navigate to any accountant sub-page based on permissions

## Future Enhancements

- [ ] Invoice generation and PDF export
- [ ] Recurring payment automation
- [ ] Multi-currency support for international schools
- [ ] Real-time cash flow forecasting
- [ ] Bank reconciliation tools
- [ ] Mobile-optimized financial app
