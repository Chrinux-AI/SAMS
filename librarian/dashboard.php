<?php

/**
 * SAMS Librarian Dashboard - Modern Library Management Interface
 * Professional dashboard with library insights and AI-powered features
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');
if (!has_role('librarian') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Librarian privileges required.', 'error');
}

$librarian_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get library statistics
$library_stats = [
    'total_books' => lib_count('library_books', 'tenant_id = ?', [$tenantId]),
    'borrowed_books' => lib_count('library_loans', 'tenant_id = ? AND status = "active"', [$tenantId]),
    'overdue_books' => lib_count('library_loans', 'tenant_id = ? AND status = "active" AND due_date < CURDATE()', [$tenantId]),
    'available_books' => lib_count('library_books', 'tenant_id = ? AND status = "available"', [$tenantId]),
];

// Get recent loans
$recent_loans = db()->fetchAll("
    SELECT ll.*, u.first_name, u.last_name, lb.title, lb.author, lb.isbn
    FROM library_loans ll
    JOIN users u ON ll.student_id = u.id
    JOIN library_books lb ON ll.book_id = lb.id
    WHERE ll.tenant_id = ?
    ORDER BY ll.loan_date DESC
    LIMIT 10
", [$tenantId]);

// Get overdue books
$overdue_books = db()->fetchAll("
    SELECT ll.*, u.first_name, u.last_name, lb.title, lb.author,
           DATEDIFF(CURDATE(), ll.due_date) as days_overdue
    FROM library_loans ll
    JOIN users u ON ll.student_id = u.id
    JOIN library_books lb ON ll.book_id = lb.id
    WHERE ll.tenant_id = ? AND ll.status = 'active' AND ll.due_date < CURDATE()
    ORDER BY ll.due_date ASC
", [$tenantId]);

// Get popular books
$popular_books = db()->fetchAll("
    SELECT lb.*, COUNT(ll.id) as loan_count
    FROM library_books lb
    LEFT JOIN library_loans ll ON lb.id = ll.book_id
    WHERE lb.tenant_id = ?
    GROUP BY lb.id
    HAVING loan_count > 0
    ORDER BY loan_count DESC
    LIMIT 5
", [$tenantId]);

// AI Library Insights
$ai_insights = [];
try {
    require_once '../includes/sams-init.php';
    try {
        if (class_exists('SAMS_LibraryBot')) {
            $libraryBot = new SAMS_LibraryBot();
            $ai_insights = $libraryBot->getLibraryInsights($tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'library_health' => $library_stats['overdue_books'] > 5 ? 'needs_attention' : 'good',
            'reading_trend' => 'stable',
            'recommendation' => $library_stats['overdue_books'] > 0 ? 'Follow up on overdue books to improve circulation' : 'Library operations are running smoothly'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'library_health' => 'good',
        'reading_trend' => 'stable',
        'recommendation' => 'Continue regular library monitoring and book maintenance'
    ];
}

$csrf = generate_csrf_token();

// Safe count helper
function lib_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}

$stats = [
    'total_books'   => lib_count('library_books'),
    'issued_books'  => lib_count('library_transactions', "status = 'issued'"),
    'overdue_books' => lib_count('library_transactions', "status = 'issued' AND due_date < CURDATE()"),
    'total_members' => lib_count('library_members'),
    'categories'    => lib_count('library_categories'),
    'fines_pending' => lib_count('library_fines', "status = 'pending'"),
];

// Recent transactions
$recent = [];
try {
    if (table_exists('library_transactions') && table_exists('library_books')) {
        $recent = db()->fetchAll("
            SELECT lt.*, lb.title AS book_title, u.first_name, u.last_name
            FROM library_transactions lt
            LEFT JOIN library_books lb ON lt.book_id = lb.id
            LEFT JOIN users u ON lt.user_id = u.id
            ORDER BY lt.created_at DESC LIMIT 10
        ") ?: [];
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#8b5cf6">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>
    <main class="main-content">
        <header class="cyber-header">
            <div class="page-title-section">
                <div class="page-icon-orb" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"><i class="fas fa-book-open"></i></div>
                <div>
                    <h1 class="page-title">Library Dashboard</h1>
                    <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($full_name); ?></p>
                </div>
            </div>
        </header>
        <div class="cyber-content">
            <!-- KPI Cards -->
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px">
                <?php
                $kpis = [
                    ['Total Books','book',$stats['total_books'],'#8b5cf6'],
                    ['Issued','arrow-right-arrow-left',$stats['issued_books'],'#0ea5e9'],
                    ['Overdue','exclamation-triangle',$stats['overdue_books'],'#ef4444'],
                    ['Members','users',$stats['total_members'],'#10b981'],
                    ['Categories','tags',$stats['categories'],'#f59e0b'],
                    ['Pending Fines','money-bill-wave',$stats['fines_pending'],'#f97316'],
                ];
                foreach ($kpis as [$label,$icon,$val,$color]): ?>
                <div class="cyber-card" style="padding:20px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?php echo $label; ?></span>
                        <div style="width:36px;height:36px;border-radius:10px;background:<?php echo $color; ?>18;display:flex;align-items:center;justify-content:center"><i class="fas fa-<?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:.85rem"></i></div>
                    </div>
                    <div style="font-size:1.8rem;font-weight:800;color:var(--text-primary)"><?php echo number_format($val); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="cyber-card" style="padding:20px;margin-bottom:24px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-bolt" style="color:#8b5cf6;margin-right:8px"></i>Quick Actions</h3>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    <a href="add-book.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-plus"></i> Add Book</a>
                    <a href="issue-return.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-exchange-alt"></i> Issue / Return</a>
                    <a href="overdue.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;border-color:#ef4444;color:#ef4444"><i class="fas fa-exclamation-triangle"></i> Overdue (<?php echo $stats['overdue_books']; ?>)</a>
                    <a href="books.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-search"></i> Search Catalog</a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="cyber-card" style="padding:20px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-clock-rotate-left" style="color:#0ea5e9;margin-right:8px"></i>Recent Transactions</h3>
                <?php if (empty($recent)): ?>
                    <p style="color:var(--text-muted);font-size:.9rem">No library module tables found yet. Run the library setup SQL to enable full functionality.</p>
                    <div style="margin-top:12px;padding:16px;border-radius:12px;background:var(--bg-secondary);border:1px dashed var(--border-color)">
                        <p style="font-size:.85rem;color:var(--text-secondary);margin:0"><i class="fas fa-info-circle" style="color:#8b5cf6;margin-right:6px"></i>The library tables (<code>library_books</code>, <code>library_transactions</code>, etc.) will be created when you run the library setup migration.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" style="width:100%">
                            <thead><tr><th>Book</th><th>User</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent as $t): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['book_title'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></td>
                                    <td><span class="badge"><?php echo ucfirst($t['type'] ?? 'issue'); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                                    <td><span class="badge badge-<?php echo ($t['status'] ?? 'issued') === 'returned' ? 'success' : 'warning'; ?>"><?php echo ucfirst($t['status'] ?? 'issued'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
