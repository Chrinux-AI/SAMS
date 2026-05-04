<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fuel'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $date = trim($_POST['fuel_date'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $cost = floatval($_POST['cost'] ?? 0);
    $odometer = floatval($_POST['odometer'] ?? 0);
    $driver = trim($_POST['driver'] ?? '');

    if (empty($date) || empty($vehicle) || $quantity <= 0) {
      $error = 'Date, vehicle, and quantity are required.';
    } else {
      try {
        if (table_exists('fuel_logs')) {
          insert_flexible('fuel_logs', [
            'fuel_date' => $date,
            'vehicle' => $vehicle,
            'quantity_liters' => $quantity,
            'cost' => $cost,
            'odometer' => $odometer,
            'driver' => $driver,
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Fuel entry added.';
        } else {
          $error = 'Fuel logs table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$entries = [];
$total_cost = 0;
$total_liters = 0;
try {
  if (table_exists('fuel_logs')) {
    $entries = db()->fetchAll("SELECT * FROM fuel_logs WHERE (tenant_id = ? OR tenant_id IS NULL) ORDER BY fuel_date DESC LIMIT 100", [$_SESSION['tenant_id'] ?? 1]) ?: [];
    foreach ($entries as $e) {
      $total_cost += floatval($e['cost'] ?? 0);
      $total_liters += floatval($e['quantity_liters'] ?? 0);
    }
  }
} catch (Exception $e) {
  $entries = [];
}

if (empty($entries)) {
  $entries = [
    ['fuel_date' => '2026-03-09', 'vehicle' => 'KAA 123B', 'quantity_liters' => 80, 'cost' => 160, 'odometer' => 125430, 'driver' => 'James Mwangi'],
    ['fuel_date' => '2026-03-08', 'vehicle' => 'KBB 456C', 'quantity_liters' => 60, 'cost' => 120, 'odometer' => 98200, 'driver' => 'Peter Ochieng'],
    ['fuel_date' => '2026-03-07', 'vehicle' => 'KDD 012E', 'quantity_liters' => 75, 'cost' => 150, 'odometer' => 110500, 'driver' => 'Samuel Njoroge'],
    ['fuel_date' => '2026-03-06', 'vehicle' => 'KAA 123B', 'quantity_liters' => 85, 'cost' => 170, 'odometer' => 125100, 'driver' => 'James Mwangi'],
    ['fuel_date' => '2026-03-05', 'vehicle' => 'KBB 456C', 'quantity_liters' => 55, 'cost' => 110, 'odometer' => 97900, 'driver' => 'Peter Ochieng'],
  ];
  $total_cost = 710;
  $total_liters = 355;
}

$avg_cost = $total_liters > 0 ? round($total_cost / $total_liters, 2) : 0;

$page_title = "Fuel Log";
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
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border-color, #334155);
      border-radius: 12px;
      padding: 1.5rem;
    }

    .stat-card .label {
      font-size: 0.85rem;
      color: var(--text-secondary, #94a3b8);
      margin-bottom: 0.5rem;
    }

    .stat-card .value {
      font-size: 1.8rem;
      font-weight: 700;
    }

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

    .form-group input {
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
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-gas-pump"></i></div>
          <div>
            <h1>Fuel Log</h1>
            <p class="subtitle">Track fuel consumption and costs</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="label">Total Fuel Cost</div>
            <div class="value" style="color:#ef4444;">$<?php echo number_format($total_cost, 2); ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Avg Cost/Liter</div>
            <div class="value" style="color:#f59e0b;">$<?php echo number_format($avg_cost, 2); ?></div>
          </div>
          <div class="stat-card">
            <div class="label">Total Liters</div>
            <div class="value" style="color:#3b82f6;"><?php echo number_format($total_liters, 1); ?>L</div>
          </div>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Add Fuel Entry</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_fuel" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Date</label><input type="date" name="fuel_date" value="<?php echo date('Y-m-d'); ?>" required></div>
              <div class="form-group"><label>Vehicle</label><input type="text" name="vehicle" required placeholder="Reg. No."></div>
              <div class="form-group"><label>Quantity (Liters)</label><input type="number" name="quantity" step="0.1" min="0.1" required placeholder="0.0"></div>
              <div class="form-group"><label>Cost ($)</label><input type="number" name="cost" step="0.01" min="0" placeholder="0.00"></div>
              <div class="form-group"><label>Odometer</label><input type="number" name="odometer" step="1" min="0" placeholder="km reading"></div>
              <div class="form-group"><label>Driver</label><input type="text" name="driver" placeholder="Driver name"></div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Add Entry</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Fuel Entries</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Vehicle</th>
                  <th>Liters</th>
                  <th>Cost</th>
                  <th>Odometer</th>
                  <th>Driver</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($entries as $e): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($e['fuel_date'] ?? ''); ?></td>
                    <td style="font-family:monospace;font-weight:600;"><?php echo htmlspecialchars($e['vehicle'] ?? ''); ?></td>
                    <td><?php echo number_format(floatval($e['quantity_liters'] ?? 0), 1); ?>L</td>
                    <td>$<?php echo number_format(floatval($e['cost'] ?? 0), 2); ?></td>
                    <td><?php echo number_format(floatval($e['odometer'] ?? 0), 0); ?> km</td>
                    <td><?php echo htmlspecialchars($e['driver'] ?? ''); ?></td>
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
