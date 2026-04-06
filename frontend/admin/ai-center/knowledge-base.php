<?php

/**
 * AI Knowledge Base - Search and manage organizational knowledge
 */
require_once __DIR__ . '/../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$csrf = generate_csrf_token();
$message = '';
$message_type = '';
$search_results = [];
$search_query = '';
$modules = ['discipline', 'academic', 'financial', 'administration', 'security', 'general'];

// Ensure table exists
try {
    db()->query("CREATE TABLE IF NOT EXISTS ai_knowledge_base (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        module VARCHAR(100) DEFAULT 'general',
        category VARCHAR(100) DEFAULT NULL,
        keywords TEXT DEFAULT NULL,
        author_id INT DEFAULT NULL,
        tenant_id INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_module (module),
        INDEX idx_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add-entry') {
        $title = trim(htmlspecialchars(strip_tags($_POST['title'] ?? '')));
        $content = trim(htmlspecialchars(strip_tags($_POST['content'] ?? '')));
        $module = htmlspecialchars(strip_tags($_POST['module'] ?? 'general'));

        if ($title && $content) {
            try {
                db()->insert('ai_knowledge_base', [
                    'title' => $title,
                    'content' => $content,
                    'module' => $module,
                    'author_id' => $_SESSION['user_id'],
                    'tenant_id' => $_SESSION['tenant_id'] ?? 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $message = 'Knowledge entry added successfully.';
                $message_type = 'success';
                Logger::audit('knowledge_entry_added', $_SESSION['user_id'], ['title' => $title]);
            } catch (Exception $e) {
                $message = 'Error adding entry.';
                $message_type = 'danger';
            }
        } else {
            $message = 'Title and content are required.';
            $message_type = 'danger';
        }
    } elseif ($action === 'delete-entry') {
        $id = (int)($_POST['entry_id'] ?? 0);
        if ($id > 0) {
            try {
                db()->query("DELETE FROM ai_knowledge_base WHERE id = ?", [$id]);
                $message = 'Entry deleted.';
                $message_type = 'success';
                Logger::audit('knowledge_entry_deleted', $_SESSION['user_id'], ['id' => $id]);
            } catch (Exception $e) {
                $message = 'Error deleting entry.';
                $message_type = 'danger';
            }
        }
    }
}

// Handle search
if (!empty($_GET['q'])) {
    $search_query = trim($_GET['q']);
    $like = '%' . $search_query . '%';
    try {
        $search_results = db()->fetchAll(
            "SELECT kb.*, u.full_name as author_name FROM ai_knowledge_base kb
             LEFT JOIN users u ON kb.author_id = u.id
             WHERE kb.title LIKE ? OR kb.content LIKE ?
             ORDER BY kb.updated_at DESC LIMIT 50",
            [$like, $like]
        );
    } catch (Exception $e) {
        $search_results = [];
    }
}

// Filter by module
$filter_module = $_GET['module'] ?? '';
if ($filter_module && !$search_query) {
    try {
        $search_results = db()->fetchAll(
            "SELECT kb.*, u.full_name as author_name FROM ai_knowledge_base kb
             LEFT JOIN users u ON kb.author_id = u.id
             WHERE kb.module = ? ORDER BY kb.updated_at DESC LIMIT 50",
            [$filter_module]
        );
    } catch (Exception $e) {
        $search_results = [];
    }
}

// Recent entries
$recent_entries = [];
try {
    $recent_entries = db()->fetchAll(
        "SELECT kb.*, u.full_name as author_name FROM ai_knowledge_base kb
         LEFT JOIN users u ON kb.author_id = u.id
         ORDER BY kb.created_at DESC LIMIT 20"
    );
} catch (Exception $e) {
}

// Stats
$stats = ['total' => 0, 'by_module' => []];
try {
    $r = db()->fetchOne("SELECT COUNT(*) as cnt FROM ai_knowledge_base");
    $stats['total'] = $r['cnt'] ?? 0;
    $stats['by_module'] = db()->fetchAll("SELECT module, COUNT(*) as cnt FROM ai_knowledge_base GROUP BY module ORDER BY cnt DESC");
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Knowledge Base - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../../assets/css/sams-layout.css">
</head>

<body>
    <div class="app-layout">
        <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>

        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-book-open"></i></div>
                <div>
                    <h1>AI Knowledge Base</h1>
                    <p>Search and manage organizational knowledge &bull; <?= $stats['total'] ?> entries</p>
                </div>
            </div>

            <div class="cyber-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <!-- Search Bar -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-body">
                        <form method="GET" style="display:flex; gap:12px; align-items:end;">
                            <div class="form-group" style="flex:1; margin-bottom:0;">
                                <label><i class="fas fa-search"></i> Search Knowledge Base</label>
                                <input type="text" name="q" class="form-control" placeholder="Search documents, notes, policies..." value="<?= htmlspecialchars($search_query) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                            <?php if ($search_query || $filter_module): ?>
                                <a href="knowledge-base.php" class="btn btn-outline">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Module Filter Pills -->
                <div style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap;">
                    <a href="knowledge-base.php" class="btn btn-sm <?= !$filter_module ? 'btn-primary' : 'btn-outline' ?>">All</a>
                    <?php foreach ($modules as $mod): ?>
                        <a href="?module=<?= urlencode($mod) ?>" class="btn btn-sm <?= $filter_module === $mod ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($mod) ?></a>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">
                    <!-- Results / Recent -->
                    <div>
                        <?php $display_items = !empty($search_results) ? $search_results : $recent_entries; ?>
                        <div class="section-title"><i class="fas fa-file-alt"></i> <?= $search_query ? 'Search Results' : ($filter_module ? ucfirst($filter_module) . ' Documents' : 'Recent Entries') ?></div>
                        <?php if (empty($display_items)): ?>
                            <div class="card">
                                <div class="card-body" style="text-align:center; padding:32px; color:var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size:2rem; margin-bottom:12px; display:block;"></i>
                                    No entries found. Add your first knowledge entry below.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($display_items as $item): ?>
                                <div class="card" style="margin-bottom:12px;">
                                    <div class="card-body">
                                        <div style="display:flex; justify-content:space-between; align-items:start;">
                                            <div style="flex:1;">
                                                <h3 style="font-size:1rem; margin-bottom:6px;"><?= htmlspecialchars($item['title']) ?></h3>
                                                <p style="color:var(--text-secondary); font-size:0.85rem; margin-bottom:8px;">
                                                    <?= htmlspecialchars(mb_substr(strip_tags($item['content']), 0, 200)) ?><?= mb_strlen($item['content']) > 200 ? '...' : '' ?>
                                                </p>
                                                <div style="display:flex; gap:12px; font-size:0.75rem; color:var(--text-muted);">
                                                    <span><i class="fas fa-folder"></i> <?= ucfirst(htmlspecialchars($item['module'] ?? 'general')) ?></span>
                                                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($item['author_name'] ?? 'System') ?></span>
                                                    <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($item['created_at'])) ?></span>
                                                </div>
                                            </div>
                                            <form method="POST" style="margin-left:12px;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="action" value="delete-entry">
                                                <input type="hidden" name="entry_id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Delete this entry?')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Add New Entry -->
                    <div>
                        <div class="section-title"><i class="fas fa-plus-circle"></i> Add Knowledge Entry</div>
                        <div class="card">
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="add-entry">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="title" class="form-control" required maxlength="255" placeholder="Document title...">
                                    </div>
                                    <div class="form-group">
                                        <label>Module</label>
                                        <select name="module" class="form-control">
                                            <?php foreach ($modules as $mod): ?>
                                                <option value="<?= $mod ?>"><?= ucfirst($mod) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Content</label>
                                        <textarea name="content" class="form-control" rows="8" required placeholder="Enter notes, policies, procedures..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-save"></i> Add Entry</button>
                                </form>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="section-title" style="margin-top:20px;"><i class="fas fa-chart-pie"></i> Statistics</div>
                        <div class="card">
                            <div class="card-body">
                                <div style="font-size:2rem; font-weight:700; color:var(--primary);"><?= $stats['total'] ?></div>
                                <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:12px;">Total Entries</div>
                                <?php foreach ($stats['by_module'] as $ms): ?>
                                    <div style="display:flex; justify-content:space-between; padding:4px 0; font-size:0.85rem;">
                                        <span><?= ucfirst(htmlspecialchars($ms['module'])) ?></span>
                                        <span style="font-weight:600;"><?= $ms['cnt'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../../assets/js/main.js"></script>
</body>

</html>
