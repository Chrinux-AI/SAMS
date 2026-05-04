<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['librarian', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$books = [];
try {
    $books = db()->fetchAll("SELECT id, title, author, isbn, category, total_copies, available_copies, shelf_location, status FROM library_books WHERE tenant_id = ? ORDER BY title", [$tenantId]);
} catch (Exception $e) {}
$total = count($books);
$available = array_sum(array_column($books, 'available_copies'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-warehouse"></i></div>
            <div><h1>Book Inventory</h1><p><?= $total ?> titles &bull; <?= $available ?> copies available</p></div></div>
        <div class="cyber-content">
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Shelf</th><th>Total</th><th>Available</th><th>Status</th></tr></thead><tbody>
                <?php if (empty($books)): ?><tr><td colspan="9" style="text-align:center;padding:24px;">No books in inventory.</td></tr>
                <?php else: foreach ($books as $b): ?>
                <tr><td><?= $b['id'] ?></td><td><strong><?= htmlspecialchars($b['title']) ?></strong></td><td><?= htmlspecialchars($b['author'] ?? '') ?></td><td><code><?= htmlspecialchars($b['isbn'] ?? '-') ?></code></td><td><?= htmlspecialchars($b['category'] ?? '-') ?></td><td><?= htmlspecialchars($b['shelf_location'] ?? '-') ?></td><td><?= (int)$b['total_copies'] ?></td><td><?= (int)$b['available_copies'] ?></td><td><span class="badge badge-<?= $b['status'] === 'available' ? 'success' : ($b['status'] === 'lost' ? 'danger' : 'warning') ?>"><?= ucfirst($b['status']) ?></span></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
