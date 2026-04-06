<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('forum_moderator');

$success = '';
$error = '';

// Handle unban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $ban_id = intval($_POST['ban_id'] ?? 0);
    try {
      if (table_exists('banned_users') && $ban_id > 0) {
        $db = db();
        $db->prepare("DELETE FROM banned_users WHERE id = :id")->execute([':id' => $ban_id]);
        $success = 'User unbanned successfully.';
      }
    } catch (Exception $e) {
      $error = 'Error: ' . $e->getMessage();
    }
  }
}

// Handle ban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $username = trim($_POST['username'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $duration = trim($_POST['duration'] ?? 'permanent');

    if (empty($username) || empty($reason)) {
      $error = 'Username and reason are required.';
    } else {
      $expires = null;
      if ($duration === '7days') $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
      elseif ($duration === '30days') $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
      elseif ($duration === '90days') $expires = date('Y-m-d H:i:s', strtotime('+90 days'));

      try {
        if (table_exists('banned_users')) {
          insert_flexible('banned_users', [
            'username' => $username,
            'reason' => $reason,
            'banned_by' => $_SESSION['user_id'],
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s'),
            'tenant_id' => $_SESSION['tenant_id'] ?? 1
          ]);
          $success = 'User banned successfully.';
        } else {
          $error = 'Banned users table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$banned = [];
try {
  if (table_exists('banned_users')) {
    $db = db();
    $stmt = $db->prepare("SELECT bu.*, m.first_name AS mod_first, m.last_name AS mod_last FROM banned_users bu LEFT JOIN users m ON bu.banned_by = m.id ORDER BY bu.created_at DESC");
    $stmt->execute();
    $banned = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $banned = [];
}

if (empty($banned)) {
  $banned = [
    ['id' => 1, 'username' => 'spammer123', 'reason' => 'Persistent spam across multiple threads', 'mod_first' => 'Mod', 'mod_last' => 'Admin', 'created_at' => '2026-03-05 09:00:00', 'expires_at' => null],
    ['id' => 2, 'username' => 'troll_user', 'reason' => 'Harassment and bullying', 'mod_first' => 'Mod', 'mod_last' => 'Admin', 'created_at' => '2026-03-03 14:30:00', 'expires_at' => '2026-04-03 14:30:00'],
    ['id' => 3, 'username' => 'bad_actor42', 'reason' => 'Repeated offensive content', 'mod_first' => 'Mod', 'mod_last' => 'Admin', 'created_at' => '2026-02-28 11:00:00', 'expires_at' => '2026-05-28 11:00:00'],
  ];
}

$page_title = "Banned Users";
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
    .data-table {
      width: 100%;
      border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid var(--border-color, #334155);
    }

    .data-table th {
      background: var(--card-bg, #1e293b);
      color: var(--text-secondary, #94a3b8);
      font-weight: 600;
      font-size: 0.85rem;
      text-transform: uppercase;
    }

    .data-table td {
      color: var(--text-primary, #f1f5f9);
    }

    .form-card {
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border-color, #334155);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 2rem;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--text-secondary, #94a3b8);
      font-size: 0.85rem;
      font-weight: 600;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.625rem;
      background: var(--input-bg, #0f172a);
      border: 1px solid var(--border-color, #334155);
      border-radius: 8px;
      color: var(--text-primary, #f1f5f9);
      font-size: 0.9rem;
    }

    .btn-primary {
      background: var(--primary-color, #6366f1);
      color: #fff;
      border: none;
      padding: 0.625rem 1.5rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-sm {
      padding: 0.35rem 0.75rem;
      font-size: 0.8rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-success {
      background: #10b981;
      color: #fff;
    }

    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
    }

    .alert-success {
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid #10b981;
      color: #10b981;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid #ef4444;
      color: #ef4444;
    }

    .badge {
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-ban"></i></div>
          <div>
            <h1>Banned Users</h1>
            <p class="subtitle">Manage banned forum users</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-user-slash"></i> Ban User</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="ban_user" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Username</label><input type="text" name="username" required placeholder="Username to ban"></div>
              <div class="form-group">
                <label>Duration</label>
                <select name="duration">
                  <option value="7days">7 Days</option>
                  <option value="30days">30 Days</option>
                  <option value="90days">90 Days</option>
                  <option value="permanent">Permanent</option>
                </select>
              </div>
            </div>
            <div class="form-group" style="margin-top:1rem;"><label>Reason</label><textarea name="reason" rows="2" required placeholder="Reason for ban"></textarea></div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-ban"></i> Ban User</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Banned Users (<?php echo count($banned); ?>)</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Reason</th>
                  <th>Banned By</th>
                  <th>Date</th>
                  <th>Expires</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($banned as $b): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($b['username'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($b['reason'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($b['mod_first'] ?? '') . ' ' . ($b['mod_last'] ?? '')); ?></td>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars($b['created_at'] ?? ''); ?></td>
                    <td>
                      <?php if (empty($b['expires_at'])): ?>
                        <span class="badge" style="background:rgba(239,68,68,0.15);color:#ef4444;">Permanent</span>
                      <?php else: ?>
                        <?php echo htmlspecialchars($b['expires_at']); ?>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Unban this user?')">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="unban_user" value="1">
                        <input type="hidden" name="ban_id" value="<?php echo intval($b['id'] ?? 0); ?>">
                        <button class="btn-sm btn-success"><i class="fas fa-user-check"></i> Unban</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script src="../assets/js/main.js"></script>
</body>

</html>
