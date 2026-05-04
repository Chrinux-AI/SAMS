<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$msg = '';

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payroll_' . date('Y-m-d_H-i-s') . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, ['Employee', 'Period', 'Basic Salary', 'Allowances', 'Deductions', 'Net Pay', 'Status']);
        try {
            $employeeColumn = table_has_column('payroll', 'employee_id') ? 'employee_id' : 'user_id';
            $csvRows = db()->fetchAll(
                "SELECT p.pay_period, p.basic_salary, p.allowances, p.deductions, p.net_pay, p.status, u.full_name
                 FROM payroll p
                 LEFT JOIN users u ON p.{$employeeColumn} = u.id
                 WHERE p.tenant_id = ?
                 ORDER BY p.created_at DESC",
                [$tenantId]
            ) ?: [];

            foreach ($csvRows as $row) {
                fputcsv($out, [
                    (string)($row['full_name'] ?? 'N/A'),
                    (string)($row['pay_period'] ?? ''),
                    (float)($row['basic_salary'] ?? 0),
                    (float)($row['allowances'] ?? 0),
                    (float)($row['deductions'] ?? 0),
                    (float)($row['net_pay'] ?? 0),
                    (string)($row['status'] ?? ''),
                ]);
            }
        } catch (Throwable $e) {
            // Keep response valid
        }
        fclose($out);
    }
    exit;
}

