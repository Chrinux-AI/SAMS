<?php

/**
 * View Thread and Replies
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
$thread_id = $_GET['id'] ?? 0;

// Get thread details
$thread = db()->fetchOne("
    SELECT t.*, u.first_name, u.last_name, u.role, c.name as category_name, c.id as category_id
    FROM forum_threads t
    JOIN users u ON t.user_id = u.id
    JOIN forum_categories c ON t.category_id = c.id
    WHERE t.id = ?
", [$thread_id]);

if (!$thread) {
    $_SESSION['error_message'] = "Thread not found";
    header('Location: index.php');
    exit;
}

// Increment view count
db()->query("UPDATE forum_threads SET view_count = view_count + 1 WHERE id = ?", [$thread_id]);

$page_title = $thread['title'];

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$thread['is_locked']) {
    $content = trim($_POST['content'] ?? '');
    $errors = [];

    if (empty($content)) {
        $errors[] = "Reply content is required";
    } elseif (strlen($content) < 5) {
        $errors[] = "Reply must be at least 5 characters";
    }

    // Profanity filter
    $profanity_words = ['damn', 'hell', 'crap', 'stupid', 'idiot', 'dumb'];
    if (!empty($content)) {
        foreach ($profanity_words as $word) {
            if (stripos($content, $word) !== false) {
                $errors[] = "Please keep language appropriate";
                break;
            }
        }
    }

    if (empty($errors)) {
        try {
            db()->insert('forum_posts', [
                'thread_id' => $thread_id,
                'user_id' => $user_id,
                'content' => $content
            ]);

            db()->query("
                UPDATE forum_threads
                SET reply_count = reply_count + 1, last_activity_at = NOW()
                WHERE id = ?
            ", [$thread_id]);

            $_SESSION['success_message'] = "Reply posted successfully!";
            header("Location: thread.php?id=" . $thread_id);
            exit;
        } catch (Exception $e) {
            $errors[] = "Failed to post reply";
        }
    }
}

// Get posts/replies
$posts = db()->fetchAll("
    SELECT p.*, u.first_name, u.last_name, u.role
    FROM forum_posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.thread_id = ?
    ORDER BY p.created_at ASC
", [$thread_id]);

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
    .thread-badges {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .badge {
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning-color);
    }

    .badge-secondary {
        background: rgba(107, 114, 128, 0.2);
        color: var(--text-muted);
    }

    .badge-info {
        background: rgba(0, 243, 255, 0.2);
        color: var(--cyber-cyan);
    }

    .thread-post {
        margin-bottom: 20px;
    }

    .original-post {
        border-left: 4px solid var(--cyber-cyan);
    }

    .post-author {
        display: flex;
        gap: 15px;
        align-items: center;
        padding: 20px;
        background: rgba(0, 243, 255, 0.05);
        border-radius: 8px 8px 0 0;
    }

    .author-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        color: white;
    }

    .author-name {
        font-weight: 700;
        color: var(--cyber-cyan);
        font-size: 1.1rem;
    }

    .author-role {
        color: var(--text-muted);
        font-size: 0.85rem;
        text-transform: capitalize;
    }

    .post-date {
        color: var(--text-muted);
        font-size: 0.8rem;
        margin-top: 3px;
    }

    .post-content {
        padding: 25px;
        line-height: 1.8;
        color: var(--text-color);
        min-height: 80px;
    }

    .post-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 25px;
        background: rgba(0, 243, 255, 0.03);
        border-radius: 0 0 8px 8px;
    }

    .post-stats {
        display: flex;
        gap: 20px;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .post-stats span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .action-btn {
        background: transparent;
        border: 1px solid rgba(0, 243, 255, 0.3);
        color: var(--cyber-cyan);
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }

    .action-btn:hover {
        background: rgba(0, 243, 255, 0.1);
        border-color: var(--cyber-cyan);
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid var(--danger-color);
        color: var(--danger-color);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border-left: 4px solid var(--warning-color);
        color: var(--warning-color);
        padding: 15px;
        border-radius: 8px;
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
                <a href="category.php?id=<?php echo $thread['category_id']; ?>" style="color: var(--text-muted); text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to <?php echo htmlspecialchars($thread['category_name']); ?>
                </a>
            </div>
            <h1><?php echo htmlspecialchars($thread['title']); ?></h1>
            <div class="thread-badges">
                <?php if ($thread['is_pinned']): ?>
                    <span class="badge badge-warning"><i class="fas fa-thumbtack"></i> Pinned</span>
                <?php endif; ?>
                <?php if ($thread['is_locked']): ?>
                    <span class="badge badge-secondary"><i class="fas fa-lock"></i> Locked</span>
                <?php endif; ?>
                <span class="badge badge-info"><?php echo htmlspecialchars($thread['category_name']); ?></span>
            </div>
        </div>
    </div>

    <!-- Original Post -->
    <div class="holo-card thread-post original-post">
        <div class="post-author">
            <div class="author-avatar">
                <?php echo strtoupper(substr($thread['first_name'], 0, 1) . substr($thread['last_name'], 0, 1)); ?>
            </div>
            <div class="author-info">
                <div class="author-name"><?php echo htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']); ?></div>
                <div class="author-role"><?php echo ucfirst($thread['role']); ?></div>
                <div class="post-date"><?php echo date('M d, Y \a\t H:i', strtotime($thread['created_at'])); ?></div>
            </div>
        </div>
        <div class="post-content">
            <?php echo nl2br(htmlspecialchars($thread['content'])); ?>
        </div>
        <div class="post-actions">
            <div class="post-stats">
                <span><i class="fas fa-eye"></i> <?php echo $thread['view_count']; ?> views</span>
                <span><i class="fas fa-comments"></i> <?php echo $thread['reply_count']; ?> replies</span>
            </div>
            <?php if ($user_role === 'admin' || $thread['user_id'] == $user_id): ?>
                <button class="action-btn" onclick="reportContent('thread', <?php echo $thread_id; ?>)">
                    <i class="fas fa-flag"></i> Report
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Replies -->
    <?php if (!empty($posts)): ?>
        <h3 style="margin: 30px 0 20px; color: var(--cyber-cyan);">
            <i class="fas fa-comments"></i> Replies (<?php echo count($posts); ?>)
        </h3>
        <?php foreach ($posts as $post): ?>
            <div class="holo-card thread-post">
                <div class="post-author">
                    <div class="author-avatar">
                        <?php echo strtoupper(substr($post['first_name'], 0, 1) . substr($post['last_name'], 0, 1)); ?>
                    </div>
                    <div class="author-info">
                        <div class="author-name"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></div>
                        <div class="author-role"><?php echo ucfirst($post['role']); ?></div>
                        <div class="post-date"><?php echo date('M d, Y \a\t H:i', strtotime($post['created_at'])); ?></div>
                    </div>
                </div>
                <div class="post-content">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                </div>
                <div class="post-actions">
                    <button class="action-btn" onclick="reportContent('post', <?php echo $post['id']; ?>)">
                        <i class="fas fa-flag"></i> Report
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Reply Form -->
    <?php if (!$thread['is_locked']): ?>
        <div class="holo-card" style="margin-top: 30px;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-reply"></i> Post a Reply</div>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <textarea name="content"
                            class="cyber-input"
                            rows="6"
                            placeholder="Write your reply..."
                            required></textarea>
                    </div>
                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-paper-plane"></i> Post Reply
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning" style="margin-top: 20px;">
            <i class="fas fa-lock"></i> This thread is locked. No new replies can be posted.
        </div>
    <?php endif; ?>
</div>
</div>
</div>

<script>
    function reportContent(type, id) {
        const reason = prompt('Please provide a reason for reporting this ' + type + ':');
        if (reason && reason.trim()) {
            fetch('../api/forum-report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: type,
                        id: id,
                        reason: reason
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Report submitted successfully. Moderators will review it.');
                    } else {
                        alert('Failed to submit report: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(e => alert('Error submitting report'));
        }
    }
</script>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
</body>
</html>
