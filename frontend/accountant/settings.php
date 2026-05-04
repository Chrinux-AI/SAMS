<?php

/**
 * SAMS - Accountant Settings Page
 * Modern UI with profile avatar, account overview, theme selection
 */

require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'accountant';
$message = '';
$message_type = '';

$user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);

$accountantSettings = [
    'currency' => 'NGN (₦)',
    'fiscal_start_month' => 'July',
    'accounting_method' => 'accrual',
    'rounding_policy' => 'Standard (0.5 up)',
    'base_tax_rate' => '15.00',
    'vat_gst_rate' => '7.5',
    'current_tax_year' => '2024-2025',
    'expense_approval' => '1',
    'budget_approval' => '0',
    'approval_threshold' => '5000',
    'approval_chain' => 'controller@sams.edu, principal@sams.edu, audit@sams.edu',
    'notification_frequency' => 'Daily Digest',
    'default_report_type' => 'Balance Sheet',
    'default_date_range' => 'Last Quarter',
    'default_recipients' => 'board-reports@sams.edu',
];

foreach (array_keys($accountantSettings) as $settingKey) {
    $stored = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$settingKey]);
    if ($stored && array_key_exists('setting_value', $stored) && $stored['setting_value'] !== null && $stored['setting_value'] !== '') {
        $accountantSettings[$settingKey] = (string) $stored['setting_value'];
    }
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = filter_var(sanitize($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $message = 'Please fill in all required fields';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address';
            $message_type = 'error';
        } else {
            db()->query(
                "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?",
                [$first_name, $last_name, $email, $phone, $user_id]
            );
            $_SESSION['full_name'] = "$first_name $last_name";
            $message = 'Profile updated successfully!';
            $message_type = 'success';
            $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $user_check = db()->fetch("SELECT password FROM users WHERE id = ?", [$user_id]);

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required';
            $message_type = 'error';
        } elseif (!password_verify($current_password, $user_check['password'])) {
            $message = 'Current password is incorrect';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match';
            $message_type = 'error';
        } elseif (strlen($new_password) < 8) {
            $message = 'Password must be at least 8 characters long';
            $message_type = 'error';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            db()->query("UPDATE users SET password = ? WHERE id = ?", [$hashed, $user_id]);
            $message = 'Password changed successfully!';
            $message_type = 'success';
        }
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        db()->query(
            "UPDATE users SET email_notifications = ?, sms_notifications = ?, push_notifications = ? WHERE id = ?",
            [$email_notifications, $sms_notifications, $push_notifications, $user_id]
        );
        $message = 'Notification preferences updated!';
        $message_type = 'success';
        $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    }
}

