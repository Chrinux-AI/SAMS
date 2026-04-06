<?php

/**
 * Platform Settings (Super Admin)
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
  header('Location: ../login.php');
  exit;
}

$page_title = 'Platform Settings';
$page_icon = 'settings';
$page_subtitle = 'Global configuration controls';

$msg = '';
$type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $msg = 'Security validation failed.';
    $type = 'error';
  } else {
    $pairs = [
      'platform_name' => trim($_POST['platform_name'] ?? 'SAMS'),
      'support_email' => trim($_POST['support_email'] ?? ''),
      'session_timeout' => (int)($_POST['session_timeout'] ?? 60),
      'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
      'smtp_host' => trim($_POST['smtp_host'] ?? ''),
      'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
      'default_currency' => trim($_POST['default_currency'] ?? 'USD'),
      'stripe_public_key' => trim($_POST['stripe_public_key'] ?? ''),
      'stripe_secret_key' => trim($_POST['stripe_secret_key'] ?? ''),
      'paypal_client_id' => trim($_POST['paypal_client_id'] ?? ''),
      'paypal_client_secret' => trim($_POST['paypal_client_secret'] ?? ''),
      'aws_s3_enabled' => isset($_POST['aws_s3_enabled']) ? '1' : '0',
      'aws_access_key' => trim($_POST['aws_access_key'] ?? ''),
      'aws_secret_key' => trim($_POST['aws_secret_key'] ?? ''),
      'aws_bucket_name' => trim($_POST['aws_bucket_name'] ?? ''),
      'gdrive_enabled' => isset($_POST['gdrive_enabled']) ? '1' : '0',
      'subscription_basic_price' => trim($_POST['subscription_basic_price'] ?? '0'),
      'subscription_pro_price' => trim($_POST['subscription_pro_price'] ?? '0'),
      'subscription_enterprise_price' => trim($_POST['subscription_enterprise_price'] ?? '0'),
      'subscription_billing_cycle' => trim($_POST['subscription_billing_cycle'] ?? 'monthly')
    ];

    foreach ($pairs as $k => $v) {
      if (table_exists('settings')) {
        $exists = db()->fetchOne("SELECT id FROM settings WHERE `key` = ?", [$k]);
        if ($exists) {
          db()->update('settings', ['value' => (string)$v, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$exists['id']]);
        } else {
          db()->insert('settings', ['key' => $k, 'value' => (string)$v, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
      }
    }

    $msg = 'Platform settings saved successfully.';
    log_activity($_SESSION['user_id'] ?? 0, 'update_platform_settings', 'settings', 0);
  }
}

$defaults = [
  'platform_name' => 'SAMS',
  'support_email' => '',
  'session_timeout' => '60',
  'maintenance_mode' => '0',
  'smtp_host' => '',
  'smtp_port' => '587',
  'default_currency' => 'USD',
  'stripe_public_key' => '',
  'stripe_secret_key' => '',
  'paypal_client_id' => '',
  'paypal_client_secret' => '',
  'aws_s3_enabled' => '0',
  'aws_access_key' => '',
  'aws_secret_key' => '',
  'aws_bucket_name' => '',
  'gdrive_enabled' => '0',
  'subscription_basic_price' => '0',
  'subscription_pro_price' => '0',
  'subscription_enterprise_price' => '0',
  'subscription_billing_cycle' => 'monthly',
];

$current = $defaults;
if (table_exists('settings')) {
  $rows = db()->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` IN (
    'platform_name','support_email','session_timeout','maintenance_mode','smtp_host','smtp_port','default_currency',
    'stripe_public_key','stripe_secret_key','paypal_client_id','paypal_client_secret',
    'aws_s3_enabled','aws_access_key','aws_secret_key','aws_bucket_name','gdrive_enabled',
    'subscription_basic_price','subscription_pro_price','subscription_enterprise_price','subscription_billing_cycle'
  )") ?: [];
  foreach ($rows as $r) $current[$r['key']] = $r['value'];
}

ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 bg-white rounded-xl border border-gray-100 p-6">
    <h2 class="text-xl font-semibold mb-2">Global Settings</h2>
    <p class="text-sm text-gray-500 mb-6">Configure global platform behavior.</p>

    <?php if ($msg): ?>
      <div class="mb-4 px-4 py-3 rounded-lg <?php echo $type === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100'; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

      <div><label class="block text-sm text-gray-600 mb-1">Platform Name</label><input name="platform_name" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['platform_name']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">Support Email</label><input type="email" name="support_email" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['support_email']); ?>"></div>

      <div><label class="block text-sm text-gray-600 mb-1">Session Timeout (minutes)</label><input type="number" min="10" max="1440" name="session_timeout" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['session_timeout']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">Default Currency</label><input name="default_currency" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['default_currency']); ?>"></div>

      <div><label class="block text-sm text-gray-600 mb-1">SMTP Host</label><input name="smtp_host" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['smtp_host']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">SMTP Port</label><input type="number" min="1" max="65535" name="smtp_port" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['smtp_port']); ?>"></div>

      <div class="lg:col-span-2 mt-2">
        <h3 class="font-semibold text-gray-800">Payment Gateway Setup</h3>
      </div>
      <div><label class="block text-sm text-gray-600 mb-1">Stripe Public Key</label><input name="stripe_public_key" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['stripe_public_key']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">Stripe Secret Key</label><input name="stripe_secret_key" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['stripe_secret_key']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">PayPal Client ID</label><input name="paypal_client_id" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['paypal_client_id']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">PayPal Client Secret</label><input name="paypal_client_secret" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['paypal_client_secret']); ?>"></div>

      <div class="lg:col-span-2 mt-2">
        <h3 class="font-semibold text-gray-800">Cloud Storage Settings</h3>
      </div>
      <div><label class="block text-sm text-gray-600 mb-1">AWS Access Key</label><input name="aws_access_key" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['aws_access_key']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">AWS Secret Key</label><input name="aws_secret_key" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['aws_secret_key']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">AWS Bucket Name</label><input name="aws_bucket_name" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['aws_bucket_name']); ?>"></div>
      <div class="flex items-center gap-3 mt-7"><input id="aws_s3_enabled" type="checkbox" name="aws_s3_enabled" <?php echo $current['aws_s3_enabled'] === '1' ? 'checked' : ''; ?>><label for="aws_s3_enabled" class="text-sm text-gray-700">Enable AWS S3</label></div>
      <div class="lg:col-span-2 flex items-center gap-3 mt-1"><input id="gdrive_enabled" type="checkbox" name="gdrive_enabled" <?php echo $current['gdrive_enabled'] === '1' ? 'checked' : ''; ?>><label for="gdrive_enabled" class="text-sm text-gray-700">Enable Google Drive Integration</label></div>

      <div class="lg:col-span-2 mt-2">
        <h3 class="font-semibold text-gray-800">Subscription Plans Editor</h3>
      </div>
      <div><label class="block text-sm text-gray-600 mb-1">Basic Plan Price</label><input type="number" step="0.01" min="0" name="subscription_basic_price" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['subscription_basic_price']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">Pro Plan Price</label><input type="number" step="0.01" min="0" name="subscription_pro_price" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['subscription_pro_price']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">Enterprise Plan Price</label><input type="number" step="0.01" min="0" name="subscription_enterprise_price" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($current['subscription_enterprise_price']); ?>"></div>
      <div><label class="block text-sm text-gray-600 mb-1">Billing Cycle</label><select name="subscription_billing_cycle" class="w-full border rounded-lg px-3 py-2">
          <option value="monthly" <?php echo $current['subscription_billing_cycle'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
          <option value="quarterly" <?php echo $current['subscription_billing_cycle'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
          <option value="yearly" <?php echo $current['subscription_billing_cycle'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
        </select></div>

      <div class="lg:col-span-2 flex items-center gap-3 mt-1">
        <input id="maintenance_mode" type="checkbox" name="maintenance_mode" <?php echo $current['maintenance_mode'] === '1' ? 'checked' : ''; ?>>
        <label for="maintenance_mode" class="text-sm text-gray-700">Enable maintenance mode</label>
      </div>

      <div class="lg:col-span-2 flex justify-end gap-2 pt-2">
        <a href="super-admin-dashboard.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Cancel</a>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Save Settings</button>
      </div>
    </form>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
