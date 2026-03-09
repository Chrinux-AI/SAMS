<?php

/**
 * SAMS Library - Book Catalog
 * Search, filter, and view all books in the library collection
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

// Filter parameters
$search = sanitize($_GET['search'] ?? '');
$category_filter = sanitize($_GET['category'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('books.php', 'Invalid security token.', 'error');
    }
    $book_id = (int)$_POST['book_id'];
    try {
        // Check if book has active loans
        $active = db()->fetch("SELECT COUNT(*) as cnt FROM library_loans WHERE book_id = ? AND status = 'active' AND tenant_id = ?", [$book_id, $tenantId]);
        if ($active && $active['cnt'] > 0) {
            redirect('books.php', 'Cannot delete book with active loans.', 'error');
        }
        db()->delete('library_books', 'id = ? AND tenant_id = ?', [$book_id, $tenantId]);
        redirect('books.php', 'Book deleted successfully.', 'success');
    } catch (Throwable $e) {
        redirect('books.php', 'Error deleting book.', 'error');
    }
}

// Fetch categories
$categories = [];
try {
    if (table_exists('library_categories')) {
        $categories = db()->fetchAll("SELECT * FROM library_categories WHERE tenant_id = ? ORDER BY name", [$tenantId]);
    }
} catch (Throwable $e) {
}

// Build query
$where = ["lb.tenant_id = ?"];
$params = [$tenantId];

if ($search !== '') {
    $where[] = "(lb.title LIKE ? OR lb.author LIKE ? OR lb.isbn LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($category_filter !== '') {
    $where[] = "lb.category = ?";
    $params[] = $category_filter;
}
if ($status_filter !== '') {
    $where[] = "lb.status = ?";
    $params[] = $status_filter;
}

$whereClause = implode(' AND ', $where);
$books = [];
try {
    if (table_exists('library_books')) {
        $books = db()->fetchAll("
            SELECT lb.*, lc.name as category_name
            FROM library_books lb
            LEFT JOIN library_categories lc ON lb.category = lc.id AND lc.tenant_id = lb.tenant_id
            WHERE {$whereClause}
            ORDER BY lb.title ASC
        ", $params);
    }
} catch (Throwable $e) {
}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Catalog - SAMS</title>
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
                <div class="page-icon-orb"><i class="fas fa-book"></i></div>
                <div>
                    <h1>Book Catalog</h1>
                    <p>Browse and manage the library book collection</p>
                </div>
            </div>
            <div class="cyber-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Title, Author, ISBN..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="available" <?= $status_filter === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="checked_out" <?= $status_filter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                                    <option value="reserved" <?= $status_filter === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                                    <option value="damaged" <?= $status_filter === 'damaged' ? 'selected' : '' ?>>Damaged</option>
                                    <option value="lost" <?= $status_filter === 'lost' ? 'selected' : '' ?>>Lost</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><?= count($books) ?> book(s) found</h5>
                    <a href="add-book.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Book</a>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>ISBN</th>
                                    <th>Category</th>
                                    <th>Shelf</th>
                                    <th>Total</th>
                                    <th>Available</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($books)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">No books found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($books as $book): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                            <td><?= htmlspecialchars($book['author'] ?? '') ?></td>
                                            <td><code><?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></code></td>
                                            <td><?= htmlspecialchars($book['category_name'] ?? $book['category'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($book['shelf_location'] ?? '-') ?></td>
                                            <td><?= (int)($book['total_copies'] ?? 0) ?></td>
                                            <td><?= (int)($book['available_copies'] ?? 0) ?></td>
                                            <td>
                                                <?php
                                                $status = $book['status'] ?? 'available';
                                                $badge_class = match ($status) {
                                                    'available' => 'badge bg-success',
                                                    'checked_out' => 'badge bg-warning',
                                                    'reserved' => 'badge bg-info',
                                                    'damaged' => 'badge bg-danger',
                                                    'lost' => 'badge bg-dark',
                                                    default => 'badge bg-secondary',
                                                };
                                                ?>
                                                <span class="<?= $badge_class ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?></span>
                                            </td>
                                            <td>
                                                <a href="add-book.php?edit=<?= (int)$book['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this book?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                    <input type="hidden" name="book_id" value="<?= (int)$book['id'] ?>">
                                                    <button type="submit" name="delete_book" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
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
