<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';

// Handle add route
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_route'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $route_name = trim($_POST['route_name'] ?? '');
    $start_point = trim($_POST['start_point'] ?? '');
    $end_point = trim($_POST['end_point'] ?? '');
    $distance = floatval($_POST['distance'] ?? 0);

    if (empty($route_name) || empty($start_point) || empty($end_point)) {
      $error = 'Route name, start point, and end point are required.';
    } else {
      try {
        if (table_exists('transport_routes')) {
          insert_flexible('transport_routes', [
            'route_name' => $route_name,
            'start_point' => $start_point,
            'end_point' => $end_point,
            'distance' => $distance,
            'status' => 'active',
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Route added successfully.';
        } else {
          $error = 'Transport routes table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error adding route: ' . $e->getMessage();
      }
    }
  }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_route'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    try {
      db()->query("DELETE FROM transport_routes WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)", [intval($_POST['route_id']), $_SESSION['tenant_id'] ?? 1]);
      $success = 'Route deleted.';
    } catch (Exception $e) {
      $error = 'Error deleting route.';
    }
  }
}

$routes = [];
try {
  if (table_exists('transport_routes')) {
    $routes = db()->fetchAll("SELECT * FROM transport_routes WHERE (tenant_id = ? OR tenant_id IS NULL) ORDER BY route_name ASC", [$_SESSION['tenant_id'] ?? 1]) ?: [];
  }
} catch (Exception $e) {
  $routes = [];
}

if (empty($routes)) {
  $routes = [
    ['id' => 1, 'route_name' => 'Route A - North', 'start_point' => 'School Campus', 'end_point' => 'North Suburb', 'distance' => 15.5, 'students_count' => 42, 'status' => 'active'],
    ['id' => 2, 'route_name' => 'Route B - South', 'start_point' => 'School Campus', 'end_point' => 'South Town', 'distance' => 22.0, 'students_count' => 38, 'status' => 'active'],
    ['id' => 3, 'route_name' => 'Route C - East', 'start_point' => 'School Campus', 'end_point' => 'East District', 'distance' => 18.3, 'students_count' => 35, 'status' => 'active'],
    ['id' => 4, 'route_name' => 'Route D - West', 'start_point' => 'School Campus', 'end_point' => 'West Valley', 'distance' => 25.0, 'students_count' => 28, 'status' => 'inactive'],
  ];
}

$page_title = "Route Management";
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

    .btn-sm {
      padding: 0.35rem 0.75rem;
      font-size: 0.8rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-danger {
      background: #ef4444;
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

    .badge-active {
      background: rgba(16, 185, 129, 0.15);
      color: #10b981;
    }

    .badge-inactive {
      background: rgba(239, 68, 68, 0.15);
      color: #ef4444;
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-route"></i></div>
          <div>
            <h1>Route Management</h1>
            <p class="subtitle">Manage school transport routes</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom: 1rem; color: var(--text-primary, #f1f5f9);"><i class="fas fa-plus-circle"></i> Add New Route</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_route" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Route Name</label><input type="text" name="route_name" required placeholder="e.g. Route A - North"></div>
              <div class="form-group"><label>Start Point</label><input type="text" name="start_point" required placeholder="e.g. School Campus"></div>
              <div class="form-group"><label>End Point</label><input type="text" name="end_point" required placeholder="e.g. North Suburb"></div>
              <div class="form-group"><label>Distance (km)</label><input type="number" name="distance" step="0.1" min="0" placeholder="0.0"></div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Add Route</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom: 1rem; color: var(--text-primary, #f1f5f9);"><i class="fas fa-list"></i> Routes</h2>
          <div style="overflow-x: auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Route Name</th>
                  <th>Start</th>
                  <th>End</th>
                  <th>Distance</th>
                  <th>Students</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($routes as $r): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($r['route_name'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['start_point'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['end_point'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['distance'] ?? '0'); ?> km</td>
                    <td><?php echo htmlspecialchars($r['students_count'] ?? '0'); ?></td>
                    <td><span class="badge badge-<?php echo ($r['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst(htmlspecialchars($r['status'] ?? 'active')); ?></span></td>
                    <td>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this route?')">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="delete_route" value="1">
                        <input type="hidden" name="route_id" value="<?php echo intval($r['id'] ?? 0); ?>">
                        <button type="submit" class="btn-sm btn-danger"><i class="fas fa-trash"></i></button>
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
