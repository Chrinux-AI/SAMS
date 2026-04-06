<?php

/**
 * Accountant Expenses
 * Modernized Stitch-inspired page using shared master dashboard layout.
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
$statusMessage = '';
$statusType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $statusMessage = 'Security validation failed. Please refresh and try again.';
        $statusType = 'error';
    } else {
        $action = trim($_POST['action'] ?? '');
        if ($action === 'add') {
            $data = [
                'tenant_id' => $tenantId,
                'expense_date' => trim($_POST['expense_date'] ?? date('Y-m-d')),
                'category' => trim($_POST['category'] ?? 'other'),
                'description' => trim($_POST['description'] ?? ''),
                'amount' => (float)($_POST['amount'] ?? 0),
                'vendor' => trim($_POST['vendor'] ?? ''),
                'payment_method' => trim($_POST['payment_method'] ?? 'cash'),
                'receipt_number' => trim($_POST['receipt_number'] ?? ''),
                'approved_by' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($data['amount'] <= 0 || $data['description'] === '') {
                $statusMessage = 'Please provide a description and a valid amount.';
                $statusType = 'error';
            } else {
                try {
                    insert_flexible('expenses', $data);
                    $statusMessage = 'Expense recorded successfully.';
                    $statusType = 'success';
                } catch (Throwable $e) {
                    $statusMessage = 'Could not record expense. Please try again.';
                    $statusType = 'error';
                }
            }
        }
    }
}

$expenses = [];
try {
    $expenses = db()->fetchAll(
        "SELECT * FROM expenses WHERE tenant_id = ? ORDER BY expense_date DESC, created_at DESC LIMIT 200",
        [$tenantId]
    ) ?: [];
} catch (Throwable $e) {
    $expenses = [];
}

$totalExpenses = array_sum(array_map(static function ($e) {
    return (float)($e['amount'] ?? 0);
}, $expenses));

$currentMonth = date('Y-m');
$monthlyExpenses = array_sum(array_map(static function ($e) use ($currentMonth) {
    return (isset($e['expense_date']) && strpos((string)$e['expense_date'], $currentMonth) === 0)
        ? (float)($e['amount'] ?? 0)
        : 0;
}, $expenses));

$previousMonth = date('Y-m', strtotime('-1 month'));
$previousMonthExpenses = array_sum(array_map(static function ($e) use ($previousMonth) {
    return (isset($e['expense_date']) && strpos((string)$e['expense_date'], $previousMonth) === 0)
        ? (float)($e['amount'] ?? 0)
        : 0;
}, $expenses));

$monthChangePct = 0.0;
if ($previousMonthExpenses > 0) {
    $monthChangePct = (($monthlyExpenses - $previousMonthExpenses) / $previousMonthExpenses) * 100;
} elseif ($monthlyExpenses > 0) {
    $monthChangePct = 100.0;
}

$monthChangeLabel = ($monthChangePct >= 0 ? '+' : '') . number_format($monthChangePct, 1) . '% vs last month';

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$liveCategories = [];
foreach ($expenses as $e) {
    $status = strtolower((string)($e['status'] ?? ''));
    $isPending = in_array($status, ['pending', 'submitted', 'draft'], true);
    if ($status === 'approved' || $status === 'paid') {
        $approvedCount++;
    }
    if ($status === 'rejected' || $status === 'failed') {
        $rejectedCount++;
    }
    if ($isPending) {
        $pendingCount++;
    }

    $category = trim((string)($e['category'] ?? ''));
    if ($category !== '') {
        $liveCategories[strtolower($category)] = ucwords(str_replace('_', ' ', strtolower($category)));
    }
}

if (empty($liveCategories)) {
    $liveCategories = [
        'supplies' => 'Supplies',
        'utilities' => 'Utilities',
        'maintenance' => 'Maintenance',
        'welfare' => 'Welfare',
        'other' => 'Other',
    ];
}

$statusTotal = max(1, $approvedCount + $pendingCount + $rejectedCount);
$approvedBar = (int)max(15, round(($approvedCount / $statusTotal) * 100));
$pendingBar = (int)max(15, round(($pendingCount / $statusTotal) * 100));
$rejectedBar = (int)max(15, round(($rejectedCount / $statusTotal) * 100));

$operationalHealth = $pendingCount <= 5 ? 'Stable' : ($pendingCount <= 12 ? 'Watch' : 'Action Needed');
$operationalSummary = $operationalHealth === 'Stable'
    ? 'Approvals are flowing normally with low pending requests.'
    : ($operationalHealth === 'Watch'
        ? 'Pending approvals are rising; schedule a review window.'
        : 'Backlog is high; prioritize approvals and expense reconciliation.');

$page_title = 'Expense Management';
$page_icon = 'receipt_long';
$page_subtitle = 'Review, record, and track institutional expenditures.';

ob_start();
?>

<?php if ($statusMessage !== ''): ?>
    <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium <?php echo $statusType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'; ?>">
        <?php echo htmlspecialchars($statusMessage); ?>
    </div>
<?php endif; ?>

<div class="mb-8">
    <h2 class="text-3xl font-headline font-extrabold tracking-tight text-on-surface">Expense Management</h2>
    <p class="text-on-surface-variant mt-1">Review and approve institutional expenditures.</p>
</div>

<div class="flex flex-wrap gap-3 justify-end mb-6">
    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/40 bg-surface-container-lowest px-4 py-2 text-sm font-bold">
        <span class="material-symbols-outlined text-base">file_download</span> CSV
    </button>
    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/40 bg-surface-container-lowest px-4 py-2 text-sm font-bold">
        <span class="material-symbols-outlined text-base">picture_as_pdf</span> PDF
    </button>
    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-6 py-2 text-sm font-bold shadow-md shadow-primary/20" onclick="document.getElementById('quick-add-expense').scrollIntoView({behavior:'smooth'})">
        <span class="material-symbols-outlined text-base">add</span> Record New Expense
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-7">
    <div class="bg-surface-container-lowest p-6 rounded-lg border border-outline-variant/10 shadow-sm relative overflow-hidden">
        <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Total Expenses (This Month)</p>
        <h2 class="text-4xl font-extrabold tracking-tight text-on-surface tabular-nums"><?php echo format_local_currency($monthlyExpenses, 2, $tenantId); ?></h2>
        <div class="mt-4 flex items-center gap-2 text-tertiary font-bold text-sm">
            <span class="material-symbols-outlined text-base">trending_up</span>
            <span><?php echo htmlspecialchars($monthChangeLabel); ?></span>
        </div>
    </div>

    <div class="bg-surface-container-lowest p-6 rounded-lg border border-outline-variant/10 shadow-sm">
        <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Approved vs Pending</p>
        <h2 class="text-4xl font-extrabold tracking-tight text-on-surface tabular-nums"><?php echo (int)$approvedCount; ?> / <?php echo (int)$pendingCount; ?></h2>
        <p class="text-xs text-on-surface-variant font-medium mt-1">Approved / Pending records</p>
        <div class="mt-4 h-10 flex items-end gap-1.5">
            <div class="w-4 bg-primary rounded-t" style="height:<?php echo $approvedBar; ?>%" title="Approved"></div>
            <div class="w-4 bg-secondary-container rounded-t" style="height:<?php echo $pendingBar; ?>%" title="Pending"></div>
            <div class="w-4 bg-error rounded-t" style="height:<?php echo $rejectedBar; ?>%" title="Rejected"></div>
        </div>
    </div>

    <div class="bg-primary p-6 rounded-lg shadow-xl shadow-primary/20 text-on-primary">
        <p class="text-[10px] font-bold text-on-primary/70 uppercase tracking-[0.2em] mb-2">Operational Health</p>
        <h2 class="text-4xl font-extrabold tracking-tight"><?php echo htmlspecialchars($operationalHealth); ?></h2>
        <p class="text-sm font-medium text-on-primary/80 mt-2"><?php echo htmlspecialchars($operationalSummary); ?></p>
        <div class="mt-4 h-1.5 w-full bg-on-primary/20 rounded-full overflow-hidden">
            <div class="h-full bg-on-primary" style="width:<?php echo $operationalHealth === 'Stable' ? '85' : ($operationalHealth === 'Watch' ? '60' : '40'); ?>%"></div>
        </div>
    </div>
</div>

<div class="bg-surface-container-low p-4 rounded-lg mb-6 flex flex-wrap items-center gap-4">
    <div class="relative flex-1 min-w-[220px]">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
        <input class="w-full rounded-lg border border-outline-variant/40 bg-surface-container-lowest pl-10 pr-4 py-2 text-sm" placeholder="Search by vendor or description..." type="text" />
    </div>
    <select class="rounded-lg border border-outline-variant/40 bg-surface-container-lowest px-4 py-2 text-sm font-semibold text-on-surface-variant">
        <option>All Categories</option>
        <?php foreach ($liveCategories as $catValue => $catLabel): ?>
            <option value="<?php echo htmlspecialchars((string)$catValue); ?>"><?php echo htmlspecialchars((string)$catLabel); ?></option>
        <?php endforeach; ?>
    </select>
    <div class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/40 bg-surface-container-lowest px-3 py-2 text-sm font-semibold text-on-surface-variant">
        <span class="material-symbols-outlined text-base">calendar_month</span>
        <span><?php echo date('M 01'); ?> - <?php echo date('M t, Y'); ?></span>
    </div>
    <button type="button" class="p-2 rounded-lg text-outline hover:bg-surface-container-highest">
        <span class="material-symbols-outlined">refresh</span>
    </button>
</div>

<div class="bg-surface-container-lowest rounded-lg border border-outline-variant/10 shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-surface-container-high/30 border-b border-outline-variant/30">
            <tr>
                <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Date</th>
                <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Category</th>
                <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Description</th>
                <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Vendor</th>
                <th class="px-6 py-4 text-right text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Amount</th>
                <th class="px-6 py-4 text-left text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Status</th>
                <th class="px-6 py-4 text-right text-[10px] uppercase tracking-[0.2em] text-on-surface-variant/70 font-bold">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/20">
            <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-on-surface-variant">No expenses recorded yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($expenses as $e): ?>
                    <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-on-surface-variant"><?php echo !empty($e['expense_date']) ? date('M j, Y', strtotime((string)$e['expense_date'])) : '-'; ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 rounded bg-secondary-container text-on-secondary-container text-[10px] uppercase tracking-wider font-bold">
                                <?php echo htmlspecialchars((string)($e['category'] ?? 'other')); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-on-surface"><?php echo htmlspecialchars((string)($e['description'] ?? '')); ?></td>
                        <td class="px-6 py-4 text-sm text-on-surface-variant font-medium"><?php echo htmlspecialchars((string)($e['vendor'] ?? '-')); ?></td>
                        <td class="px-6 py-4 text-right text-sm font-extrabold tabular-nums text-on-surface"><?php echo format_local_currency((float)($e['amount'] ?? 0), 2, $tenantId); ?></td>
                        <td class="px-6 py-4">
                            <?php
                            $rowStatus = strtolower((string)($e['status'] ?? 'submitted'));
                            $statusClass = 'bg-surface-container-highest text-on-surface-variant';
                            if ($rowStatus === 'approved' || $rowStatus === 'paid') {
                                $statusClass = 'bg-primary/10 text-primary';
                            } elseif ($rowStatus === 'rejected' || $rowStatus === 'failed') {
                                $statusClass = 'bg-error-container text-error';
                            } elseif ($rowStatus === 'draft') {
                                $statusClass = 'bg-surface-container-highest text-on-surface-variant';
                            } elseif ($rowStatus === 'pending' || $rowStatus === 'submitted') {
                                $statusClass = 'bg-secondary-container text-on-secondary-container';
                            }
                            ?>
                            <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars(ucfirst($rowStatus)); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex gap-1">
                                <button type="button" class="p-2 rounded-lg text-outline hover:bg-surface-container-highest" title="Edit">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </button>
                                <button type="button" class="p-2 rounded-lg text-tertiary hover:bg-tertiary-container/40" title="Delete">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="px-6 py-4 border-t border-outline-variant/30 flex items-center justify-between bg-surface-container-lowest">
        <p class="text-xs font-bold text-on-surface-variant/60">Showing <?php echo min(40, count($expenses)); ?> to <?php echo min(40, count($expenses)); ?> of <?php echo count($expenses); ?> results</p>
        <div class="flex gap-2">
            <button type="button" class="w-8 h-8 flex items-center justify-center rounded hover:bg-surface-container-high transition-colors" disabled>
                <span class="material-symbols-outlined text-base">chevron_left</span>
            </button>
            <button type="button" class="w-8 h-8 flex items-center justify-center rounded bg-primary text-on-primary text-xs font-extrabold">1</button>
            <button type="button" class="w-8 h-8 flex items-center justify-center rounded hover:bg-surface-container-high text-xs font-bold">2</button>
            <button type="button" class="w-8 h-8 flex items-center justify-center rounded hover:bg-surface-container-high text-xs font-bold">3</button>
            <button type="button" class="w-8 h-8 flex items-center justify-center rounded hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-base">chevron_right</span>
            </button>
        </div>
    </div>
</div>

<div id="quick-add-expense" class="mt-7 bg-surface-container-lowest rounded-lg border border-outline-variant/10 p-6 shadow-sm">
    <h3 class="text-lg font-extrabold mb-4">Record New Expense</h3>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        <input type="hidden" name="action" value="add">

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Date</label>
            <input type="date" name="expense_date" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Category</label>
            <select name="category" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm">
                <option value="supplies">Supplies</option>
                <option value="utilities">Utilities</option>
                <option value="maintenance">Maintenance</option>
                <option value="salaries">Salaries</option>
                <option value="transport">Transport</option>
                <option value="events">Events</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Amount</label>
            <input type="number" name="amount" min="0" step="0.01" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm" placeholder="0.00" required>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Vendor</label>
            <input type="text" name="vendor" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm" placeholder="Vendor name">
        </div>
        <div class="lg:col-span-2">
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Description</label>
            <input type="text" name="description" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm" placeholder="Expense details" required>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Payment Method</label>
            <select name="payment_method" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="mobile_money">Mobile Money</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 mb-1">Receipt #</label>
            <input type="text" name="receipt_number" class="w-full rounded-lg border border-outline-variant/40 bg-surface px-3 py-2 text-sm" placeholder="Optional">
        </div>

        <div class="lg:col-span-4 flex justify-end">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-6 py-2.5 text-sm font-extrabold">
                <span class="material-symbols-outlined text-base">add</span>
                Save Expense
            </button>
        </div>
    </form>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'expenses', $page_content, $_SESSION['full_name'] ?? 'Accountant');
