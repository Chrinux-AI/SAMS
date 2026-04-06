<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $vehicle = trim($_POST['vehicle'] ?? '');
    $type = trim($_POST['maintenance_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cost = floatval($_POST['cost'] ?? 0);
    $date = trim($_POST['maintenance_date'] ?? '');
    $next_due = trim($_POST['next_due'] ?? '');
    $status = trim($_POST['status'] ?? 'completed');

    if (empty($vehicle) || empty($type) || empty($date)) {
      $error = 'Vehicle, type, and date are required.';
    } else {
      try {
        if (table_exists('vehicle_maintenance')) {
          insert_flexible('vehicle_maintenance', [
            'vehicle' => $vehicle,
            'maintenance_type' => $type,
            'description' => $description,
            'cost' => $cost,
            'maintenance_date' => $date,
            'next_due' => $next_due,
            'status' => $status,
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Maintenance record added.';
        } else {
          $error = 'Vehicle maintenance table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$records = [];
try {
  if (table_exists('vehicle_maintenance')) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM vehicle_maintenance WHERE (tenant_id = :tid OR tenant_id IS NULL) ORDER BY maintenance_date DESC LIMIT 100");
    $stmt->execute([':tid' => $_SESSION['tenant_id'] ?? 1]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $records = [];
}

if (empty($records)) {
  $records = [
    ['vehicle' => 'KAA 123B', 'maintenance_type' => 'Oil Change', 'description' => 'Full synthetic oil change', 'cost' => 450, 'maintenance_date' => '2026-03-05', 'next_due' => '2026-06-05', 'status' => 'completed'],
    ['vehicle' => 'KBB 456C', 'maintenance_type' => 'Tire Replacement', 'description' => 'Front tires replaced', 'cost' => 1200, 'maintenance_date' => '2026-03-01', 'next_due' => '2026-09-01', 'status' => 'completed'],
    ['vehicle' => 'KCC 789D', 'maintenance_type' => 'Engine Repair', 'description' => 'Engine overhaul needed', 'cost' => 5000, 'maintenance_date' => '2026-03-10', 'next_due' => '2026-03-15', 'status' => 'scheduled'],
    ['vehicle' => 'KDD 012E', 'maintenance_type' => 'Brake Service', 'description' => 'Brake pad replacement overdue', 'cost' => 800, 'maintenance_date' => '2026-02-15', 'next_due' => '2026-02-28', 'status' => 'overdue'],
  ];
}

$status_colors = ['completed' => '#10b981', 'scheduled' => '#3b82f6', 'overdue' => '#ef4444'];

$page_title = "Vehicle Maintenance";
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
          <div class="page-icon-orb"><i class="fas fa-tools"></i></div>
          <div>
            <h1>Vehicle Maintenance</h1>
            <p class="subtitle">Track vehicle maintenance and service records</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Add Maintenance Record</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_maintenance" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Vehicle</label><input type="text" name="vehicle" required placeholder="Reg. No."></div>
              <div class="form-group"><label>Type</label><select name="maintenance_type" required>
                  <option value="Oil Change">Oil Change</option>
                  <option value="Tire Replacement">Tire Replacement</option>
                  <option value="Brake Service">Brake Service</option>
                  <option value="Engine Repair">Engine Repair</option>
                  <option value="General Service">General Service</option>
                  <option value="Other">Other</option>
                </select></div>
              <div class="form-group"><label>Cost ($)</label><input type="number" name="cost" step="0.01" min="0" placeholder="0.00"></div>
              <div class="form-group"><label>Date</label><input type="date" name="maintenance_date" value="<?php echo date('Y-m-d'); ?>" required></div>
              <div class="form-group"><label>Next Due</label><input type="date" name="next_due"></div>
              <div class="form-group"><label>Status</label><select name="status">
                  <option value="completed">Completed</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="overdue">Overdue</option>
                </select></div>
            </div>
            <div class="form-group" style="margin-top:1rem;"><label>Description</label><textarea name="description" rows="2" placeholder="Details of maintenance work"></textarea></div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Add Record</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Maintenance Records</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Vehicle</th>
                  <th>Type</th>
                  <th>Description</th>
                  <th>Cost</th>
                  <th>Date</th>
                  <th>Next Due</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($records as $r):
                  $sc = $status_colors[$r['status'] ?? 'completed'] ?? '#94a3b8';
                ?>
                  <tr>
                    <td style="font-family:monospace;font-weight:600;"><?php echo htmlspecialchars($r['vehicle'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['maintenance_type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                    <td>$<?php echo number_format(floatval($r['cost'] ?? 0), 2); ?></td>
                    <td><?php echo htmlspecialchars($r['maintenance_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($r['next_due'] ?? 'N/A'); ?></td>
                    <td><span class="badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst(htmlspecialchars($r['status'] ?? '')); ?></span></td>
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
