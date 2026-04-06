<?php

/**
 * Accountant Balance Sheet
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

$assets = 0.0;
$liabilities = 0.0;
$equity = 0.0;

try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM ledger_entries WHERE tenant_id = ? AND account = 'assets'", [$tenantId]);
    $assets = (float)($row['bal'] ?? 0);

    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM ledger_entries WHERE tenant_id = ? AND account = 'liabilities'", [$tenantId]);
    $liabilities = (float)($row['bal'] ?? 0);

    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM ledger_entries WHERE tenant_id = ? AND account = 'equity'", [$tenantId]);
    $equity = (float)($row['bal'] ?? 0);
} catch (Throwable $e) {
    $assets = $liabilities = $equity = 0.0;
}

$revenue = 0.0;
$totalExpenses = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS bal FROM ledger_entries WHERE tenant_id = ? AND account = 'revenue'", [$tenantId]);
    $revenue = (float)($row['bal'] ?? 0);

    $row = db()->fetchOne("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS bal FROM ledger_entries WHERE tenant_id = ? AND account = 'expenses'", [$tenantId]);
    $totalExpenses = (float)($row['bal'] ?? 0);
} catch (Throwable $e) {
    $revenue = 0.0;
    $totalExpenses = 0.0;
}

try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM fee_payments WHERE tenant_id = ?", [$tenantId]);
    $revenue += (float)($row['total'] ?? 0);
} catch (Throwable $e) {
}

try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE tenant_id = ?", [$tenantId]);
    $totalExpenses += (float)($row['total'] ?? 0);
} catch (Throwable $e) {
}

$retainedEarnings = $revenue - $totalExpenses;
$totalLiabilitiesEquity = $liabilities + $equity + $retainedEarnings;
$variance = $assets - $totalLiabilitiesEquity;
$balanced = abs($variance) < 0.01;

$page_title = 'Balance Sheet';
$page_icon = 'account_balance';
$page_subtitle = 'Assets, liabilities, and equity position as of ' . date('M j, Y') . '.';

ob_start();
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Total Assets</p>
        <p class="text-3xl font-extrabold text-primary">$<?php echo number_format(max(0, $assets), 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Liabilities</p>
        <p class="text-3xl font-extrabold text-error">$<?php echo number_format($liabilities, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Equity + Retained</p>
        <p class="text-3xl font-extrabold text-secondary">$<?php echo number_format($equity + $retainedEarnings, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Balance Check</p>
        <p class="text-2xl font-extrabold <?php echo $balanced ? 'text-secondary' : 'text-error'; ?>">
            <?php echo $balanced ? 'Balanced' : 'Variance'; ?>
        </p>
        <p class="mt-2 text-sm text-outline">$<?php echo number_format(abs($variance), 2); ?></p>
    </div>
</div>

<div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-4 mb-8">
    <p class="text-xs font-bold uppercase tracking-wider text-outline mb-2">Accounting Equation</p>
    <p class="text-lg font-headline font-extrabold text-on-surface">
        Assets ($<?php echo number_format($assets, 2); ?>) = Liabilities ($<?php echo number_format($liabilities, 2); ?>) + Equity ($<?php echo number_format($equity + $retainedEarnings, 2); ?>)
    </p>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
    <div class="bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-container-high">
            <h3 class="text-base font-bold">Assets</h3>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-surface-container-low">
                <tr>
                    <td class="px-4 py-3 text-outline">Ledger Asset Position</td>
                    <td class="px-4 py-3 text-right font-bold text-primary">$<?php echo number_format($assets, 2); ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold">Total Assets</td>
                    <td class="px-4 py-3 text-right font-extrabold text-secondary">$<?php echo number_format($assets, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-container-high">
            <h3 class="text-base font-bold">Liabilities & Equity</h3>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-surface-container-low">
                <tr>
                    <td class="px-4 py-3 text-outline">Liabilities</td>
                    <td class="px-4 py-3 text-right">$<?php echo number_format($liabilities, 2); ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-outline">Owner's Equity</td>
                    <td class="px-4 py-3 text-right">$<?php echo number_format($equity, 2); ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 text-outline">Retained Earnings</td>
                    <td class="px-4 py-3 text-right">$<?php echo number_format($retainedEarnings, 2); ?></td>
                </tr>
                <tr>
                    <td class="px-4 py-3 font-bold">Total Liabilities + Equity</td>
                    <td class="px-4 py-3 text-right font-extrabold text-primary">$<?php echo number_format($totalLiabilitiesEquity, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="rounded-2xl border px-6 py-5 <?php echo $balanced ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'; ?>">
    <?php if ($balanced): ?>
        <p class="font-bold"><span class="material-symbols-outlined align-middle mr-1" style="font-size:18px">verified</span>Balance sheet is balanced.</p>
    <?php else: ?>
        <p class="font-bold"><span class="material-symbols-outlined align-middle mr-1" style="font-size:18px">warning</span>Balance sheet variance detected: $<?php echo number_format(abs($variance), 2); ?>.</p>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'reports', $page_content, $_SESSION['full_name'] ?? 'Accountant');