// Handle accountant settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_accountant_settings'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $allowedMethods = ['accrual', 'cash'];
        $allowedRounding = ['Standard (0.5 up)', 'Floor (Always down)', 'Ceiling (Always up)'];
        $allowedReportTypes = ['Balance Sheet', 'Profit & Loss', 'Cash Flow Projection', 'Aged Payables'];
        $allowedDateRanges = ['Last Month', 'Last Quarter', 'Fiscal Year-to-Date'];
        $allowedFrequencies = ['Real-time Push', 'Daily Digest', 'Weekly Summary'];

        $accountantSettings['currency'] = trim((string) ($_POST['currency'] ?? $accountantSettings['currency']));
        $accountantSettings['fiscal_start_month'] = trim((string) ($_POST['fiscal_start_month'] ?? $accountantSettings['fiscal_start_month']));
        $accountantSettings['accounting_method'] = in_array(($_POST['accounting_method'] ?? ''), $allowedMethods, true) ? (string) $_POST['accounting_method'] : $accountantSettings['accounting_method'];
        $accountantSettings['rounding_policy'] = in_array(($_POST['rounding_policy'] ?? ''), $allowedRounding, true) ? (string) $_POST['rounding_policy'] : $accountantSettings['rounding_policy'];
        $accountantSettings['base_tax_rate'] = number_format((float) ($_POST['base_tax_rate'] ?? $accountantSettings['base_tax_rate']), 2, '.', '');
        $accountantSettings['vat_gst_rate'] = number_format((float) ($_POST['vat_gst_rate'] ?? $accountantSettings['vat_gst_rate']), 2, '.', '');
        $accountantSettings['current_tax_year'] = trim((string) ($_POST['current_tax_year'] ?? $accountantSettings['current_tax_year']));
        $accountantSettings['expense_approval'] = isset($_POST['expense_approval']) ? '1' : '0';
        $accountantSettings['budget_approval'] = isset($_POST['budget_approval']) ? '1' : '0';
        $accountantSettings['approval_threshold'] = (string) max(0, (int) ($_POST['approval_threshold'] ?? $accountantSettings['approval_threshold']));
        $accountantSettings['approval_chain'] = trim((string) ($_POST['approval_chain'] ?? $accountantSettings['approval_chain']));
        $accountantSettings['notification_frequency'] = in_array(($_POST['notification_frequency'] ?? ''), $allowedFrequencies, true) ? (string) $_POST['notification_frequency'] : $accountantSettings['notification_frequency'];
        $accountantSettings['default_report_type'] = in_array(($_POST['default_report_type'] ?? ''), $allowedReportTypes, true) ? (string) $_POST['default_report_type'] : $accountantSettings['default_report_type'];
        $accountantSettings['default_date_range'] = in_array(($_POST['default_date_range'] ?? ''), $allowedDateRanges, true) ? (string) $_POST['default_date_range'] : $accountantSettings['default_date_range'];
        $accountantSettings['default_recipients'] = trim((string) ($_POST['default_recipients'] ?? $accountantSettings['default_recipients']));

        foreach ($accountantSettings as $settingKey => $settingValue) {
            $existing = db()->fetchOne("SELECT setting_key FROM system_settings WHERE setting_key = ?", [$settingKey]);
            if ($existing) {
                db()->update('system_settings', [
                    'setting_value' => $settingValue,
                    'description' => 'Accountant settings preference'
                ], 'setting_key = ?', [$settingKey]);
            } else {
                db()->insert('system_settings', [
                    'setting_key' => $settingKey,
                    'setting_value' => $settingValue,
                    'description' => 'Accountant settings preference'
                ]);
            }
        }

        $message = 'Accountant settings updated successfully!';
        $message_type = 'success';
    }
}

$page_title = 'Accountant Settings';
$page_icon = 'settings';
$page_subtitle = 'Manage your profile, security options, notifications, and theme preferences.';
$page_css = ['../assets/css/pwa-styles.css'];
$page_js = ['../assets/js/pwa-manager.js', '../assets/js/pwa-analytics.js'];

$activeTab = 'settings';
require_once __DIR__ . '/partials/header.php';
?>

<?php
$fullName = trim((string)($user['full_name'] ?? ($_SESSION['full_name'] ?? 'Accountant')));
[$currentFirstName, $currentLastName] = array_pad(preg_split('/\s+/', $fullName, 2) ?: [], 2, '');
$emailValue = (string)($user['email'] ?? '');
$phoneValue = (string)($user['phone'] ?? '');
$emailNotif = (int)($user['email_notifications'] ?? 1) === 1;
$smsNotif = (int)($user['sms_notifications'] ?? 0) === 1;
$pushNotif = (int)($user['push_notifications'] ?? 1) === 1;
$currencyValue = $accountantSettings['currency'];
$fiscalStartMonth = $accountantSettings['fiscal_start_month'];
$accountingMethod = $accountantSettings['accounting_method'];
$roundingPolicy = $accountantSettings['rounding_policy'];
$baseTaxRate = $accountantSettings['base_tax_rate'];
$vatGstRate = $accountantSettings['vat_gst_rate'];
$currentTaxYear = $accountantSettings['current_tax_year'];
$expenseApproval = $accountantSettings['expense_approval'] === '1';
$budgetApproval = $accountantSettings['budget_approval'] === '1';
$approvalThreshold = $accountantSettings['approval_threshold'];
$approvalChain = $accountantSettings['approval_chain'];
$notificationFrequency = $accountantSettings['notification_frequency'];
$defaultReportType = $accountantSettings['default_report_type'];
$defaultDateRange = $accountantSettings['default_date_range'];
$defaultRecipients = $accountantSettings['default_recipients'];
?>

