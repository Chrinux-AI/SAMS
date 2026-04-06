<?php

/**
 * Accountant Tax Reports
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

$payrollTax = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(deductions),0) AS total FROM payroll WHERE tenant_id = ?", [$tenantId]);
    $payrollTax = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $payrollTax = 0.0;
}

$taxableIncome = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM fee_payments WHERE tenant_id = ?", [$tenantId]);
    $taxableIncome = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $taxableIncome = 0.0;
}

$deductibleExpenses = 0.0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE tenant_id = ?", [$tenantId]);
    $deductibleExpenses = (float)($row['total'] ?? 0);
} catch (Throwable $e) {
    $deductibleExpenses = 0.0;
}

$netTaxable = max(0, $taxableIncome - $deductibleExpenses);
$estimatedTaxAt30 = $netTaxable * 0.30;

$page_title = 'Tax Reports';
$page_icon = 'receipt_long';
$page_subtitle = 'Tax summaries and compliance-ready financial snapshots.';

ob_start();
?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Gross Revenue</p>
        <p class="text-3xl font-extrabold text-primary">$<?php echo number_format($taxableIncome, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Deductible Expenses</p>
        <p class="text-3xl font-extrabold text-tertiary">$<?php echo number_format($deductibleExpenses, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Net Taxable</p>
        <p class="text-3xl font-extrabold text-secondary">$<?php echo number_format($netTaxable, 2); ?></p>
    </div>
    <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm text-center">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Payroll Deductions</p>
        <p class="text-3xl font-extrabold text-error">$<?php echo number_format($payrollTax, 2); ?></p>
    </div>
</div>

<div class="bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-surface-container-high">
        <h3 class="text-base font-bold">Tax Summary</h3>
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-surface-container-low">
            <tr>
                <td class="px-4 py-3 text-outline">Gross Revenue (Fee Collections)</td>
                <td class="px-4 py-3 text-right">$<?php echo number_format($taxableIncome, 2); ?></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-outline">Less: Deductible Expenses</td>
                <td class="px-4 py-3 text-right text-error">($<?php echo number_format($deductibleExpenses, 2); ?>)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-bold">Net Taxable Income</td>
                <td class="px-4 py-3 text-right font-bold text-secondary">$<?php echo number_format($netTaxable, 2); ?></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-outline">Payroll Tax Withholdings</td>
                <td class="px-4 py-3 text-right">$<?php echo number_format($payrollTax, 2); ?></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-outline">Estimated Tax (30% of Net Taxable)</td>
                <td class="px-4 py-3 text-right">$<?php echo number_format($estimatedTaxAt30, 2); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-surface-container-low rounded-xl p-5 border border-outline-variant/10">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Compliance Notes</p>
        <ul class="list-disc list-inside text-sm text-on-surface-variant space-y-1">
            <li>Review payroll deductions before monthly filing.</li>
            <li>Validate invoices and receipts for deductible claims.</li>
            <li>Export report snapshots for audit archives.</li>
        </ul>
    </div>
    <div class="bg-surface-container-low rounded-xl p-5 border border-outline-variant/10">
        <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Estimated Liability</p>
        <p class="text-3xl font-headline font-extrabold text-primary">$<?php echo number_format($estimatedTaxAt30 + $payrollTax, 2); ?></p>
        <p class="text-xs text-on-surface-variant mt-2">Combined estimate includes payroll withholdings + 30% net taxable income.</p>
    </div>
</div>

<div class="rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 px-6 py-4 text-sm">
    <span class="material-symbols-outlined align-middle mr-1" style="font-size:18px">info</span>
    Tax rates and filing obligations vary by jurisdiction. Review these values with your tax advisor before submission.
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'reports', $page_content, $_SESSION['full_name'] ?? 'Accountant');
