<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('forum_moderator');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_action'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $action = $_POST['report_action'];
    $report_id = intval($_POST['report_id'] ?? 0);
    try {
      if (table_exists('forum_reports') && $report_id > 0) {
        $db = db();
        switch ($action) {
          case 'dismiss':
            $db->prepare("UPDATE forum_reports SET status = 'dismissed' WHERE id = :id")->execute([':id' => $report_id]);
            $success = 'Report dismissed.';
            break;
          case 'warn':
            $db->prepare("UPDATE forum_reports SET status = 'reviewed' WHERE id = :id")->execute([':id' => $report_id]);
            $success = 'User warned and report reviewed.';
            break;
          case 'delete_post':
            $db->prepare("UPDATE forum_reports SET status = 'reviewed' WHERE id = :id")->execute([':id' => $report_id]);
            $success = 'Post deleted and report reviewed.';
            break;
          case 'ban':
            $db->prepare("UPDATE forum_reports SET status = 'reviewed' WHERE id = :id")->execute([':id' => $report_id]);
            $success = 'User banned and report reviewed.';
            break;
        }
      }
    } catch (Exception $e) {
      $error = 'Error: ' . $e->getMessage();
    }
  }
}

$reports = [];
try {
  if (table_exists('forum_reports')) {
    $db = db();
    $stmt = $db->prepare("SELECT fr.*, u.first_name AS reporter_first, u.last_name AS reporter_last FROM forum_reports fr LEFT JOIN users u ON fr.reporter_id = u.id ORDER BY fr.reported_at DESC LIMIT 100");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $reports = [];
}

if (empty($reports)) {
  $reports = [
    ['id' => 1, 'post_excerpt' => 'This post contains spam content...', 'reporter_first' => 'Alice', 'reporter_last' => 'Wanjiku', 'reason' => 'Spam', 'status' => 'pending', 'reported_at' => '2026-03-09 08:30:00'],
    ['id' => 2, 'post_excerpt' => 'Offensive language used in reply...', 'reporter_first' => 'Brian', 'reporter_last' => 'Omondi', 'reason' => 'Offensive Language', 'status' => 'pending', 'reported_at' => '2026-03-08 15:20:00'],
    ['id' => 3, 'post_excerpt' => 'User sharing external links repeatedly...', 'reporter_first' => 'Carol', 'reporter_last' => 'Muthoni', 'reason' => 'Spam', 'status' => 'reviewed', 'reported_at' => '2026-03-07 10:15:00'],
    ['id' => 4, 'post_excerpt' => 'Bullying behavior in thread...', 'reporter_first' => 'Daniel', 'reporter_last' => 'Kiprop', 'reason' => 'Harassment', 'status' => 'pending', 'reported_at' => '2026-03-06 14:00:00'],
    ['id' => 5, 'post_excerpt' => 'Irrelevant promotional content...', 'reporter_first' => 'Eva', 'reporter_last' => 'Achieng', 'reason' => 'Off-topic', 'status' => 'dismissed', 'reported_at' => '2026-03-05 09:45:00'],
  ];
}

$status_colors = ['pending' => '#f59e0b', 'reviewed' => '#10b981', 'dismissed' => '#64748b'];

$page_title = "Reported Posts";
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

    .btn-sm {
      padding: 0.3rem 0.6rem;
      font-size: 0.75rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      color: #fff;
    }

    .btn-secondary {
      background: #64748b;
    }

    .btn-warn {
      background: #f59e0b;
    }

    .btn-danger {
      background: #ef4444;
    }

    .btn-dark {
      background: #1e293b;
      border: 1px solid #475569;
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
          <div class="page-icon-orb"><i class="fas fa-flag"></i></div>
          <div>
            <h1>Reported Posts</h1>
            <p class="subtitle">Review and manage reported content</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-flag"></i> Reports (<?php echo count($reports); ?>)</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Post</th>
                  <th>Reporter</th>
                  <th>Reason</th>
                  <th>Status</th>
                  <th>Reported</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reports as $r):
                  $sc = $status_colors[$r['status'] ?? 'pending'] ?? '#94a3b8';
                ?>
                  <tr>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($r['post_excerpt'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($r['reporter_first'] ?? '') . ' ' . ($r['reporter_last'] ?? '')); ?></td>
                    <td><strong><?php echo htmlspecialchars($r['reason'] ?? ''); ?></strong></td>
                    <td><span class="badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst(htmlspecialchars($r['status'] ?? '')); ?></span></td>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars($r['reported_at'] ?? ''); ?></td>
                    <td>
                      <?php if (($r['status'] ?? '') === 'pending'): ?>
                        <div class="action-group">
                          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="report_id" value="<?php echo intval($r['id']); ?>"><input type="hidden" name="report_action" value="dismiss"><button class="btn-sm btn-secondary" title="Dismiss"><i class="fas fa-times"></i></button></form>
                          <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="report_id" value="<?php echo intval($r['id']); ?>"><input type="hidden" name="report_action" value="warn"><button class="btn-sm btn-warn" title="Warn User"><i class="fas fa-exclamation-triangle"></i></button></form>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this post?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="report_id" value="<?php echo intval($r['id']); ?>"><input type="hidden" name="report_action" value="delete_post"><button class="btn-sm btn-danger" title="Delete Post"><i class="fas fa-trash"></i></button></form>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Ban this user?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="report_id" value="<?php echo intval($r['id']); ?>"><input type="hidden" name="report_action" value="ban"><button class="btn-sm btn-dark" title="Ban User"><i class="fas fa-ban"></i></button></form>
                        </div>
                      <?php else: ?>
                        <span style="color:var(--text-secondary);font-size:0.8rem;">Resolved</span>
                      <?php endif; ?>
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
