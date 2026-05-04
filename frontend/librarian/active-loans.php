<?php
/**
 * SAMS Library - Active Loans
 * View all currently active book loans with student and book details
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

$search = sanitize($_GET['search'] ?? '');

// Fetch active loans
$loans = [];
try {
    if (table_exists('library_loans')) {
        $where = "ll.tenant_id = ? AND ll.status = 'active'";
        $params = [$tenantId];

        if ($search !== '') {
            $where .= " AND (lb.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.assigned_id LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $loans = db()->fetchAll("
            SELECT ll.*, lb.title, lb.author, lb.isbn,
                   u.first_name, u.last_name, u.assigned_id,
                   iu.first_name as issued_first, iu.last_name as issued_last,
                   DATEDIFF(CURDATE(), ll.due_date) as days_overdue
            FROM library_loans ll
            JOIN library_books lb ON ll.book_id = lb.id
            JOIN users u ON ll.student_id = u.id
            LEFT JOIN users iu ON ll.issued_by = iu.id
            WHERE {$where}
            ORDER BY ll.due_date ASC
        ", $params);
    }
} catch (Throwable $e) {}

$total_active = count($loans);
$total_overdue = count(array_filter($loans, fn($l) => ($l['days_overdue'] ?? 0) > 0));

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Loans - SAMS</title>
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
            <div class="page-icon-orb"><i class="fas fa-hand-holding-heart"></i></div>
            <div>
                <h1>Active Loans</h1>
                <p>All currently issued books and their return status</p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-primary"><?= $total_active ?></h3>
                        <small class="text-muted">Total Active Loans</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-success"><?= $total_active - $total_overdue ?></h3>
                        <small class="text-muted">On-Time</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center py-3">
                        <h3 class="mb-0 text-danger"><?= $total_overdue ?></h3>
                        <small class="text-muted">Overdue</small>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-9">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Student name, book title, or ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loans Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Book</th>
                                <th>ISBN</th>
                                <th>Loan Date</th>
                                <th>Due Date</th>
                                <th>Issued By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loans)): ?>
                                <tr><td colspan="8" class="text-center py-4">No active loans found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($loans as $loan): ?>
                                    <tr class="<?= ($loan['days_overdue'] ?? 0) > 0 ? 'table-danger' : '' ?>">
                                        <td><?= (int)$loan['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?></strong>
                                            <?php if ($loan['assigned_id']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($loan['assigned_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($loan['title']) ?><br><small class="text-muted"><?= htmlspecialchars($loan['author'] ?? '') ?></small></td>
                                        <td><code><?= htmlspecialchars($loan['isbn'] ?? 'N/A') ?></code></td>
                                        <td><?= htmlspecialchars($loan['loan_date']) ?></td>
                                        <td><?= htmlspecialchars($loan['due_date']) ?></td>
                                        <td><?= htmlspecialchars(($loan['issued_first'] ?? '') . ' ' . ($loan['issued_last'] ?? '')) ?></td>
                                        <td>
                                            <?php if (($loan['days_overdue'] ?? 0) > 0): ?>
                                                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Overdue <?= (int)$loan['days_overdue'] ?>d</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="fas fa-check"></i> On Time</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3 text-end">
                <a href="issue-return.php" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> Issue / Return</a>
                <a href="overdue.php" class="btn btn-danger"><i class="fas fa-clock"></i> View Overdue</a>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
