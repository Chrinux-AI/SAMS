<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $reg = trim($_POST['registration_no'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 0);
    $driver = trim($_POST['driver'] ?? '');

    if (empty($reg) || empty($type)) {
      $error = 'Registration number and type are required.';
    } else {
      try {
        if (table_exists('vehicles')) {
          insert_flexible('vehicles', [
            'registration_no' => $reg,
            'type' => $type,
            'capacity' => $capacity,
            'driver' => $driver,
            'status' => 'active',
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Vehicle added successfully.';
        } else {
          $error = 'Vehicles table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$vehicles = [];
try {
  if (table_exists('vehicles')) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE (tenant_id = :tid OR tenant_id IS NULL) ORDER BY registration_no");
    $stmt->execute([':tid' => $_SESSION['tenant_id'] ?? 1]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $vehicles = [];
}

if (empty($vehicles)) {
  $vehicles = [
    ['id' => 1, 'registration_no' => 'KAA 123B', 'type' => 'Bus', 'capacity' => 52, 'driver' => 'James Mwangi', 'status' => 'active'],
    ['id' => 2, 'registration_no' => 'KBB 456C', 'type' => 'Mini Bus', 'capacity' => 32, 'driver' => 'Peter Ochieng', 'status' => 'active'],
    ['id' => 3, 'registration_no' => 'KCC 789D', 'type' => 'Van', 'capacity' => 14, 'driver' => 'David Kimani', 'status' => 'maintenance'],
    ['id' => 4, 'registration_no' => 'KDD 012E', 'type' => 'Bus', 'capacity' => 48, 'driver' => 'Samuel Njoroge', 'status' => 'retired'],
  ];
}

$status_colors = ['active' => '#10b981', 'maintenance' => '#f59e0b', 'retired' => '#ef4444'];

$page_title = "Vehicle Management";
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
          <div class="page-icon-orb"><i class="fas fa-bus"></i></div>
          <div>
            <h1>Vehicle Management</h1>
            <p class="subtitle">Manage school transport fleet</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Add Vehicle</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_vehicle" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Registration No.</label><input type="text" name="registration_no" required placeholder="e.g. KAA 123B"></div>
              <div class="form-group"><label>Type</label><select name="type" required>
                  <option value="Bus">Bus</option>
                  <option value="Mini Bus">Mini Bus</option>
                  <option value="Van">Van</option>
                  <option value="Sedan">Sedan</option>
                </select></div>
              <div class="form-group"><label>Capacity</label><input type="number" name="capacity" min="1" required placeholder="52"></div>
              <div class="form-group"><label>Driver</label><input type="text" name="driver" placeholder="Driver name"></div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Add Vehicle</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Fleet</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Registration</th>
                  <th>Type</th>
                  <th>Capacity</th>
                  <th>Driver</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($vehicles as $v):
                  $sc = $status_colors[$v['status'] ?? 'active'] ?? '#94a3b8';
                ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($v['registration_no'] ?? ''); ?></strong></td>
                    <td><?php echo htmlspecialchars($v['type'] ?? ''); ?></td>
                    <td><?php echo intval($v['capacity'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($v['driver'] ?? 'N/A'); ?></td>
                    <td><span class="badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst(htmlspecialchars($v['status'] ?? 'active')); ?></span></td>
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
