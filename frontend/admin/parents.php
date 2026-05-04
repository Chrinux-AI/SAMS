<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

// Handle parent operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['link_child'])) {
        $parent_id = (int)$_POST['parent_id'];
        $student_id = (int)$_POST['student_id'];

        // Update student's parent_id
        db()->query("UPDATE students SET parent_id = ? WHERE id = ?", [$parent_id, $student_id]);
        log_activity($_SESSION['user_id'], 'link_child', 'parents', $parent_id);
        $message = 'Child linked successfully!';
        $message_type = 'success';
    } elseif (isset($_POST['unlink_child'])) {
        $student_id = (int)$_POST['student_id'];

        // Remove parent link
        db()->query("UPDATE students SET parent_id = NULL WHERE id = ?", [$student_id]);
        log_activity($_SESSION['user_id'], 'unlink_child', 'students', $student_id);
        $message = 'Child unlinked successfully!';
        $message_type = 'success';
    } elseif (isset($_POST['delete_parent'])) {
        $parent_id = (int)$_POST['parent_id'];
        $user_id = (int)$_POST['user_id'];

        // First unlink all children
        db()->query("UPDATE students SET parent_id = NULL WHERE parent_id = ?", [$parent_id]);

        // Delete parent record
        db()->delete('parents', 'id = ?', [$parent_id]);

        // Delete user account
        db()->delete('users', 'id = ?', [$user_id]);

        log_activity($_SESSION['user_id'], 'delete', 'parents', $parent_id);
        $message = 'Parent account deleted successfully!';
        $message_type = 'success';
    } elseif (isset($_POST['update_parent_info'])) {
        $parent_id = (int)$_POST['parent_id'];
        $user_id = (int)$_POST['user_id'];

        // Update user info
        db()->query(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?",
            [sanitize($_POST['first_name']), sanitize($_POST['last_name']), sanitize($_POST['email']), sanitize($_POST['phone']), $user_id]
        );

        // Update parent info
        db()->query(
            "UPDATE parents SET address = ? WHERE id = ?",
            [sanitize($_POST['address']), $parent_id]
        );

        log_activity($_SESSION['user_id'], 'update', 'parents', $parent_id);
        $message = 'Parent information updated successfully!';
        $message_type = 'success';
    }
}

