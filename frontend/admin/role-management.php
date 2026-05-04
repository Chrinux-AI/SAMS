<?php

/**
 * Comprehensive Role Management System
 * Enhanced multi-role support for educational institutions
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Only admins can access this
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
    header('Location: ../login.php');
    exit;
}

// Get all roles with user counts
$all_roles = db()->fetchAll("
    SELECT r.*, COUNT(u.id) as user_count
    FROM system_roles r
    LEFT JOIN users u ON r.role_name = u.role
    GROUP BY r.id
    ORDER BY r.hierarchy_level ASC
");

// Get role permissions
$role_permissions = db()->fetchAll("
    SELECT rp.*, r.role_name
    FROM role_permissions rp
    JOIN system_roles r ON rp.role_id = r.id
    ORDER BY r.role_name, rp.module_name
");

// Get all available permissions
$all_permissions = db()->fetchAll("SELECT * FROM system_permissions ORDER BY module_name, permission_name");

// Handle role creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_role') {
        $role_data = [
            'role_name' => $_POST['role_name'],
            'display_name' => $_POST['display_name'],
            'description' => $_POST['description'],
            'hierarchy_level' => $_POST['hierarchy_level'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('system_roles', $role_data);
        $role_id = db()->getConnection()->lastInsertId();

        // Add permissions
        if (isset($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $permission_id) {
                db()->insert('role_permissions', [
                    'role_id' => $role_id,
                    'permission_id' => $permission_id,
                    'granted_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        header('Location: role-management.php?success=role_created');
        exit;
    }

    if ($action === 'update_role') {
        $role_id = $_POST['role_id'];

        db()->update('system_roles', [
            'display_name' => $_POST['display_name'],
            'description' => $_POST['description'],
            'hierarchy_level' => $_POST['hierarchy_level'],
            'is_active' => $_POST['is_active'] ?? 0
        ], 'id = ?', [$role_id]);

        // Update permissions
        db()->delete('role_permissions', 'role_id = ?', [$role_id]);

        if (isset($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $permission_id) {
                db()->insert('role_permissions', [
                    'role_id' => $role_id,
                    'permission_id' => $permission_id,
                    'granted_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        header('Location: role-management.php?success=role_updated');
        exit;
    }

    if ($action === 'delete_role') {
        $role_id = (int)($_POST['role_id'] ?? 0);
        if ($role_id > 0) {
            $role = db()->fetchOne('SELECT id, role_name FROM system_roles WHERE id = ?', [$role_id]);
            $users_with_role = db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE role = ?', [$role['role_name'] ?? ''])['c'] ?? 0;
            $protected = ['super_admin', 'superadmin', 'admin', 'owner', 'teacher', 'student', 'parent'];
            if ($role && (int)$users_with_role === 0 && !in_array($role['role_name'], $protected, true)) {
                db()->delete('role_permissions', 'role_id = ?', [$role_id]);
                db()->delete('system_roles', 'id = ?', [$role_id]);
                header('Location: role-management.php?success=role_deleted');
                exit;
            }
            header('Location: role-management.php?error=role_delete_blocked');
            exit;
        }
    }
}

// Success message
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'role_created':
            $success_message = 'Role created successfully!';
            break;
        case 'role_updated':
            $success_message = 'Role updated successfully!';
            break;
        case 'role_deleted':
            $success_message = 'Role deleted successfully!';
            break;
    }
}

$error_message = '';
if (isset($_GET['error']) && $_GET['error'] === 'role_delete_blocked') {
    $error_message = 'Role cannot be deleted. Remove users from this role first, and note core system roles are protected.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - SAMS Platform</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .role-management-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.3);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
        }

        .role-grid {
            display: grid;
            gap: 20px;
        }

        .role-card {
            background: #F9FAFB;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .role-card:hover {
            border-color: #4F46E5;
            transform: translateY(-2px);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .role-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 5px;
        }

        .role-display-name {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .role-count {
            background: #4F46E5;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-description {
            color: #6B7280;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .role-permissions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .permission-tag {
            background: #E5E7EB;
            color: #374151;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: #F9FAFB;
            border-radius: 10px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .permission-item input[type="checkbox"] {
            width: auto;
        }

        .permission-item label {
            margin: 0;
            font-size: 14px;
            cursor: pointer;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4F46E5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338CA;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6B7280;
            color: white;
        }

        .btn-danger {
            background: #EF4444;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .role-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .permissions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="role-management-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">Role Management System</div>
            <div class="page-subtitle">Comprehensive role and permission management for educational institutions</div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert" style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Role Statistics -->
        <div class="role-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($all_roles); ?></div>
                <div class="stat-label">Total Roles</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($all_permissions); ?></div>
                <div class="stat-label">Available Permissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo array_sum(array_column($all_roles, 'user_count')); ?></div>
                <div class="stat-label">Users with Roles</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($all_roles, fn($r) => $r['is_active'])); ?></div>
                <div class="stat-label">Active Roles</div>
            </div>
        </div>

        <div class="panel" style="margin-bottom:30px;">
            <div class="panel-header">
                <h2 class="panel-title">Role Hierarchy Visualization</h2>
            </div>
            <div style="display:grid;gap:10px;">
                <?php foreach ($all_roles as $r): ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #E5E7EB;border-radius:10px;">
                        <span style="display:inline-flex;min-width:90px;justify-content:center;padding:4px 8px;border-radius:999px;background:#EEF2FF;color:#4338CA;font-size:12px;font-weight:600;">Level <?php echo (int)$r['hierarchy_level']; ?></span>
                        <span style="font-weight:600;color:#111827;"><?php echo htmlspecialchars($r['role_name']); ?></span>
                        <span style="color:#6B7280;">(<?php echo htmlspecialchars($r['display_name']); ?>)</span>
                        <span style="margin-left:auto;color:#6B7280;font-size:13px;"><?php echo (int)$r['user_count']; ?> user(s)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Existing Roles -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">System Roles</h2>
                    <button class="btn btn-primary" onclick="showCreateRoleForm()">
                        <i class="fas fa-plus"></i>
                        Add Role
                    </button>
                </div>

                <div class="role-grid">
                    <?php foreach ($all_roles as $role): ?>
                        <div class="role-card" onclick="editRole(<?php echo $role['id']; ?>)">
                            <div class="role-header">
                                <div>
                                    <div class="role-name"><?php echo htmlspecialchars($role['role_name']); ?></div>
                                    <div class="role-display-name"><?php echo htmlspecialchars($role['display_name']); ?></div>
                                </div>
                                <div class="role-count"><?php echo $role['user_count']; ?></div>
                            </div>
                            <div class="role-description">
                                <?php echo htmlspecialchars($role['description']); ?>
                            </div>
                            <div class="role-permissions">
                                <?php
                                $role_perms = array_filter($role_permissions, fn($rp) => $rp['role_id'] == $role['id']);
                                $perm_count = min(count($role_perms), 3);
                                for ($i = 0; $i < $perm_count; $i++):
                                    $perm = $role_perms[$i];
                                ?>
                                    <span class="permission-tag"><?php echo htmlspecialchars($perm['module_name']); ?></span>
                                <?php endfor; ?>
                                <?php if (count($role_perms) > 3): ?>
                                    <span class="permission-tag">+<?php echo count($role_perms) - 3; ?> more</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Role Form -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title" id="form-title">Create New Role</h2>
                </div>

                <form id="role-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <input type="hidden" name="action" id="form-action" value="create_role">
                    <input type="hidden" name="role_id" id="role-id">

                    <div class="form-group">
                        <label for="role_name">Role Name *</label>
                        <input type="text" id="role_name" name="role_name" required>
                    </div>

                    <div class="form-group">
                        <label for="display_name">Display Name *</label>
                        <input type="text" id="display_name" name="display_name" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="hierarchy_level">Hierarchy Level</label>
                        <select id="hierarchy_level" name="hierarchy_level">
                            <option value="1">Level 1 (Highest)</option>
                            <option value="2">Level 2</option>
                            <option value="3">Level 3</option>
                            <option value="4">Level 4</option>
                            <option value="5">Level 5 (Lowest)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="permissions-grid">
                            <?php foreach ($all_permissions as $permission): ?>
                                <div class="permission-item">
                                    <input type="checkbox" id="perm_<?php echo $permission['id']; ?>"
                                        name="permissions[]" value="<?php echo $permission['id']; ?>">
                                    <label for="perm_<?php echo $permission['id']; ?>">
                                        <?php echo htmlspecialchars($permission['module_name']); ?> -
                                        <?php echo htmlspecialchars($permission['permission_name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" checked>
                            Active Role
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Role
                        </button>
                        <button type="button" id="delete-role-btn" class="btn btn-danger" style="display:none;" onclick="deleteCurrentRole()">
                            <i class="fas fa-trash"></i>
                            Delete Role
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const roleMap = <?php
                        $roleMap = [];
                        foreach ($all_roles as $r) {
                            $roleMap[(int)$r['id']] = $r;
                        }
                        echo json_encode($roleMap);
                        ?>;

        function showCreateRoleForm() {
            document.getElementById('form-title').textContent = 'Create New Role';
            document.getElementById('form-action').value = 'create_role';
            document.getElementById('role-form').reset();
            document.getElementById('role_name').readOnly = false;
            document.getElementById('delete-role-btn').style.display = 'none';
            document.getElementById('role_name').focus();
        }

        function editRole(roleId) {
            // Load role data for editing
            fetch('get-role-data.php?id=' + roleId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('form-title').textContent = 'Edit Role';
                    document.getElementById('form-action').value = 'update_role';
                    document.getElementById('role-id').value = data.id;
                    document.getElementById('role_name').value = data.role_name;
                    document.getElementById('role_name').readOnly = true;
                    document.getElementById('display_name').value = data.display_name;
                    document.getElementById('description').value = data.description;
                    document.getElementById('hierarchy_level').value = data.hierarchy_level;
                    document.getElementById('delete-role-btn').style.display = 'inline-flex';

                    // Clear all checkboxes
                    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);

                    // Check role permissions
                    data.permissions.forEach(permId => {
                        const checkbox = document.getElementById('perm_' + permId);
                        if (checkbox) checkbox.checked = true;
                    });

                    // Scroll to form
                    document.querySelector('.panel:last-child').scrollIntoView({
                        behavior: 'smooth'
                    });
                });
        }

        function resetForm() {
            document.getElementById('role-form').reset();
            showCreateRoleForm();
        }

        function deleteCurrentRole() {
            const roleId = document.getElementById('role-id').value;
            if (!roleId) return;
            const role = roleMap[roleId] || {};
            if (!confirm(`Delete role "${role.role_name || roleId}"? This is only allowed for empty, non-core roles.`)) {
                return;
            }

            const form = document.getElementById('role-form');
            const actionInput = document.getElementById('form-action');
            const previousAction = actionInput.value;
            actionInput.value = 'delete_role';
            form.submit();
            actionInput.value = previousAction;
        }
    </script>
</body>

</html>
