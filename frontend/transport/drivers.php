<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('transport');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $name = trim($_POST['name'] ?? '');
    $license_no = trim($_POST['license_no'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $assigned_vehicle = trim($_POST['assigned_vehicle'] ?? '');

    if (empty($name) || empty($license_no)) {
      $error = 'Name and license number are required.';
    } else {
      try {
        if (table_exists('drivers')) {
          insert_flexible('drivers', [
            'name' => $name,
            'license_no' => $license_no,
            'phone' => $phone,
            'assigned_vehicle' => $assigned_vehicle,
            'status' => 'active',
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
          ]);
          $success = 'Driver added successfully.';
        } else {
          $error = 'Drivers table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
      }
    }
  }
}

$drivers = [];
try {
  if (table_exists('drivers')) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM drivers WHERE (tenant_id = :tid OR tenant_id IS NULL) ORDER BY name");
    $stmt->execute([':tid' => $_SESSION['tenant_id'] ?? 1]);
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) {
  $drivers = [];
}

if (empty($drivers)) {
  $drivers = [
    ['id' => 1, 'name' => 'James Mwangi', 'license_no' => 'DL-2024-001234', 'phone' => '+254700123456', 'assigned_vehicle' => 'KAA 123B', 'status' => 'active'],
    ['id' => 2, 'name' => 'Peter Ochieng', 'license_no' => 'DL-2023-005678', 'phone' => '+254711234567', 'assigned_vehicle' => 'KBB 456C', 'status' => 'active'],
    ['id' => 3, 'name' => 'David Kimani', 'license_no' => 'DL-2022-009012', 'phone' => '+254722345678', 'assigned_vehicle' => 'KCC 789D', 'status' => 'on_leave'],
    ['id' => 4, 'name' => 'Samuel Njoroge', 'license_no' => 'DL-2024-003456', 'phone' => '+254733456789', 'assigned_vehicle' => 'KDD 012E', 'status' => 'active'],
  ];
}

$status_colors = ['active' => '#10b981', 'on_leave' => '#f59e0b', 'inactive' => '#ef4444'];

$page_title = "Driver Management";
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
          <div class="page-icon-orb"><i class="fas fa-id-card"></i></div>
          <div>
            <h1>Driver Management</h1>
            <p class="subtitle">Manage transport drivers</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-plus-circle"></i> Add Driver</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="add_driver" value="1">
            <div class="form-grid">
              <div class="form-group"><label>Full Name</label><input type="text" name="name" required placeholder="Driver full name"></div>
              <div class="form-group"><label>License No.</label><input type="text" name="license_no" required placeholder="DL-XXXX-XXXXXX"></div>
              <div class="form-group"><label>Phone</label><input type="text" name="phone" placeholder="+254..."></div>
              <div class="form-group"><label>Assigned Vehicle</label><input type="text" name="assigned_vehicle" placeholder="Vehicle registration"></div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:1rem;"><i class="fas fa-save"></i> Add Driver</button>
          </form>
        </div>

        <div class="form-card">
          <h2 style="margin-bottom:1rem;color:var(--text-primary,#f1f5f9);"><i class="fas fa-list"></i> Drivers</h2>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>License No.</th>
                  <th>Phone</th>
                  <th>Assigned Vehicle</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($drivers as $d):
                  $sc = $status_colors[$d['status'] ?? 'active'] ?? '#94a3b8';
                ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($d['name'] ?? ''); ?></strong></td>
                    <td style="font-family:monospace;"><?php echo htmlspecialchars($d['license_no'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['phone'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($d['assigned_vehicle'] ?? 'N/A'); ?></td>
                    <td><span class="badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($d['status'] ?? 'active'))); ?></span></td>
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
