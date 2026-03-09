<?php
/**
 * SAMS Library - Reports & Statistics
 * Library analytics: collection stats, loan trends, popular books
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

// Helper
function lib_safe_count($table, $where = '1=1', $params = []) {
    try {
        if (!table_exists($table)) return 0;
        return (int)db()->count($table, $where, $params);
    } catch (Throwable $e) { return 0; }
}

// Collection stats
$stats = [
    'total_books'      => lib_safe_count('library_books', 'tenant_id = ?', [$tenantId]),
    'available_books'  => lib_safe_count('library_books', 'tenant_id = ? AND available_copies > 0', [$tenantId]),
    'total_copies'     => 0,
    'active_loans'     => lib_safe_count('library_loans', "tenant_id = ? AND status = 'active'", [$tenantId]),
    'overdue_count'    => lib_safe_count('library_loans', "tenant_id = ? AND status = 'active' AND due_date < CURDATE()", [$tenantId]),
    'returned_total'   => lib_safe_count('library_loans', "tenant_id = ? AND status = 'returned'", [$tenantId]),
    'categories_count' => lib_safe_count('library_categories', 'tenant_id = ?', [$tenantId]),
    'total_fines'      => 0,
];

// Get total copies sum
try {
    if (table_exists('library_books')) {
        $r = db()->fetch("SELECT COALESCE(SUM(total_copies), 0) as total FROM library_books WHERE tenant_id = ?", [$tenantId]);
        $stats['total_copies'] = (int)($r['total'] ?? 0);
    }
} catch (Throwable $e) {}

// Get total fines
try {
    if (table_exists('library_loans')) {
        $r = db()->fetch("SELECT COALESCE(SUM(fine_amount), 0) as total FROM library_loans WHERE tenant_id = ? AND fine_amount > 0", [$tenantId]);
        $stats['total_fines'] = (float)($r['total'] ?? 0);
    }
} catch (Throwable $e) {}

// Popular books (most loaned)
$popular_books = [];
try {
    if (table_exists('library_loans') && table_exists('library_books')) {
        $popular_books = db()->fetchAll("
            SELECT lb.title, lb.author, COUNT(ll.id) as loan_count
            FROM library_loans ll
            JOIN library_books lb ON ll.book_id = lb.id
            WHERE ll.tenant_id = ?
            GROUP BY ll.book_id
            ORDER BY loan_count DESC
            LIMIT 10
        ", [$tenantId]);
    }
} catch (Throwable $e) {}

// Monthly loan trend (last 6 months)
$monthly_trend = [];
try {
    if (table_exists('library_loans')) {
        $monthly_trend = db()->fetchAll("
            SELECT DATE_FORMAT(loan_date, '%Y-%m') as month, COUNT(*) as total
            FROM library_loans
            WHERE tenant_id = ? AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ", [$tenantId]);
    }
} catch (Throwable $e) {}

// Category distribution
$category_dist = [];
try {
    if (table_exists('library_books') && table_exists('library_categories')) {
        $category_dist = db()->fetchAll("
            SELECT lc.name, COUNT(lb.id) as book_count
            FROM library_books lb
            JOIN library_categories lc ON lb.category = lc.id AND lc.tenant_id = lb.tenant_id
            WHERE lb.tenant_id = ?
            GROUP BY lb.category
            ORDER BY book_count DESC
            LIMIT 10
        ", [$tenantId]);
    }
} catch (Throwable $e) {}

// Top borrowers
$top_borrowers = [];
try {
    if (table_exists('library_loans')) {
        $top_borrowers = db()->fetchAll("
            SELECT u.first_name, u.last_name, u.assigned_id, COUNT(ll.id) as borrow_count
            FROM library_loans ll
            JOIN users u ON ll.student_id = u.id
            WHERE ll.tenant_id = ?
            GROUP BY ll.student_id
            ORDER BY borrow_count DESC
            LIMIT 10
        ", [$tenantId]);
    }
} catch (Throwable $e) {}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Reports - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header">
            <div class="page-icon-orb"><i class="fas fa-chart-bar"></i></div>
            <div>
                <h1>Library Reports</h1>
                <p>Comprehensive library statistics and analytics</p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-book fa-2x text-primary"></i></div>
                        <h3 class="mb-0"><?= $stats['total_books'] ?></h3>
                        <small class="text-muted">Total Titles</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-copy fa-2x text-info"></i></div>
                        <h3 class="mb-0"><?= $stats['total_copies'] ?></h3>
                        <small class="text-muted">Total Copies</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-hand-holding fa-2x text-warning"></i></div>
                        <h3 class="mb-0"><?= $stats['active_loans'] ?></h3>
                        <small class="text-muted">Active Loans</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-exclamation-circle fa-2x text-danger"></i></div>
                        <h3 class="mb-0"><?= $stats['overdue_count'] ?></h3>
                        <small class="text-muted">Overdue</small>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-check-circle fa-2x text-success"></i></div>
                        <h3 class="mb-0"><?= $stats['available_books'] ?></h3>
                        <small class="text-muted">Available Titles</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-undo fa-2x text-secondary"></i></div>
                        <h3 class="mb-0"><?= $stats['returned_total'] ?></h3>
                        <small class="text-muted">Total Returns</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-tags fa-2x text-purple"></i></div>
                        <h3 class="mb-0"><?= $stats['categories_count'] ?></h3>
                        <small class="text-muted">Categories</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center py-3">
                        <div class="mb-1"><i class="fas fa-dollar-sign fa-2x text-danger"></i></div>
                        <h3 class="mb-0">$<?= number_format($stats['total_fines'], 2) ?></h3>
                        <small class="text-muted">Total Fines</small>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-line"></i> Monthly Loan Trend</h5></div>
                        <div class="card-body">
                            <canvas id="loanTrendChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-pie"></i> Category Distribution</h5></div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0"><i class="fas fa-fire"></i> Most Popular Books</h5></div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Loans</th></tr></thead>
                                <tbody>
                                    <?php if (empty($popular_books)): ?>
                                        <tr><td colspan="4" class="text-center py-3">No loan data yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($popular_books as $i => $pb): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($pb['title']) ?></td>
                                                <td><?= htmlspecialchars($pb['author'] ?? '-') ?></td>
                                                <td><span class="badge bg-primary"><?= (int)$pb['loan_count'] ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0"><i class="fas fa-users"></i> Top Borrowers</h5></div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>#</th><th>Student</th><th>ID</th><th>Borrows</th></tr></thead>
                                <tbody>
                                    <?php if (empty($top_borrowers)): ?>
                                        <tr><td colspan="4" class="text-center py-3">No borrower data yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($top_borrowers as $i => $tb): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($tb['first_name'] . ' ' . $tb['last_name']) ?></td>
                                                <td><?= htmlspecialchars($tb['assigned_id'] ?? '-') ?></td>
                                                <td><span class="badge bg-success"><?= (int)$tb['borrow_count'] ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
<script>
// Monthly Loan Trend Chart
const trendLabels = <?= json_encode(array_column($monthly_trend, 'month')) ?>;
const trendData = <?= json_encode(array_map('intval', array_column($monthly_trend, 'total'))) ?>;
new Chart(document.getElementById('loanTrendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Loans',
            data: trendData,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79,70,229,0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Category Distribution Chart
const catLabels = <?= json_encode(array_column($category_dist, 'name')) ?>;
const catData = <?= json_encode(array_map('intval', array_column($category_dist, 'book_count'))) ?>;
const catColors = ['#4f46e5','#059669','#dc2626','#7c3aed','#ea580c','#0284c7','#b45309','#be185d','#6366f1','#14b8a6'];
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catData,
            backgroundColor: catColors.slice(0, catLabels.length)
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
    }
});
</script>
</body>
</html>
