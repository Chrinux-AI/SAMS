<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('forum_moderator');

$categories = [];
$success = '';
$error = '';

try {
  $db = db();

  // Handle create category
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid CSRF token.';
    } else {
      $name = trim($_POST['name'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $sort_order = (int)($_POST['sort_order'] ?? 0);

      if (empty($name)) {
        $error = 'Category name is required.';
      } else {
        if (table_exists('forum_categories')) {
          $stmt = $db->prepare("INSERT INTO forum_categories (name, description, sort_order, is_active, created_at) VALUES (:name, :desc, :sort, 1, NOW())");
          $stmt->execute([':name' => $name, ':desc' => $description, ':sort' => $sort_order]);
          $success = 'Category created successfully.';
        } else {
          $error = 'Forum categories table not found. Please run migrations.';
        }
      }
    }
  }

  // Handle update category
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid CSRF token.';
    } else {
      $cat_id = (int)($_POST['category_id'] ?? 0);
      $name = trim($_POST['name'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $sort_order = (int)($_POST['sort_order'] ?? 0);
      $is_active = isset($_POST['is_active']) ? 1 : 0;

      if ($cat_id > 0 && !empty($name)) {
        $stmt = $db->prepare("UPDATE forum_categories SET name = :name, description = :desc, sort_order = :sort, is_active = :active WHERE id = :id");
        $stmt->execute([':name' => $name, ':desc' => $description, ':sort' => $sort_order, ':active' => $is_active, ':id' => $cat_id]);
        $success = 'Category updated successfully.';
      }
    }
  }

  // Handle delete category
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid CSRF token.';
    } else {
      $cat_id = (int)($_POST['category_id'] ?? 0);
      if ($cat_id > 0) {
        $stmt = $db->prepare("DELETE FROM forum_categories WHERE id = :id");
        $stmt->execute([':id' => $cat_id]);
        $success = 'Category deleted.';
      }
    }
  }

  // Fetch categories
  if (table_exists('forum_categories')) {
    $stmt = $db->query("SELECT fc.*, (SELECT COUNT(*) FROM forum_threads ft WHERE ft.category_id = fc.id) as thread_count FROM forum_categories fc ORDER BY fc.sort_order, fc.name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Exception $e) {
  if (empty($error)) $error = 'Database error: ' . $e->getMessage();
}

$page_title = "Forum Categories";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <script src="../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?> - SAMS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

  <link href="../assets/css/sidebar-nav.css" rel="stylesheet">
  <link href="../assets/css/sams-theme-system.css" rel="stylesheet">
  <style>
    .cat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 1.5rem;
      margin-top: 1.5rem;
    }

    .cat-card {
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border-color, #334155);
      border-radius: 12px;
      padding: 1.5rem;
    }

    .cat-card h3 {
      color: var(--text-primary, #f1f5f9);
      margin-bottom: 0.5rem;
    }

    .cat-card p {
      color: var(--text-secondary, #94a3b8);
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    .cat-meta {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      color: var(--text-secondary);
      font-size: 0.8rem;
    }

    .btn-primary {
      background: var(--primary-color, #6366f1);
      color: #fff;
      border: none;
      padding: 0.5rem 1.25rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-sm {
      padding: 0.35rem 0.75rem;
      font-size: 0.8rem;
    }

    .btn-danger {
      background: rgba(239, 68, 68, 0.15);
      color: #ef4444;
      border: 1px solid #ef4444;
      padding: 0.35rem 0.75rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.8rem;
    }

    .btn-edit {
      background: rgba(56, 189, 248, 0.15);
      color: #38bdf8;
      border: 1px solid #38bdf8;
      padding: 0.35rem 0.75rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.8rem;
    }

    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
    }

    .alert-success {
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid #10b981;
      color: #10b981;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid #ef4444;
      color: #ef4444;
    }

    .form-card {
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border-color, #334155);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .form-card h2 {
      color: var(--text-primary);
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }

    .form-row {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      align-items: end;
    }

    .form-group {
      flex: 1;
      min-width: 200px;
      margin-bottom: 0.5rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.3rem;
      color: var(--text-secondary);
      font-size: 0.8rem;
      font-weight: 600;
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 0.5rem;
      background: var(--input-bg, #0f172a);
      border: 1px solid var(--border-color, #334155);
      border-radius: 8px;
      color: var(--text-primary);
      font-size: 0.9rem;
    }

    .badge-active {
      background: rgba(16, 185, 129, 0.2);
      color: #10b981;
      padding: 0.2rem 0.6rem;
      border-radius: 12px;
      font-size: 0.75rem;
    }

    .badge-inactive {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
      padding: 0.2rem 0.6rem;
      border-radius: 12px;
      font-size: 0.75rem;
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--text-secondary);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      display: block;
      opacity: 0.5;
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-folder-open"></i></div>
          <div>
            <h1>Forum Categories</h1>
            <p class="subtitle">Manage forum discussion categories</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Create Category Form -->
        <div class="form-card">
          <h2><i class="fas fa-plus-circle"></i> Create New Category</h2>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="form-row">
              <div class="form-group"><label>Category Name</label><input type="text" name="name" required placeholder="e.g., General Discussion"></div>
              <div class="form-group"><label>Description</label><input type="text" name="description" placeholder="Brief description"></div>
              <div class="form-group" style="max-width:100px;"><label>Sort Order</label><input type="number" name="sort_order" value="0" min="0"></div>
              <div class="form-group" style="max-width:150px;"><button type="submit" name="create_category" class="btn-primary"><i class="fas fa-plus"></i> Create</button></div>
            </div>
          </form>
        </div>

        <!-- Categories List -->
        <?php if (empty($categories)): ?>
          <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No forum categories yet. Create one above to get started.</p>
          </div>
        <?php else: ?>
          <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
              <div class="cat-card">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                  <h3><i class="fas fa-folder" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($cat['name']); ?></h3>
                  <span class="<?php echo ($cat['is_active'] ?? 1) ? 'badge-active' : 'badge-inactive'; ?>">
                    <?php echo ($cat['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                  </span>
                </div>
                <p><?php echo htmlspecialchars($cat['description'] ?? 'No description'); ?></p>
                <div class="cat-meta">
                  <span><i class="fas fa-comments"></i> <?php echo (int)($cat['thread_count'] ?? 0); ?> threads</span>
                  <span><i class="fas fa-sort-numeric-up"></i> Order: <?php echo (int)($cat['sort_order'] ?? 0); ?></span>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="category_id" value="<?php echo (int)$cat['id']; ?>">
                    <button type="submit" name="delete_category" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="../assets/js/main.js"></script>
</body>

</html>
