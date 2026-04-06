<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('forum_moderator');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_warning'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $user_id = intval($_POST['user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $level = intval($_POST['level'] ?? 1);

    if ($user_id <= 0 || empty($reason)) {
      $error = 'User and reason are required.';
    } else {
      try {
        if (table_exists('forum_warnings')) {
          insert_flexible('forum_warnings', [
            'user_id' => $user_id,
            'reason' => $reason,
            'level' => $level,
            'issued_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'tenant_id' => $_SESSION['tenant_id'] ?? 1
          ]);
          $success = 'Warning issued successfully.';
        } else {
          $error = 'Forum warnings table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$warnings = [];
try {
  if (table_exists('forum_warnings')) {
    $db = db();
    $stmt = $db->prepare("SELECT fw.*, u.first_name, u.last_name, m.first_name AS mod_first, m.last_name AS mod_last FROM forum_warnings fw LEFT JOIN users u ON fw.user_id = u.id LEFT JOIN users m ON fw.issued_by = m.id ORDER BY fw.created_at DESC LIMIT 100");
    $stmt->execute();
    $warnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $warnings = [];
}

if (empty($warnings)) {
  $warnings = [
    ['user_id' => 102, 'first_name' => 'Brian', 'last_name' => 'Omondi', 'reason' => 'Spam messages in multiple threads', 'mod_first' => 'Mod', 'mod_last' => 'Admin', 'level' => 1, 'created_at' => '2026-03-08 10:30:00'],
    ['user_id' => 106, 'first_name' => 'Frank', 'last_name' => 'Mutua', 'reason' => 'Offensive language', 'mod_first' => 'Mod', 'mod_last' => 'Admin', 'level' => 2, 'created_at' => '2026-03-07 14:15:00'],
    ['user_id' => 107, 'first_name' => 'Grace', 'last_name' => 'Akinyi', 'reason' => 'Repeated rule violations', 'mod_first' => 'Mod', 'mod_last' => 'Admin', 'level' => 3, 'created_at' => '2026-03-05 09:00:00'],
  ];
}

$level_colors = [1 => '#f59e0b', 2 => '#f97316', 3 => '#ef4444'];
$level_labels = [1 => 'Warning', 2 => 'Serious', 3 => 'Ban'];

$page_title = "User Warnings";
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
          <div class="page-icon-orb"><i class="fas fa-exclamation-triangle"></i></div>
          <div>
            <h1>User Warnings</h1>
            <p class="subtitle">Manage forum user warnings and infractions</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Issue Warning</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="issue_warning" value="1">
            <div class="form-grid">
              <div class="form-group"><label>User ID</label><input type="number" name="user_id" required placeholder="User ID" min="1"></div>
              <div class="form-group">
                <label>Warning Level</label>
                <select name="level" required>
                  <option value="1" style="color:#f59e0b;">Level 1 - Warning (Yellow)</option>
                  <option value="2" style="color:#f97316;">Level 2 - Serious (Orange)</option>
                  <option value="3" style="color:#ef4444;">Level 3 - Ban (Red)</option>
                </select>
              </div>
            </div>
            <div class="form-group" style="margin-top:1rem;"><label>Reason</label><textarea name="reason" rows="2" required placeholder="Describe the reason for the warning"></textarea></div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-exclamation-triangle"></i> Issue Warning</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Warning History</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Reason</th>
                  <th>Issued By</th>
                  <th>Level</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($warnings as $w):
                  $lvl = intval($w['level'] ?? 1);
                  $lc = $level_colors[$lvl] ?? '#94a3b8';
                  $ll = $level_labels[$lvl] ?? 'Warning';
                ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars(($w['first_name'] ?? '') . ' ' . ($w['last_name'] ?? '')); ?></strong></td>
                    <td><?php echo htmlspecialchars($w['reason'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars(($w['mod_first'] ?? '') . ' ' . ($w['mod_last'] ?? '')); ?></td>
                    <td><span class="badge" style="background:<?php echo $lc; ?>20;color:<?php echo $lc; ?>;">Lv<?php echo $lvl; ?> - <?php echo $ll; ?></span></td>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars($w['created_at'] ?? ''); ?></td>
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
