<?php
/**
 * SAMS Library - Overdue Books
 * Track and manage overdue book loans with days overdue calculation
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

// Handle apply fine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_fine'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('overdue.php', 'Invalid security token.', 'error');
    }
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    $fine = max(0, (float)($_POST['fine_amount'] ?? 0));
    try {
        db()->update('library_loans', ['fine_amount' => $fine], 'id = ? AND tenant_id = ?', [$loan_id, $tenantId]);
        redirect('overdue.php', 'Fine applied successfully.', 'success');
    } catch (Throwable $e) {
        redirect('overdue.php', 'Error applying fine.', 'error');
    }
}

// Fetch overdue loans
$overdue = [];
try {
    if (table_exists('library_loans')) {
        $overdue = db()->fetchAll("
            SELECT ll.*,
                   lb.title, lb.author, lb.isbn,
                   u.first_name, u.last_name, u.assigned_id, u.email,
                   DATEDIFF(CURDATE(), ll.due_date) as days_overdue
            FROM library_loans ll
            JOIN library_books lb ON ll.book_id = lb.id
            JOIN users u ON ll.student_id = u.id
            WHERE ll.tenant_id = ? AND ll.status = 'active' AND ll.due_date < CURDATE()
            ORDER BY ll.due_date ASC
        ", [$tenantId]);
    }
} catch (Throwable $e) {}

$total_overdue = count($overdue);
$total_fines = array_sum(array_column($overdue, 'fine_amount'));
$max_overdue = $total_overdue > 0 ? max(array_column($overdue, 'days_overdue')) : 0;

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdue Books - SAMS</title>
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
        <div class="cyber-header">
            <div class="page-icon-orb"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <h1>Overdue Books</h1>
                <p>Books past their return date requiring follow-up</p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card text-center py-3 border-danger">
                        <h3 class="mb-0 text-danger"><?= $total_overdue ?></h3>
                        <small class="text-muted">Overdue Books</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center py-3 border-warning">
                        <h3 class="mb-0 text-warning"><?= $max_overdue ?></h3>
                        <small class="text-muted">Max Days Overdue</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center py-3 border-info">
                        <h3 class="mb-0 text-info">$<?= number_format($total_fines, 2) ?></h3>
                        <small class="text-muted">Total Fines Assigned</small>
                    </div>
                </div>
            </div>

            <!-- Overdue Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock text-danger"></i> Overdue Loans</h5>
                    <a href="issue-return.php" class="btn btn-sm btn-success"><i class="fas fa-undo"></i> Process Returns</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Loan #</th>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th>Fine</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($overdue)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-success"><i class="fas fa-check-circle"></i> No overdue books. Great job!</td></tr>
                            <?php else: ?>
                                <?php foreach ($overdue as $od): ?>
                                    <tr>
                                        <td>#<?= (int)$od['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($od['first_name'] . ' ' . $od['last_name']) ?></strong>
                                            <?php if ($od['assigned_id']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($od['assigned_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($od['title']) ?><br><small class="text-muted"><?= htmlspecialchars($od['author'] ?? '') ?></small></td>
                                        <td><?= htmlspecialchars($od['due_date']) ?></td>
                                        <td>
                                            <?php
                                            $days = (int)$od['days_overdue'];
                                            $severity = $days > 30 ? 'bg-danger' : ($days > 14 ? 'bg-warning text-dark' : 'bg-info');
                                            ?>
                                            <span class="badge <?= $severity ?>"><?= $days ?> day<?= $days !== 1 ? 's' : '' ?></span>
                                        </td>
                                        <td>$<?= number_format((float)($od['fine_amount'] ?? 0), 2) ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="loan_id" value="<?= (int)$od['id'] ?>">
                                                <div class="input-group input-group-sm" style="width:160px;">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="fine_amount" class="form-control" step="0.01" min="0" value="<?= number_format((float)($od['fine_amount'] ?? 0), 2) ?>">
                                                    <button type="submit" name="apply_fine" class="btn btn-sm btn-warning" title="Apply Fine"><i class="fas fa-dollar-sign"></i></button>
                                                </div>
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