// Get all parents with their children count and details
$parents = db()->fetchAll("
    SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.status, u.approved, u.id as user_id,
           COUNT(DISTINCT s.id) as children_count,
           GROUP_CONCAT(DISTINCT CONCAT(su.first_name, ' ', su.last_name) SEPARATOR ', ') as children_names
    FROM parents p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN students s ON p.id = s.parent_id
    LEFT JOIN users su ON s.user_id = su.id
    GROUP BY p.id
    ORDER BY u.first_name, u.last_name
");

// Get all students without parents for linking
$unlinked_students = db()->fetchAll("
    SELECT s.id, s.admission_number AS student_id, u.first_name, u.last_name, u.email
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.parent_id IS NULL AND u.status = 'active'
    ORDER BY u.first_name, u.last_name
");

$total_parents = count($parents);
$total_linked_children = array_sum(array_column($parents, 'children_count'));
$total_unlinked = count($unlinked_students);

$page_title = 'Parents Management';
$page_icon = 'users';
$full_name = $_SESSION['full_name'];
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
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


    <style>
        .parent-card {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.05), rgba(255, 0, 255, 0.05));
            border: 1px solid rgba(138, 43, 226, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .parent-card:hover {
            border-color: var(--hologram-purple);
            box-shadow: 0 0 30px rgba(138, 43, 226, 0.3);
            transform: translateY(-5px);
        }

        .parent-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .parent-info {
            flex: 1;
        }

        .parent-name {
            font-size: 1.5rem;
            color: var(--hologram-purple);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .parent-email {
            color: rgba(138, 43, 226, 0.6);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .parent-phone {
            color: var(--cyber-cyan);
            font-size: 0.9rem;
        }

        .children-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .child-tag {
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid var(--cyber-cyan);
            border-radius: 8px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .child-name {
            color: var(--cyber-cyan);
            font-weight: 600;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: rgba(0, 255, 127, 0.2);
            color: var(--neon-green);
            border: 1px solid var(--neon-green);
        }

        .status-badge.inactive {
            background: rgba(255, 0, 0, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }

        .search-box {
            width: 100%;
            padding: 15px;
            background: rgba(138, 43, 226, 0.05);
            border: 1px solid var(--hologram-purple);
            border-radius: 12px;
            color: var(--hologram-purple);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>



    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">
                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_parents; ?></div>
                            <div class="stat-label">Total Parents</div>
                            <div class="stat-trend up"><i class="fas fa-user-check"></i><span>Registered</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-child"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_linked_children; ?></div>
                            <div class="stat-label">Linked Children</div>
                            <div class="stat-trend up"><i class="fas fa-link"></i><span>Connected</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon orange"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_unlinked; ?></div>
                            <div class="stat-label">Unlinked Students</div>
                            <div class="stat-trend down"><i class="fas fa-unlink"></i><span>Need Parent</span></div>
                        </div>
                    </div>
                </section>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-search"></i> <span>Search Parents</span></div>
                    </div>
                    <div class="card-body">
                        <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search by name, email, phone...">
                    </div>
                </div>

                <div id="parentsContainer">
                    <?php foreach ($parents as $parent): ?>
                        <div class="parent-card" data-search="<?php echo strtolower($parent['first_name'] . ' ' . $parent['last_name'] . ' ' . $parent['email'] . ' ' . $parent['phone'] . ' ' . ($parent['children_names'] ?? '')); ?>">
                            <div class="parent-header">
                                <div class="parent-info">
                                    <div class="parent-name">
                                        <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                        <span class="status-badge <?php echo $parent['status']; ?>"><?php echo $parent['status']; ?></span>
                                    </div>
                                    <div class="parent-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($parent['email']); ?></div>
                                    <div class="parent-phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($parent['phone'] ?? 'No phone'); ?></div>
                                    <?php if (!empty($parent['address'])): ?>
                                        <div style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin-top:5px;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($parent['address']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <button onclick="linkChild(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>')" class="cyber-btn primary" style="padding:8px 15px;">
                                        <i class="fas fa-link"></i> Link Child
                                    </button>
                                    <button onclick="editParent(<?php echo $parent['id']; ?>)" class="cyber-btn" style="padding:8px 15px;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this parent account? All linked children will be unlinked.');">
                                        <input type="hidden" name="parent_id" value="<?php echo $parent['id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $parent['user_id']; ?>">
                                        <button type="submit" name="delete_parent" class="cyber-btn danger" style="padding:8px 15px;"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>

                            <div style="margin-top:15px;padding-top:15px;border-top:1px solid rgba(138,43,226,0.2);">
                                <div style="color:var(--hologram-purple);font-weight:600;margin-bottom:10px;">
                                    <i class="fas fa-child"></i> Linked Children (<?php echo $parent['children_count']; ?>)
                                </div>
                                <?php if ($parent['children_count'] > 0): ?>
                                    <div class="children-list">
                                        <?php
                                        // Get detailed children info
                                        $children = db()->fetchAll("
                                            SELECT s.id, s.admission_number AS student_id, u.first_name, u.last_name, c.grade_level AS grade
                                            FROM students s
                                            JOIN users u ON s.user_id = u.id
                                            LEFT JOIN classes c ON s.class_id = c.id
                                            WHERE s.parent_id = ?
                                        ", [$parent['id']]);
                                        foreach ($children as $child):
                                        ?>
                                            <div class="child-tag">
                                                <div>
                                                    <div class="child-name"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></div>
                                                    <div style="color:rgba(255,255,255,0.5);font-size:0.75rem;"><?php echo htmlspecialchars($child['student_id']); ?> • Grade <?php echo $child['grade'] ?? 'N/A'; ?></div>
                                                </div>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Unlink this child?');">
                                                    <input type="hidden" name="student_id" value="<?php echo $child['id']; ?>">
                                                    <button type="submit" name="unlink_child" class="cyber-btn danger" style="padding:4px 8px;font-size:0.75rem;">
                                                        <i class="fas fa-unlink"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="color:rgba(255,255,255,0.4);font-style:italic;padding:10px;">No children linked yet</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Link Child Modal -->
    <div id="linkChildModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:80vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-link"></i> <span>Link Child to Parent</span></div>
                <button onclick="document.getElementById('linkChildModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <div id="parentNameDisplay" style="color:var(--hologram-purple);font-size:1.2rem;margin-bottom:20px;"></div>
                <form method="POST">
                    <input type="hidden" name="parent_id" id="linkParentId">
                    <label style="color:var(--cyber-cyan);font-size:0.9rem;font-weight:600;margin-bottom:10px;display:block;">SELECT STUDENT</label>
                    <select name="student_id" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;margin-bottom:20px;">
                        <option value="">Choose a student...</option>
                        <?php foreach ($unlinked_students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" onclick="document.getElementById('linkChildModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="link_child" class="cyber-btn primary"><i class="fas fa-link"></i> Link Child</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('searchBox').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.parent-card').forEach(card => {
                const text = card.getAttribute('data-search');
                card.style.display = text.includes(search) ? 'block' : 'none';
            });
        });

        function linkChild(parentId, parentName) {
            document.getElementById('linkParentId').value = parentId;
            document.getElementById('parentNameDisplay').textContent = 'Parent: ' + parentName;
            document.getElementById('linkChildModal').style.display = 'flex';
        }

        function editParent(parentId) {
            alert('Edit parent functionality - to be implemented');
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>