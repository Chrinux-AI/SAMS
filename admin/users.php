<?php

/**
 * Enhanced Users Management Page
 * Complete CRUD operations with filtering and bulk actions
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

// Get filter parameters
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$tenant_filter_sql = '';
$tenant_filter_params = [];

if (table_exists('tenant_users')) {
    $tenant_filter_sql = 'id IN (SELECT user_id FROM tenant_users WHERE tenant_id = :tenant_id AND is_active = 1)';
    $tenant_filter_params['tenant_id'] = current_tenant_id();
    $where_conditions[] = $tenant_filter_sql;
}

if ($filter_role !== 'all') {
    $where_conditions[] = 'role = :role';
    $params['role'] = $filter_role;
}

if ($filter_status !== 'all') {
    $where_conditions[] = 'status = :status';
    $params['status'] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = '(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)';
    $params['search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with filters
$users = db()->fetchAll("
    SELECT * FROM users
    $where_clause
    ORDER BY created_at DESC
", array_merge($tenant_filter_params, $params));

// Get counts for badges
$count_where_prefix = $tenant_filter_sql !== '' ? $tenant_filter_sql . ' AND ' : '';
$count_params = $tenant_filter_params;

$total_users = db()->count('users', $tenant_filter_sql !== '' ? $tenant_filter_sql : '1=1', $count_params);
$active_users = db()->count('users', $count_where_prefix . 'status = :status', array_merge($count_params, ['status' => 'active']));
$pending_users = db()->count('users', $count_where_prefix . 'status = :status', array_merge($count_params, ['status' => 'pending']));
$admins = db()->count('users', $count_where_prefix . 'role = :role', array_merge($count_params, ['role' => 'admin']));
$teachers = db()->count('users', $count_where_prefix . 'role = :role', array_merge($count_params, ['role' => 'teacher']));
$students = db()->count('users', $count_where_prefix . 'role = :role', array_merge($count_params, ['role' => 'student']));

$page_title = 'Users Management';
$page_icon = 'users-cog';
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
    

    <style>
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--cyber-cyan);
        }

        .filter-select {
            padding: 12px 15px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-primary);
            cursor: pointer;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--cyber-cyan);
        }

        .bulk-actions {
            display: none;
            gap: 10px;
            padding: 15px;
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid var(--golden-pulse);
            border-radius: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .bulk-actions.active {
            display: flex;
        }

        .user-row {
            display: grid;
            grid-template-columns: 40px 1fr 200px 120px 120px 150px 180px;
            gap: 15px;
            align-items: center;
            padding: 15px;
            background: rgba(0, 191, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .user-row:hover {
            background: rgba(0, 191, 255, 0.08);
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.2);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.9rem;
        }

        .user-info {
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 0.85rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-badge.active {
            background: rgba(0, 255, 127, 0.2);
            color: var(--neon-green);
        }

        .status-badge.pending {
            background: rgba(255, 215, 0, 0.2);
            color: var(--golden-pulse);
        }

        .status-badge.inactive {
            background: rgba(128, 128, 128, 0.2);
            color: #999;
        }

        .stats-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .stat-pill {
            padding: 10px 20px;
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .stat-pill:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.3);
        }

        .stat-pill i {
            font-size: 1.2rem;
        }

        .stat-number {
            font-weight: 700;
            font-size: 1.1rem;
            font-family: 'Orbitron', sans-serif;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .delete-modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(10, 10, 30, 0.95), rgba(20, 20, 50, 0.95));
            border: 2px solid var(--cyber-red);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 0 50px rgba(255, 69, 0, 0.5);
        }

        .modal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-red), var(--golden-pulse));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
    </style>
</head>
<body>
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb">
                        <i class="fas fa-<?php echo $page_icon; ?>"></i>
                    </div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>

                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan">
                        <i class="fas fa-fingerprint"></i>
                    </div>

                    <div class="user-card" style="padding: 8px 15px; margin: 0;">
                        <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size: 0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <?php if ($message): ?>
                    <div class="cyber-alert cyber-alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 20px;">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Statistics Bar -->
                <div class="stats-bar">
                    <div class="stat-pill" style="border-color: var(--cyber-cyan);">
                        <i class="fas fa-users" style="color: var(--cyber-cyan);"></i>
                        <div>
                            <div class="stat-number" style="color: var(--cyber-cyan);"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>

                    <div class="stat-pill" style="border-color: var(--neon-green);">
                        <i class="fas fa-check-circle" style="color: var(--neon-green);"></i>
                        <div>
                            <div class="stat-number" style="color: var(--neon-green);"><?php echo $active_users; ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>

                    <?php if ($pending_users > 0): ?>
                        <div class="stat-pill" style="border-color: var(--golden-pulse);">
                            <i class="fas fa-clock" style="color: var(--golden-pulse);"></i>
                            <div>
                                <div class="stat-number" style="color: var(--golden-pulse);"><?php echo $pending_users; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="stat-pill" style="border-color: var(--hologram-purple);">
                        <i class="fas fa-user-shield" style="color: var(--hologram-purple);"></i>
                        <div>
                            <div class="stat-number" style="color: var(--hologram-purple);"><?php echo $admins; ?></div>
                            <div class="stat-label">Admins</div>
                        </div>
                    </div>

                    <div class="stat-pill">
                        <i class="fas fa-chalkboard-teacher" style="color: var(--cyber-cyan);"></i>
                        <div>
                            <div class="stat-number"><?php echo $teachers; ?></div>
                            <div class="stat-label">Teachers</div>
                        </div>
                    </div>

                    <div class="stat-pill">
                        <i class="fas fa-user-graduate" style="color: var(--neon-green);"></i>
                        <div>
                            <div class="stat-number"><?php echo $students; ?></div>
                            <div class="stat-label">Students</div>
                        </div>
                    </div>
                </div>

                <!-- Filter and Search Bar -->
                <div class="holo-card" style="margin-bottom: 20px;">
                    <form method="GET" class="filter-bar">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                            <i class="fas fa-search search-icon"></i>
                        </div>

                        <select name="role" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                            <option value="teacher" <?php echo $filter_role === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                            <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Students</option>
                            <option value="parent" <?php echo $filter_role === 'parent' ? 'selected' : ''; ?>>Parents</option>
                        </select>

                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>

                        <button type="submit" class="cyber-btn cyber-btn-primary">
                            <i class="fas fa-filter"></i>
                            <span>Filter</span>
                        </button>

                        <?php if (!empty($search) || $filter_role !== 'all' || $filter_status !== 'all'): ?>
                            <a href="users.php" class="cyber-btn cyber-btn-outline">
                                <i class="fas fa-times"></i>
                                <span>Clear</span>
                            </a>
                        <?php endif; ?>

                        <button type="button" onclick="toggleSelectAll()" class="cyber-btn cyber-btn-outline" id="selectAllBtn">
                            <i class="fas fa-check-double"></i>
                            <span>Select All</span>
                        </button>
                    </form>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions" id="bulkActions">
                    <i class="fas fa-info-circle" style="color: var(--golden-pulse);"></i>
                    <span><strong id="selectedCount">0</strong> users selected</span>
                    <div style="margin-left: auto; display: flex; gap: 10px;">
                        <button onclick="bulkDelete()" class="cyber-btn cyber-btn-outline" style="border-color: var(--cyber-red); color: var(--cyber-red);">
                            <i class="fas fa-trash"></i>
                            <span>Delete Selected</span>
                        </button>
                        <button onclick="clearSelection()" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                    </div>
                </div>

                <!-- Users List -->
                <div class="holo-card">
                    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <h3>
                            <i class="fas fa-list"></i>
                            Users List (<?php echo count($users); ?>)
                        </h3>

                        <?php if ($pending_users > 0): ?>
                            <button onclick="deleteAllPending()" class="cyber-btn cyber-btn-outline" style="border-color: var(--cyber-red); color: var(--cyber-red);">
                                <i class="fas fa-trash-alt"></i>
                                <span>Delete All Pending</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($users)): ?>
                        <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                            <i class="fas fa-users-slash" style="font-size: 4rem; opacity: 0.3; margin-bottom: 20px;"></i>
                            <p style="font-size: 1.1rem;">No users found</p>
                            <p>Try adjusting your filters or search criteria</p>
                        </div>
                    <?php else: ?>
                        <!-- Table Header -->
                        <div class="user-row" style="background: rgba(0, 191, 255, 0.1); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">
                            <div>
                                <input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
                            </div>
                            <div>User</div>
                            <div>Email</div>
                            <div>Role</div>
                            <div>Status</div>
                            <div>Joined</div>
                            <div style="text-align: right;">Actions</div>
                        </div>

                        <!-- User Rows -->
                        <div id="usersList">
                            <?php foreach ($users as $user): ?>
                                <div class="user-row">
                                    <div>
                                        <input type="checkbox" class="user-checkbox" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" onclick="updateSelection()" style="width: 18px; height: 18px; cursor: pointer;">
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <div class="user-name">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="user-email">
                                                ID: <?php echo $user['id']; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="user-email">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>

                                    <div>
                                        <span class="cyber-badge <?php
                                                                    echo match ($user['role']) {
                                                                        'admin' => 'purple',
                                                                        'teacher' => 'cyan',
                                                                        'student' => 'success',
                                                                        default => 'default'
                                                                    };
                                                                    ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>

                                    <div>
                                        <span class="status-badge <?php echo strtolower($user['status'] ?? 'active'); ?>">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    </div>

                                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </div>

                                    <div class="action-buttons" style="justify-content: flex-end;">
                                        <button onclick="viewUser(<?php echo $user['id']; ?>)" class="cyber-btn cyber-btn-outline" style="padding: 6px 12px; font-size: 0.85rem;" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')" class="cyber-btn cyber-btn-outline" style="padding: 6px 12px; font-size: 0.85rem; border-color: var(--cyber-red); color: var(--cyber-red);" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="cyber-btn cyber-btn-outline" style="padding: 6px 12px; font-size: 0.85rem; opacity: 0.3; cursor: not-allowed;" title="Cannot delete yourself" disabled>
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="delete-modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h2 style="margin: 0; color: var(--cyber-red);">Confirm Deletion</h2>
                    <p style="margin: 5px 0 0 0; color: var(--text-muted);">This action cannot be undone</p>
                </div>
            </div>

            <div style="margin-bottom: 25px; padding: 15px; background: rgba(255, 69, 0, 0.1); border-left: 4px solid var(--cyber-red); border-radius: 8px;">
                <p style="margin: 0; color: var(--text-primary);" id="deleteMessage"></p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: var(--text-muted); font-size: 0.9rem;">
                    <li>User account will be permanently deleted</li>
                    <li>All attendance records will be removed</li>
                    <li>Biometric data will be cleared</li>
                    <li>Uploaded files will be deleted</li>
                    <li>All related data will be destroyed</li>
                </ul>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeDeleteModal()" class="cyber-btn cyber-btn-outline">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button onclick="executeDelete()" class="cyber-btn cyber-btn-primary" style="background: var(--cyber-red); border-color: var(--cyber-red);">
                    <i class="fas fa-trash"></i>
                    <span>Delete Permanently</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        let deleteUserId = null;
        let selectedUsers = [];

        function confirmDelete(userId, userName) {
            deleteUserId = userId;
            document.getElementById('deleteMessage').textContent = `Are you sure you want to delete ${userName}?`;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteUserId = null;
        }

        async function executeDelete() {
            if (!deleteUserId) return;

            try {
                const response = await fetch('../api/delete-user.php?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: deleteUserId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ User deleted successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + result.error);
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            } finally {
                closeDeleteModal();
            }
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            selectedUsers = Array.from(checkboxes).map(cb => ({
                id: parseInt(cb.dataset.userId),
                name: cb.dataset.userName
            }));

            document.getElementById('selectedCount').textContent = selectedUsers.length;
            document.getElementById('bulkActions').classList.toggle('active', selectedUsers.length > 0);
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const selectAllBtn = document.getElementById('selectAllBtn');

            // Toggle the state
            const newState = !selectAllCheckbox.checked;
            selectAllCheckbox.checked = newState;

            checkboxes.forEach(cb => {
                cb.checked = newState;
            });

            // Update button text
            if (selectAllBtn) {
                const btnText = selectAllBtn.querySelector('span');
                if (newState) {
                    btnText.textContent = 'Deselect All';
                    selectAllBtn.querySelector('i').className = 'fas fa-times-circle';
                } else {
                    btnText.textContent = 'Select All';
                    selectAllBtn.querySelector('i').className = 'fas fa-check-double';
                }
            }

            updateSelection();
        }

        function clearSelection() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const selectAllBtn = document.getElementById('selectAllBtn');

            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
            selectAllCheckbox.checked = false;

            // Reset button text
            if (selectAllBtn) {
                selectAllBtn.querySelector('span').textContent = 'Select All';
                selectAllBtn.querySelector('i').className = 'fas fa-check-double';
            }

            updateSelection();
        }

        async function bulkDelete() {
            if (selectedUsers.length === 0) return;

            if (!confirm(`⚠️ Delete ${selectedUsers.length} users?\n\nThis will permanently delete:\n• User accounts\n• Attendance records\n• Biometric data\n• All related files\n\nThis cannot be undone!`)) {
                return;
            }

            try {
                const response = await fetch('../api/delete-user.php?action=bulk_delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_ids: selectedUsers.map(u => u.id)
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Successfully deleted ${result.deleted} users!`);
                    location.reload();
                } else {
                    alert('❌ Errors occurred:\n' + result.errors.join('\n'));
                    location.reload();
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            }
        }

        async function deleteAllPending() {
            const confirmation = prompt('⚠️ DELETE ALL PENDING USERS?\n\nThis will permanently delete ALL pending registration requests.\nType "DELETE_ALL_PENDING" to confirm:');

            if (confirmation !== 'DELETE_ALL_PENDING') {
                return;
            }

            try {
                const response = await fetch('../api/delete-user.php?action=delete_pending', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        confirm: 'DELETE_ALL_PENDING'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Successfully deleted ${result.deleted} pending users!`);
                    location.reload();
                } else {
                    alert('❌ Error: ' + result.error);
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            }
        }

        function viewUser(userId) {
            window.location.href = 'student-view.php?id=' + userId;
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
