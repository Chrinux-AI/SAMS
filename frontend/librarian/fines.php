<?php
/**
 * SAMS Library - Fines Management
 * View and manage fines from overdue and damaged book loans
 */
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
require_login('../login.php');
if (!has_role('librarian') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Librarian privileges required.', 'error');
}

$csrf = generate_csrf_token();
$tenantId = $_SESSION['tenant_id'] ?? 1;
$user_id = $_SESSION['user_id'];

// Handle fine update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fine'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('fines.php', 'Invalid security token.', 'error');
    }
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    $new_amount = max(0, (float)($_POST['fine_amount'] ?? 0));
    try {
        db()->update('library_loans', ['fine_amount' => $new_amount], 'id = ? AND tenant_id = ?', [$loan_id, $tenantId]);
        redirect('fines.php', 'Fine updated successfully.', 'success');
    } catch (Throwable $e) {
        redirect('fines.php', 'Error updating fine.', 'error');
    }
}

// Handle waive fine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waive_fine'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('fines.php', 'Invalid security token.', 'error');
    }
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    try {
        db()->update('library_loans', ['fine_amount' => 0], 'id = ? AND tenant_id = ?', [$loan_id, $tenantId]);
        redirect('fines.php', 'Fine waived successfully.', 'success');
    } catch (Throwable $e) {
        redirect('fines.php', 'Error waiving fine.', 'error');
    }
}

$filter = sanitize($_GET['filter'] ?? 'all');

// Fetch loans with fines
$fines = [];
try {
    if (table_exists('library_loans')) {
        $where = "ll.tenant_id = ? AND ll.fine_amount > 0";
        $params = [$tenantId];

        if ($filter === 'active') {
            $where .= " AND ll.status = 'active'";
        } elseif ($filter === 'returned') {
            $where .= " AND ll.status = 'returned'";
        }

        $fines = db()->fetchAll("
            SELECT ll.*,
                   lb.title, lb.author,
                   u.first_name, u.last_name, u.assigned_id, u.email
            FROM library_loans ll
            JOIN library_books lb ON ll.book_id = lb.id
            JOIN users u ON ll.student_id = u.id
            WHERE {$where}
            ORDER BY ll.fine_amount DESC, ll.due_date ASC
        ", $params);
    }
} catch (Throwable $e) {}

$total_fines = array_sum(array_column($fines, 'fine_amount'));
$active_fines = count(array_filter($fines, fn($f) => $f['status'] === 'active'));
$returned_fines = count($fines) - $active_fines;

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fines Management - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header">
            <div class="page-icon-orb"><i class="fas fa-dollar-sign"></i></div>
            <div>
                <h1>Fines Management</h1>
                <p>Track and manage library fines for overdue and damaged books</p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-danger">$<?= number_format($total_fines, 2) ?></h3>
                        <small class="text-muted">Total Fines</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-primary"><?= count($fines) ?></h3>
                        <small class="text-muted">Loans with Fines</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-warning"><?= $active_fines ?></h3>
                        <small class="text-muted">Active (Unreturned)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-success"><?= $returned_fines ?></h3>
                        <small class="text-muted">Returned with Fine</small>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="mb-3">
                <a href="fines.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                <a href="fines.php?filter=active" class="btn btn-sm <?= $filter === 'active' ? 'btn-warning' : 'btn-outline-warning' ?>">Active Loans</a>
                <a href="fines.php?filter=returned" class="btn btn-sm <?= $filter === 'returned' ? 'btn-success' : 'btn-outline-success' ?>">Returned</a>
            </div>

            <!-- Fines Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Loan #</th>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fines)): ?>
                                <tr><td colspan="8" class="text-center py-4"><i class="fas fa-check-circle text-success"></i> No fines recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($fines as $f): ?>
                                    <tr>
                                        <td>#<?= (int)$f['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></strong>
                                            <?php if ($f['assigned_id']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($f['assigned_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($f['title']) ?></td>
                                        <td><?= htmlspecialchars($f['due_date']) ?></td>
                                        <td><?= htmlspecialchars($f['return_date'] ?? 'Not returned') ?></td>
                                        <td>
                                            <span class="badge <?= $f['status'] === 'active' ? 'bg-warning text-dark' : 'bg-success' ?>">
                                                <?= htmlspecialchars(ucfirst($f['status'])) ?>
                                            </span>
                                        </td>
                                        <td><strong class="text-danger">$<?= number_format((float)$f['fine_amount'], 2) ?></strong></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="loan_id" value="<?= (int)$f['id'] ?>">
                                                <div class="input-group input-group-sm" style="width:200px;">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="fine_amount" class="form-control" step="0.01" min="0" value="<?= number_format((float)$f['fine_amount'], 2) ?>">
                                                    <button type="submit" name="update_fine" class="btn btn-warning btn-sm" title="Update"><i class="fas fa-save"></i></button>
                                                </div>
                                            </form>
                                            <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Waive this fine?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="loan_id" value="<?= (int)$f['id'] ?>">
                                                <button type="submit" name="waive_fine" class="btn btn-sm btn-outline-success" title="Waive Fine"><i class="fas fa-eraser"></i></button>
                                            </form>
                                        </td>
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
