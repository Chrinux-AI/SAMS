<?php

/**
 * Community Forum - The Quad
 * Main category listing and thread browsing
 */

require_once '../includes/session-handler.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "The Quad - Community Forum";

// Get all active categories visible to user's role
$categories_query = "
    SELECT c.*,
           COUNT(DISTINCT t.id) as thread_count,
           MAX(t.last_activity_at) as last_activity
    FROM forum_categories c
    LEFT JOIN forum_threads t ON c.id = t.category_id AND t.is_locked = 0
    WHERE c.is_active = 1
    AND (c.allowed_roles IS NULL OR FIND_IN_SET(?, c.allowed_roles) > 0)
    GROUP BY c.id
    ORDER BY c.display_order, c.name
";

$categories = db()->fetchAll($categories_query, [$user_role]);

// Get trending threads (most active in last 7 days)
$trending_query = "
    SELECT t.*, u.first_name, u.last_name, c.name as category_name, c.color as category_color
    FROM forum_threads t
    JOIN users u ON t.user_id = u.id
    JOIN forum_categories c ON t.category_id = c.id
    WHERE t.last_activity_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND c.is_active = 1
    AND (c.allowed_roles IS NULL OR FIND_IN_SET(?, c.allowed_roles) > 0)
    ORDER BY t.reply_count DESC, t.view_count DESC
    LIMIT 5
";

$trending = db()->fetchAll($trending_query, [$user_role]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <title><?php echo $page_title; ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <style>
        .trending-threads { display: flex; flex-direction: column; gap: 15px; }
        .trending-item { padding: 15px; background: var(--bg-white); border-left: 3px solid var(--primary); border-radius: 8px; transition: all 0.3s ease; }
        .trending-item:hover { background: var(--gray-50); transform: translateX(5px); }
        .thread-link { text-decoration: none; color: inherit; }
        .thread-info h4 { margin: 8px 0; color: var(--primary); font-size: 1.1rem; }
        .thread-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 0.85rem; color: var(--text-secondary); margin-top: 8px; }
        .thread-meta span { display: flex; align-items: center; gap: 5px; }
        .category-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; color: white; text-transform: uppercase; }
        .forum-categories { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .category-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .category-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .category-link { display: flex; align-items: center; gap: 20px; text-decoration: none; color: inherit; padding: 20px; }
        .category-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; color: #fff; }
        .category-info { flex: 1; }
        .category-info h3 { margin: 0 0 8px 0; color: var(--text-primary); font-size: 1.15rem; }
        .category-info p { margin: 0 0 10px 0; color: var(--text-secondary); font-size: 0.9rem; }
        .category-stats { display: flex; gap: 15px; font-size: 0.85rem; color: var(--text-secondary); }
        .category-stats span { display: flex; align-items: center; gap: 5px; }
        .category-arrow { font-size: 1.2rem; color: var(--gray-400); opacity: 0.5; transition: opacity 0.3s ease; }
        .category-card:hover .category-arrow { opacity: 1; color: var(--primary); }
        .forum-rules { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }
        .forum-rules li { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(16,185,129,0.08); border-radius: 8px; color: var(--text-primary); }
        .forum-rules li i { color: var(--success); }
        @media (max-width: 768px) { .forum-categories { grid-template-columns: 1fr; } .category-link { padding: 15px; } .category-icon { width: 50px; height: 50px; font-size: 1.3rem; } }
    </style>
</head>
<body class="cyber-bg">
    <?php include '../includes/cyber-nav.php'; ?>
    <div class="cyber-layout">
        <div class="cyber-main">
            <div class="cyber-content" style="padding: 24px;">
                <div class="content-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-primary);"><i class="fas fa-comments"></i> The Quad</h1>
                        <p style="color:var(--text-secondary);font-size:0.9rem;">Community Discussion Forum</p>
                    </div>
                    <a href="create-thread.php" class="cyber-btn"><i class="fas fa-plus"></i> New Thread</a>
                </div>

        <!-- Trending Threads -->
        <?php if (!empty($trending)): ?>
            <div class="holo-card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-fire" style="color: #f59e0b;"></i> Trending This Week
                    </div>
                </div>
                <div class="card-body">
                    <div class="trending-threads">
                        <?php foreach ($trending as $thread): ?>
                            <div class="trending-item">
                                <a href="thread.php?id=<?php echo $thread['id']; ?>" class="thread-link">
                                    <div class="thread-info">
                                        <span class="category-badge" style="background: <?php echo htmlspecialchars($thread['category_color']); ?>;">
                                            <?php echo htmlspecialchars($thread['category_name']); ?>
                                        </span>
                                        <h4><?php echo htmlspecialchars($thread['title']); ?></h4>
                                        <div class="thread-meta">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']); ?></span>
                                            <span><i class="fas fa-comments"></i> <?php echo $thread['reply_count']; ?> replies</span>
                                            <span><i class="fas fa-eye"></i> <?php echo $thread['view_count']; ?> views</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Forum Categories -->
        <div class="forum-categories">
            <?php foreach ($categories as $category): ?>
                <div class="holo-card category-card">
                    <a href="category.php?id=<?php echo $category['id']; ?>" class="category-link">
                        <div class="category-icon" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($category['color']); ?>, rgba(255,255,255,0.1));">
                            <i class="fas fa-<?php echo htmlspecialchars($category['icon']); ?>"></i>
                        </div>
                        <div class="category-info">
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            <p><?php echo htmlspecialchars($category['description']); ?></p>
                            <div class="category-stats">
                                <span><i class="fas fa-comments"></i> <?php echo $category['thread_count']; ?> threads</span>
                                <?php if ($category['last_activity']): ?>
                                    <span><i class="fas fa-clock"></i> Active <?php echo timeAgo($category['last_activity']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="category-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Forum Rules -->
        <div class="holo-card" style="margin-top: 30px;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-shield-alt"></i> Community Guidelines</div>
            </div>
            <div class="card-body">
                <ul class="forum-rules">
                    <li><i class="fas fa-check-circle"></i> Be respectful and courteous to all members</li>
                    <li><i class="fas fa-check-circle"></i> No profanity, hate speech, or bullying</li>
                    <li><i class="fas fa-check-circle"></i> Keep discussions relevant to the category</li>
                    <li><i class="fas fa-check-circle"></i> No spam or self-promotion</li>
                    <li><i class="fas fa-check-circle"></i> Report inappropriate content using the report button</li>
                    <li><i class="fas fa-check-circle"></i> Academic integrity matters - no cheating help</li>
                </ul>
                <p style="margin-top: 15px; color: var(--text-muted); font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> Violation of these guidelines may result in post removal and account restrictions.
                </p>
            </div>
        </div>
            </div>
        </div>
    </div>

    <?php
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
    ?>
</body>
</html>