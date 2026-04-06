<?php

/**
 * Accountant General Ledger
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
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Security token invalid. Please refresh and retry.';
        $messageType = 'error';
    } else {
        $data = [
            'tenant_id' => $tenantId,
            'entry_date' => trim($_POST['entry_date'] ?? date('Y-m-d')),
            'description' => trim($_POST['description'] ?? ''),
            'debit' => (float)($_POST['debit'] ?? 0),
            'credit' => (float)($_POST['credit'] ?? 0),
            'account' => trim($_POST['account'] ?? ''),
            'reference' => trim($_POST['reference'] ?? ''),
            'created_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if ($data['description'] === '' || ($data['debit'] <= 0 && $data['credit'] <= 0)) {
            $message = 'Provide a description and at least one positive amount (debit or credit).';
            $messageType = 'error';
        } else {
            try {
                insert_flexible('ledger_entries', $data);
                $message = 'Ledger entry recorded successfully.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'Error recording ledger entry.';
                $messageType = 'error';
            }
        }
    }
}

$entries = [];
try {
    $entries = db()->fetchAll(
        "SELECT * FROM ledger_entries WHERE tenant_id = ? ORDER BY entry_date DESC, id DESC LIMIT 200",
        [$tenantId]
    ) ?: [];
} catch (Throwable $e) {
    $entries = [];
}

$totalDebit = array_sum(array_map(static function ($e) {
    return (float)($e['debit'] ?? 0);
}, $entries));

$totalCredit = array_sum(array_map(static function ($e) {
    return (float)($e['credit'] ?? 0);
}, $entries));

$balance = $totalDebit - $totalCredit;

$page_title = 'General Ledger';
$page_icon = 'account_balance';
$page_subtitle = 'Double-entry transaction records and account balances.';

ob_start();
?>

<?php if ($message !== ''): ?>
    <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium <?php echo $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="mb-8">
    <h2 class="text-3xl font-headline font-extrabold tracking-tight text-on-surface">General Ledger</h2>
    <p class="text-on-surface-variant mt-1">Detailed record of all financial transactions for the current period.</p>
</div>

<div class="flex space-x-6 overflow-x-auto pb-6 mb-2">
    <div class="flex-none w-72 bg-surface-container-lowest p-6 rounded-xl shadow-sm border-l-4 border-primary">
        <div class="flex justify-between items-start mb-4">
            <span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">account_balance_wallet</span>
            <span class="text-xs font-bold text-primary px-2 py-0.5 bg-primary/10 rounded-full">Liquid</span>
        </div>
        <p class="text-on-surface-variant text-sm font-medium">Cash at Bank</p>
        <p class="text-2xl font-headline font-bold text-on-surface mt-1">$<?php echo number_format($totalDebit, 2); ?></p>
    </div>
    <div class="flex-none w-72 bg-surface-container-lowest p-6 rounded-xl shadow-sm border-l-4 border-secondary-container">
        <div class="flex justify-between items-start mb-4">
            <span class="material-symbols-outlined text-secondary bg-secondary-container/20 p-2 rounded-lg">trending_up</span>
            <span class="text-xs font-bold text-secondary px-2 py-0.5 bg-secondary-container/20 rounded-full">Pending</span>
        </div>
        <p class="text-on-surface-variant text-sm font-medium">Accounts Receivable</p>
        <p class="text-2xl font-headline font-bold text-on-surface mt-1">$<?php echo number_format(max(0, $balance), 2); ?></p>
    </div>
    <div class="flex-none w-72 bg-surface-container-lowest p-6 rounded-xl shadow-sm border-l-4 border-error">
        <div class="flex justify-between items-start mb-4">
            <span class="material-symbols-outlined text-error bg-error-container/30 p-2 rounded-lg">trending_down</span>
            <span class="text-xs font-bold text-error px-2 py-0.5 bg-error-container/30 rounded-full">Due</span>
        </div>
        <p class="text-on-surface-variant text-sm font-medium">Accounts Payable</p>
        <p class="text-2xl font-headline font-bold text-on-surface mt-1">$<?php echo number_format($totalCredit, 2); ?></p>
    </div>
    <a href="#quick-journal" class="flex-none w-72 bg-primary-container p-6 rounded-xl shadow-md flex flex-col justify-center items-center text-white hover:opacity-90 transition-all">
        <span class="material-symbols-outlined text-4xl mb-2">add_circle</span>
        <p class="font-headline font-bold">New Journal Entry</p>
    </a>
</div>

<div class="grid grid-cols-12 gap-8">
    <div class="col-span-12 lg:col-span-9 space-y-6">
        <div class="bg-surface-container-lowest p-6 rounded-xl shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Account Filter</label>
                    <select class="w-full bg-surface-container-low border-none rounded-lg text-sm py-2 px-3 focus:ring-primary/20">
                        <option>All Accounts</option>
                        <option>Assets</option>
                        <option>Liabilities</option>
                        <option>Revenue</option>
                        <option>Expenses</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Date Range</label>
                    <div class="flex items-center bg-surface-container-low rounded-lg px-3 py-2">
                        <span class="material-symbols-outlined text-sm text-slate-400 mr-2">calendar_today</span>
                        <input class="bg-transparent border-none p-0 text-sm w-full focus:ring-0" type="text" value="<?php echo htmlspecialchars(date('M d, Y')); ?>" />
                    </div>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Status</label>
                    <div class="flex bg-surface-container-low rounded-lg p-1">
                        <button type="button" class="flex-1 py-1 px-3 text-xs font-bold bg-white shadow-sm rounded-md">All</button>
                        <button type="button" class="flex-1 py-1 px-3 text-xs font-bold text-on-surface-variant">Reconciled</button>
                    </div>
                </div>
                <div class="flex items-end">
                    <button type="button" class="p-2 bg-surface-container-high rounded-lg text-primary hover:bg-primary-fixed transition-colors">
                        <span class="material-symbols-outlined">filter_list</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wider font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Account &amp; Reference</th>
                        <th class="px-6 py-4">Description</th>
                        <th class="px-6 py-4 text-right">Debit</th>
                        <th class="px-6 py-4 text-right">Credit</th>
                        <th class="px-6 py-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container-low text-sm">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-on-surface-variant">No ledger entries yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_slice($entries, 0, 40) as $e): ?>
                            <?php
                            $entryDate = !empty($e['entry_date']) ? date('M d, Y', strtotime((string)$e['entry_date'])) : '-';
                            $debit = (float)($e['debit'] ?? 0);
                            $credit = (float)($e['credit'] ?? 0);
                            $isRecon = (($e['reference'] ?? '') !== '');
                            ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($entryDate); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-on-surface"><?php echo htmlspecialchars(strtoupper((string)($e['account'] ?? 'ACCOUNT'))); ?></div>
                                    <div class="text-[10px] text-on-surface-variant">REF: <?php echo htmlspecialchars((string)($e['reference'] ?? 'N/A')); ?></div>
                                </td>
                                <td class="px-6 py-4 text-on-surface-variant italic max-w-[260px] truncate"><?php echo htmlspecialchars((string)($e['description'] ?? '')); ?></td>
                                <td class="px-6 py-4 text-right font-mono font-bold <?php echo $debit > 0 ? 'text-error' : ''; ?>"><?php echo $debit > 0 ? number_format($debit, 2) : '0.00'; ?></td>
                                <td class="px-6 py-4 text-right font-mono font-bold <?php echo $credit > 0 ? 'text-primary' : ''; ?>"><?php echo $credit > 0 ? number_format($credit, 2) : '0.00'; ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $isRecon ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                        <?php echo $isRecon ? 'Reconciled' : 'Pending'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="px-6 py-4 bg-surface-container-low flex justify-between items-center text-xs font-bold text-on-surface-variant uppercase tracking-widest">
                <span>Showing <?php echo min(40, count($entries)); ?> of <?php echo count($entries); ?> entries</span>
                <div class="flex space-x-2">
                    <button type="button" class="px-3 py-1 bg-surface-container-lowest rounded-md border border-outline-variant/10 shadow-sm hover:bg-white">Prev</button>
                    <button type="button" class="px-3 py-1 bg-primary text-white rounded-md shadow-sm">1</button>
                    <button type="button" class="px-3 py-1 bg-surface-container-lowest rounded-md border border-outline-variant/10 shadow-sm hover:bg-white">2</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 space-y-6">
        <div class="bg-surface-container-highest p-6 rounded-xl shadow-sm relative overflow-hidden">
            <div class="relative z-10">
                <h3 class="font-headline font-extrabold text-on-surface mb-6 flex items-center">
                    <span class="material-symbols-outlined mr-2 text-primary">balance</span>
                    Trial Balance Summary
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-[10px] font-bold text-on-surface-variant uppercase">Assets</p>
                            <p class="text-lg font-headline font-bold text-on-surface">$<?php echo number_format($totalDebit, 2); ?></p>
                        </div>
                    </div>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-[10px] font-bold text-on-surface-variant uppercase">Liabilities</p>
                            <p class="text-lg font-headline font-bold text-on-surface">$<?php echo number_format($totalCredit, 2); ?></p>
                        </div>
                    </div>
                    <div class="pt-4 mt-4 border-t border-primary/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-primary uppercase">Status</span>
                            <div class="flex items-center text-emerald-600 font-bold text-xs">
                                <span class="material-symbols-outlined text-sm mr-1">check_circle</span>
                                IN BALANCE
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="absolute -right-4 -bottom-4 opacity-10">
                <span class="material-symbols-outlined text-[120px] text-primary" style="font-variation-settings: 'FILL' 1;">account_balance</span>
            </div>
        </div>

        <div id="quick-journal" class="bg-surface-container-lowest p-6 rounded-xl shadow-sm border-t-4 border-primary">
            <h3 class="font-headline font-bold text-on-surface mb-4">New Entry</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div>
                    <label class="block text-[10px] font-bold text-on-surface-variant uppercase mb-1">Date</label>
                    <input type="date" name="entry_date" class="w-full bg-surface-container-low border-none rounded-lg text-sm p-2 focus:ring-primary/20" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-on-surface-variant uppercase mb-1">Account</label>
                    <select name="account" class="w-full bg-surface-container-low border-none rounded-lg text-sm p-2 focus:ring-primary/20" required>
                        <option value="assets">Assets</option>
                        <option value="liabilities">Liabilities</option>
                        <option value="revenue">Revenue</option>
                        <option value="expenses">Expenses</option>
                        <option value="equity">Equity</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-on-surface-variant uppercase mb-1">Debit</label>
                        <input type="number" name="debit" min="0" step="0.01" class="w-full bg-surface-container-low border-none rounded-lg text-sm p-2 focus:ring-primary/20" placeholder="0.00" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-on-surface-variant uppercase mb-1">Credit</label>
                        <input type="number" name="credit" min="0" step="0.01" class="w-full bg-surface-container-low border-none rounded-lg text-sm p-2 focus:ring-primary/20" placeholder="0.00" />
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-on-surface-variant uppercase mb-1">Description</label>
                    <input type="text" name="description" class="w-full bg-surface-container-low border-none rounded-lg text-sm p-2 focus:ring-primary/20" placeholder="Transaction details..." required />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-on-surface-variant uppercase mb-1">Reference</label>
                    <input type="text" name="reference" class="w-full bg-surface-container-low border-none rounded-lg text-sm p-2 focus:ring-primary/20" placeholder="Ref code" />
                </div>
                <button type="submit" class="w-full bg-primary-container text-white font-headline font-bold py-3 rounded-xl hover:bg-primary transition-all shadow-sm">Post Entry</button>
            </form>
        </div>

        <div class="bg-blue-600 p-6 rounded-xl text-white shadow-lg relative overflow-hidden">
            <span class="material-symbols-outlined absolute top-4 right-4 text-white/20 text-4xl">info</span>
            <h4 class="font-bold mb-2">Month-End Audit</h4>
            <p class="text-xs text-blue-100 leading-relaxed mb-4">You have unreconciled transactions pending review. Complete reconciliation before monthly close.</p>
            <a class="text-xs font-bold underline underline-offset-4 hover:text-white" href="audit-trail.php">Start Audit Tool</a>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'ledger', $page_content, $_SESSION['full_name'] ?? 'Accountant');
