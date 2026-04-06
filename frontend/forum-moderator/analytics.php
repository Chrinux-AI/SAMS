<?php
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('forum_moderator');

$stats = [
  'total_threads' => 0,
  'total_posts' => 0,
  'total_categories' => 0,
  'reported_posts' => 0,
  'active_users' => 0,
  'banned_users' => 0,
  'warnings_issued' => 0,
  'threads_today' => 0,
];
$recent_threads = [];
$top_categories = [];

try {
  $db = db();

  if (table_exists('forum_threads')) {
    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_threads")->fetch(PDO::FETCH_ASSOC);
    $stats['total_threads'] = (int)($row['cnt'] ?? 0);

    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_threads WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    $stats['threads_today'] = (int)($row['cnt'] ?? 0);

    $stmt = $db->query("SELECT ft.*, u.first_name, u.last_name FROM forum_threads ft LEFT JOIN users u ON ft.user_id = u.id ORDER BY ft.created_at DESC LIMIT 10");
    $recent_threads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if (table_exists('forum_posts')) {
    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_posts")->fetch(PDO::FETCH_ASSOC);
    $stats['total_posts'] = (int)($row['cnt'] ?? 0);
  }

  if (table_exists('forum_categories')) {
    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_categories WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC);
    $stats['total_categories'] = (int)($row['cnt'] ?? 0);

    $stmt = $db->query("SELECT fc.name, (SELECT COUNT(*) FROM forum_threads ft WHERE ft.category_id = fc.id) as thread_count FROM forum_categories fc WHERE fc.is_active = 1 ORDER BY thread_count DESC LIMIT 5");
    $top_categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  if (table_exists('forum_reported_posts')) {
    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_reported_posts WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC);
    $stats['reported_posts'] = (int)($row['cnt'] ?? 0);
  }

  if (table_exists('forum_bans')) {
    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_bans WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC);
    $stats['banned_users'] = (int)($row['cnt'] ?? 0);
  }

  if (table_exists('forum_warnings')) {
    $row = $db->query("SELECT COUNT(*) as cnt FROM forum_warnings")->fetch(PDO::FETCH_ASSOC);
    $stats['warnings_issued'] = (int)($row['cnt'] ?? 0);
  }
} catch (Exception $e) {
  // Stats remain at defaults
}

$page_title = "Forum Analytics";
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
  <link href="../assets/css/sidebar-nav.css" rel="stylesheet">
  <link href="../assets/css/sams-theme-system.css" rel="stylesheet">
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border-color, #334155);
      border-radius: 12px;
      padding: 1.5rem;
      text-align: center;
    }

    .stat-card .icon {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }

    .stat-card .number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--text-primary, #f1f5f9);
    }

    .stat-card .label {
      color: var(--text-secondary, #94a3b8);
      font-size: 0.85rem;
      margin-top: 0.25rem;
    }

    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 1.5rem;
    }

    @media (max-width: 900px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
    }

    .panel {
      background: var(--card-bg, #1e293b);
      border: 1px solid var(--border-color, #334155);
      border-radius: 12px;
      padding: 1.5rem;
    }

    .panel h2 {
      color: var(--text-primary, #f1f5f9);
      font-size: 1.1rem;
      margin-bottom: 1rem;
    }

    .thread-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .thread-list li {
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--border-color, #334155);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .thread-list li:last-child {
      border-bottom: none;
    }

    .thread-title {
      color: var(--text-primary, #f1f5f9);
      font-size: 0.9rem;
      font-weight: 500;
    }

    .thread-meta {
      color: var(--text-secondary, #94a3b8);
      font-size: 0.75rem;
    }

    .cat-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.6rem 0;
      border-bottom: 1px solid var(--border-color, #334155);
    }

    .cat-row:last-child {
      border-bottom: none;
    }

    .cat-name {
      color: var(--text-primary);
      font-size: 0.9rem;
    }

    .cat-count {
      color: var(--primary-color, #6366f1);
      font-weight: 700;
      font-size: 0.9rem;
    }

    .empty-state {
      text-align: center;
      padding: 2rem;
      color: var(--text-secondary);
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="header-left">
          <div class="page-icon-orb"><i class="fas fa-chart-bar"></i></div>
          <div>
            <h1>Forum Analytics</h1>
            <p class="subtitle">Monitor forum activity and performance</p>
          </div>
        </div>
      </div>
      <div class="cyber-content">
        <!-- Stats Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="icon" style="color: #6366f1;"><i class="fas fa-comments"></i></div>
            <div class="number"><?php echo $stats['total_threads']; ?></div>
            <div class="label">Total Threads</div>
          </div>
          <div class="stat-card">
            <div class="icon" style="color: #10b981;"><i class="fas fa-reply-all"></i></div>
            <div class="number"><?php echo $stats['total_posts']; ?></div>
            <div class="label">Total Posts</div>
          </div>
          <div class="stat-card">
            <div class="icon" style="color: #38bdf8;"><i class="fas fa-folder-open"></i></div>
            <div class="number"><?php echo $stats['total_categories']; ?></div>
            <div class="label">Categories</div>
          </div>
          <div class="stat-card">
            <div class="icon" style="color: #f59e0b;"><i class="fas fa-bolt"></i></div>
            <div class="number"><?php echo $stats['threads_today']; ?></div>
            <div class="label">Threads Today</div>
          </div>
          <div class="stat-card">
            <div class="icon" style="color: #ef4444;"><i class="fas fa-flag"></i></div>
            <div class="number"><?php echo $stats['reported_posts']; ?></div>
            <div class="label">Pending Reports</div>
          </div>
          <div class="stat-card">
            <div class="icon" style="color: #f97316;"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="number"><?php echo $stats['warnings_issued']; ?></div>
            <div class="label">Warnings Issued</div>
          </div>
          <div class="stat-card">
            <div class="icon" style="color: #dc2626;"><i class="fas fa-ban"></i></div>
            <div class="number"><?php echo $stats['banned_users']; ?></div>
            <div class="label">Banned Users</div>
          </div>
        </div>

        <!-- Content -->
        <div class="content-grid">
          <div class="panel">
            <h2><i class="fas fa-clock"></i> Recent Threads</h2>
            <?php if (empty($recent_threads)): ?>
              <div class="empty-state">
                <p>No threads yet.</p>
              </div>
            <?php else: ?>
              <ul class="thread-list">
                <?php foreach ($recent_threads as $thread): ?>
                  <li>
                    <div>
                      <div class="thread-title"><?php echo htmlspecialchars($thread['title'] ?? 'Untitled'); ?></div>
                      <div class="thread-meta">by <?php echo htmlspecialchars(($thread['first_name'] ?? '') . ' ' . ($thread['last_name'] ?? '')); ?> &middot; <?php echo date('M d, Y H:i', strtotime($thread['created_at'] ?? 'now')); ?></div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="panel">
            <h2><i class="fas fa-trophy"></i> Top Categories</h2>
            <?php if (empty($top_categories)): ?>
              <div class="empty-state">
                <p>No categories yet.</p>
              </div>
            <?php else: ?>
              <?php foreach ($top_categories as $cat): ?>
                <div class="cat-row">
                  <span class="cat-name"><i class="fas fa-folder" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($cat['name']); ?></span>
                  <span class="cat-count"><?php echo (int)($cat['thread_count'] ?? 0); ?> threads</span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script src="../assets/js/main.js"></script>
</body>

</html>