if (($_GET['run_batch'] ?? '') === '1') {
    try {
        db()->query("UPDATE payroll SET status = 'paid' WHERE tenant_id = ? AND status IN ('draft', 'processed')", [$tenantId]);
        $msg = 'Payroll batch processed. Draft and processed entries marked as paid.';
    } catch (Throwable $e) {
        $msg = 'Could not process payroll batch.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $netPay = floatval($_POST['basic_salary'] ?? 0) + floatval($_POST['allowances'] ?? 0) - floatval($_POST['deductions'] ?? 0);
    $data = ['tenant_id' => $tenantId, 'employee_id' => $employeeId, 'user_id' => $employeeId, 'pay_period' => trim($_POST['pay_period'] ?? ''), 'basic_salary' => floatval($_POST['basic_salary'] ?? 0), 'allowances' => floatval($_POST['allowances'] ?? 0), 'deductions' => floatval($_POST['deductions'] ?? 0), 'net_pay' => $netPay, 'amount' => $netPay, 'status' => 'draft', 'created_at' => date('Y-m-d H:i:s')];
    try {
        insert_flexible('payroll', $data);
        $msg = 'Payroll entry added.';
    } catch (Exception $e) {
        $msg = 'Error adding payroll entry.';
    }
}
$payroll = [];
try {
    $employeeColumn = table_has_column('payroll', 'employee_id') ? 'employee_id' : 'user_id';
    $payroll = db()->fetchAll("SELECT p.*, u.full_name FROM payroll p LEFT JOIN users u ON p.{$employeeColumn} = u.id WHERE p.tenant_id = ? ORDER BY p.created_at DESC LIMIT 100", [$tenantId]);
} catch (Exception $e) {
}
$staff = [];
try {
    $staff = db()->fetchAll("SELECT id, full_name, role FROM users WHERE tenant_id = ? AND role IN ('teacher','admin','bursar','accountant','librarian') ORDER BY full_name", [$tenantId]);
} catch (Exception $e) {
}
$totalNet = array_sum(array_column($payroll, 'net_pay'));
$totalDeductions = array_sum(array_map(static function ($row) {
    return (float)($row['deductions'] ?? 0);
}, $payroll));
$grossPayroll = array_sum(array_map(static function ($row) {
    return (float)($row['basic_salary'] ?? 0) + (float)($row['allowances'] ?? 0);
}, $payroll));

$page_title = 'Payroll Management';
$page_icon = 'payments';
$page_subtitle = 'Create payroll entries and track compensation records.';

$activeTab = 'payroll';
require_once __DIR__ . '/partials/header.php';
?>

<?php if ($msg): ?><div class="rounded-xl border border-primary/20 bg-primary text-primary px-4 py-3 mb-5 text-sm font-medium"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 flex flex-col justify-center">
        <h2 class="text-4xl font-headline font-extrabold tracking-tight text-on-surface mb-2">Payroll Run Creator</h2>
        <p class="text-on-surface-variant max-w-lg">Initiate and manage employee compensation cycles. Ensure all adjustments are finalized before processing.</p>
    </div>
    <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm space-y-4">
        <div>
            <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Target Period</label>
            <input type="text" class="w-full bg-surface-container-low border-none rounded-xl mt-1" value="<?= htmlspecialchars(date('F Y')) ?>" readonly>
        </div>
        <a href="index.php?page=payroll&amp;run_batch=1" class="inline-flex w-full items-center justify-center bg-gradient-to-r from-primary to-primary-container text-white py-3 rounded-xl font-bold shadow-sm">
            Process Payroll Batch
        </a>
    </div>
</section>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-surface-container-low p-6 rounded-3xl">
        <p class="text-sm font-semibold text-on-surface-variant">Gross Payroll</p>
        <p class="text-3xl font-headline font-black text-on-surface mt-1"><?= accountant_currency((float)$grossPayroll) ?></p>
        <div class="mt-4 flex items-center gap-2 text-xs text-secondary font-bold">
            <span class="material-symbols-outlined text-sm">trending_up</span>
            <span>+4.2% from last month</span>
        </div>
    </div>
    <div class="bg-surface-container-low p-6 rounded-3xl">
        <p class="text-sm font-semibold text-on-surface-variant">Total Deductions</p>
        <p class="text-3xl font-headline font-black text-on-surface mt-1"><?= accountant_currency((float)$totalDeductions) ?></p>
        <div class="mt-4 flex items-center gap-2 text-xs text-on-surface-variant font-medium">
            <span class="material-symbols-outlined text-sm">info</span>
            <span>Tax, Pension, Insurance</span>
        </div>
    </div>
    <div class="bg-primary text-white p-6 rounded-3xl shadow-sm">
        <p class="text-sm font-semibold opacity-80">Net Disbursement</p>
        <p class="text-3xl font-headline font-black mt-1"><?= accountant_currency((float)$totalNet) ?></p>
        <div class="mt-4 flex items-center gap-2 text-xs font-bold">
            <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">check_circle</span>
            <span>Calculated &amp; Ready</span>
        </div>
    </div>
    <div class="bg-surface-container-highest p-6 rounded-3xl">
        <p class="text-sm font-semibold text-on-surface-variant">Active Staff</p>
        <p class="text-3xl font-headline font-black text-primary mt-1"><?= count($staff) ?></p>
        <p class="text-xs text-on-surface-variant mt-2">Processed Monthly</p>
    </div>
</section>

<section class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-8">
    <div class="xl:col-span-8 bg-surface-container-lowest rounded-3xl border border-surface-container-high shadow-sm overflow-hidden">
        <div class="p-6 border-b border-outline-variant/10 flex justify-between items-center bg-surface-container-low">
            <h3 class="font-headline font-bold text-xl">Staff Salary Setup</h3>
            <a href="index.php?page=payroll&amp;export=csv" class="inline-flex items-center bg-surface-container-high text-primary px-4 py-2 rounded-xl text-sm font-bold">Export CSV</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 bg-white z-10">
                    <tr class="text-xs font-bold text-on-surface-variant/60 uppercase tracking-widest border-b border-outline-variant/10">
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Period</th>
                        <th class="px-6 py-4 text-right">Basic</th>
                        <th class="px-6 py-4 text-right">Allowances</th>
                        <th class="px-6 py-4 text-right">Deductions</th>
                        <th class="px-6 py-4 text-right">Net</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/5">
                    <?php if (empty($payroll)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-outline">No payroll entries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payroll as $p): ?>
                            <?php $status = (string)($p['status'] ?? 'pending'); ?>
                            <tr class="hover:bg-surface-container-low transition-colors">
                                <td class="px-6 py-4 font-medium"><?= htmlspecialchars((string)($p['full_name'] ?? 'N/A')) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars((string)($p['pay_period'] ?? '')) ?></td>
                                <td class="px-6 py-4 text-right"><?= accountant_currency((float)($p['basic_salary'] ?? 0)) ?></td>
                                <td class="px-6 py-4 text-right text-secondary">+<?= accountant_currency((float)($p['allowances'] ?? 0)) ?></td>
                                <td class="px-6 py-4 text-right text-error">-<?= accountant_currency((float)($p['deductions'] ?? 0)) ?></td>
                                <td class="px-6 py-4 text-right font-bold text-primary"><?= accountant_currency((float)($p['net_pay'] ?? 0)) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?php echo $status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                        <?= htmlspecialchars(ucfirst($status)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="xl:col-span-4 bg-white rounded-3xl shadow-sm border border-outline-variant/10 overflow-hidden">
        <div class="bg-primary p-6 text-white">
            <p class="text-xs font-bold uppercase tracking-widest opacity-70">Create Entry</p>
            <h4 class="text-2xl font-headline font-bold mt-1">Payroll Form</h4>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <select name="employee_id" class="w-full rounded-lg border-outline-variant" required>
                    <option value="">Select Staff</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars((string)$s['full_name']) ?> (<?= ucfirst((string)$s['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="pay_period" class="w-full rounded-lg border-outline-variant" required placeholder="e.g. Jan 2025">
                <input type="number" name="basic_salary" class="w-full rounded-lg border-outline-variant" step="0.01" min="0" required placeholder="Basic Salary ($)">
                <input type="number" name="allowances" class="w-full rounded-lg border-outline-variant" step="0.01" min="0" value="0" placeholder="Allowances ($)">
                <input type="number" name="deductions" class="w-full rounded-lg border-outline-variant" step="0.01" min="0" value="0" placeholder="Deductions ($)">
                <button type="submit" class="w-full bg-primary text-white py-3 rounded-xl text-sm font-bold shadow-sm">Add Entry</button>
            </form>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/partials/footer.php';
