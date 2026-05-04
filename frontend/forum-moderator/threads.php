<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('forum_moderator');

$success = '';
$error = '';
$search = $_GET['search'] ?? '';

// Handle thread actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['thread_action'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $action = $_POST['thread_action'];
    $thread_id = intval($_POST['thread_id'] ?? 0);
    try {
      if (table_exists('forum_threads') && $thread_id > 0) {
        $db = db();
        switch ($action) {
          case 'lock':
            $db->prepare("UPDATE forum_threads SET status = 'locked' WHERE id = :id")->execute([':id' => $thread_id]);
            $success = 'Thread locked.';
            break;
          case 'unlock':
            $db->prepare("UPDATE forum_threads SET status = 'open' WHERE id = :id")->execute([':id' => $thread_id]);
            $success = 'Thread unlocked.';
            break;
          case 'pin':
            $db->prepare("UPDATE forum_threads SET is_pinned = 1 WHERE id = :id")->execute([':id' => $thread_id]);
            $success = 'Thread pinned.';
            break;
          case 'unpin':
            $db->prepare("UPDATE forum_threads SET is_pinned = 0 WHERE id = :id")->execute([':id' => $thread_id]);
            $success = 'Thread unpinned.';
            break;
          case 'delete':
            $db->prepare("DELETE FROM forum_threads WHERE id = :id")->execute([':id' => $thread_id]);
            $success = 'Thread deleted.';
            break;
        }
      }
    } catch (Exception $e) {
      $error = 'Error performing action: ' . $e->getMessage();
    }
  }
}

$threads = [];
try {
  if (table_exists('forum_threads')) {
    $db = db();
    $sql = "SELECT ft.*, u.first_name, u.last_name FROM forum_threads ft LEFT JOIN users u ON ft.author_id = u.id WHERE 1=1";
    $params = [];
    if (!empty($search)) {
      $sql .= " AND ft.title LIKE :search";
      $params[':search'] = '%' . $search . '%';
    }
    $sql .= " ORDER BY ft.is_pinned DESC, ft.created_at DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $threads = [];
}

if (empty($threads)) {
  $threads = [
    ['id' => 1, 'title' => 'Welcome to the School Forum', 'first_name' => 'Admin', 'last_name' => 'User', 'replies_count' => 24, 'status' => 'open', 'is_pinned' => 1, 'created_at' => '2026-02-01 09:00:00'],
    ['id' => 2, 'title' => 'Math Homework Help - Grade 10', 'first_name' => 'Alice', 'last_name' => 'Wanjiku', 'replies_count' => 15, 'status' => 'open', 'is_pinned' => 0, 'created_at' => '2026-03-05 14:30:00'],
    ['id' => 3, 'title' => 'Science Fair 2026 Announcement', 'first_name' => 'Teacher', 'last_name' => 'Smith', 'replies_count' => 32, 'status' => 'open', 'is_pinned' => 1, 'created_at' => '2026-03-01 10:00:00'],
    ['id' => 4, 'title' => 'Inappropriate Content Discussion', 'first_name' => 'Brian', 'last_name' => 'Omondi', 'replies_count' => 8, 'status' => 'locked', 'is_pinned' => 0, 'created_at' => '2026-03-07 16:45:00'],
    ['id' => 5, 'title' => 'Sports Day Schedule', 'first_name' => 'Carol', 'last_name' => 'Muthoni', 'replies_count' => 19, 'status' => 'open', 'is_pinned' => 0, 'created_at' => '2026-03-08 11:20:00'],
  ];
}

$status_colors = ['open' => '#10b981', 'locked' => '#ef4444', 'pinned' => '#f59e0b'];

$page_title = "Thread Management";
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
    <?php include '../includes/sams-head-bootstrap.php'; ?>

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
      padding: 0.3rem 0.6rem;
      font-size: 0.75rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      color: #fff;
    }

    .btn-warn {
      background: #f59e0b;
    }

    .btn-danger {
      background: #ef4444;
    }

    .btn-info {
      background: #3b82f6;
    }

    .btn-success {
      background: #10b981;
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

    .filter-bar {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      align-items: flex-end;
    }

    .filter-bar input {
      padding: 0.5rem 1rem;
      background: var(--input-bg, #0f172a);
      border: 1px solid var(--border-color, #334155);
      border-radius: 8px;
      color: var(--text-primary, #f1f5f9);
    }

    .action-group {
      display: flex;
      gap: 0.25rem;
      flex-wrap: wrap;
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-comments"></i></div>
          <div>
            <h1>Thread Management</h1>
            <p class="subtitle">Manage forum threads and discussions</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="filter-bar">
          <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;">
            <div><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search threads..."></div>
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Search</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Forum Threads</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Author</th>
                  <th>Replies</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($threads as $t):
                  $status = $t['status'] ?? 'open';
                  $pinned = !empty($t['is_pinned']);
                  $sc = $status_colors[$status] ?? '#94a3b8';
                ?>
                  <tr>
                    <td>
                      <?php if ($pinned): ?><i class="fas fa-thumbtack" style="color:#f59e0b;margin-right:0.3rem;" title="Pinned"></i><?php endif; ?>
                      <strong><?php echo htmlspecialchars($t['title'] ?? ''); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></td>
                    <td><?php echo intval($t['replies_count'] ?? 0); ?></td>
                    <td><span class="badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst($status); ?></span></td>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars($t['created_at'] ?? ''); ?></td>
                    <td>
                      <div class="action-group">
                        <?php if ($status === 'open'): ?>
                          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="thread_id" value="<?php echo intval($t['id']); ?>"><input type="hidden" name="thread_action" value="lock"><button class="btn-sm btn-warn" title="Lock"><i class="fas fa-lock"></i></button></form>
                        <?php else: ?>
                          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="thread_id" value="<?php echo intval($t['id']); ?>"><input type="hidden" name="thread_action" value="unlock"><button class="btn-sm btn-success" title="Unlock"><i class="fas fa-lock-open"></i></button></form>
                        <?php endif; ?>
                        <?php if (!$pinned): ?>
                          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="thread_id" value="<?php echo intval($t['id']); ?>"><input type="hidden" name="thread_action" value="pin"><button class="btn-sm btn-info" title="Pin"><i class="fas fa-thumbtack"></i></button></form>
                        <?php else: ?>
                          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="thread_id" value="<?php echo intval($t['id']); ?>"><input type="hidden" name="thread_action" value="unpin"><button class="btn-sm btn-info" title="Unpin"><i class="fas fa-times"></i></button></form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this thread?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="thread_id" value="<?php echo intval($t['id']); ?>"><input type="hidden" name="thread_action" value="delete"><button class="btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button></form>
                      </div>
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
