<?php

/**
 * SAMS Accountant Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with financial insights and AI-powered features
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
  redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$accountant_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'] ?? 'Accountant';

// Get financial statistics
$financial_stats = [
  'total_income' => acc_sum('fee_payments', 'amount'),
  'total_expenses' => acc_sum('expenses', 'amount'),
  'payroll_total' => acc_sum('payroll', 'amount'),
  'ledger_entries' => acc_count('ledger_entries'),
  'pending_approvals' => acc_count('expense_approvals', "status = 'pending'"),
  'budget_items' => acc_count('budget_items'),
];

$net = $financial_stats['total_income'] - $financial_stats['total_expenses'];

// Get recent transactions
$recent_transactions = [];
try {
  if (table_exists('fee_payments')) {
    $recent_transactions = db()->fetchAll("
        SELECT fr.*, u.first_name, u.last_name, c.class_name
        FROM fee_payments fr
        LEFT JOIN users u ON fr.student_id = u.id
        LEFT JOIN class_enrollments ce ON u.id = ce.student_id
        LEFT JOIN classes c ON ce.class_id = c.id
        WHERE fr.tenant_id = ?
        ORDER BY fr.created_at DESC
        LIMIT 10
    ", [$tenantId]) ?: [];
  }
} catch (Throwable $e) {
  error_log('Accountant dashboard recent transactions error: ' . $e->getMessage());
}

// Get monthly revenue trend
$revenue_trend = [];
try {
  if (table_exists('fee_payments')) {
    $revenue_trend = db()->fetchAll("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as revenue,
            COUNT(*) as transaction_count
        FROM fee_payments
        WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$tenantId]) ?: [];
  }
} catch (Throwable $e) {
  error_log('Accountant dashboard revenue trend error: ' . $e->getMessage());
}

// AI Financial Insights (strictly database-backed, no fabricated assumptions)
$paidTransactionCount = 0;
$overdueStudentFees = 0.0;
$expenseMonthTotal = 0.0;
$incomeMonthTotal = 0.0;

try {
  if (table_exists('fee_payments')) {
    $paidTransactionCount = (int)(db()->fetchOne(
      "SELECT COUNT(*) AS c FROM fee_payments WHERE tenant_id = ? AND status = 'paid'",
      [$tenantId]
    )['c'] ?? 0);

    $incomeMonthTotal = (float)(db()->fetchOne(
      "SELECT COALESCE(SUM(amount), 0) AS total FROM fee_payments WHERE tenant_id = ? AND status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')",
      [$tenantId]
    )['total'] ?? 0);

    if (function_exists('table_has_column') && table_has_column('fee_payments', 'balance')) {
      $overdueStudentFees = (float)(db()->fetchOne(
        "SELECT COALESCE(SUM(balance), 0) AS total FROM fee_payments WHERE tenant_id = ? AND status != 'paid'",
        [$tenantId]
      )['total'] ?? 0);
    }
  }

  if (table_exists('expenses')) {
    $expenseMonthTotal = (float)(db()->fetchOne(
      "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE tenant_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')",
      [$tenantId]
    )['total'] ?? 0);
  }
} catch (Throwable $e) {
  error_log('Accountant dashboard AI metrics error: ' . $e->getMessage());
}

$ai_insights = [
  'financial_health' => $net > 0 ? 'excellent' : ($net === 0.0 ? 'balanced' : 'needs_attention'),
  'payment_trend' => $incomeMonthTotal >= $expenseMonthTotal ? 'positive_cashflow' : 'negative_cashflow',
  'recommendation' => '',
  'evidence' => [
    'paid_transactions' => $paidTransactionCount,
    'pending_approvals' => (int)$financial_stats['pending_approvals'],
    'month_income' => $incomeMonthTotal,
    'month_expenses' => $expenseMonthTotal,
    'overdue_fees' => $overdueStudentFees,
  ],
  'generated_at' => date('Y-m-d H:i:s'),
  'is_db_backed' => true,
];

if ($net < 0) {
  $ai_insights['recommendation'] = 'Verified from current database totals: expenses are higher than income. Prioritize expense controls and improve collections.';
} elseif ((int)$financial_stats['pending_approvals'] > 0) {
  $ai_insights['recommendation'] = 'Verified from live records: there are pending expense approvals that should be reviewed to keep reports up to date.';
} elseif ($overdueStudentFees > 0) {
  $ai_insights['recommendation'] = 'Verified from unpaid fee balances: follow up overdue accounts to protect cash flow.';
} else {
  $ai_insights['recommendation'] = 'Verified from current ledger totals: financial position is stable. Continue routine monitoring and reconciliation.';
}

$csrf = generate_csrf_token();
$saved_theme = null;
try {
  if (function_exists('table_has_column') && table_has_column('users', 'theme')) {
    $themeRow = db()->fetchOne("SELECT theme FROM users WHERE id = ? LIMIT 1", [$accountant_id]);
    $candidateTheme = strtolower((string)($themeRow['theme'] ?? ''));
    if (in_array($candidateTheme, ['light', 'dark'], true)) {
      $saved_theme = $candidateTheme;
    }
  }
} catch (Throwable $e) {
  error_log('Accountant dashboard theme read error: ' . $e->getMessage());
}
$budgetUtilization = $financial_stats['total_income'] > 0
  ? (int)min(100, round(($financial_stats['total_expenses'] / max(1, $financial_stats['total_income'])) * 100))
  : 0;

$trend_points = [];
if (!empty($revenue_trend)) {
  foreach (array_reverse($revenue_trend) as $point) {
    $monthRaw = (string)($point['month'] ?? '');
    $monthLabel = 'N/A';
    if ($monthRaw !== '') {
      $ts = strtotime($monthRaw . '-01');
      if ($ts !== false) {
        $monthLabel = strtoupper(date('M', $ts));
      }
    }
    $trend_points[] = [
      'month' => $monthLabel,
      'value' => (float)($point['revenue'] ?? 0),
    ];
  }
}

$trendMax = 1;
foreach ($trend_points as $trendPoint) {
  if ((float)$trendPoint['value'] > $trendMax) {
    $trendMax = (float)$trendPoint['value'];
  }
}

$accountant_notifications = [];
$accountant_unread_notifications = 0;
try {
  if (function_exists('table_exists') && table_exists('notifications')) {
    $accountant_notifications = db()->fetchAll(
      "SELECT id, title, message, category, is_read, created_at
       FROM notifications
       WHERE user_id = ?
       ORDER BY created_at DESC
       LIMIT 5",
      [$accountant_id]
    ) ?: [];
    $accountant_unread_notifications = (int) ((db()->fetchOne(
      "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0",
      [$accountant_id]
    )['c'] ?? 0));
  }
} catch (Throwable $e) {
  error_log('Accountant dashboard notification load error: ' . $e->getMessage());
}

$expenseBreakdown = [];
$budgetUtilizationRows = [];
$overdueCards = [];
$expenseChartSegments = [0, 0, 0];
$expenseChartOffsets = [0, 0, 0];

try {
  if (table_exists('expenses')) {
    $expenseBreakdown = db()->fetchAll(
      "SELECT COALESCE(NULLIF(TRIM(category), ''), 'other') AS category, COALESCE(SUM(amount), 0) AS total
       FROM expenses
       WHERE tenant_id = ?
       GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'other')
       ORDER BY total DESC
       LIMIT 3",
      [$tenantId]
    ) ?: [];

    $budgetUtilizationRows = db()->fetchAll(
      "SELECT COALESCE(NULLIF(TRIM(category), ''), 'other') AS category, COALESCE(SUM(amount), 0) AS total
       FROM expenses
       WHERE tenant_id = ?
         AND DATE_FORMAT(COALESCE(expense_date, created_at), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
       GROUP BY COALESCE(NULLIF(TRIM(category), ''), 'other')
       ORDER BY total DESC
       LIMIT 3",
      [$tenantId]
    ) ?: [];
  }

  $expenseTotalForBreakdown = 0.0;
  foreach ($expenseBreakdown as $row) {
    $expenseTotalForBreakdown += (float)($row['total'] ?? 0);
  }

  if ($expenseTotalForBreakdown > 0) {
    for ($i = 0; $i < min(3, count($expenseBreakdown)); $i++) {
      $pct = max(0.0, min(100.0, (((float)$expenseBreakdown[$i]['total']) / $expenseTotalForBreakdown) * 100));
      $expenseChartSegments[$i] = round($pct, 2);
    }
  }

  $runningOffset = 0.0;
  for ($i = 0; $i < 3; $i++) {
    $expenseChartOffsets[$i] = -$runningOffset;
    $runningOffset += (float)$expenseChartSegments[$i];
  }

  if (empty($budgetUtilizationRows)) {
    $budgetUtilizationRows = $expenseBreakdown;
  }

  $budgetMonthTotal = 0.0;
  foreach ($budgetUtilizationRows as $row) {
    $budgetMonthTotal += (float)($row['total'] ?? 0);
  }

  foreach ($budgetUtilizationRows as &$row) {
    $rowTotal = (float)($row['total'] ?? 0);
    $row['pct'] = $budgetMonthTotal > 0 ? (int)round(($rowTotal / $budgetMonthTotal) * 100) : 0;
  }
  unset($row);

  $overdueFeeCount = 0;
  $overdueFeeTotal = 0.0;
  if (table_exists('fee_payments')) {
    $balanceCol = function_exists('table_has_column') && table_has_column('fee_payments', 'balance') ? 'balance' : 'amount';
    $overdueFeeCount = (int)(db()->fetchOne(
      "SELECT COUNT(*) AS c FROM fee_payments WHERE tenant_id = ? AND status != 'paid'",
      [$tenantId]
    )['c'] ?? 0);
    $overdueFeeTotal = (float)(db()->fetchOne(
      "SELECT COALESCE(SUM({$balanceCol}), 0) AS t FROM fee_payments WHERE tenant_id = ? AND status != 'paid'",
      [$tenantId]
    )['t'] ?? 0);
  }

  $pendingApprovalCount = 0;
  $pendingApprovalTotal = 0.0;
  if (table_exists('expense_approvals')) {
    $pendingApprovalCount = (int)(db()->fetchOne(
      "SELECT COUNT(*) AS c FROM expense_approvals WHERE tenant_id = ? AND status = 'pending'",
      [$tenantId]
    )['c'] ?? 0);

    if (function_exists('table_has_column') && table_has_column('expense_approvals', 'amount')) {
      $pendingApprovalTotal = (float)(db()->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) AS t FROM expense_approvals WHERE tenant_id = ? AND status = 'pending'",
        [$tenantId]
      )['t'] ?? 0);
    }
  }

  $overdueCards = [
    [
      'title' => 'Outstanding Fees',
      'meta' => $overdueFeeCount . ' Unpaid Records',
      'amount' => $overdueFeeTotal,
    ],
    [
      'title' => 'Pending Expense Approvals',
      'meta' => $pendingApprovalCount . ' Pending Requests',
      'amount' => $pendingApprovalTotal,
    ],
  ];
} catch (Throwable $e) {
  error_log('Accountant dashboard financial block error: ' . $e->getMessage());
}

// Helper functions
function acc_count($table, $where = '1=1', $params = [])
{
  try {
    if (!table_exists($table)) return 0;
    return (int)db()->count($table, $where, $params);
  } catch (Throwable $e) {
    return 0;
  }
}
function acc_sum($table, $col, $where = '1=1', $params = [])
{
  try {
    if (!table_exists($table)) return 0;
    $r = db()->fetchOne("SELECT COALESCE(SUM($col),0) AS total FROM $table WHERE $where", $params);
    return (float)($r['total'] ?? 0);
  } catch (Throwable $e) {
    return 0;
  }
}

// Master layout configuration
$page_title = 'Accountant Dashboard';
?>
<!doctype html>
<html class="light" lang="en">

<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta name="csrf-token" content="<?php echo htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8'); ?>" />
  <script src="<?php echo htmlspecialchars('/attendance/assets/js/theme-loader.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
  <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars((string)APP_NAME); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#1868DB",
            "primary-container": "#D6E4FF",
            "on-primary": "#FFFFFF",
            "on-primary-container": "#001B3D",
            secondary: "#545F71",
            "secondary-container": "#D9E3F8",
            "on-secondary": "#FFFFFF",
            "on-secondary-container": "#111C2B",
            tertiary: "#8F4C00",
            "tertiary-container": "#FFDCC0",
            "on-tertiary": "#FFFFFF",
            error: "#BA1A1A",
            "error-container": "#FFDAD6",
            surface: "#FDFBFF",
            "surface-container-low": "#F7F9FF",
            "surface-container": "#F1F4FA",
            "surface-container-high": "#EBEFF5",
            "surface-container-highest": "#E2E7EF",
            outline: "#73777F",
            "outline-variant": "#C3C7CF",
            background: "#FDFBFF",
            "on-background": "#1A1C1E",
            "on-surface": "#1A1C1E",
            "on-surface-variant": "#43474E"
          },
          borderRadius: {
            DEFAULT: "0.75rem",
            lg: "1rem",
            xl: "1.25rem",
            full: "9999px"
          },
          fontFamily: {
            headline: ["Manrope", "sans-serif"],
            body: ["Manrope", "sans-serif"],
            label: ["Manrope", "sans-serif"]
          }
        }
      }
    };
  </script>
  <style>
    body {
      font-family: 'Manrope', sans-serif;
    }

    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
    }

    .tabular-nums {
      font-variant-numeric: tabular-nums;
    }

    .rounded-atlas {
      border-radius: 1rem;
    }

    html.dark body,
    html[data-theme="dark"] body {
      background: #0b1220 !important;
      color: #e5e7eb !important;
    }

    html.dark .bg-surface,
    html[data-theme="dark"] .bg-surface {
      background: #0b1220 !important;
    }

    html.dark .bg-white,
    html[data-theme="dark"] .bg-white {
      background: #111827 !important;
    }

    html.dark .text-on-surface,
    html[data-theme="dark"] .text-on-surface {
      color: #e5e7eb !important;
    }

    html.dark .text-secondary,
    html.dark .text-outline,
    html[data-theme="dark"] .text-secondary,
    html[data-theme="dark"] .text-outline {
      color: #9ca3af !important;
    }

    html.dark [class*="bg-surface-container"],
    html[data-theme="dark"] [class*="bg-surface-container"] {
      background: #1f2937 !important;
    }

    html.dark [class*="border-outline-variant"],
    html[data-theme="dark"] [class*="border-outline-variant"] {
      border-color: rgba(156, 163, 175, 0.35) !important;
    }

    html.dark .shadow-sm,
    html.dark .shadow-md,
    html.dark .shadow-lg,
    html[data-theme="dark"] .shadow-sm,
    html[data-theme="dark"] .shadow-md,
    html[data-theme="dark"] .shadow-lg {
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45) !important;
    }

    html.dark .bg-primary-container,
    html[data-theme="dark"] .bg-primary-container {
      background: rgba(37, 99, 235, 0.32) !important;
    }

    html.dark .text-on-primary-container,
    html[data-theme="dark"] .text-on-primary-container {
      color: #eff6ff !important;
    }

    html.dark .text-primary,
    html[data-theme="dark"] .text-primary {
      color: #93c5fd !important;
    }

    .text-on-error-container {
      color: #410e0b;
    }

    .bg-error-container {
      background: #ffd9d6;
    }

    .accountant-icon-btn {
      width: 2.5rem;
      height: 2.5rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 9999px;
      border: 1px solid transparent;
      background: transparent;
      transition: all 160ms ease;
    }

    .accountant-icon-btn:hover {
      background: #eef3ff;
      border-color: rgba(24, 104, 219, 0.2);
      color: #1868db;
    }

    .accountant-dropdown-card {
      background: #ffffff;
      border: 1px solid rgba(195, 199, 207, 0.5);
      box-shadow: 0 24px 44px rgba(15, 23, 42, 0.16);
    }

    .accountant-side-action {
      position: relative;
      overflow: hidden;
      border: 1px solid transparent;
      border-radius: 0.85rem;
      background: transparent;
    }

    .accountant-side-action::before {
      content: '';
      position: absolute;
      inset: 0;
      opacity: 0;
      transition: opacity 160ms ease;
      background: linear-gradient(135deg, rgba(24, 104, 219, 0.12), rgba(24, 104, 219, 0.02));
      pointer-events: none;
    }

    .accountant-side-action:hover {
      border-color: rgba(24, 104, 219, 0.25);
      color: #1868db !important;
    }

    .accountant-side-action:hover::before {
      opacity: 1;
    }

    .accountant-side-action>* {
      position: relative;
      z-index: 1;
    }

    .accountant-side-action.accountant-side-action-danger:hover {
      border-color: rgba(186, 26, 26, 0.25);
      color: #ba1a1a !important;
    }

    .accountant-side-action.accountant-side-action-danger::before {
      background: linear-gradient(135deg, rgba(186, 26, 26, 0.16), rgba(186, 26, 26, 0.03));
    }

    .accountant-theme-option {
      border: 1px solid rgba(195, 199, 207, 0.6);
    }

    .accountant-theme-option.active {
      border-color: rgba(24, 104, 219, 0.55);
      background: rgba(214, 228, 255, 0.45);
    }

    .accountant-overlay-scrim {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.2);
      backdrop-filter: blur(2px);
      -webkit-backdrop-filter: blur(2px);
      opacity: 0;
      pointer-events: none;
      transition: opacity 160ms ease;
      z-index: 35;
    }

    .accountant-overlay-scrim.active {
      opacity: 1;
      pointer-events: auto;
    }

    .accountant-topbar {
      z-index: 80;
      isolation: isolate;
      border-bottom-color: rgba(148, 163, 184, 0.28);
      box-shadow: 0 8px 24px rgba(2, 6, 23, 0.14);
    }

    html.dark .accountant-icon-btn:hover,
    html[data-theme="dark"] .accountant-icon-btn:hover {
      background: rgba(55, 65, 81, 0.82);
      border-color: rgba(148, 163, 184, 0.35);
      color: #bfdbfe;
    }

    html.dark .accountant-icon-btn,
    html[data-theme="dark"] .accountant-icon-btn {
      background: rgba(15, 23, 42, 0.34);
      border-color: rgba(100, 116, 139, 0.28);
      color: #cbd5e1;
    }

    html.dark .accountant-topbar,
    html[data-theme="dark"] .accountant-topbar {
      background: rgba(15, 23, 42, 0.94) !important;
      border-color: rgba(148, 163, 184, 0.24) !important;
    }

    html.dark .accountant-dropdown-card,
    html[data-theme="dark"] .accountant-dropdown-card {
      background: #111827;
      border-color: rgba(148, 163, 184, 0.3);
      box-shadow: 0 26px 48px rgba(0, 0, 0, 0.5);
    }

    html.dark .accountant-side-action:hover,
    html[data-theme="dark"] .accountant-side-action:hover {
      border-color: rgba(147, 197, 253, 0.35);
      color: #bfdbfe !important;
    }

    html.dark .accountant-side-action::before,
    html[data-theme="dark"] .accountant-side-action::before {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.22), rgba(59, 130, 246, 0.05));
    }

    html.dark .accountant-side-action.accountant-side-action-danger:hover,
    html[data-theme="dark"] .accountant-side-action.accountant-side-action-danger:hover {
      border-color: rgba(248, 113, 113, 0.35);
      color: #fca5a5 !important;
    }

    html.dark .accountant-overlay-scrim,
    html[data-theme="dark"] .accountant-overlay-scrim {
      background: rgba(2, 6, 23, 0.32);
    }

    html.dark .bg-error-container,
    html[data-theme="dark"] .bg-error-container {
      background: rgba(127, 29, 29, 0.36) !important;
    }

    html.dark .text-on-error-container,
    html[data-theme="dark"] .text-on-error-container {
      color: #fecaca !important;
    }

    html.dark .text-error,
    html[data-theme="dark"] .text-error {
      color: #fca5a5 !important;
    }

    html.dark .accountant-theme-option.active,
    html[data-theme="dark"] .accountant-theme-option.active {
      border-color: rgba(191, 219, 254, 0.6);
      background: rgba(37, 99, 235, 0.5);
    }
  </style>
</head>

<body class="bg-surface text-on-surface">
  <aside class="h-screen w-64 fixed left-0 top-0 border-r border-outline-variant/30 bg-white flex flex-col p-4 space-y-2 z-50">
    <div class="px-2 py-4 mb-4">
      <div class="flex items-center gap-3">
        <picture class="block w-12 h-12 shrink-0 overflow-hidden rounded-2xl">
          <source media="(prefers-color-scheme: dark)" srcset="../../assets/logo/logo4.png" />
          <img src="../../assets/logo/logo5.png" alt="SAMS Logo" class="w-full h-full object-cover scale-[1.18] origin-center" data-accountant-brand-img data-brand-light="/attendance/assets/logo/logo5.png" data-brand-dark="/attendance/assets/logo/logo4.png" />
        </picture>
        <div>
          <h1 class="font-headline font-extrabold text-on-surface leading-tight tracking-tight">SAMS</h1>
          <p class="text-[10px] text-primary font-bold uppercase tracking-widest">Financial Architect</p>
        </div>
      </div>
    </div>
    <nav class="flex-1 space-y-1">
      <a class="flex items-center gap-3 px-4 py-3 bg-primary-container text-on-primary-container rounded-lg font-bold transition-all group" href="dashboard.php">
        <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1">dashboard</span>
        <span class="text-sm">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all group" href="ledger.php">
        <span class="material-symbols-outlined">account_balance</span>
        <span class="text-sm font-medium">General Ledger</span>
      </a>
      <a class="flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all group" href="expenses.php">
        <span class="material-symbols-outlined">receipt_long</span>
        <span class="text-sm font-medium">Expenses</span>
      </a>
      <a class="flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all group" href="income.php">
        <span class="material-symbols-outlined">payments</span>
        <span class="text-sm font-medium">Income</span>
      </a>
      <a class="flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all group" href="payroll.php">
        <span class="material-symbols-outlined">group</span>
        <span class="text-sm font-medium">Payroll</span>
      </a>
      <a class="flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all group" href="reports.php">
        <span class="material-symbols-outlined">analytics</span>
        <span class="text-sm font-medium">Reports</span>
      </a>
    </nav>
    <div class="mt-auto border-t border-outline-variant/30 pt-4 space-y-1">
      <a class="accountant-side-action flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all" href="settings.php">
        <span class="material-symbols-outlined">settings</span>
        <span class="text-sm font-medium">Settings</span>
      </a>
      <a class="accountant-side-action flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all" href="../notices.php">
        <span class="material-symbols-outlined">contact_support</span>
        <span class="text-sm font-medium">Support</span>
      </a>
      <a class="accountant-side-action accountant-side-action-danger flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all" href="../../logout.php">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm font-medium">Logout</span>
      </a>
    </div>
  </aside>

  <main class="ml-64 min-h-screen flex flex-col">
    <header class="accountant-topbar sticky top-0 bg-white/95 backdrop-blur-md border-b border-outline-variant/20 h-16 flex justify-between items-center w-full px-8">
      <div class="flex items-center gap-4 flex-1">
        <div class="relative w-full max-w-md">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
          <input class="w-full bg-surface-container-low border-none rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 placeholder:text-outline" placeholder="Search transactions, ledgers..." type="text" />
        </div>
      </div>
      <div class="flex items-center gap-6">
        <div class="relative flex items-center gap-1 text-secondary" data-accountant-toolbar>
          <button type="button" class="accountant-icon-btn relative p-2 hover:bg-surface-container rounded-full transition-all" title="Notifications" aria-label="Notifications" aria-expanded="false" aria-controls="accountant-notifications-menu" data-accountant-dropdown-toggle="accountant-notifications-menu">
            <span class="material-symbols-outlined">notifications</span>
            <?php if ($accountant_unread_notifications > 0): ?>
              <span class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-error text-white text-[9px] font-bold flex items-center justify-center leading-none">
                <?php echo $accountant_unread_notifications > 9 ? '9+' : (int) $accountant_unread_notifications; ?>
              </span>
            <?php endif; ?>
          </button>
          <button type="button" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="Theme mode" aria-label="Theme mode" aria-expanded="false" aria-controls="accountant-theme-menu" data-accountant-dropdown-toggle="accountant-theme-menu">
            <span class="material-symbols-outlined" data-accountant-theme-icon>light_mode</span>
          </button>
          <a href="settings.php" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="Settings" aria-label="Settings">
            <span class="material-symbols-outlined">settings</span>
          </a>
          <button type="button" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="More actions" aria-label="More actions" aria-expanded="false" aria-controls="accountant-more-menu" data-accountant-dropdown-toggle="accountant-more-menu">
            <span class="material-symbols-outlined">more_vert</span>
          </button>
          <a href="../notices.php" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="Help" aria-label="Help">
            <span class="material-symbols-outlined">help_outline</span>
          </a>

          <div id="accountant-notifications-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-[23rem] overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
            <div class="flex items-center justify-between px-4 py-3 border-b border-outline-variant/10 bg-surface-container-low">
              <div>
                <p class="text-sm font-extrabold text-on-surface">Notifications</p>
                <p class="text-[10px] uppercase tracking-[0.24em] text-secondary font-bold"><?php echo $accountant_unread_notifications > 0 ? (int) $accountant_unread_notifications . ' unread' : 'All caught up'; ?></p>
              </div>
              <a href="settings.php#notifications" class="text-xs font-bold text-primary hover:underline">Preferences</a>
            </div>
            <div class="max-h-80 overflow-y-auto divide-y divide-outline-variant/10">
              <?php if (!empty($accountant_notifications)): ?>
                <?php foreach ($accountant_notifications as $notification):
                  $notificationTitle = trim((string)($notification['title'] ?? 'Notification'));
                  $notificationMessage = trim((string)($notification['message'] ?? ''));
                  $notificationCategory = strtolower((string)($notification['category'] ?? 'info'));
                  $notificationIcon = match ($notificationCategory) {
                    'success' => 'check_circle',
                    'warning' => 'warning',
                    'error', 'danger' => 'error',
                    'payment' => 'payments',
                    'approval' => 'task_alt',
                    default => 'notifications',
                  };
                  $notificationTime = !empty($notification['created_at']) ? date('M j, g:i A', strtotime((string)$notification['created_at'])) : 'Just now';
                  $isUnread = (int)($notification['is_read'] ?? 0) === 0;
                ?>
                  <a href="../notices.php" class="block px-4 py-3 transition-colors hover:bg-surface-container-low <?php echo $isUnread ? 'bg-primary-container/20' : ''; ?>">
                    <div class="flex items-start gap-3">
                      <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?php echo $isUnread ? 'bg-primary-container text-primary' : 'bg-surface-container text-secondary'; ?>">
                        <span class="material-symbols-outlined text-[20px]"><?php echo htmlspecialchars($notificationIcon); ?></span>
                      </div>
                      <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                          <p class="truncate text-sm font-bold text-on-surface"><?php echo htmlspecialchars($notificationTitle); ?></p>
                          <?php if ($isUnread): ?>
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                          <?php endif; ?>
                        </div>
                        <?php if ($notificationMessage !== ''): ?>
                          <p class="mt-1 text-sm text-secondary leading-snug"><?php echo htmlspecialchars($notificationMessage); ?></p>
                        <?php endif; ?>
                        <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.2em] text-outline"><?php echo htmlspecialchars($notificationTime); ?></p>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="px-4 py-6 text-sm text-secondary">
                  <p class="font-bold text-on-surface">No notifications yet.</p>
                  <p class="mt-1 text-sm leading-relaxed">Alerts and reminders will show here once they’re available.</p>
                </div>
              <?php endif; ?>
            </div>
            <div class="border-t border-outline-variant/10 bg-surface-container-low px-4 py-3">
              <a href="../notices.php" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-primary hover:underline">
                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                View all notices
              </a>
            </div>
          </div>

          <div id="accountant-theme-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-[22rem] overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
            <div class="px-4 py-3 border-b border-outline-variant/10 bg-surface-container-low">
              <p class="text-sm font-extrabold text-on-surface">Theme Selection</p>
              <p class="text-[10px] uppercase tracking-[0.24em] text-secondary font-bold">Unified across all accountant tabs</p>
            </div>
            <div class="p-3 grid grid-cols-2 gap-3">
              <button type="button" class="accountant-theme-option w-full flex flex-col items-start gap-2 rounded-xl px-3 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors" data-accountant-theme-choice="light">
                <span class="material-symbols-outlined text-[18px]">light_mode</span>
                <span class="text-left">Light mode</span>
                <span class="material-symbols-outlined text-primary hidden" data-accountant-theme-check>check</span>
              </button>
              <button type="button" class="accountant-theme-option w-full flex flex-col items-start gap-2 rounded-xl px-3 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors" data-accountant-theme-choice="dark">
                <span class="material-symbols-outlined text-[18px]">dark_mode</span>
                <span class="text-left">Dark mode</span>
                <span class="material-symbols-outlined text-primary hidden" data-accountant-theme-check>check</span>
              </button>
            </div>
          </div>

          <div id="accountant-more-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-56 overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
            <a href="expenses.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
              <span class="material-symbols-outlined text-secondary">receipt_long</span>
              Record expense
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
              <span class="material-symbols-outlined text-secondary">analytics</span>
              Open reports
            </a>
            <a href="settings.php#notifications" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
              <span class="material-symbols-outlined text-secondary">tune</span>
              Notification settings
            </a>
            <a href="../../logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-error hover:bg-error-container/50">
              <span class="material-symbols-outlined text-error">logout</span>
              Sign out
            </a>
          </div>
        </div>
        <a href="expenses.php" class="bg-primary text-on-primary px-5 py-2 rounded-lg text-sm font-bold hover:opacity-90 transition-all shadow-md shadow-primary/10">Add Expense</a>
        <a href="settings.php" class="flex items-center gap-3 pl-4 border-l border-outline-variant/30 hover:opacity-90" title="Edit Profile">
          <div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-primary font-bold text-xs"><?php echo htmlspecialchars(strtoupper(substr($full_name, 0, 1))); ?></div>
          <div class="hidden lg:block text-left">
            <p class="text-xs font-bold text-on-surface leading-none"><?php echo htmlspecialchars($full_name); ?></p>
            <p class="text-[10px] text-secondary font-semibold uppercase tracking-tighter mt-1">Edit Profile</p>
          </div>
        </a>
      </div>
    </header>

    <div class="p-8 space-y-8">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h2 class="font-headline text-3xl font-extrabold text-on-surface tracking-tight">Financial Overview</h2>
          <p class="text-secondary text-sm mt-1 font-medium"><?php echo date('l, F j, Y'); ?> • <span class="text-primary font-bold">Academic Year 2023-24</span></p>
        </div>
        <div class="flex items-center gap-3">
          <a href="expenses.php" class="flex items-center gap-2 px-4 py-2 bg-white border border-outline-variant text-primary text-sm font-bold rounded-lg hover:bg-primary-container/30 transition-all">
            <span class="material-symbols-outlined text-lg">history</span> Record Expense
          </a>
          <a href="reports.php" class="flex items-center gap-2 px-4 py-2 bg-on-surface text-white text-sm font-bold rounded-lg hover:opacity-90 transition-all">
            <span class="material-symbols-outlined text-lg">description</span> Generate Report
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-atlas shadow-sm border border-outline-variant/20 hover:border-primary/30 transition-colors">
          <p class="text-[11px] font-bold text-outline uppercase tracking-widest mb-2">Total Income</p>
          <div class="flex items-end justify-between">
            <h3 class="font-headline text-2xl font-extrabold tabular-nums text-on-surface"><?php echo format_local_currency($financial_stats['total_income'], 0, $tenantId); ?></h3>
            <div class="flex items-center text-primary bg-primary-container px-2 py-1 rounded-md text-xs font-bold">
              <span class="material-symbols-outlined text-xs mr-1">trending_up</span>12%
            </div>
          </div>
        </div>
        <div class="bg-white p-6 rounded-atlas shadow-sm border border-outline-variant/20">
          <p class="text-[11px] font-bold text-outline uppercase tracking-widest mb-2">Total Expenses</p>
          <div class="flex items-end justify-between">
            <h3 class="font-headline text-2xl font-extrabold tabular-nums text-on-surface"><?php echo format_local_currency($financial_stats['total_expenses'], 0, $tenantId); ?></h3>
            <div class="flex items-center text-error bg-error-container px-2 py-1 rounded-md text-xs font-bold">
              <span class="material-symbols-outlined text-xs mr-1">trending_up</span>4%
            </div>
          </div>
        </div>
        <div class="bg-white p-6 rounded-atlas shadow-md border border-primary/10 bg-gradient-to-br from-white to-primary-container/20">
          <p class="text-[11px] font-bold text-primary uppercase tracking-widest mb-2">Net Balance</p>
          <div class="flex items-end justify-between">
            <h3 class="font-headline text-2xl font-extrabold tabular-nums text-primary"><?php echo format_local_currency($net, 0, $tenantId); ?></h3>
            <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1">account_balance_wallet</span>
          </div>
        </div>
        <div class="bg-white p-6 rounded-atlas shadow-sm border border-outline-variant/20">
          <p class="text-[11px] font-bold text-outline uppercase tracking-widest mb-2">Pending Approvals</p>
          <div class="flex items-end justify-between">
            <h3 class="font-headline text-2xl font-extrabold tabular-nums text-on-surface"><?php echo (int)$financial_stats['pending_approvals']; ?></h3>
            <div class="w-8 h-8 rounded-full bg-secondary-container flex items-center justify-center text-on-secondary-container">
              <span class="material-symbols-outlined text-xl">pending_actions</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-12 gap-8">
        <div class="col-span-12 lg:col-span-8 space-y-8">
          <div class="bg-white p-8 rounded-atlas shadow-sm border border-outline-variant/20">
            <div class="flex items-center justify-between mb-8">
              <div>
                <h4 class="font-headline text-lg font-extrabold text-on-surface">Monthly Revenue Trend</h4>
                <p class="text-xs text-secondary font-medium">Comparing tuition and auxiliary income</p>
              </div>
              <div class="flex items-center gap-2">
                <span class="flex items-center gap-1 text-[10px] font-bold text-outline uppercase"><span class="w-2 h-2 rounded-full bg-primary"></span> Revenue</span>
                <span class="flex items-center gap-1 text-[10px] font-bold text-outline uppercase ml-4"><span class="w-2 h-2 rounded-full bg-secondary-container"></span> Target</span>
              </div>
            </div>
            <div class="h-64 w-full relative flex items-end justify-between gap-4 px-2">
              <div class="absolute inset-0 flex flex-col justify-between py-2 pointer-events-none">
                <div class="border-b border-outline-variant/10 w-full h-0"></div>
                <div class="border-b border-outline-variant/10 w-full h-0"></div>
                <div class="border-b border-outline-variant/10 w-full h-0"></div>
                <div class="border-b border-outline-variant/10 w-full h-0"></div>
                <div class="border-b border-outline-variant/10 w-full h-0"></div>
              </div>
              <?php if (!empty($trend_points)): ?>
                <?php foreach ($trend_points as $point):
                  $height = (int)max(30, round(($point['value'] / $trendMax) * 100));
                  $tooltip = format_local_currency((float)$point['value'], 0, $tenantId);
                ?>
                  <div class="flex-1 bg-gradient-to-t from-primary/10 to-primary/40 rounded-t-lg group relative" style="height: <?php echo $height; ?>%">
                    <div class="opacity-0 group-hover:opacity-100 absolute -top-8 left-1/2 -translate-x-1/2 bg-on-surface text-white text-[10px] px-2 py-1 rounded font-bold"><?php echo htmlspecialchars($tooltip); ?></div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-xs font-semibold text-secondary">No paid revenue records found for the last 6 months.</div>
              <?php endif; ?>
            </div>
            <div class="flex justify-between mt-4 px-2 text-[10px] font-extrabold text-outline uppercase tracking-widest">
              <?php if (!empty($trend_points)): ?>
                <?php foreach ($trend_points as $point): ?>
                  <span><?php echo htmlspecialchars($point['month']); ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="bg-on-surface text-white p-6 rounded-atlas shadow-lg relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
              <span class="material-symbols-outlined text-[120px]">auto_awesome</span>
            </div>
            <div class="relative z-10 flex items-start gap-4">
              <div class="p-3 bg-white/10 backdrop-blur-md rounded-xl border border-white/20">
                <span class="material-symbols-outlined text-primary-container">insights</span>
              </div>
              <div>
                <h4 class="font-headline text-lg font-bold mb-2">AI Financial Insight</h4>
                <p class="text-surface-container-highest text-sm leading-relaxed max-w-xl">
                  <?php echo htmlspecialchars($ai_insights['recommendation']); ?>
                </p>
                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-surface-container-high">Read-only insight • source: live accountant DB records</p>
                <p class="mt-1 text-xs text-surface-container-highest/90">
                  Paid Txns: <?php echo (int)$ai_insights['evidence']['paid_transactions']; ?> • Pending Approvals: <?php echo (int)$ai_insights['evidence']['pending_approvals']; ?> •
                  This Month Income: <?php echo format_local_currency((float)$ai_insights['evidence']['month_income'], 0, $tenantId); ?> • This Month Expenses: <?php echo format_local_currency((float)$ai_insights['evidence']['month_expenses'], 0, $tenantId); ?>
                </p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-atlas shadow-sm border border-outline-variant/20 overflow-hidden">
            <div class="p-6 border-b border-outline-variant/10 flex items-center justify-between">
              <h4 class="font-headline text-lg font-extrabold">Recent Transactions</h4>
              <a href="reports.php" class="text-primary text-xs font-extrabold uppercase tracking-widest hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-surface-container-low">
                    <th class="px-6 py-4 text-[10px] font-bold text-secondary uppercase tracking-widest">Date</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-secondary uppercase tracking-widest">Category</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-secondary uppercase tracking-widest">Description</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-secondary uppercase tracking-widest">Amount</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-secondary uppercase tracking-widest text-right">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                  <?php if (!empty($recent_transactions)): ?>
                    <?php foreach (array_slice($recent_transactions, 0, 5) as $transaction):
                      $st = strtolower((string)($transaction['status'] ?? 'pending'));
                      $rowStatusClass = $st === 'paid'
                        ? 'bg-primary-container text-primary'
                        : ($st === 'rejected' || $st === 'failed' || $st === 'overdue'
                          ? 'bg-error-container text-error'
                          : 'bg-surface-container-highest text-secondary');
                      $category = !empty($transaction['class_name']) ? 'Tuition' : 'Operations';
                      $descName = trim((string)($transaction['first_name'] ?? '') . ' ' . (string)($transaction['last_name'] ?? ''));
                      if ($descName === '') {
                        $descName = 'Fee Payment';
                      }
                      $description = $descName . (!empty($transaction['class_name']) ? ' - ' . (string)$transaction['class_name'] : '');
                    ?>
                      <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="px-6 py-4 text-sm tabular-nums font-medium"><?php echo htmlspecialchars(date('M d, Y', strtotime((string)($transaction['created_at'] ?? 'now')))); ?></td>
                        <td class="px-6 py-4">
                          <span class="text-[11px] px-2 py-1 bg-secondary-container text-on-secondary-container rounded font-bold uppercase"><?php echo htmlspecialchars($category); ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-on-surface"><?php echo htmlspecialchars($description); ?></td>
                        <td class="px-6 py-4 text-sm tabular-nums font-extrabold <?php echo $st === 'paid' ? 'text-primary' : 'text-error'; ?>">
                          <?php echo $st === 'paid' ? '+' : '-'; ?><?php echo format_local_currency((float)($transaction['amount'] ?? 0), 2, $tenantId); ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                          <span class="text-[10px] px-2 py-1 rounded-md font-bold uppercase <?php echo $rowStatusClass; ?>"><?php echo htmlspecialchars($st); ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="px-6 py-8 text-center text-sm text-secondary">No transactions found.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-span-12 lg:col-span-4 space-y-8">
          <div class="bg-white p-8 rounded-atlas shadow-sm border border-outline-variant/20">
            <h4 class="font-headline text-lg font-extrabold text-on-surface mb-6">Expense Breakdown</h4>
            <div class="relative w-48 h-48 mx-auto mb-8">
              <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#1868DB" stroke-dasharray="<?php echo (float)$expenseChartSegments[0]; ?>, 100" stroke-width="3.5"></path>
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#D9E3F8" stroke-dasharray="<?php echo (float)$expenseChartSegments[1]; ?>, 100" stroke-dashoffset="<?php echo (float)$expenseChartOffsets[1]; ?>" stroke-width="3.5"></path>
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#8F4C00" stroke-dasharray="<?php echo (float)$expenseChartSegments[2]; ?>, 100" stroke-dashoffset="<?php echo (float)$expenseChartOffsets[2]; ?>" stroke-width="3.5"></path>
              </svg>
              <div class="absolute inset-0 flex items-center justify-center flex-col">
                <span class="text-[10px] uppercase font-extrabold text-outline">Total</span>
                <span class="text-xl font-extrabold tabular-nums text-on-surface"><?php echo format_local_currency($financial_stats['total_expenses'], 0, $tenantId); ?></span>
              </div>
            </div>
            <div class="space-y-3">
              <?php if (!empty($expenseBreakdown)): ?>
                <?php $breakdownColors = ['bg-primary', 'bg-secondary-container', 'bg-tertiary']; ?>
                <?php foreach ($expenseBreakdown as $idx => $row):
                  $total = max(0.0, (float)($row['total'] ?? 0));
                  $sumAll = max(0.0001, (float)$financial_stats['total_expenses']);
                  $pct = (int)round(($total / $sumAll) * 100);
                  $color = $breakdownColors[$idx] ?? 'bg-outline-variant';
                ?>
                  <div class="flex items-center justify-between text-sm">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-sm <?php echo $color; ?>"></div><span class="text-secondary font-bold"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$row['category']))); ?></span>
                    </div><span class="font-extrabold tabular-nums"><?php echo $pct; ?>%</span>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-sm text-secondary font-medium">No expense categories found yet.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="bg-white p-8 rounded-atlas shadow-sm border border-outline-variant/20">
            <h4 class="font-headline text-lg font-extrabold text-on-surface mb-6">Budget Utilization</h4>
            <div class="space-y-6">
              <?php if (!empty($budgetUtilizationRows)): ?>
                <?php foreach ($budgetUtilizationRows as $idx => $row):
                  $pct = (int)($row['pct'] ?? 0);
                  $barClass = $idx === 0 ? 'bg-primary' : ($idx === 1 ? 'bg-primary/60' : 'bg-error');
                  $labelClass = $idx === 2 ? 'text-error' : 'text-secondary';
                ?>
                  <div>
                    <div class="flex justify-between text-sm mb-2"><span class="<?php echo $labelClass; ?> font-bold"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($row['category'] ?? 'other')))); ?></span><span class="font-extrabold tabular-nums <?php echo $idx < 2 ? 'text-primary' : ''; ?>"><?php echo $pct; ?>%</span></div>
                    <div class="w-full h-2.5 bg-surface-container rounded-full overflow-hidden">
                      <div class="h-full <?php echo $barClass; ?> rounded-full shadow-sm" style="width:<?php echo max(0, min(100, $pct)); ?>%"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-sm text-secondary font-medium">No monthly expense utilization data available.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="bg-error-container p-6 rounded-atlas border border-error/10">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-error" style="font-variation-settings: 'FILL' 1">warning</span>
              <h4 class="font-headline font-extrabold text-on-error-container">Overdue Accounts</h4>
            </div>
            <div class="space-y-4">
              <?php foreach ($overdueCards as $card): ?>
                <div class="flex items-center justify-between border-b border-error/10 pb-3">
                  <div>
                    <p class="text-sm font-bold text-on-error-container"><?php echo htmlspecialchars((string)$card['title']); ?></p>
                    <p class="text-[10px] text-error font-extrabold uppercase"><?php echo htmlspecialchars((string)$card['meta']); ?></p>
                  </div>
                  <span class="text-error font-extrabold tabular-nums"><?php echo format_local_currency((float)($card['amount'] ?? 0), 0, $tenantId); ?></span>
                </div>
              <?php endforeach; ?>
              <button class="w-full py-3 bg-white text-error text-[11px] font-extrabold rounded-lg border border-error/20 hover:bg-error hover:text-white transition-all uppercase tracking-widest shadow-sm">Review Outstanding Items</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="md:hidden sticky bottom-0 w-full bg-white border-t border-outline-variant/20 flex justify-around p-4 z-50">
      <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1">dashboard</span>
      <span class="material-symbols-outlined text-secondary">payments</span>
      <span class="material-symbols-outlined text-secondary">receipt_long</span>
      <span class="material-symbols-outlined text-secondary">person</span>
    </div>
  </main>
  <div class="accountant-overlay-scrim" data-accountant-menu-overlay></div>

  <script>
    (() => {
      const toolbar = document.querySelector('[data-accountant-toolbar]');
      if (!toolbar) {
        return;
      }

      const toggles = Array.from(toolbar.querySelectorAll('[data-accountant-dropdown-toggle]'));
      const menus = Array.from(toolbar.querySelectorAll('[data-accountant-dropdown-menu]'));
      const themeChoices = Array.from(toolbar.querySelectorAll('[data-accountant-theme-choice]'));
      const themeIcon = toolbar.querySelector('[data-accountant-theme-icon]');
      const overlay = document.querySelector('[data-accountant-menu-overlay]');
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      const themeApiUrl = '/attendance/api/save-theme.php';
      const serverTheme = <?php echo json_encode($saved_theme); ?>;
      const brandImg = document.querySelector('[data-accountant-brand-img]');
      const iconBase = '/attendance/assets/images/icons/';
      const lightIcon = iconBase + 'logo5.png';
      const darkIcon = iconBase + 'logo4.png';
      let scrollLockY = 0;
      let previousBodyPaddingRight = '';

      const normalizeTheme = (theme) => theme === 'dark' ? 'dark' : 'light';

      const readTheme = () => {
        try {
          const preferred = localStorage.getItem('sams_theme') || localStorage.getItem('sams-theme');
          if (preferred) {
            return normalizeTheme(preferred);
          }
        } catch (error) {
          // ignore storage read issues
        }

        if (serverTheme) {
          return normalizeTheme(serverTheme);
        }

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          return 'dark';
        }

        return 'light';
      };

      const persistTheme = (theme) => {
        try {
          localStorage.setItem('sams_theme', theme);
          localStorage.setItem('sams-theme', theme);
        } catch (error) {
          // ignore storage write issues
        }

        fetch(themeApiUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            theme: theme,
            csrf_token: csrfToken
          })
        }).catch(() => {
          // ignore theme sync failures; local theme still applies
        });
      };

      const setOrCreateHeadLink = (rel, href) => {
        let link = document.querySelector('link[rel="' + rel + '"]');
        if (!link) {
          link = document.createElement('link');
          link.rel = rel;
          document.head.appendChild(link);
        }
        link.href = href;
      };

      const syncThemeIcons = (theme) => {
        const isDark = theme === 'dark';
        const targetIcon = isDark ? darkIcon : lightIcon;
        setOrCreateHeadLink('icon', targetIcon);
        setOrCreateHeadLink('shortcut icon', targetIcon);
        setOrCreateHeadLink('apple-touch-icon', targetIcon);

        if (brandImg) {
          const lightBrand = brandImg.getAttribute('data-brand-light') || '';
          const darkBrand = brandImg.getAttribute('data-brand-dark') || '';
          brandImg.src = isDark ? (darkBrand || targetIcon) : (lightBrand || targetIcon);
        }
      };

      const updateThemeButtons = (theme) => {
        themeChoices.forEach((button) => {
          const selected = button.getAttribute('data-accountant-theme-choice') === theme;
          button.classList.toggle('bg-primary-container', selected);
          button.classList.toggle('text-on-primary-container', selected);
          button.classList.toggle('text-on-surface', !selected);
          button.classList.toggle('active', selected);
          const check = button.querySelector('[data-accountant-theme-check]');
          if (check) {
            check.classList.toggle('hidden', !selected);
          }
        });

        if (themeIcon) {
          themeIcon.textContent = theme === 'dark' ? 'dark_mode' : 'light_mode';
        }
      };

      const applyTheme = (theme) => {
        const normalizedTheme = normalizeTheme(theme);
        document.documentElement.setAttribute('data-theme', normalizedTheme);
        document.documentElement.classList.toggle('dark', normalizedTheme === 'dark');
        document.documentElement.classList.toggle('light', normalizedTheme !== 'dark');
        persistTheme(normalizedTheme);
        updateThemeButtons(normalizedTheme);
        syncThemeIcons(normalizedTheme);
      };

      const lockPageScroll = () => {
        if (document.body.classList.contains('accountant-scroll-locked')) {
          return;
        }

        scrollLockY = window.scrollY || window.pageYOffset || 0;
        previousBodyPaddingRight = document.body.style.paddingRight || '';
        const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);

        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollLockY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
        if (scrollbarWidth > 0) {
          document.body.style.paddingRight = `${scrollbarWidth}px`;
        }
        document.body.classList.add('accountant-scroll-locked');
      };

      const unlockPageScroll = () => {
        if (!document.body.classList.contains('accountant-scroll-locked')) {
          return;
        }

        document.body.classList.remove('accountant-scroll-locked');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        document.body.style.paddingRight = previousBodyPaddingRight;
        window.scrollTo(0, scrollLockY);
      };

      const closeMenus = () => {
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        menus.forEach((menu) => menu.classList.add('hidden'));
        if (overlay) {
          overlay.classList.remove('active');
        }
        unlockPageScroll();
      };

      const openMenu = (menuId) => {
        const menu = document.getElementById(menuId);
        if (!menu) {
          return;
        }

        const isHidden = menu.classList.contains('hidden');
        closeMenus();
        if (isHidden) {
          menu.classList.remove('hidden');
          const toggle = toolbar.querySelector('[data-accountant-dropdown-toggle="' + menuId + '"]');
          if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
          }
          if (overlay) {
            overlay.classList.add('active');
          }
          lockPageScroll();
        }
      };

      toggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          openMenu(toggle.getAttribute('data-accountant-dropdown-toggle'));
        });
      });

      themeChoices.forEach((choice) => {
        choice.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          applyTheme(choice.getAttribute('data-accountant-theme-choice') || 'light');
          closeMenus();
        });
      });

      applyTheme(readTheme());

      document.addEventListener('click', (event) => {
        if (!toolbar.contains(event.target)) {
          closeMenus();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeMenus();
        }
      });

      if (overlay) {
        overlay.addEventListener('click', () => {
          closeMenus();
        });
      }
    })();
  </script>
</body>

</html>
