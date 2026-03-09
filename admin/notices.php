<?php

/**
 * Admin Notices Management
 * Create, edit, delete, and pin notices for universal notice board
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_admin();

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $notice_id = $_POST['notice_id'] ?? null;
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $category = $_POST['category'];
        $priority = $_POST['priority'];
        $target_roles = !empty($_POST['target_roles']) ? implode(',', $_POST['target_roles']) : null;
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if ($action === 'create') {
            db()->execute(
                "INSERT INTO notices (title, content, category, priority, target_roles, is_pinned, expires_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $content, $category, $priority, $target_roles, $is_pinned, $expires_at, $admin_id]
            );
            $message = 'Notice created successfully!';
            $message_type = 'success';
            log_activity($admin_id, 'notice_created', 'notices', db()->lastInsertId(), "Created notice: $title");
        } else {
            db()->execute(
                "UPDATE notices SET title=?, content=?, category=?, priority=?, target_roles=?, is_pinned=?, expires_at=?, updated_at=NOW()
                 WHERE id=?",
                [$title, $content, $category, $priority, $target_roles, $is_pinned, $expires_at, $notice_id]
            );
            $message = 'Notice updated successfully!';
            $message_type = 'success';
            log_activity($admin_id, 'notice_updated', 'notices', $notice_id, "Updated notice: $title");
        }
    }

    if ($action === 'delete') {
        $notice_id = (int)$_POST['notice_id'];
        db()->execute("DELETE FROM notices WHERE id = ?", [$notice_id]);
        $message = 'Notice deleted successfully!';
        $message_type = 'success';
        log_activity($admin_id, 'notice_deleted', 'notices', $notice_id, "Deleted notice ID: $notice_id");
    }

    if ($action === 'archive') {
        $notice_id = (int)$_POST['notice_id'];
        db()->execute("UPDATE notices SET status='archived' WHERE id=?", [$notice_id]);
        $message = 'Notice archived successfully!';
        $message_type = 'success';
        log_activity($admin_id, 'notice_archived', 'notices', $notice_id, "Archived notice ID: $notice_id");
    }

    if ($action === 'toggle_pin') {
        $notice_id = (int)$_POST['notice_id'];
        db()->execute("UPDATE notices SET is_pinned = NOT is_pinned WHERE id=?", [$notice_id]);
        $message = 'Notice pin status updated!';
        $message_type = 'success';
    }
}

// Get statistics
$stats = [
    'active' => db()->fetchOne("SELECT COUNT(*) as count FROM notices WHERE status='active'")['count'],
    'pinned' => db()->fetchOne("SELECT COUNT(*) as count FROM notices WHERE is_pinned=1 AND status='active'")['count'],
    'urgent' => db()->fetchOne("SELECT COUNT(*) as count FROM notices WHERE priority='urgent' AND status='active'")['count'],
    'archived' => db()->fetchOne("SELECT COUNT(*) as count FROM notices WHERE status='archived'")['count']
];

// Get all notices
$filter = $_GET['filter'] ?? 'active';
$where_clause = $filter === 'archived' ? "status='archived'" : "status='active'";

$notices = db()->fetchAll("
    SELECT n.*, u.first_name, u.last_name
    FROM notices n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE $where_clause
    ORDER BY n.is_pinned DESC, n.created_at DESC
");

$unread_messages = db()->fetchOne(
    "SELECT COUNT(*) as count FROM message_recipients WHERE recipient_id = ? AND is_read = 0",
    [$admin_id]
)['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notices - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    
</head>
<body>
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-bullhorn"></i></div>
                    <h1 class="page-title">Notice Board Management</h1>
                </div>
                <button onclick="showNoticeModal()" class="cyber-btn">
                    <i class="fas fa-plus"></i> Create Notice
                </button>
            </header>

            <div class="cyber-content slide-in">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 20px;">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-orb"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Active Notices</div>
                            <div class="stat-value"><?php echo $stats['active']; ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-yellow);"><i class="fas fa-thumbtack"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Pinned</div>
                            <div class="stat-value"><?php echo $stats['pinned']; ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-red);"><i class="fas fa-bolt"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Urgent</div>
                            <div class="stat-value"><?php echo $stats['urgent']; ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-purple);"><i class="fas fa-archive"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Archived</div>
                            <div class="stat-value"><?php echo $stats['archived']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div style="margin-bottom: 20px;">
                    <a href="?filter=active" class="cyber-btn <?php echo $filter === 'active' ? '' : 'cyber-btn-outline'; ?>">
                        <i class="fas fa-check-circle"></i> Active (<?php echo $stats['active']; ?>)
                    </a>
                    <a href="?filter=archived" class="cyber-btn <?php echo $filter === 'archived' ? '' : 'cyber-btn-outline'; ?>">
                        <i class="fas fa-archive"></i> Archived (<?php echo $stats['archived']; ?>)
                    </a>
                </div>

                <!-- Notices List -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list"></i> <?php echo ucfirst($filter); ?> Notices
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notices)): ?>
                            <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size:4rem;margin-bottom:20px;opacity:0.3;"></i>
                                <p>No notices found. Create your first notice to get started!</p>
                            </div>
                        <?php else: ?>
                            <div class="cyber-table-container">
                                <table class="cyber-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Priority</th>
                                            <th>Target</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Expires</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notices as $notice): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($notice['title']); ?></strong>
                                                    <?php if ($notice['is_pinned']): ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-yellow);margin-left:5px;">
                                                            <i class="fas fa-thumbtack"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                    <div style="font-size:0.85rem;color:var(--text-muted);margin-top:5px;">
                                                        <?php echo mb_substr(strip_tags($notice['content']), 0, 100) . '...'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="cyber-badge" style="background:rgba(138,43,226,0.2);">
                                                        <?php echo ucfirst($notice['category']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $priority_colors = [
                                                        'normal' => 'var(--cyber-green)',
                                                        'high' => 'var(--cyber-yellow)',
                                                        'urgent' => 'var(--cyber-red)'
                                                    ];
                                                    ?>
                                                    <span class="cyber-badge" style="background:<?php echo $priority_colors[$notice['priority']]; ?>;">
                                                        <?php echo ucfirst($notice['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($notice['target_roles']): ?>
                                                        <?php echo str_replace(',', ', ', ucwords($notice['target_roles'])); ?>
                                                    <?php else: ?>
                                                        <span style="color:var(--cyber-cyan);">All Roles</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="cyber-badge" style="background:<?php echo $notice['status'] === 'active' ? 'var(--cyber-green)' : 'var(--cyber-purple)'; ?>;">
                                                        <?php echo ucfirst($notice['status']); ?>
                                                    </span>
                                                </td>
                                                <td style="font-size:0.85rem;">
                                                    <?php echo date('M d, Y', strtotime($notice['created_at'])); ?>
                                                    <div style="color:var(--text-muted);font-size:0.75rem;">
                                                        by <?php echo htmlspecialchars($notice['first_name'] . ' ' . $notice['last_name']); ?>
                                                    </div>
                                                </td>
                                                <td style="font-size:0.85rem;">
                                                    <?php if ($notice['expires_at']): ?>
                                                        <?php echo date('M d, Y', strtotime($notice['expires_at'])); ?>
                                                    <?php else: ?>
                                                        <span style="color:var(--text-muted);">No expiry</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display:flex;gap:5px;">
                                                        <button onclick="editNotice(<?php echo htmlspecialchars(json_encode($notice)); ?>)"
                                                            class="cyber-btn cyber-btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle pin status?');">
                                                            <input type="hidden" name="action" value="toggle_pin">
                                                            <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                                            <button type="submit" class="cyber-btn cyber-btn-sm" title="Toggle Pin">
                                                                <i class="fas fa-thumbtack"></i>
                                                            </button>
                                                        </form>
                                                        <?php if ($notice['status'] === 'active'): ?>
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this notice?');">
                                                                <input type="hidden" name="action" value="archive">
                                                                <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                                                <button type="submit" class="cyber-btn cyber-btn-sm cyber-btn-outline" title="Archive">
                                                                    <i class="fas fa-archive"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this notice?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                                            <button type="submit" class="cyber-btn cyber-btn-sm" style="background:var(--cyber-red);" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Notice Modal -->
    <div id="noticeModal" class="cyber-modal">
        <div class="cyber-modal-content" style="max-width: 700px;">
            <div class="cyber-modal-header">
                <h2 id="modalTitle">Create Notice</h2>
                <button onclick="closeNoticeModal()" class="cyber-modal-close">&times;</button>
            </div>
            <form method="POST" id="noticeForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="notice_id" id="noticeId">

                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="noticeTitle" class="cyber-input" required maxlength="255">
                </div>

                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" id="noticeContent" class="cyber-input" rows="6" required></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" id="noticeCategory" class="cyber-input" required>
                            <option value="general">General</option>
                            <option value="academic">Academic</option>
                            <option value="sports">Sports</option>
                            <option value="event">Event</option>
                            <option value="emergency">Emergency</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Priority *</label>
                        <select name="priority" id="noticePriority" class="cyber-input" required>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Target Roles (leave empty for all)</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:10px;">
                        <label style="display:flex;align-items:center;gap:5px;">
                            <input type="checkbox" name="target_roles[]" value="admin">
                            <span>Admin</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;">
                            <input type="checkbox" name="target_roles[]" value="teacher">
                            <span>Teacher</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;">
                            <input type="checkbox" name="target_roles[]" value="student">
                            <span>Student</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:5px;">
                            <input type="checkbox" name="target_roles[]" value="parent">
                            <span>Parent</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Expiration Date (optional)</label>
                    <input type="datetime-local" name="expires_at" id="noticeExpires" class="cyber-input">
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" name="is_pinned" id="noticePinned">
                        <span>📌 Pin this notice (appears at top)</span>
                    </label>
                </div>

                <div class="cyber-modal-footer">
                    <button type="button" onclick="closeNoticeModal()" class="cyber-btn cyber-btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-save"></i> <span id="submitBtnText">Create Notice</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showNoticeModal() {
            document.getElementById('noticeModal').style.display = 'flex';
            document.getElementById('noticeForm').reset();
            document.getElementById('formAction').value = 'create';
            document.getElementById('modalTitle').textContent = 'Create Notice';
            document.getElementById('submitBtnText').textContent = 'Create Notice';
        }

        function closeNoticeModal() {
            document.getElementById('noticeModal').style.display = 'none';
        }

        function editNotice(notice) {
            document.getElementById('noticeModal').style.display = 'flex';
            document.getElementById('formAction').value = 'update';
            document.getElementById('noticeId').value = notice.id;
            document.getElementById('modalTitle').textContent = 'Edit Notice';
            document.getElementById('submitBtnText').textContent = 'Update Notice';

            document.getElementById('noticeTitle').value = notice.title;
            document.getElementById('noticeContent').value = notice.content;
            document.getElementById('noticeCategory').value = notice.category;
            document.getElementById('noticePriority').value = notice.priority;
            document.getElementById('noticePinned').checked = notice.is_pinned == 1;

            if (notice.expires_at) {
                document.getElementById('noticeExpires').value = notice.expires_at.replace(' ', 'T');
            }

            // Clear and set target roles
            document.querySelectorAll('input[name="target_roles[]"]').forEach(cb => cb.checked = false);
            if (notice.target_roles) {
                notice.target_roles.split(',').forEach(role => {
                    const checkbox = document.querySelector(`input[value="${role.trim()}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('noticeModal');
            if (event.target === modal) {
                closeNoticeModal();
            }
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>