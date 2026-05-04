<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trip'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $date = trim($_POST['trip_date'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $driver = trim($_POST['driver'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');
    $departure = trim($_POST['departure'] ?? '');
    $arrival = trim($_POST['arrival'] ?? '');
    $students_count = intval($_POST['students_count'] ?? 0);
    $status = trim($_POST['status'] ?? 'completed');

    if (empty($date) || empty($route)) {
      $error = 'Date and route are required.';
    } else {
      try {
        if (table_exists('trip_logs')) {
          insert_flexible('trip_logs', [
            'trip_date' => $date,
            'route' => $route,
            'driver' => $driver,
            'vehicle' => $vehicle,
            'departure_time' => $departure,
            'arrival_time' => $arrival,
            'students_count' => $students_count,
            'status' => $status,
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Trip log added successfully.';
        } else {
          $error = 'Trip logs table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$trips = [];
try {
  if (table_exists('trip_logs')) {
    $trips = db()->fetchAll("SELECT * FROM trip_logs WHERE (tenant_id = ? OR tenant_id IS NULL) ORDER BY trip_date DESC, departure_time DESC LIMIT 100", [$_SESSION['tenant_id'] ?? 1]) ?: [];
  }
} catch (Exception $e) {
  $trips = [];
}

if (empty($trips)) {
  $trips = [
    ['trip_date' => '2026-03-09', 'route' => 'Route A - North', 'driver' => 'James Mwangi', 'vehicle' => 'KAA 123B', 'departure_time' => '06:30', 'arrival_time' => '07:45', 'students_count' => 42, 'status' => 'completed'],
    ['trip_date' => '2026-03-09', 'route' => 'Route B - South', 'driver' => 'Peter Ochieng', 'vehicle' => 'KBB 456C', 'departure_time' => '06:15', 'arrival_time' => '07:30', 'students_count' => 38, 'status' => 'completed'],
    ['trip_date' => '2026-03-09', 'route' => 'Route C - East', 'driver' => 'Samuel Njoroge', 'vehicle' => 'KDD 012E', 'departure_time' => '06:45', 'arrival_time' => '08:00', 'students_count' => 35, 'status' => 'in_progress'],
    ['trip_date' => '2026-03-08', 'route' => 'Route A - North', 'driver' => 'James Mwangi', 'vehicle' => 'KAA 123B', 'departure_time' => '15:30', 'arrival_time' => '16:45', 'students_count' => 40, 'status' => 'completed'],
    ['trip_date' => '2026-03-08', 'route' => 'Route B - South', 'driver' => 'Peter Ochieng', 'vehicle' => 'KBB 456C', 'departure_time' => '15:15', 'arrival_time' => '16:30', 'students_count' => 36, 'status' => 'delayed'],
  ];
}

$status_colors = ['completed' => '#10b981', 'in_progress' => '#3b82f6', 'delayed' => '#f59e0b', 'cancelled' => '#ef4444'];

$page_title = "Trip Logs";
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
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
          <div class="page-icon-orb"><i class="fas fa-clipboard-list"></i></div>
          <div>
            <h1>Trip Logs</h1>
            <p class="subtitle">Daily transport trip tracking</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Add Trip Log</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_trip" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Date</label><input type="date" name="trip_date" value="<?php echo date('Y-m-d'); ?>" required></div>
              <div class="form-group"><label>Route</label><input type="text" name="route" required placeholder="Route name"></div>
              <div class="form-group"><label>Driver</label><input type="text" name="driver" placeholder="Driver name"></div>
              <div class="form-group"><label>Vehicle</label><input type="text" name="vehicle" placeholder="Reg. No."></div>
              <div class="form-group"><label>Departure</label><input type="time" name="departure"></div>
              <div class="form-group"><label>Arrival</label><input type="time" name="arrival"></div>
              <div class="form-group"><label>Students</label><input type="number" name="students_count" min="0" placeholder="0"></div>
              <div class="form-group"><label>Status</label><select name="status">
                  <option value="completed">Completed</option>
                  <option value="in_progress">In Progress</option>
                  <option value="delayed">Delayed</option>
                  <option value="cancelled">Cancelled</option>
                </select></div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Add Trip</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Trip History</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Route</th>
                  <th>Driver</th>
                  <th>Vehicle</th>
                  <th>Departure</th>
                  <th>Arrival</th>
                  <th>Students</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($trips as $t):
                  $sc = $status_colors[$t['status'] ?? 'completed'] ?? '#94a3b8';
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars($t['trip_date'] ?? ''); ?></td>
                    <td><strong><?php echo htmlspecialchars($t['route'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($t['driver'] ?? ''); ?></td>
                    <td style="font-family:monospace;"><?php echo htmlspecialchars($t['vehicle'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['departure_time'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($t['arrival_time'] ?? ''); ?></td>
                    <td><?php echo intval($t['students_count'] ?? 0); ?></td>
                    <td><span class="badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($t['status'] ?? ''))); ?></span></td>
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