<?php if ($message !== ''): ?>
    <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium <?php echo $message_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="max-w-6xl mx-auto pb-28">
    <div class="mb-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
        <div>
            <h2 class="text-3xl font-headline font-extrabold tracking-tight text-on-surface">Settings Hub</h2>
            <p class="text-on-surface-variant mt-1">Financial Workflows &amp; Compliance</p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-full bg-surface-container-low px-4 py-2 text-xs font-bold text-on-surface-variant">
            <span class="material-symbols-outlined text-sm text-emerald-600">verified</span>
            Settings automatically validated for 2024 compliance.
        </div>
    </div>

    <form method="POST" class="grid grid-cols-12 gap-6 items-start">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        <input type="hidden" name="update_profile" value="1">
        <input type="hidden" name="update_notifications" value="1">
        <input type="hidden" name="update_accountant_settings" value="1">

        <section class="col-span-12 lg:col-span-8 bg-surface-container-lowest p-8 rounded-xl shadow-sm border border-outline-variant/10">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">account_balance</span>
                <h3 class="text-xl font-headline font-bold">Financial Settings</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Currency</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="text" name="currency" value="<?php echo htmlspecialchars($currencyValue); ?>" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Fiscal Start Month</label>
                    <select class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" name="fiscal_start_month">
                        <option <?php echo $fiscalStartMonth === 'January' ? 'selected' : ''; ?>>January</option>
                        <option <?php echo $fiscalStartMonth === 'July' ? 'selected' : ''; ?>>July</option>
                        <option <?php echo $fiscalStartMonth === 'September' ? 'selected' : ''; ?>>September</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Accounting Method</label>
                    <div class="flex gap-6 pt-1">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input class="w-4 h-4 text-primary" type="radio" name="accounting_method" value="accrual" <?php echo $accountingMethod === 'accrual' ? 'checked' : ''; ?>>
                            Accrual
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input class="w-4 h-4 text-primary" type="radio" name="accounting_method" value="cash" <?php echo $accountingMethod === 'cash' ? 'checked' : ''; ?>>
                            Cash
                        </label>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Rounding Policy</label>
                    <select class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" name="rounding_policy">
                        <option <?php echo $roundingPolicy === 'Standard (0.5 up)' ? 'selected' : ''; ?>>Standard (0.5 up)</option>
                        <option <?php echo $roundingPolicy === 'Floor (Always down)' ? 'selected' : ''; ?>>Floor (Always down)</option>
                        <option <?php echo $roundingPolicy === 'Ceiling (Always up)' ? 'selected' : ''; ?>>Ceiling (Always up)</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="col-span-12 lg:col-span-4 bg-surface-container-lowest p-8 rounded-xl shadow-sm border border-outline-variant/10">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">percent</span>
                <h3 class="text-xl font-headline font-bold">Tax Settings</h3>
            </div>
            <div class="space-y-5">
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Base Tax Rate %</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="number" step="0.01" name="base_tax_rate" value="<?php echo htmlspecialchars($baseTaxRate); ?>" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">VAT / GST %</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="number" step="0.1" name="vat_gst_rate" value="<?php echo htmlspecialchars($vatGstRate); ?>" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Current Tax Year</label>
                    <select class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" name="current_tax_year">
                        <option <?php echo $currentTaxYear === '2023-2024' ? 'selected' : ''; ?>>2023-2024</option>
                        <option <?php echo $currentTaxYear === '2024-2025' ? 'selected' : ''; ?>>2024-2025</option>
                        <option <?php echo $currentTaxYear === '2025-2026' ? 'selected' : ''; ?>>2025-2026</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="col-span-12 bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10">
            <div class="flex items-center gap-3 mb-8">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">account_tree</span>
                <h3 class="text-xl font-headline font-bold">Workflow &amp; Approval Settings</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-sm">Expense Approval</p>
                            <p class="text-xs text-on-surface-variant">Required for all outflows</p>
                        </div>
                        <input type="checkbox" class="w-5 h-5 text-primary" name="expense_approval" value="1" <?php echo $expenseApproval ? 'checked' : ''; ?>>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-sm">Budget Approval</p>
                            <p class="text-xs text-on-surface-variant">Audit trail for new budgets</p>
                        </div>
                        <input type="checkbox" class="w-5 h-5 text-primary" name="budget_approval" value="1" <?php echo $budgetApproval ? 'checked' : ''; ?>>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Approval Threshold ($)</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="number" name="approval_threshold" value="<?php echo htmlspecialchars($approvalThreshold); ?>" />
                    <p class="text-xs text-on-surface-variant italic">Amounts above this require Senior Accountant signature.</p>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Approval Chain (Email List)</label>
                    <textarea class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3 resize-none" name="approval_chain" rows="3"><?php echo htmlspecialchars($approvalChain); ?></textarea>
                </div>
            </div>
        </section>

        <section class="col-span-12 lg:col-span-6 bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">notifications</span>
                <h3 class="text-xl font-headline font-bold">Alert Preferences</h3>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <label class="flex items-center space-x-3 p-3 bg-surface-container-low rounded-lg">
                    <input class="w-5 h-5 text-primary" type="checkbox" name="email_notifications" value="1" <?php echo $emailNotif ? 'checked' : ''; ?> />
                    <span class="text-sm font-medium">Pending Approvals</span>
                </label>
                <label class="flex items-center space-x-3 p-3 bg-surface-container-low rounded-lg">
                    <input class="w-5 h-5 text-primary" type="checkbox" name="push_notifications" value="1" <?php echo $pushNotif ? 'checked' : ''; ?> />
                    <span class="text-sm font-medium">Overdue Fees</span>
                </label>
                <label class="flex items-center space-x-3 p-3 bg-surface-container-low rounded-lg">
                    <input class="w-5 h-5 text-primary" type="checkbox" name="sms_notifications" value="1" <?php echo $smsNotif ? 'checked' : ''; ?> />
                    <span class="text-sm font-medium">Budget Alerts</span>
                </label>
                <label class="flex items-center space-x-3 p-3 bg-surface-container-low rounded-lg">
                    <input class="w-5 h-5 text-primary" type="checkbox" checked />
                    <span class="text-sm font-medium">Audit Flags</span>
                </label>
            </div>
            <div class="space-y-2 mt-4">
                <label class="text-sm font-semibold text-on-surface-variant">Notification Frequency</label>
                <select class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" name="notification_frequency">
                    <option <?php echo $notificationFrequency === 'Real-time Push' ? 'selected' : ''; ?>>Real-time Push</option>
                    <option <?php echo $notificationFrequency === 'Daily Digest' ? 'selected' : ''; ?>>Daily Digest</option>
                    <option <?php echo $notificationFrequency === 'Weekly Summary' ? 'selected' : ''; ?>>Weekly Summary</option>
                </select>
            </div>
        </section>

        <section class="col-span-12 lg:col-span-6 bg-surface-container-lowest p-8 rounded-xl border border-outline-variant/10">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">badge</span>
                <h3 class="text-xl font-headline font-bold">Accountant View</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">First Name</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="text" name="first_name" value="<?php echo htmlspecialchars($currentFirstName); ?>" required>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Last Name</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="text" name="last_name" value="<?php echo htmlspecialchars($currentLastName); ?>" required>
                </div>
                <div class="space-y-2 md:col-span-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Email</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="email" name="email" value="<?php echo htmlspecialchars($emailValue); ?>" required>
                </div>
                <div class="space-y-2 md:col-span-2">
                    <label class="text-sm font-semibold text-on-surface-variant">Phone</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" type="text" name="phone" value="<?php echo htmlspecialchars($phoneValue); ?>">
                </div>
            </div>
        </section>

        <section id="active-vendors" class="col-span-12 bg-surface-container-lowest rounded-xl border border-outline-variant/10 overflow-hidden shadow-sm">
            <div class="p-8 flex justify-between items-center border-b border-outline-variant/5">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">store</span>
                    <h3 class="text-xl font-headline font-bold">Active Vendors</h3>
                </div>
                <a href="index.php?page=expenses#quick-add-expense" class="inline-flex bg-primary text-white px-5 py-2.5 rounded-lg font-semibold items-center gap-2 hover:opacity-90 transition-all shadow-md">
                    <span class="material-symbols-outlined text-sm">add</span>
                    Add New Vendor
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-container-low text-on-surface-variant text-xs font-bold uppercase tracking-widest">
                        <tr>
                            <th class="px-8 py-4">Vendor Name</th>
                            <th class="px-8 py-4">Category</th>
                            <th class="px-8 py-4">Payment Terms</th>
                            <th class="px-8 py-4">Status</th>
                            <th class="px-8 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10 text-sm">
                        <tr class="hover:bg-primary/5 transition-colors">
                            <td class="px-8 py-4 font-semibold">EduSupply Co.</td>
                            <td class="px-8 py-4">Academic Supplies</td>
                            <td class="px-8 py-4">Net 30</td>
                            <td class="px-8 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">ACTIVE</span></td>
                            <td class="px-8 py-4 text-right"><span class="material-symbols-outlined text-slate-400">edit</span></td>
                        </tr>
                        <tr class="hover:bg-primary/5 transition-colors">
                            <td class="px-8 py-4 font-semibold">Global Tech Solutions</td>
                            <td class="px-8 py-4">IT Services</td>
                            <td class="px-8 py-4">Immediate</td>
                            <td class="px-8 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">ACTIVE</span></td>
                            <td class="px-8 py-4 text-right"><span class="material-symbols-outlined text-slate-400">edit</span></td>
                        </tr>
                        <tr class="hover:bg-primary/5 transition-colors">
                            <td class="px-8 py-4 font-semibold">Premier Maintenance</td>
                            <td class="px-8 py-4">Facilities</td>
                            <td class="px-8 py-4">Net 15</td>
                            <td class="px-8 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-surface-container-highest text-slate-500">INACTIVE</span></td>
                            <td class="px-8 py-4 text-right"><span class="material-symbols-outlined text-slate-400">edit</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="col-span-12 grid grid-cols-1 md:grid-cols-2 gap-6">
            <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-bold text-lg flex items-center gap-2"><span class="material-symbols-outlined text-primary">hub</span>Cost Centers</h4>
                    <span class="text-primary text-xs font-bold">ADD</span>
                </div>
                <ul class="space-y-2 text-sm">
                    <li class="p-3 bg-surface-container-low rounded-lg flex justify-between"><span>101 - Primary School</span><span class="text-xs text-on-surface-variant font-mono">CC_PRI_101</span></li>
                    <li class="p-3 bg-surface-container-low rounded-lg flex justify-between"><span>202 - Secondary School</span><span class="text-xs text-on-surface-variant font-mono">CC_SEC_202</span></li>
                    <li class="p-3 bg-surface-container-low rounded-lg flex justify-between"><span>505 - Administration</span><span class="text-xs text-on-surface-variant font-mono">CC_ADM_505</span></li>
                </ul>
            </section>
            <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-bold text-lg flex items-center gap-2"><span class="material-symbols-outlined text-primary">account_balance</span>Linked Bank Accounts</h4>
                    <span class="text-primary text-xs font-bold">ADD</span>
                </div>
                <ul class="space-y-2 text-sm">
                    <li class="p-3 bg-surface-container-low rounded-lg flex justify-between"><span>Operational Checking</span><span class="text-xs text-on-surface-variant font-mono">**** 4492</span></li>
                    <li class="p-3 bg-surface-container-low rounded-lg flex justify-between"><span>Tuition Savings</span><span class="text-xs text-on-surface-variant font-mono">**** 1108</span></li>
                    <li class="p-3 bg-surface-container-low rounded-lg flex justify-between"><span>Corporate Visa</span><span class="text-xs text-on-surface-variant font-mono">**** 9091</span></li>
                </ul>
            </section>
        </div>

        <div class="fixed bottom-0 left-64 right-0 p-6 bg-surface-container-lowest backdrop-blur-lg border-t border-outline-variant/10 flex justify-end items-center z-20">
            <div class="mr-auto flex items-center text-xs text-on-surface-variant">
                <span class="material-symbols-outlined text-sm mr-2 text-green-600">verified</span>
                Settings automatically validated for 2024 compliance.
            </div>
            <div class="flex space-x-4">
                <button class="px-6 py-2.5 text-on-surface hover:bg-surface-container-high rounded-lg font-semibold transition-colors" type="button" onclick="window.location.reload()">Discard Changes</button>
                <button class="px-8 py-2.5 bg-gradient-to-r from-primary to-primary-container text-white rounded-lg font-bold shadow-lg hover:shadow-primary/30 transition-all active:scale-95" type="submit">Save Global Changes</button>
            </div>
        </div>
    </form>

    <div class="mt-6 bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10">
        <h4 class="font-bold text-base mb-3 flex items-center gap-2"><span class="material-symbols-outlined text-primary">lock</span>Quick Password Update</h4>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="change_password" value="1">
            <input type="password" name="current_password" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" placeholder="Current password" required>
            <input type="password" name="new_password" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" placeholder="New password" minlength="8" required>
            <input type="password" name="confirm_password" class="w-full bg-surface-container-low border-none rounded-lg px-4 py-3" placeholder="Confirm password" minlength="8" required>
            <button type="submit" class="w-full bg-primary text-white px-4 py-3 rounded-lg font-bold">Change Password</button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
