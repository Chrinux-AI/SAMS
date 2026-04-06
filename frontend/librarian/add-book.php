<?php
/**
 * SAMS Library - Add/Edit Book
 * Form to add a new book or edit existing book details
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

$edit_id = (int)($_GET['edit'] ?? 0);
$book = null;

// Load book for editing
if ($edit_id > 0) {
    try {
        $book = db()->fetch("SELECT * FROM library_books WHERE id = ? AND tenant_id = ?", [$edit_id, $tenantId]);
        if (!$book) {
            redirect('books.php', 'Book not found.', 'error');
        }
    } catch (Throwable $e) {
        redirect('books.php', 'Error loading book.', 'error');
    }
}

// Fetch categories
$categories = [];
try {
    if (table_exists('library_categories')) {
        $categories = db()->fetchAll("SELECT * FROM library_categories WHERE tenant_id = ? ORDER BY name", [$tenantId]);
    }
} catch (Throwable $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('add-book.php', 'Invalid security token.', 'error');
    }

    $data = [
        'tenant_id'       => $tenantId,
        'title'           => sanitize($_POST['title'] ?? ''),
        'author'          => sanitize($_POST['author'] ?? ''),
        'isbn'            => sanitize($_POST['isbn'] ?? ''),
        'category'        => sanitize($_POST['category'] ?? ''),
        'publisher'       => sanitize($_POST['publisher'] ?? ''),
        'publish_year'    => (int)($_POST['publish_year'] ?? 0),
        'total_copies'    => max(0, (int)($_POST['total_copies'] ?? 1)),
        'available_copies' => max(0, (int)($_POST['available_copies'] ?? 1)),
        'shelf_location'  => sanitize($_POST['shelf_location'] ?? ''),
        'status'          => sanitize($_POST['status'] ?? 'available'),
        'added_by'        => $user_id,
    ];

    if ($data['title'] === '') {
        redirect('add-book.php' . ($edit_id ? "?edit={$edit_id}" : ''), 'Title is required.', 'error');
    }

    try {
        if ($edit_id > 0) {
            unset($data['tenant_id'], $data['added_by']);
            db()->update('library_books', $data, 'id = ? AND tenant_id = ?', [$edit_id, $tenantId]);
            redirect('books.php', 'Book updated successfully.', 'success');
        } else {
            insert_flexible('library_books', $data);
            redirect('books.php', 'Book added successfully.', 'success');
        }
    } catch (Throwable $e) {
        redirect('add-book.php' . ($edit_id ? "?edit={$edit_id}" : ''), 'Error saving book: ' . $e->getMessage(), 'error');
    }
}

$flash = get_flash_message();
$page_title = $edit_id ? 'Edit Book' : 'Add New Book';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - SAMS</title>
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
            <div class="page-icon-orb"><i class="fas fa-plus-circle"></i></div>
            <div>
                <h1><?= htmlspecialchars($page_title) ?></h1>
                <p><?= $edit_id ? 'Update book details' : 'Add a new book to the library collection' ?></p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($book['title'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ISBN</label>
                                <input type="text" name="isbn" class="form-control" value="<?= htmlspecialchars($book['isbn'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Author</label>
                                <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($book['author'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Publisher</label>
                                <input type="text" name="publisher" class="form-control" value="<?= htmlspecialchars($book['publisher'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($book['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Publish Year</label>
                                <input type="number" name="publish_year" class="form-control" min="1800" max="2100" value="<?= (int)($book['publish_year'] ?? date('Y')) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Total Copies</label>
                                <input type="number" name="total_copies" class="form-control" min="0" value="<?= (int)($book['total_copies'] ?? 1) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Available Copies</label>
                                <input type="number" name="available_copies" class="form-control" min="0" value="<?= (int)($book['available_copies'] ?? 1) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Shelf Location</label>
                                <input type="text" name="shelf_location" class="form-control" value="<?= htmlspecialchars($book['shelf_location'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php
                                    $statuses = ['available' => 'Available', 'checked_out' => 'Checked Out', 'reserved' => 'Reserved', 'damaged' => 'Damaged', 'lost' => 'Lost'];
                                    foreach ($statuses as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($book['status'] ?? 'available') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $edit_id ? 'Update Book' : 'Add Book' ?></button>
                            <a href="books.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Back to Catalog</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
