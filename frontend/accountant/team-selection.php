<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
  redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$user_id = $_SESSION['user_id'] ?? 0;
$teams = [];
$all_teams = [];
$success = '';
$error = '';

try {
  $db = db();

  // Handle join team
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_team'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid CSRF token.';
    } else {
      $team_id = (int)($_POST['team_id'] ?? 0);
      if ($team_id > 0) {
        $exists = $db->prepare("SELECT id FROM team_members WHERE team_id = :tid AND user_id = :uid");
        $exists->execute([':tid' => $team_id, ':uid' => $user_id]);
        if ($exists->fetch()) {
          $error = 'You are already a member of this team.';
        } else {
          $stmt = $db->prepare("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (:tid, :uid, 'member', NOW())");
          $stmt->execute([':tid' => $team_id, ':uid' => $user_id]);
          $success = 'Successfully joined the team!';
        }
      }
    }
  }

  // Handle leave team
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_team'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid CSRF token.';
    } else {
      $team_id = (int)($_POST['team_id'] ?? 0);
      if ($team_id > 0) {
        $stmt = $db->prepare("DELETE FROM team_members WHERE team_id = :tid AND user_id = :uid");
        $stmt->execute([':tid' => $team_id, ':uid' => $user_id]);
        $success = 'You have left the team.';
      }
    }
  }

  // Get user's teams
  if (table_exists('teams') && table_exists('team_members')) {
    $stmt = $db->prepare("
      SELECT t.*, tm.role as member_role, tm.joined_at as join_date,
             (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
      FROM teams t
      JOIN team_members tm ON t.id = tm.team_id
      WHERE tm.user_id = :uid AND t.is_active = 1
      ORDER BY tm.joined_at DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get all available teams
    $stmt = $db->prepare("
      SELECT t.*, (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
      FROM teams t
      WHERE t.is_active = 1 AND t.id NOT IN (SELECT team_id FROM team_members WHERE user_id = :uid)
      ORDER BY t.name
    ");
    $stmt->execute([':uid' => $user_id]);
    $all_teams = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Exception $e) {
  $teams = [];
  $all_teams = [];
}

$page_title = 'Team Selection';
$page_icon = 'groups';
$page_subtitle = 'View and manage your team memberships.';

$activeTab = 'team-selection';
require_once __DIR__ . '/partials/header.php';
?>

<?php if ($success): ?>
  <div class="mb-4 rounded-xl border border-emerald-300/40 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-4 rounded-xl border border-red-300/40 bg-red-50 px-4 py-3 text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5 text-center">
    <p class="text-3xl font-extrabold text-primary"><?php echo count($teams); ?></p>
    <p class="text-xs uppercase tracking-wider font-bold text-outline mt-1">My Teams</p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5 text-center">
    <p class="text-3xl font-extrabold text-tertiary"><?php echo count($all_teams); ?></p>
    <p class="text-xs uppercase tracking-wider font-bold text-outline mt-1">Available Teams</p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5 text-center">
    <p class="text-3xl font-extrabold text-secondary"><?php echo count($teams) + count($all_teams); ?></p>
    <p class="text-xs uppercase tracking-wider font-bold text-outline mt-1">Total Teams</p>
  </div>
</div>

<h2 class="text-base font-bold mb-3">My Teams</h2>
<?php if (empty($teams)): ?>
  <div class="rounded-xl border border-outline-variant/10 bg-surface-container-low p-8 text-center text-on-surface-variant">
    You haven't joined any teams yet. Browse available teams below.
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-8">
    <?php foreach ($teams as $team): ?>
      <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
        <div class="flex items-start justify-between gap-3 mb-2">
          <h3 class="font-bold text-on-surface"><?php echo htmlspecialchars($team['name'] ?? ''); ?></h3>
          <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider <?php echo ($team['member_role'] ?? '') === 'leader' ? 'bg-primary text-primary' : 'bg-secondary text-secondary'; ?>">
            <?php echo htmlspecialchars($team['member_role'] ?? 'member'); ?>
          </span>
        </div>
        <p class="text-sm text-on-surface-variant mb-3"><?php echo htmlspecialchars($team['description'] ?? 'No description'); ?></p>
        <div class="flex flex-wrap gap-2 text-xs text-outline mb-4">
          <span class="rounded-full bg-surface-container-high px-2 py-1"><?php echo htmlspecialchars($team['type'] ?? 'general'); ?></span>
          <span><?php echo (int)($team['member_count'] ?? 0); ?> members</span>
          <span>Joined <?php echo date('M d, Y', strtotime($team['join_date'] ?? 'now')); ?></span>
        </div>
        <?php if (($team['member_role'] ?? '') !== 'leader'): ?>
          <form method="POST" onsubmit="return confirm('Are you sure you want to leave this team?');">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="team_id" value="<?php echo (int)$team['id']; ?>">
            <button type="submit" name="leave_team" class="rounded-lg border border-red-300/60 text-red-600 px-3 py-1.5 text-sm font-semibold">Leave Team</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="text-base font-bold mb-3">Available Teams</h2>
<?php if (empty($all_teams)): ?>
  <div class="rounded-xl border border-outline-variant/10 bg-surface-container-low p-8 text-center text-on-surface-variant">
    No additional teams available to join at this time.
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    <?php foreach ($all_teams as $team): ?>
      <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
        <h3 class="font-bold text-on-surface mb-2"><?php echo htmlspecialchars($team['name'] ?? ''); ?></h3>
        <p class="text-sm text-on-surface-variant mb-3"><?php echo htmlspecialchars($team['description'] ?? 'No description'); ?></p>
        <div class="flex flex-wrap gap-2 text-xs text-outline mb-4">
          <span class="rounded-full bg-surface-container-high px-2 py-1"><?php echo htmlspecialchars($team['type'] ?? 'general'); ?></span>
          <span><?php echo (int)($team['member_count'] ?? 0); ?> members</span>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <input type="hidden" name="team_id" value="<?php echo (int)$team['id']; ?>">
          <button type="submit" name="join_team" class="rounded-lg bg-primary text-on-primary px-3 py-1.5 text-sm font-semibold">Join Team</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/partials/footer.php';
