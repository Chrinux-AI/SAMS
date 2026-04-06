<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('bursar');

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

$page_title = "Team Selection";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script src="../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?> - SAMS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="../assets/css/professional-ui.css" rel="stylesheet">
  <link href="../assets/css/sidebar-nav.css" rel="stylesheet">
  <link href="../assets/css/sams-theme-system.css" rel="stylesheet">
  <style>
    .teams-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
    .team-card { background: var(--card-bg, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 12px; padding: 1.5rem; transition: transform 0.2s, box-shadow 0.2s; }
    .team-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    .team-card h3 { color: var(--text-primary, #f1f5f9); margin-bottom: 0.5rem; font-size: 1.1rem; }
    .team-card p { color: var(--text-secondary, #94a3b8); font-size: 0.9rem; margin-bottom: 1rem; }
    .team-meta { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .team-meta span { display: flex; align-items: center; gap: 0.3rem; color: var(--text-secondary, #94a3b8); font-size: 0.8rem; }
    .team-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .badge-leader { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
    .badge-member { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .badge-type { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
    .btn-join { background: var(--primary-color, #6366f1); color: #fff; border: none; padding: 0.5rem 1.25rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
    .btn-join:hover { opacity: 0.9; }
    .btn-leave { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid #ef4444; padding: 0.5rem 1.25rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
    .btn-leave:hover { background: rgba(239, 68, 68, 0.3); }
    .section-title { color: var(--text-primary, #f1f5f9); font-size: 1.2rem; margin: 2rem 0 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color, #334155); }
    .section-title:first-of-type { margin-top: 0; }
    .empty-state { text-align: center; padding: 3rem; color: var(--text-secondary, #94a3b8); }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.5; }
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
    .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }
    .stats-bar { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .stat-item { background: var(--card-bg, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 10px; padding: 1rem 1.5rem; flex: 1; min-width: 150px; text-align: center; }
    .stat-item .stat-num { font-size: 1.8rem; font-weight: 700; color: var(--primary-color, #6366f1); }
    .stat-item .stat-label { color: var(--text-secondary, #94a3b8); font-size: 0.8rem; margin-top: 0.25rem; }
  </style>
</head>
<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-users"></i></div>
          <div>
            <h1>Team Selection</h1>
            <p class="subtitle">View and manage your team memberships</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="stats-bar">
          <div class="stat-item">
            <div class="stat-num"><?php echo count($teams); ?></div>
            <div class="stat-label">My Teams</div>
          </div>
          <div class="stat-item">
            <div class="stat-num"><?php echo count($all_teams); ?></div>
            <div class="stat-label">Available Teams</div>
          </div>
          <div class="stat-item">
            <div class="stat-num"><?php echo count($teams) + count($all_teams); ?></div>
            <div class="stat-label">Total Teams</div>
          </div>
        </div>

        <h2 class="section-title"><i class="fas fa-star"></i> My Teams</h2>
        <?php if (empty($teams)): ?>
          <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <p>You haven't joined any teams yet. Browse available teams below.</p>
          </div>
        <?php else: ?>
          <div class="teams-grid">
            <?php foreach ($teams as $team): ?>
              <div class="team-card">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                  <h3><i class="fas fa-users" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($team['name'] ?? ''); ?></h3>
                  <span class="team-badge <?php echo ($team['member_role'] ?? '') === 'leader' ? 'badge-leader' : 'badge-member'; ?>">
                    <?php echo htmlspecialchars($team['member_role'] ?? 'member'); ?>
                  </span>
                </div>
                <p><?php echo htmlspecialchars($team['description'] ?? 'No description'); ?></p>
                <div class="team-meta">
                  <span><i class="fas fa-tag"></i> <span class="team-badge badge-type"><?php echo htmlspecialchars($team['type'] ?? 'general'); ?></span></span>
                  <span><i class="fas fa-user-friends"></i> <?php echo (int)($team['member_count'] ?? 0); ?> members</span>
                  <span><i class="fas fa-calendar"></i> Joined <?php echo date('M d, Y', strtotime($team['join_date'] ?? 'now')); ?></span>
                </div>
                <?php if (($team['member_role'] ?? '') !== 'leader'): ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to leave this team?');">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="team_id" value="<?php echo (int)$team['id']; ?>">
                    <button type="submit" name="leave_team" class="btn-leave"><i class="fas fa-sign-out-alt"></i> Leave Team</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <h2 class="section-title"><i class="fas fa-globe"></i> Available Teams</h2>
        <?php if (empty($all_teams)): ?>
          <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No additional teams available to join at this time.</p>
          </div>
        <?php else: ?>
          <div class="teams-grid">
            <?php foreach ($all_teams as $team): ?>
              <div class="team-card">
                <h3><i class="fas fa-users" style="color: var(--text-secondary);"></i> <?php echo htmlspecialchars($team['name'] ?? ''); ?></h3>
                <p><?php echo htmlspecialchars($team['description'] ?? 'No description'); ?></p>
                <div class="team-meta">
                  <span><i class="fas fa-tag"></i> <span class="team-badge badge-type"><?php echo htmlspecialchars($team['type'] ?? 'general'); ?></span></span>
                  <span><i class="fas fa-user-friends"></i> <?php echo (int)($team['member_count'] ?? 0); ?> members</span>
                </div>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                  <input type="hidden" name="team_id" value="<?php echo (int)$team['id']; ?>">
                  <button type="submit" name="join_team" class="btn-join"><i class="fas fa-plus"></i> Join Team</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="../assets/js/main.js"></script>
</body>
</html>
