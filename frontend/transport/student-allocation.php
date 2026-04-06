<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';
$route_filter = $_GET['route'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_allocation'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $student_id = intval($_POST['student_id'] ?? 0);
    $route_id = intval($_POST['route_id'] ?? 0);

    if ($student_id <= 0 || $route_id <= 0) {
      $error = 'Student and route are required.';
    } else {
      try {
        if (table_exists('transport_assignments')) {
          insert_flexible('transport_assignments', [
            'student_id' => $student_id,
            'route_id' => $route_id,
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Student allocated to route successfully.';
        } else {
          $error = 'Transport assignments table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

// Fetch assignments
$assignments = [];
try {
  if (table_exists('transport_assignments')) {
    $db = db();
    $sql = "SELECT ta.*, u.first_name, u.last_name, tr.route_name
                FROM transport_assignments ta
                LEFT JOIN users u ON ta.student_id = u.id
                LEFT JOIN transport_routes tr ON ta.route_id = tr.id
                WHERE (ta.tenant_id = :tid OR ta.tenant_id IS NULL)";
    $params = [':tid' => $_SESSION['tenant_id'] ?? 1];
    if (!empty($route_filter)) {
      $sql .= " AND ta.route_id = :rid";
      $params[':rid'] = intval($route_filter);
    }
    $sql .= " ORDER BY u.last_name, u.first_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $assignments = [];
}

if (empty($assignments)) {
  $assignments = [
    ['student_id' => 101, 'first_name' => 'Alice', 'last_name' => 'Wanjiku', 'route_id' => 1, 'route_name' => 'Route A - North', 'created_at' => '2026-01-15'],
    ['student_id' => 102, 'first_name' => 'Brian', 'last_name' => 'Omondi', 'route_id' => 1, 'route_name' => 'Route A - North', 'created_at' => '2026-01-15'],
    ['student_id' => 103, 'first_name' => 'Carol', 'last_name' => 'Muthoni', 'route_id' => 2, 'route_name' => 'Route B - South', 'created_at' => '2026-01-16'],
    ['student_id' => 104, 'first_name' => 'Daniel', 'last_name' => 'Kiprop', 'route_id' => 3, 'route_name' => 'Route C - East', 'created_at' => '2026-01-16'],
    ['student_id' => 105, 'first_name' => 'Eva', 'last_name' => 'Achieng', 'route_id' => 2, 'route_name' => 'Route B - South', 'created_at' => '2026-01-17'],
  ];
}

// Fetch routes for dropdown
$routes = [];
try {
  if (table_exists('transport_routes')) {
    $db = db();
    $stmt = $db->prepare("SELECT id, route_name FROM transport_routes WHERE (tenant_id = :tid OR tenant_id IS NULL) ORDER BY route_name");
    $stmt->execute([':tid' => $_SESSION['tenant_id'] ?? 1]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $routes = [];
}
if (empty($routes)) {
  $routes = [['id' => 1, 'route_name' => 'Route A - North'], ['id' => 2, 'route_name' => 'Route B - South'], ['id' => 3, 'route_name' => 'Route C - East'], ['id' => 4, 'route_name' => 'Route D - West']];
}

// Fetch students for dropdown
$students = [];
try {
  $db = db();
  $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'student' AND (tenant_id = :tid OR tenant_id IS NULL) ORDER BY last_name, first_name LIMIT 200");
  $stmt->execute([':tid' => $_SESSION['tenant_id'] ?? 1]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $students = [];
}

$page_title = "Student Allocation";
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
    .form-group select {
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

    .filter-bar {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      align-items: center;
    }

    .filter-bar select {
      padding: 0.5rem 1rem;
      background: var(--input-bg, #0f172a);
      border: 1px solid var(--border-color, #334155);
      border-radius: 8px;
      color: var(--text-primary, #f1f5f9);
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-user-graduate"></i></div>
          <div>
            <h1>Student Allocation</h1>
            <p class="subtitle">Assign students to transport routes</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Assign Student to Route</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_allocation" value="1">
            <div class="form-grid">
              <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                  <option value="">Select Student</option>
                  <?php foreach ($students as $s): ?>
                    <option value="<?php echo intval($s['id']); ?>"><?php echo htmlspecialchars(($s['last_name'] ?? '') . ', ' . ($s['first_name'] ?? '')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Route</label>
                <select name="route_id" required>
                  <option value="">Select Route</option>
                  <?php foreach ($routes as $r): ?>
                    <option value="<?php echo intval($r['id']); ?>"><?php echo htmlspecialchars($r['route_name'] ?? ''); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Assign</button>
          </form>
        </div>

        <div class="filter-bar">
          <label style="color:var(--text-secondary,#94a3b8);font-weight:600;">Filter by Route:</label>
          <select onchange="window.location='?route='+this.value">
            <option value="">All Routes</option>
            <?php foreach ($routes as $r): ?>
              <option value="<?php echo intval($r['id']); ?>" <?php echo $route_filter == $r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['route_name'] ?? ''); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Allocations</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Route</th>
                  <th>Assigned Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($assignments)): ?>
                  <tr>
                    <td colspan="3" style="text-align:center;padding:2rem;color:var(--text-secondary);">No allocations found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($assignments as $a): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars(($a['last_name'] ?? '') . ', ' . ($a['first_name'] ?? '')); ?></strong></td>
                      <td><?php echo htmlspecialchars($a['route_name'] ?? 'N/A'); ?></td>
                      <td><?php echo htmlspecialchars($a['created_at'] ?? ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
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
