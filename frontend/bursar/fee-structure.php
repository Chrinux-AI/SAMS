<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $data = ['tenant_id' => $tenantId, 'name' => trim($_POST['name'] ?? ''), 'class_id' => intval($_POST['class_id'] ?? 0), 'amount' => floatval($_POST['amount'] ?? 0), 'fee_type' => trim($_POST['fee_type'] ?? 'tuition'), 'term' => trim($_POST['term'] ?? ''), 'academic_year' => trim($_POST['academic_year'] ?? date('Y')), 'created_at' => date('Y-m-d H:i:s')];
        try { insert_flexible('fee_structure', $data); $msg = 'Fee structure added.'; } catch (Exception $e) { $msg = 'Error adding fee structure.'; }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try { db()->query("DELETE FROM fee_structure WHERE id = ? AND tenant_id = ?", [$id, $tenantId]); $msg = 'Fee structure deleted.'; } catch (Exception $e) { $msg = 'Error deleting.'; }
    }
}
$structures = [];
try { $structures = db()->fetchAll("SELECT * FROM fee_structure WHERE tenant_id = ? ORDER BY academic_year DESC, name ASC", [$tenantId]); } catch (Exception $e) {}
$classes = [];
try { $classes = db()->fetchAll("SELECT id, name FROM classes WHERE tenant_id = ? ORDER BY name", [$tenantId]); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
    <style>
        .finance-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-grid label {
            display: block;
            margin-bottom: 0.4rem;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            min-height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            padding: 0.65rem 0.75rem;
            font: inherit;
        }

        .finance-table th,
        .finance-table td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .finance-table th {
            color: #475569;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
            background: #f8fafc;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-layer-group"></i></div><div><h1>Fee Structure</h1><p>Define and manage fee categories</p></div></div>
        <div class="cyber-content">
            <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card" style="margin-bottom:24px;"><div class="card-header"><h3>Add Fee Structure</h3></div><div class="card-body">
                <form method="POST" class="form-grid finance-form-grid">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="action" value="add">
                    <div><label>Name</label><input type="text" name="name" class="form-control" required placeholder="e.g. Tuition Fee"></div>
                    <div><label>Class</label><select name="class_id" class="form-control"><option value="0">All Classes</option><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
                    <div><label>Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0" required></div>
                    <div><label>Type</label><select name="fee_type" class="form-control"><option value="tuition">Tuition</option><option value="boarding">Boarding</option><option value="transport">Transport</option><option value="exam">Exam</option><option value="other">Other</option></select></div>
                    <div><label>Term</label><input type="text" name="term" class="form-control" placeholder="e.g. Term 1"></div>
                    <div><label>Year</label><input type="text" name="academic_year" class="form-control" value="<?= date('Y') ?>"></div>
                    <div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table finance-table"><thead><tr><th>Name</th><th>Class</th><th>Amount</th><th>Type</th><th>Term</th><th>Year</th><th>Action</th></tr></thead><tbody>
                <?php if (empty($structures)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No fee structures defined.</td></tr>
                <?php else: foreach ($structures as $s): ?>
                <tr><td><?= htmlspecialchars($s['name']) ?></td><td><?= $s['class_id'] ? htmlspecialchars($s['class_id']) : 'All' ?></td><td>$<?= number_format($s['amount'], 2) ?></td><td><?= ucfirst($s['fee_type'] ?? '') ?></td><td><?= htmlspecialchars($s['term'] ?? '-') ?></td><td><?= htmlspecialchars($s['academic_year'] ?? '-') ?></td><td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this fee structure?')"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                </td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
