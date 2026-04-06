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
        $data = ['tenant_id' => $tenantId, 'student_id' => intval($_POST['student_id'] ?? 0), 'name' => trim($_POST['name'] ?? ''), 'type' => trim($_POST['type'] ?? 'merit'), 'amount' => floatval($_POST['amount'] ?? 0), 'percentage' => floatval($_POST['percentage'] ?? 0), 'academic_year' => trim($_POST['academic_year'] ?? date('Y')), 'status' => 'active', 'created_at' => date('Y-m-d H:i:s')];
        try { insert_flexible('scholarships', $data); $msg = 'Scholarship added.'; } catch (Exception $e) { $msg = 'Error: scholarships table may not exist.'; }
    }
}
$scholarships = [];
try { $scholarships = db()->fetchAll("SELECT s.*, u.full_name FROM scholarships s LEFT JOIN users u ON s.student_id = u.id WHERE s.tenant_id = ? ORDER BY s.created_at DESC LIMIT 50", [$tenantId]); } catch (Exception $e) {}
$students = [];
try { $students = db()->fetchAll("SELECT id, full_name FROM users WHERE tenant_id = ? AND role = 'student' ORDER BY full_name LIMIT 500", [$tenantId]); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarships - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-award"></i></div><div><h1>Scholarships</h1><p>Manage student scholarships and financial aid</p></div></div>
        <div class="cyber-content">
            <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card" style="margin-bottom:24px;"><div class="card-header"><h3>Award Scholarship</h3></div><div class="card-body">
                <form method="POST" class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="action" value="add">
                    <div><label>Student</label><select name="student_id" class="form-control" required><option value="">Select Student</option><?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option><?php endforeach; ?></select></div>
                    <div><label>Scholarship Name</label><input type="text" name="name" class="form-control" required placeholder="e.g. Academic Excellence"></div>
                    <div><label>Type</label><select name="type" class="form-control"><option value="merit">Merit-Based</option><option value="need">Need-Based</option><option value="sports">Sports</option><option value="other">Other</option></select></div>
                    <div><label>Amount ($)</label><input type="number" name="amount" class="form-control" step="0.01" min="0"></div>
                    <div><label>Percentage (%)</label><input type="number" name="percentage" class="form-control" step="0.1" min="0" max="100"></div>
                    <div><label>Year</label><input type="text" name="academic_year" class="form-control" value="<?= date('Y') ?>"></div>
                    <div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Award</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Student</th><th>Scholarship</th><th>Type</th><th>Amount</th><th>%</th><th>Year</th><th>Status</th></tr></thead><tbody>
                <?php if (empty($scholarships)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No scholarships awarded yet.</td></tr>
                <?php else: foreach ($scholarships as $s): ?>
                <tr><td><?= htmlspecialchars($s['full_name'] ?? 'N/A') ?></td><td><?= htmlspecialchars($s['name']) ?></td><td><?= ucfirst($s['type'] ?? '') ?></td><td>$<?= number_format($s['amount'] ?? 0, 2) ?></td><td><?= $s['percentage'] ?? 0 ?>%</td><td><?= htmlspecialchars($s['academic_year'] ?? '') ?></td><td><span class="badge badge-<?= ($s['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($s['status'] ?? 'active') ?></span></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
