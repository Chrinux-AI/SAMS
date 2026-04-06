<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('nurse', '../login.php');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  $fullName = trim((string)($_POST['full_name'] ?? ''));
  if ($uid > 0 && $fullName !== '') {
    update_flexible('users', ['full_name' => $fullName, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$uid]);
    $_SESSION['full_name'] = $fullName;
    $msg = 'Settings updated successfully.';
  }
}

$page_title = 'Nurse Settings';
$page_icon = 'settings';
$page_subtitle = 'Manage profile settings';
ob_start();
?>
<div class="max-w-2xl bg-white border border-gray-100 rounded-xl p-5">
  <?php if ($msg): ?><div class="mb-4 px-3 py-2 rounded bg-emerald-50 text-emerald-700 text-sm"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <form method="POST" class="space-y-3">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
    <label class="block text-sm text-gray-600">Full Name</label>
    <input name="full_name" class="w-full border rounded-lg px-3 py-2" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" required>
    <button class="bg-indigo-600 text-white rounded-lg px-4 py-2">Save</button>
  </form>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
