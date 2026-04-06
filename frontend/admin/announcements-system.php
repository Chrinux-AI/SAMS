<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Announcements';
$page_icon = 'bullhorn';
$full_name = $_SESSION['full_name'];

$message = '';
$message_type = '';

// Handle create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $target_role = $_POST['target_role'];
    $priority = $_POST['priority'];
    $expires_at = $_POST['expires_at'] ? $_POST['expires_at'] : null;

    $announcement_id = db()->insert('announcements', [
        'title' => $title,
        'content' => $content,
        'created_by' => $_SESSION['user_id'],
        'target_role' => $target_role,
        'priority' => $priority,
        'expires_at' => $expires_at,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    if ($announcement_id) {
        // Send notifications to all matching users
        $where = $target_role === 'all' ? '1=1' : 'role = ?';
        $params = $target_role === 'all' ? [] : [$target_role];
        $users = db()->fetchAll("SELECT id FROM users WHERE $where", $params);

        foreach ($users as $user) {
            send_notification(
                $user['id'],
                $title,
                $content,
                $priority === 'high' ? 'warning' : 'info'
            );
        }

        log_activity($_SESSION['user_id'], 'create_announcement', 'announcements', $announcement_id, "Created announcement: $title");
        try {
            AuditLogger::log('create_announcement', 'announcements', "Created announcement: {$title} (target: {$target_role}, priority: {$priority})", $_SESSION['user_id'] ?? null);
        } catch (\Throwable $e) {
        }
        $message = "Announcement created and sent to " . count($users) . " users!";
        $message_type = 'success';
    }
}

// Handle delete announcement
if (isset($_GET['delete'])) {
    $announcement_id = (int)$_GET['delete'];
    db()->delete('announcements', 'id = ?', [$announcement_id]);
    log_activity($_SESSION['user_id'], 'delete_announcement', 'announcements', $announcement_id, "Deleted announcement");
    try {
        AuditLogger::log('delete_announcement', 'announcements', "Deleted announcement #{$announcement_id}", $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }
    header('Location: announcements-system.php');
    exit;
}

// Get all announcements
$announcements = db()->fetchAll("
    SELECT a.*, u.full_name as creator_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
");

// Get statistics
$total_announcements = db()->count('announcements');
$active_announcements = db()->count('announcements', 'expires_at IS NULL OR expires_at > NOW()');
$high_priority = db()->count('announcements', 'priority = ?', ['high']);
?>
<!DOCTYPE html>
<html>

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

</head>

<body>



    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?> Management</h1>
                </div>
                <div class="header-actions">
                    <button onclick="showCreateModal()" class="cyber-btn cyber-btn-primary">
                        <i class="fas fa-plus"></i> New Announcement
                    </button>
                </div>
            </header>
            <div class="cyber-content slide-in">

                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type; ?>" style="margin-bottom:20px;">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($total_announcements); ?></h3>
                            <p>Total Announcements</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($active_announcements); ?></h3>
                            <p>Active Now</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($high_priority); ?></h3>
                            <p>High Priority</p>
                        </div>
                    </div>
                </div>

                <!-- Announcements List -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list"></i> <span>All Announcements (<?php echo count($announcements); ?>)</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <p style="text-align:center;color:var(--text-muted);padding:60px 20px;">
                                <i class="fas fa-bullhorn" style="font-size:4rem;opacity:0.3;margin-bottom:20px;display:block;"></i>
                                No announcements yet. Create your first announcement above.
                            </p>
                        <?php else: ?>
                            <div style="display:grid;gap:20px;">
                                <?php foreach ($announcements as $announcement):
                                    $is_expired = $announcement['expires_at'] && strtotime($announcement['expires_at']) < time();
                                ?>
                                    <div style="padding:25px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:12px;<?php echo $is_expired ? 'opacity:0.5;' : ''; ?>">
                                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;">
                                            <div style="flex:1;">
                                                <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;">
                                                    <h3 style="color:var(--cyber-cyan);margin:0;">
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                    </h3>
                                                    <?php if ($announcement['priority'] === 'high'): ?>
                                                        <span class="cyber-badge danger">
                                                            <i class="fas fa-exclamation-triangle"></i> HIGH PRIORITY
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($is_expired): ?>
                                                        <span class="cyber-badge" style="background:rgba(100,100,100,0.2);color:#999;">
                                                            <i class="fas fa-clock"></i> EXPIRED
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p style="color:var(--text-primary);margin-bottom:15px;line-height:1.6;">
                                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                                </p>
                                                <div style="display:flex;gap:20px;font-size:0.85rem;color:var(--text-muted);">
                                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['creator_name'] ?? 'System'); ?></span>
                                                    <span><i class="fas fa-users"></i> <?php echo ucfirst($announcement['target_role']); ?></span>
                                                    <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?></span>
                                                    <?php if ($announcement['expires_at']): ?>
                                                        <span><i class="fas fa-hourglass-end"></i> Expires: <?php echo date('M d, Y', strtotime($announcement['expires_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="margin-left:20px;">
                                                <a href="?delete=<?php echo $announcement['id']; ?>" onclick="return confirm('Delete this announcement?')" class="cyber-btn cyber-btn-outline" style="padding:8px 15px;font-size:0.85rem;border-color:var(--cyber-red);color:var(--cyber-red);">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Modal -->
    <div id="createModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:rgba(10,10,10,0.95);border:1px solid var(--cyber-cyan);border-radius:15px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;padding:30px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;">
                <h2 style="color:var(--cyber-cyan);margin:0;"><i class="fas fa-bullhorn"></i> New Announcement</h2>
                <button onclick="closeCreateModal()" style="background:none;border:none;color:var(--cyber-red);font-size:1.5rem;cursor:pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <div style="margin-bottom:20px;">
                    <label style="color:var(--cyber-cyan);font-size:0.9rem;margin-bottom:8px;display:block;">Title</label>
                    <input type="text" name="title" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="color:var(--cyber-cyan);font-size:0.9rem;margin-bottom:8px;display:block;">Content</label>
                    <textarea name="content" rows="5" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);"></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.9rem;margin-bottom:8px;display:block;">Target Audience</label>
                        <select name="target_role" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                            <option value="all">All Users</option>
                            <option value="student">Students Only</option>
                            <option value="teacher">Teachers Only</option>
                            <option value="parent">Parents Only</option>
                            <option value="admin">Admins Only</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.9rem;margin-bottom:8px;display:block;">Priority</label>
                        <select name="priority" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                            <option value="normal">Normal</option>
                            <option value="high">High Priority</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:25px;">
                    <label style="color:var(--cyber-cyan);font-size:0.9rem;margin-bottom:8px;display:block;">Expires At (Optional)</label>
                    <input type="datetime-local" name="expires_at" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                </div>

                <div style="display:flex;gap:15px;">
                    <button type="submit" name="create_announcement" class="cyber-btn cyber-btn-primary" style="flex:1;">
                        <i class="fas fa-paper-plane"></i> Create & Send
                    </button>
                    <button type="button" onclick="closeCreateModal()" class="cyber-btn cyber-btn-outline" style="flex:1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showCreateModal() {
            document.getElementById('createModal').style.display = 'flex';
        }

        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateModal();
            }
        });

        // Close modal on outside click
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
