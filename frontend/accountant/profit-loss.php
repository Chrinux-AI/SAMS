<?php

/**
 * Accountant Profit & Loss Statement
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$tenantId = $_SESSION['tenant_id'] ?? 1;

$period = $_GET['period'] ?? 'month';
$validPeriods = ['month', 'quarter', 'year'];
if (!in_array($period, $validPeriods, true)) {
    $period = 'month';
}

$intervalMap = ['month' => 30, 'quarter' => 90, 'year' => 365];
$interval = $intervalMap[$period];

$feeRevenue = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM fee_payments WHERE tenant_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]);
    $feeRevenue = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $feeRevenue = 0.0;
}

$ledgerRevenue = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) AS total FROM ledger_entries WHERE tenant_id = ? AND account = 'revenue' AND entry_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]);
    $ledgerRevenue = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $ledgerRevenue = 0.0;
}

$tableExpenses = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE tenant_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]);
    $tableExpenses = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $tableExpenses = 0.0;
}

$ledgerExpenses = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(debit),0) AS total FROM ledger_entries WHERE tenant_id = ? AND account = 'expenses' AND entry_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]);
    $ledgerExpenses = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $ledgerExpenses = 0.0;
}

$totalRevenue = $feeRevenue + $ledgerRevenue;
$totalExpenses = $tableExpenses + $ledgerExpenses;
$netIncome = $totalRevenue - $totalExpenses;

$expenseBreakdown = [];
try {
    $expenseBreakdown = db()->fetchAll("SELECT category, SUM(amount) AS total FROM expenses WHERE tenant_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY) GROUP BY category ORDER BY total DESC", [$tenantId]) ?: [];
} catch (Throwable $e) {
    $expenseBreakdown = [];
}

$page_title = 'Profit & Loss Statement';
$page_icon = 'monitoring';
$page_subtitle = 'Income versus expenses for the selected reporting period.';

ob_start();
?>

<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($validPeriods as $p): ?>
        <a href="?period=<?php echo urlencode($p); ?>" class="px-4 py-2 rounded-full text-sm font-semibold border transition <?php echo $period === $p ? 'bg-primary text-white border-primary' : 'border-outline-variant text-outline hover:bg-surface-container-low'; ?>">
            <?php echo ucfirst($p); ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Total Revenue</p>
        <p class="text-3xl font-extrabold text-secondary">$<?php echo number_format($totalRevenue, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Total Expenses</p>
        <p class="text-3xl font-extrabold text-error">$<?php echo number_format($totalExpenses, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2"><?php echo $netIncome >= 0 ? 'Net Profit' : 'Net Loss'; ?></p>
        <p class="text-3xl font-extrabold <?php echo $netIncome >= 0 ? 'text-secondary' : 'text-error'; ?>">$<?php echo number_format(abs($netIncome), 2); ?></p>
    </div>
</div>

<div class="bg-surface-container-high rounded-xl p-5 border border-outline-variant/10 mb-8">
    <div class="flex flex-wrap gap-6 text-sm">
        <div>
            <p class="text-outline uppercase tracking-wider text-[11px] font-bold">Reporting Window</p>
            <p class="font-semibold text-on-surface">Last <?php echo (int)$interval; ?> days (<?php echo htmlspecialchars(ucfirst($period)); ?>)</p>
        </div>
        <div>
            <p class="text-outline uppercase tracking-wider text-[11px] font-bold">Net Margin</p>
            <p class="font-semibold <?php echo $totalRevenue > 0 && $netIncome >= 0 ? 'text-secondary' : 'text-error'; ?>">
                <?php echo $totalRevenue > 0 ? number_format(($netIncome / $totalRevenue) * 100, 2) : '0.00'; ?>%
            </p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
    <div class="bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-container-high">
            <h3 class="text-base font-bold">Revenue Breakdown</h3>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-surface-container-low">
                <tr>
                    <td class="px-4 py-3 text-outline">Fee Collections</td>
                    <td class="px-4 py-3 text-right">$<?php echo number_format($feeRevenue, 2); ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-outline">Other Revenue (Ledger)</td>
                    <td class="px-4 py-3 text-right">$<?php echo number_format($ledgerRevenue, 2); ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold">Total Revenue</td>
                    <td class="px-4 py-3 text-right font-bold text-secondary">$<?php echo number_format($totalRevenue, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-container-high">
            <h3 class="text-base font-bold">Expense Breakdown</h3>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-surface-container-low">
                <?php if (empty($expenseBreakdown)): ?>
                    <tr>
                        <td colspan="2" class="px-4 py-10 text-center text-outline">No expenses recorded for this period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenseBreakdown as $entry): ?>
                        <tr>
                            <td class="px-4 py-3"><?php echo ucfirst(htmlspecialchars((string)($entry['category'] ?? 'uncategorized'))); ?></td>
                            <td class="px-4 py-3 text-right">$<?php echo number_format((float)($entry['total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr>
                    <td class="px-4 py-3 font-bold">Total Expenses</td>
                    <td class="px-4 py-3 text-right font-bold text-error">$<?php echo number_format($totalExpenses, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'reports', $page_content, $_SESSION['full_name'] ?? 'Accountant');
