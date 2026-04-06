<?php

/**
 * Accountant Income Management
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

$feeIncome = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM fee_payments WHERE tenant_id = ?", [$tenantId]);
    $feeIncome = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $feeIncome = 0.0;
}

$otherIncome = [];
try {
    $otherIncome = db()->fetchAll(
        "SELECT * FROM ledger_entries WHERE tenant_id = ? AND account = 'revenue' AND credit > 0 ORDER BY entry_date DESC, id DESC LIMIT 80",
        [$tenantId]
    ) ?: [];
} catch (Throwable $e) {
    $otherIncome = [];
}

$otherTotal = array_sum(array_map(static function ($r) {
    return (float)($r['credit'] ?? 0);
}, $otherIncome));

$recentPayments = [];
try {
    $recentPayments = db()->fetchAll(
        "SELECT fp.*, u.full_name
         FROM fee_payments fp
         LEFT JOIN users u ON fp.student_id = u.id
         WHERE fp.tenant_id = ?
         ORDER BY fp.payment_date DESC, fp.created_at DESC
         LIMIT 40",
        [$tenantId]
    ) ?: [];
} catch (Throwable $e) {
    $recentPayments = [];
}

$totalIncome = $feeIncome + $otherTotal;

$outstandingTotal = 0.0;
foreach ($recentPayments as $paymentRow) {
    $status = strtolower((string)($paymentRow['status'] ?? 'pending'));
    if (!in_array($status, ['paid', 'approved', 'success'], true)) {
        $outstandingTotal += (float)($paymentRow['amount'] ?? 0);
    }
}

$page_title = 'Income Management';
$page_icon = 'payments';
$page_subtitle = 'Revenue streams, collections, and recent income activities.';

ob_start();
?>

<div class="mb-8">
    <h2 class="text-3xl font-headline font-extrabold tracking-tight text-on-surface">Income Management</h2>
    <p class="text-on-surface-variant mt-1">Fiscal year revenue oversight across collections, other income streams, and student balances.</p>
</div>

<div class="flex flex-wrap justify-end gap-3 mb-6">
    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/40 bg-surface-container-highest px-4 py-2.5 text-sm font-semibold text-primary">
        <span class="material-symbols-outlined text-base">description</span>
        Generate Invoice
    </button>
    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-6 py-2.5 text-sm font-bold shadow-lg shadow-primary/20">
        <span class="material-symbols-outlined text-base">add_circle</span>
        Record Other Income
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    <div class="bg-surface-container-lowest rounded-xl p-6 border-l-4 border-primary shadow-sm">
        <p class="text-[10px] uppercase tracking-[0.2em] text-on-surface-variant font-bold mb-1">Fee Collections</p>
        <div class="text-4xl font-extrabold text-on-surface tabular-nums">$<?php echo number_format($feeIncome, 2); ?></div>
        <div class="mt-4 inline-flex items-center gap-1 rounded-full bg-secondary-container/30 px-2 py-1 text-xs font-bold text-secondary">
            <span class="material-symbols-outlined text-sm">trending_up</span>+12% vs LY
        </div>
    </div>
    <div class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
        <p class="text-[10px] uppercase tracking-[0.2em] text-on-surface-variant font-bold mb-1">Other Income</p>
        <div class="text-4xl font-extrabold text-on-surface tabular-nums">$<?php echo number_format($otherTotal, 2); ?></div>
        <p class="mt-4 text-xs text-on-surface-variant italic">Grants, donations &amp; ledger credits</p>
    </div>
    <div class="bg-surface-container-lowest rounded-xl p-6 shadow-sm">
        <p class="text-[10px] uppercase tracking-[0.2em] text-on-surface-variant font-bold mb-1">Total Income</p>
        <div class="text-4xl font-extrabold text-primary tabular-nums">$<?php echo number_format($totalIncome, 2); ?></div>
        <div class="mt-4 h-1.5 rounded-full bg-surface-container-high overflow-hidden">
            <div class="h-full w-3/4 rounded-full bg-primary"></div>
        </div>
    </div>
    <div class="bg-surface-container-lowest rounded-xl p-6 border-l-4 border-error shadow-sm">
        <p class="text-[10px] uppercase tracking-[0.2em] text-on-surface-variant font-bold mb-1">Outstanding Balances</p>
        <div class="text-4xl font-extrabold text-error tabular-nums">$<?php echo number_format($outstandingTotal, 2); ?></div>
        <button type="button" class="mt-4 inline-flex items-center text-xs font-bold text-primary hover:underline">
            Review Aging Report <span class="material-symbols-outlined text-sm ml-1">chevron_right</span>
        </button>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
    <div class="xl:col-span-8 space-y-6">
        <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold">Recent Payments</h3>
            <div class="flex gap-2">
                <select class="rounded-full bg-surface-container-high border-none px-4 py-1.5 text-xs font-bold">
                    <option>All Classes</option>
                </select>
                <select class="rounded-full bg-surface-container-high border-none px-4 py-1.5 text-xs font-bold">
                    <option>Last 30 Days</option>
                </select>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl border border-surface-container-high shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-container-low border-b border-surface-container-high">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-black">Payer Name</th>
                        <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-black">Date</th>
                        <th class="px-6 py-4 text-right text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-black">Amount</th>
                        <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-black">Method</th>
                        <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-black">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container-low">
                    <?php if (empty($recentPayments)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-on-surface-variant">No income records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $p): ?>
                            <?php
                            $paymentStatus = strtolower((string)($p['status'] ?? 'pending'));
                            $badgeClass = 'bg-surface-container-highest text-on-surface-variant';
                            if (in_array($paymentStatus, ['paid', 'approved', 'success'], true)) {
                                $badgeClass = 'bg-secondary-container text-on-secondary-container';
                            } elseif (in_array($paymentStatus, ['failed', 'rejected', 'overdue'], true)) {
                                $badgeClass = 'bg-error-container text-on-error-container';
                            }
                            ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-6 py-4 text-sm font-semibold"><?php echo htmlspecialchars((string)($p['full_name'] ?? 'N/A')); ?></td>
                                <td class="px-6 py-4 text-sm text-on-surface-variant"><?php echo !empty($p['payment_date']) ? date('M j, Y', strtotime((string)$p['payment_date'])) : '-'; ?></td>
                                <td class="px-6 py-4 text-right text-sm font-bold tabular-nums">$<?php echo number_format((float)($p['amount'] ?? 0), 2); ?></td>
                                <td class="px-6 py-4 text-sm text-on-surface-variant"><?php echo htmlspecialchars(ucfirst((string)($p['payment_method'] ?? ''))); ?></td>
                                <td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($paymentStatus)); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="xl:col-span-4 space-y-6">
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-sm border border-surface-container-high">
            <h3 class="text-lg font-bold mb-6">Income Insights</h3>
            <?php
            $tuitionPct = $totalIncome > 0 ? round(($feeIncome / $totalIncome) * 100, 1) : 0;
            $otherPct = max(0, 100 - $tuitionPct);
            ?>
            <div class="space-y-4">
                <div class="flex items-center justify-between"><span class="text-sm font-medium text-on-surface-variant">Tuition Fees</span><span class="text-sm font-bold"><?php echo $tuitionPct; ?>%</span></div>
                <div class="h-3 w-full rounded-full bg-surface-container">
                    <div class="h-full rounded-full bg-primary" style="width:<?php echo min(100, $tuitionPct); ?>%"></div>
                </div>
                <div class="flex items-center justify-between"><span class="text-sm font-medium text-on-surface-variant">Other Income</span><span class="text-sm font-bold"><?php echo $otherPct; ?>%</span></div>
                <div class="h-3 w-full rounded-full bg-surface-container">
                    <div class="h-full rounded-full bg-secondary" style="width:<?php echo min(100, $otherPct); ?>%"></div>
                </div>
            </div>
            <div class="mt-8 rounded-lg border border-primary/10 bg-surface p-4">
                <p class="text-xs italic text-on-surface-variant">"Revenue remains healthy with tuition as the leading income driver. Continue collection reminders for pending balances."</p>
            </div>
        </div>

        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-sm border border-surface-container-high">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold">Critical Overdue</h3>
                <span class="rounded bg-error-container px-2 py-0.5 text-[10px] font-black text-error">Action Required</span>
            </div>
            <div class="space-y-3">
                <?php if ($outstandingTotal <= 0): ?>
                    <p class="text-sm text-on-surface-variant">No critical overdue balances detected.</p>
                <?php else: ?>
                    <div class="rounded-lg bg-surface p-3">
                        <p class="text-sm font-bold">Outstanding student balances</p>
                        <p class="text-xs text-on-surface-variant mt-1">Automate reminders to reduce unpaid fee records.</p>
                        <p class="mt-2 text-base font-extrabold text-error">$<?php echo number_format($outstandingTotal, 2); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="w-full mt-5 rounded-lg border border-primary/20 py-2.5 text-xs font-black uppercase tracking-widest text-primary hover:bg-primary/5">View All Delinquencies</button>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'income', $page_content, $_SESSION['full_name'] ?? 'Accountant');
