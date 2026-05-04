<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';
require_admin('../login.php');

$tenantId = current_tenant_id();
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invite'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $message = 'Invalid request token.';
    $messageType = 'error';
  } else {
    try {
      $invite = AdvancedSAMS::createInvite($tenantId, (int) $_SESSION['user_id'], $_POST);
      $message = 'Invite created. Share this link: ' . APP_URL . '/invite-register.php?token=' . urlencode($invite['token']);
      $messageType = 'success';
    } catch (Throwable $e) {
      $message = $e->getMessage();
      $messageType = 'error';
    }
  }
}

$invites = table_exists('school_invites') ? db()->fetchAll('SELECT * FROM school_invites WHERE tenant_id = ? ORDER BY id DESC LIMIT 50', [$tenantId]) : [];
$page_title = 'Invite Management';
$page_icon = 'mail';
$page_subtitle = 'Invite-only onboarding for teachers, accountants, and other school-managed roles';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 xl:col-span-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <h3 class="text-lg font-bold text-primary mb-4">Create Invite</h3>
    <?php if ($message): ?><div class="mb-5 rounded-xl p-4 text-sm <?php echo $messageType === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <div><label class="text-xs font-semibold text-on-surface-variant ml-1">Email</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="email" name="email" required></div>
      <div><label class="text-xs font-semibold text-on-surface-variant ml-1">Role</label><select class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" name="role" required><option value="teacher">Teacher</option><option value="accountant">Accountant</option><option value="librarian">Librarian</option><option value="parent">Parent</option><option value="student">Student</option></select></div>
      <button class="premium-gradient text-white px-6 py-3 rounded-xl font-bold text-sm w-full" type="submit" name="create_invite">Create Invite Link</button>
    </form>
  </div>
  <div class="col-span-12 xl:col-span-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <h3 class="text-lg font-bold text-primary mb-5">Recent Invites</h3>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Expires</th><th>Token</th></tr></thead>
        <tbody>
        <?php if (!$invites): ?>
          <tr><td colspan="5" class="text-center text-slate-500">No invites yet.</td></tr>
        <?php else: foreach ($invites as $invite): ?>
          <tr>
            <td><?php echo htmlspecialchars($invite['email']); ?></td>
            <td><?php echo htmlspecialchars($invite['role']); ?></td>
            <td><?php echo htmlspecialchars($invite['status']); ?></td>
            <td><?php echo htmlspecialchars(format_datetime($invite['expires_at'] ?? null)); ?></td>
            <td class="text-xs text-slate-500"><?php echo htmlspecialchars(substr((string) $invite['invite_token'], 0, 16)); ?>...</td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
