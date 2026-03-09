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
$full_name = $_SESSION['full_name'];

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
$recent_transactions = db()->fetchAll("
    SELECT fr.*, u.first_name, u.last_name, c.class_name
    FROM fee_payments fr
    LEFT JOIN users u ON fr.student_id = u.id
    LEFT JOIN class_enrollments ce ON u.id = ce.student_id
    LEFT JOIN classes c ON ce.class_id = c.id
    WHERE fr.tenant_id = ?
    ORDER BY fr.created_at DESC
    LIMIT 10
", [$tenantId]);

// Get monthly revenue trend
$revenue_trend = db()->fetchAll("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as revenue,
        COUNT(*) as transaction_count
    FROM fee_payments
    WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
", [$tenantId]);

// AI Financial Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_FinancialBot')) {
            $financialBot = new SAMS_FinancialBot();
            $ai_insights = $financialBot->getFinancialInsights($tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'financial_health' => $net > 0 ? 'excellent' : ($net > -5000 ? 'good' : 'needs_attention'),
            'payment_trend' => 'stable',
            'recommendation' => 'Monitor pending approvals and optimize expense tracking'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'financial_health' => 'good',
        'payment_trend' => 'stable',
        'recommendation' => 'Continue regular financial monitoring'
    ];
}

$csrf = generate_csrf_token();

// Helper functions
function acc_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}
function acc_sum($table, $col, $where = '1=1', $params = []) {
    try {
        if (!table_exists($table)) return 0;
        $r = db()->fetchOne("SELECT COALESCE(SUM($col),0) AS total FROM $table WHERE $where", $params);
        return (float)($r['total'] ?? 0);
    } catch (Throwable $e) { return 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#6366f1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <style>
        .accountant-header {
            background: linear-gradient(135deg, #16A34A, #22C55E);
            color: #fff;
            padding: 2rem;
            border-radius: var(--radius-xl, 16px);
            margin-bottom: 2rem;
        }

        .ai-financial-advisor {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 1px solid #FCD34D;
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .ai-financial-advisor h3 {
            color: #92400E;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #16A34A;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-secondary, #6b7280);
            margin-top: 0.5rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.25rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }

        .action-card:hover {
            border-color: #16A34A;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md, 8px);
            background: #DCFCE7;
            color: #16A34A;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .financial-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md, 8px);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .financial-indicator.excellent {
            background: #D1FAE5;
            color: #059669;
        }

        .financial-indicator.good {
            background: #DBEAFE;
            color: #2563EB;
        }

        .financial-indicator.needs_attention {
            background: #FEE2E2;
            color: #DC2626;
        }

        .recent-transactions {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--color-border, #e5e7eb);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.paid {
            background: #D1FAE5;
            color: #059669;
        }

        .status-badge.pending {
            background: #FEF3C7;
            color: #D97706;
        }

        .status-badge.overdue {
            background: #FEE2E2;
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-calculator"></i></div>
                <div>
                    <h1>Accountant Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>!</p>
                </div>
            </div>

            <div class="cyber-content" style="max-width: 1400px; margin: 0 auto; padding: 24px;">

                <div class="accountant-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h1><i class="fas fa-calculator"></i> Financial Dashboard</h1>
                            <p>Manage school finances, track payments, and generate reports</p>
                        </div>
                        <div style="text-align: right;">
                            <div class="financial-indicator <?php echo htmlspecialchars($ai_insights['financial_health'] ?? 'good'); ?>">
                                <i class="fas fa-chart-line"></i>
                                Financial Health: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ai_insights['financial_health'] ?? 'good'))); ?>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.9;">
                                Net Balance: $<?php echo number_format($net, 2); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Financial Advisor -->
                <div class="ai-financial-advisor">
                    <h3><i class="fas fa-robot"></i> AI Financial Advisor</h3>
                    <p><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Monitor payment trends and follow up on overdue accounts to maintain healthy cash flow.'); ?></p>
                    <div style="display: flex; gap: 2rem; margin-top: 1rem; flex-wrap: wrap;">
                        <div>
                            <strong>Payment Trend:</strong>
                            <span style="color: #92400E; font-weight: 600;">
                                <?php echo htmlspecialchars(ucfirst($ai_insights['payment_trend'] ?? 'stable')); ?>
                            </span>
                        </div>
                        <div>
                            <strong>Monthly Revenue:</strong>
                            $<?php echo number_format($financial_stats['total_income'], 2); ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($financial_stats['total_income'], 0); ?></div>
                        <div class="stat-label">Total Income</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($financial_stats['total_expenses'], 0); ?></div>
                        <div class="stat-label">Total Expenses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($financial_stats['payroll_total'], 0); ?></div>
                        <div class="stat-label">Payroll Total</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $financial_stats['pending_approvals']; ?></div>
                        <div class="stat-label">Pending Approvals</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="ledger.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-book"></i></div>
                        <div>
                            <div style="font-weight: 600;">General Ledger</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">View ledger entries</div>
                        </div>
                    </a>
                    <a href="expenses.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-receipt"></i></div>
                        <div>
                            <div style="font-weight: 600;">Record Expense</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Add expenses</div>
                        </div>
                    </a>
                    <a href="payroll.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-money-check-alt"></i></div>
                        <div>
                            <div style="font-weight: 600;">Payroll</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Manage payroll</div>
                        </div>
                    </a>
                    <a href="balance-sheet.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-balance-scale"></i></div>
                        <div>
                            <div style="font-weight: 600;">Balance Sheet</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Financial position</div>
                        </div>
                    </a>
                    <a href="profit-loss.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div style="font-weight: 600;">P&L Statement</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Profit & loss</div>
                        </div>
                    </a>
                    <a href="reports.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                        <div>
                            <div style="font-weight: 600;">Reports</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Financial reports</div>
                        </div>
                    </a>
                </div>

                <!-- Recent Transactions -->
                <div class="recent-transactions">
                    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-receipt"></i> Recent Transactions
                    </h3>

                    <?php if (!empty($recent_transactions)): ?>
                        <?php foreach (array_slice($recent_transactions, 0, 5) as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon" style="background: #DCFCE7; color: #16A34A;">
                                <i class="fas fa-<?php echo $transaction['status'] === 'paid' ? 'check' : 'clock'; ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">
                                    <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name'] ?? 'Unknown'); ?>
                                    <?php if (!empty($transaction['class_name'])): ?>
                                        <span style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">• <?php echo htmlspecialchars($transaction['class_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                    Fee Payment
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600; color: #16A34A;">
                                    $<?php echo number_format($transaction['amount'], 2); ?>
                                </div>
                                <div style="margin-top: 0.25rem;">
                                    <span class="status-badge <?php echo htmlspecialchars($transaction['status'] ?? 'pending'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($transaction['status'] ?? 'pending')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary, #6b7280);">
                            <i class="fas fa-receipt" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No recent transactions found.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
