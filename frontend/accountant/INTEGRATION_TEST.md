# Accountant Role Integration Test Guide

## Pre-Test Requirements

### Database Setup

Ensure these tables exist with proper schema:

- `users` - User accounts with role assignments
- `fee_payments` - Student fee payment records
- `expenses` - School expense records
- `expense_approvals` - Expense approval workflow
- `payroll` - Staff salary records
- `ledger_entries` - General ledger entries
- `budget_items` - Budget tracking items
- `class_enrollments` - Student class assignments
- `classes` - Class/grade information

### User Setup

Create a test accountant user:

```sql
INSERT INTO users (
  email, password, first_name, last_name,
  role, status, created_at, tenant_id
) VALUES (
  'accountant@test.local',
  PASSWORD('password123'),
  'Test',
  'Accountant',
  'accountant',
  'active',
  NOW(),
  1
);
```

### Frontend Configuration

Verify these config values in `frontend/includes/config.php`:

- `APP_URL = 'http://localhost/attendance'`
- `BASE_PATH = __DIR__ . '/..` (should point to frontend/)
- `INCLUDES_PATH = BASE_PATH . '/includes'`

## Test Scenarios

### 1. Login & Role Redirect

**Test**: User logs in with accountant credentials
**Expected**: User redirected to `/accountant/dashboard.php`
**Step by step**:

1. Navigate to http://localhost/attendance/login.php
2. Enter accountant@test.local / password123
3. Submit login form
4. Verify redirect to accountant dashboard

**Code Path**:

- login.php → checks role via `has_role()` → calls `redirect(get_role_dashboard_path('accountant'))`
- get_role_dashboard_path() returns `base_url('accountant/dashboard.php')`
- base_url() helper returns `http://localhost/attendance/accountant/dashboard.php`

### 2. Dashboard Load & Session Guards

**Test**: Dashboard loads with valid session
**Expected**: Dashboard renders with financial metrics
**Checks**:

1. `require_login()` passes (user is logged in)
2. `has_role('accountant')` returns true
3. `$_SESSION['user_id']` is set
4. `$_SESSION['tenant_id']` is set
5. Database queries execute without error
6. AI insights fallback works if bot class unavailable

**Debug Points**:

- Check browser console for CSS/JS asset loading errors
- Verify manifest.json loads: `asset_url('manifest.json')`
- Verify icon loads: `asset_url('images/icons/icon-192x192.png')`

### 3. Navigation & Links

**Test**: Sidebar navigation displays all accountant sections
**Expected**: 6 sections visible with correct links
**Sections**:

1. Main (Dashboard, Team Selection)
2. Finance (Ledger, Expenses, Income, Payroll)
3. Statements (Balance Sheet, P&L, Tax Reports, Budget)
4. Reports (Reports, Audit Trail)
5. Communication (Messages, Notices, Settings)

**Check each link**:

- Click Dashboard → `/accountant/dashboard.php`
- Click Expenses → `/accountant/expenses.php`
- Click Reports → `/accountant/reports.php`
- Click Settings → `/accountant/settings.php`

**URL Verification**:

- All links should use `base_url()` helper
- No hardcoded `/attendance/` paths remaining
- Links should be relative to current role directory

### 4. Settings Page Asset Loading

**Test**: Settings page loads correctly with PWA manifest
**Expected**: No console errors, PWA metadata loaded

**Checks** (View Page Source):

- `<link rel="manifest" href="http://localhost/attendance/manifest.json">` ✓
- `<link rel="apple-touch-icon" href="http://localhost/attendance/assets/images/icons/icon-192x192.png">` ✓
- No hardcoded `/attendance/` paths visible ✓

### 5. API Calls

**Test**: Dashboard fetches financial data
**Expected**: Financial metrics display on dashboard

**Network Check** (DevTools → Network tab):

- `/api/accounting/dashboard` calls succeed
- `/api/accounting/expenses` calls work
- `/api/accounting/income` calls work
- Responses return proper JSON data

### 6. Session Persistence

**Test**: Multiple page navigations maintain session
**Expected**: User stays logged in across pages

**Steps**:

1. Load dashboard
2. Navigate to expenses.php
3. Navigate to reports.php
4. Navigate back to dashboard
5. Verify all pages load without re-login prompt

### 7. Mobile & PWA

**Test**: Mobile view and PWA installation
**Expected**: Dashboard responsive, PWA installable

**Mobile Test**:

- Open DevTools → Toggle Device Toolbar
- Test tablets (768px) and phones (375px)
- Verify all grid layouts stack properly
- Check button/form sizes

**PWA Test**:

- Open DevTools → Application tab
- Check Manifest is valid
- Check Service Worker registered
- Attempt "Install" (Chrome may offer it)

### 8. Error Handling

**Test**: Graceful degradation if database unavailable
**Expected**: Dashboard shows cached data or fallback values

**Steps**:

1. Stop database temporarily
2. Reload dashboard
3. Verify AI insights fallback loads
4. Verify error doesn't crash page
5. Restart database

## Common Issues & Fixes

### Issue: "Access denied" after login

**Cause**: User role not 'accountant' or 'admin'
**Fix**: Verify user role in database

```sql
SELECT id, email, role FROM users WHERE email = 'accountant@test.local';
UPDATE users SET role = 'accountant' WHERE email = 'accountant@test.local';
```

### Issue: Sidebar navigation not showing

**Cause**: INCLUDES_PATH not defined or sidebar-nav.php not found
**Fix**: Verify `frontend/includes/config.php` defines INCLUDES_PATH

```php
echo defined('INCLUDES_PATH') ? 'OK' : 'MISSING';
```

### Issue: Assets not loading (404 errors)

**Cause**: asset_url() not working correctly
**Fix**: Verify base_url() helper works

```php
echo base_url('assets/css/styles.css');
// Should output: http://localhost/attendance/assets/css/styles.css
```

### Issue: Database queries fail

**Cause**: Table doesn't exist or incorrect tenant_id filter
**Fix**: Check database connection and available tables

```sql
SHOW TABLES LIKE 'fee_payments';
SHOW TABLES LIKE 'expenses';
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fee_payments';
```

### Issue: Session expires immediately

**Cause**: Session cookie not being set correctly
**Fix**: Check session configuration in `frontend/includes/config.php`

```php
session_save_path(); // Should return valid path
ini_get('session.cookie_lifetime'); // Should be > 0
```

## Performance Baseline

Expected load times:

- Dashboard render: < 2 seconds
- Settings page: < 1 second
- Reports generation: < 5 seconds
- Asset loading: < 1 second total

## Success Criteria Checklist

- [ ] Accountant user can login
- [ ] Dashboard redirects from login work
- [ ] Dashboard loads without errors
- [ ] All navigation links visible
- [ ] Links use base_url() helpers
- [ ] No hardcoded /attendance/ paths
- [ ] Settings page assets load
- [ ] API calls to financial data work
- [ ] Session persists across pages
- [ ] Mobile view functional
- [ ] Error handling graceful
- [ ] Performance acceptable

## Continuation Plan

After successful accountant role testing:

1. Apply same path fixes to other roles (teacher, student, librarian, etc.)
2. Build and test each role module
3. Complete remaining role feature implementations
4. System-wide integration testing
5. Production deployment

---

**Last Updated**: February 2025
**Tested with**: PHP 7.4+, XAMPP, MySQL 5.7+
**Status**: Ready for Integration Testing
