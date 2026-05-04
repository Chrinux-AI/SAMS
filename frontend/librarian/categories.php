<?php

/**
 * SAMS Library - Categories Management
 * CRUD for library book categories
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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('categories.php', 'Invalid security token.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if ($name === '') {
            redirect('categories.php', 'Category name is required.', 'error');
        }
        try {
            // Check duplicate
            $existing = db()->fetch("SELECT id FROM library_categories WHERE name = ? AND tenant_id = ?", [$name, $tenantId]);
            if ($existing) {
                redirect('categories.php', 'Category already exists.', 'error');
            }
            insert_flexible('library_categories', [
                'tenant_id'   => $tenantId,
                'name'        => $name,
                'description' => $description,
            ]);
            redirect('categories.php', 'Category added successfully.', 'success');
        } catch (Throwable $e) {
            redirect('categories.php', 'Error adding category.', 'error');
        }
    }

    if ($action === 'edit') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if ($name === '' || $cat_id < 1) {
            redirect('categories.php', 'Invalid data.', 'error');
        }
        try {
            db()->update('library_categories', [
                'name' => $name,
                'description' => $description,
            ], 'id = ? AND tenant_id = ?', [$cat_id, $tenantId]);
            redirect('categories.php', 'Category updated successfully.', 'success');
        } catch (Throwable $e) {
            redirect('categories.php', 'Error updating category.', 'error');
        }
    }

    if ($action === 'delete') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        try {
            $book_count = db()->fetch("SELECT COUNT(*) as cnt FROM library_books WHERE category = ? AND tenant_id = ?", [$cat_id, $tenantId]);
            if ($book_count && $book_count['cnt'] > 0) {
                redirect('categories.php', 'Cannot delete category with assigned books.', 'error');
            }
            db()->delete('library_categories', 'id = ? AND tenant_id = ?', [$cat_id, $tenantId]);
            redirect('categories.php', 'Category deleted successfully.', 'success');
        } catch (Throwable $e) {
            redirect('categories.php', 'Error deleting category.', 'error');
        }
    }
}

// Fetch categories with book counts
$categories = [];
try {
    if (table_exists('library_categories')) {
        $categories = db()->fetchAll("
            SELECT lc.*, COUNT(lb.id) as book_count
            FROM library_categories lc
            LEFT JOIN library_books lb ON lc.id = lb.category AND lb.tenant_id = lc.tenant_id
            WHERE lc.tenant_id = ?
            GROUP BY lc.id
            ORDER BY lc.name
        ", [$tenantId]);
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
    <title>Categories - SAMS</title>
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
                <div class="page-icon-orb"><i class="fas fa-tags"></i></div>
                <div>
                    <h1>Library Categories</h1>
                    <p>Manage book categories and classifications</p>
                </div>
            </div>
            <div class="cyber-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-plus"></i> Add Category</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <div class="mb-3">
                                        <label class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add Category</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list"></i> All Categories (<?= count($categories) ?>)</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Books</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($categories)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">No categories yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($categories as $cat): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                                    <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                                                    <td><span class="badge bg-info"><?= (int)$cat['book_count'] ?></span></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCategory(<?= (int)$cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($cat['description'] ?? ''), ENT_QUOTES) ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
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
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="cat_id" id="edit_cat_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function editCategory(id, name, desc) {
            document.getElementById('edit_cat_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = desc;
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }
    </script>
</body>

</html>
