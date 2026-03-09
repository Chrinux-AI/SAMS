<?php

/**
 * View Forum Category and Threads
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$category_id = $_GET['id'] ?? 0;

// Get category info
$category = db()->fetchOne("
    SELECT * FROM forum_categories
    WHERE id = ?
    AND is_active = 1
    AND (allowed_roles IS NULL OR FIND_IN_SET(?, allowed_roles) > 0)
", [$category_id, $user_role]);

if (!$category) {
    $_SESSION['error_message'] = "Category not found or access denied";
    header('Location: index.php');
    exit;
}

$page_title = $category['name'];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get threads in category
$threads = db()->fetchAll("
    SELECT t.*, u.first_name, u.last_name, u.role,
           (SELECT COUNT(*) FROM forum_posts WHERE thread_id = t.id) as post_count,
           (SELECT CONCAT(u2.first_name, ' ', u2.last_name)
            FROM forum_posts p2
            JOIN users u2 ON p2.user_id = u2.id
            WHERE p2.thread_id = t.id
            ORDER BY p2.created_at DESC LIMIT 1) as last_poster
    FROM forum_threads t
    JOIN users u ON t.user_id = u.id
    WHERE t.category_id = ?
    ORDER BY t.is_pinned DESC, t.last_activity_at DESC
    LIMIT ? OFFSET ?
", [$category_id, $per_page, $offset]);

// Get total count for pagination
$total_threads = db()->fetchOne("SELECT COUNT(*) as count FROM forum_threads WHERE category_id = ?", [$category_id])['count'];
$total_pages = ceil($total_threads / $per_page);
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
    <title><?php echo htmlspecialchars($category['name']); ?> - Forum - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <style>
        .threads-list {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .thread-item {
            display: grid;
            grid-template-columns: 40px 1fr 120px 150px;
            gap: 20px;
            padding: 20px;
            background: rgba(0, 243, 255, 0.03);
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            align-items: center;
        }

        .thread-item:hover {
            background: rgba(0, 243, 255, 0.08);
            border-left-color: var(--cyber-cyan);
        }

        .thread-item.pinned {
            background: rgba(245, 158, 11, 0.05);
            border-left-color: var(--warning-color);
        }

        .thread-status {
            text-align: center;
            font-size: 1.5rem;
        }

        .thread-title {
            color: var(--cyber-cyan);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            display: block;
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        .thread-title:hover {
            color: var(--cyber-purple);
        }

        .thread-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .thread-stats {
            display: flex;
            gap: 20px;
        }

        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .stat i {
            color: var(--cyber-cyan);
        }

        .stat span {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-color);
        }

        .stat small {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .thread-activity {
            text-align: center;
            font-size: 0.85rem;
        }

        .thread-activity small {
            display: block;
            color: var(--text-muted);
        }

        .thread-activity strong {
            display: block;
            color: var(--cyber-cyan);
            margin: 3px 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state h3 {
            margin: 20px 0 10px;
            color: var(--text-color);
        }

        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 243, 255, 0.2);
        }

        .page-link {
            padding: 8px 16px;
            background: rgba(0, 243, 255, 0.1);
            border: 1px solid var(--cyber-cyan);
            color: var(--cyber-cyan);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: var(--cyber-cyan);
            color: var(--bg-dark);
        }

        .page-info {
            color: var(--text-muted);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .thread-item {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .thread-status {
                display: none;
            }

            .thread-stats,
            .thread-activity {
                justify-self: start;
            }
        }
    </style>
</head>
<body class="cyber-bg">
<?php include '../includes/cyber-nav.php'; ?>
<div class="cyber-layout">
<div class="cyber-main">
<div class="cyber-content" style="padding:24px;">
        <div class="content-header">
            <div>
                <div style="margin-bottom: 10px;">
                    <a href="index.php" style="color: var(--text-muted); text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Forum
                    </a>
                </div>
                <h1>
                    <i class="fas fa-<?php echo htmlspecialchars($category['icon']); ?>" style="color: <?php echo htmlspecialchars($category['color']); ?>;"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </h1>
                <p class="subtitle"><?php echo htmlspecialchars($category['description']); ?></p>
            </div>
            <a href="create-thread.php?category=<?php echo $category_id; ?>" class="cyber-btn">
                <i class="fas fa-plus"></i> New Thread
            </a>
        </div>

        <div class="holo-card">
            <div class="card-body">
                <?php if (empty($threads)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments" style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
                        <h3>No threads yet</h3>
                        <p>Be the first to start a discussion in this category!</p>
                        <a href="create-thread.php?category=<?php echo $category_id; ?>" class="cyber-btn">
                            <i class="fas fa-plus"></i> Create Thread
                        </a>
                    </div>
                <?php else: ?>
                    <div class="threads-list">
                        <?php foreach ($threads as $thread): ?>
                            <div class="thread-item <?php echo $thread['is_pinned'] ? 'pinned' : ''; ?>">
                                <div class="thread-status">
                                    <?php if ($thread['is_pinned']): ?>
                                        <i class="fas fa-thumbtack" style="color: var(--warning-color);" title="Pinned"></i>
                                    <?php endif; ?>
                                    <?php if ($thread['is_locked']): ?>
                                        <i class="fas fa-lock" style="color: var(--text-muted);" title="Locked"></i>
                                    <?php else: ?>
                                        <i class="fas fa-comment" style="color: var(--cyber-cyan);"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="thread-content">
                                    <a href="thread.php?id=<?php echo $thread['id']; ?>" class="thread-title">
                                        <?php echo htmlspecialchars($thread['title']); ?>
                                    </a>
                                    <div class="thread-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($thread['created_at'])); ?></span>
                                    </div>
                                </div>

                                <div class="thread-stats">
                                    <div class="stat">
                                        <i class="fas fa-eye"></i>
                                        <span><?php echo $thread['view_count']; ?></span>
                                        <small>views</small>
                                    </div>
                                    <div class="stat">
                                        <i class="fas fa-comments"></i>
                                        <span><?php echo $thread['reply_count']; ?></span>
                                        <small>replies</small>
                                    </div>
                                </div>

                                <div class="thread-activity">
                                    <?php if ($thread['last_poster']): ?>
                                        <small>Last reply by</small>
                                        <strong><?php echo htmlspecialchars($thread['last_poster']); ?></strong>
                                        <small><?php echo timeAgo($thread['last_activity_at']); ?></small>
                                    <?php else: ?>
                                        <small>No replies yet</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

                            <?php if ($page < $total_pages): ?>
                                <a href="?id=<?php echo $category_id; ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php
    function timeAgo($datetime)
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
    ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
    </body>

</html>
