<?php

/**
 * Enhanced Registration Management Page
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';

// Handle approve registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $user_id = (int)$_POST['user_id'];

    try {
        db()->beginTransaction();

        // Get user details
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ? AND status = 'pending'", [$user_id]);
        if (!$user) throw new Exception('User not found or already processed');

        // Assign appropriate ID based on role
        $assigned_id = '';
        if ($user['role'] === 'student') {
            // Insert into students table and get ID
            $student_data = [
                'user_id' => $user_id,
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'date_of_birth' => $user['date_of_birth'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            db()->insert('students', $student_data);
            $student_db_id = db()->lastInsertId();

            // Generate student ID
            $next_id_result = db()->fetchOne("SELECT MAX(CAST(SUBSTRING(student_id, 4) AS UNSIGNED)) + 1 as next_id FROM students WHERE student_id LIKE 'STU%'");
            $next_id = $next_id_result['next_id'] ?? 1;
            $assigned_id = 'STU' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

            // Update student record with ID
            db()->update('students', ['student_id' => $assigned_id], 'id = ?', [$student_db_id]);
        } elseif ($user['role'] === 'teacher') {
            // Insert into teachers table
            $teacher_data = [
                'user_id' => $user_id,
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'date_of_birth' => $user['date_of_birth'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            db()->insert('teachers', $teacher_data);
            $teacher_db_id = db()->lastInsertId();

            // Generate employee ID
            $next_id_result = db()->fetchOne("SELECT MAX(CAST(SUBSTRING(employee_id, 4) AS UNSIGNED)) + 1 as next_id FROM teachers WHERE employee_id LIKE 'TCH%'");
            $next_id = $next_id_result['next_id'] ?? 1;
            $assigned_id = 'TCH' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

            // Update teacher record with ID
            db()->update('teachers', ['employee_id' => $assigned_id], 'id = ?', [$teacher_db_id]);
        }

        // Update user status
        db()->update('users', ['status' => 'active'], 'id = ?', [$user_id]);

        // Send approval notification
        send_approval_notification($user['id'], $user['email'], $user['first_name'] . ' ' . $user['last_name'], $user['role'], $assigned_id, $user['username']);

        // Log activity
        log_activity($_SESSION['user_id'], 'approve_user', 'user', $user_id, "Approved: {$user['first_name']} {$user['last_name']} - Assigned ID: $assigned_id");

        db()->commit();
        $message = "User approved successfully! Assigned ID: $assigned_id";
        $message_type = 'success';
    } catch (Exception $e) {
        db()->rollback();
        $message = 'Approval failed: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle reject registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $user_id = (int)$_POST['user_id'];
    $reason = sanitize($_POST['reason'] ?? 'Application rejected by administrator');

    try {
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ? AND status = 'pending'", [$user_id]);
        if (!$user) throw new Exception('User not found or already processed');

        // Update user status
        db()->update('users', ['status' => 'rejected'], 'id = ?', [$user_id]);

        // Send rejection notification
        send_rejection_notification($user['email'], $user['first_name'] . ' ' . $user['last_name'], $reason);

        // Log activity
        log_activity($_SESSION['user_id'], 'reject_user', 'user', $user_id, "Rejected: {$user['first_name']} {$user['last_name']} - Reason: $reason");

        $message = "User registration rejected successfully!";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Rejection failed: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle toggle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_registration'])) {
    $current = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'");
    $new_value = $current ? ($current['setting_value'] == '1' ? '0' : '1') : '1';

    if ($current) {
        db()->update('system_settings', ['setting_value' => $new_value], 'setting_key = ?', ['registration_enabled']);
    } else {
        db()->insert('system_settings', [
            'setting_key' => 'registration_enabled',
            'setting_value' => $new_value,
            'description' => 'Enable or disable user registration'
        ]);
    }

    log_activity($_SESSION['user_id'], 'toggle_registration', 'system_settings', 0, 'Registration ' . ($new_value == '1' ? 'enabled' : 'disabled'));
    $message = 'Registration ' . ($new_value == '1' ? 'enabled' : 'disabled') . ' successfully!';
    $message_type = 'success';
}

// Get registration status
$reg_setting = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'");
$registration_enabled = $reg_setting ? (bool)$reg_setting['setting_value'] : true;

// Get pending registrations
$pending = db()->fetchAll("
    SELECT u.*,
           CASE WHEN u.role = 'student' THEN c.grade_level ELSE NULL END as grade_level
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id AND u.role = 'student'
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE u.status = 'pending'
    ORDER BY u.created_at DESC
");

// Get recently approved/active users
$recent = db()->fetchAll("
    SELECT * FROM users
    WHERE status = 'active'
    ORDER BY created_at DESC
    LIMIT 10
");

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
    <title>Registration Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        .registration-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 15px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .registration-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 15% auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <div class="cyber-main">

    <div class="container">
            <div class="cyber-header" style="padding: 24px 32px; border-bottom: 1px solid var(--border-color);">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:44px;height:44px;background:var(--primary-50);color:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary);">User Registrations</h1>
                        <p style="color:var(--text-secondary);font-size:0.85rem;">Manage pending registrations</p>
                    </div>
                </div>
            </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Registration Toggle Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-toggle-on"></i> Registration Control</h2>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px;">
                <div>
                    <h3 style="margin-bottom: 10px;">
                        <i class="fas fa-user-plus"></i> Public Registration
                    </h3>
                    <p style="color: #64748b;">
                        <?php if ($registration_enabled): ?>
                            Registration is currently <strong style="color: #10b981;">ENABLED</strong>. New users can register and wait for approval.
                        <?php else: ?>
                            Registration is currently <strong style="color: #ef4444;">DISABLED</strong>. New users cannot register.
                        <?php endif; ?>
                    </p>
                </div>
                <form method="POST">
                    <label class="toggle-switch">
                        <input type="checkbox" <?php echo $registration_enabled ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <span class="slider"></span>
                    </label>
                    <input type="hidden" name="toggle_registration" value="1">
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-orb">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo count($pending); ?></h3>
                    <p>Pending Approval</p>
                </div>
            </div>

            <div class="stat-orb">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo count($recent); ?></h3>
                    <p>Recently Approved</p>
                </div>
            </div>

            <div class="stat-orb">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-<?php echo $registration_enabled ? 'toggle-on' : 'toggle-off'; ?>"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $registration_enabled ? 'ON' : 'OFF'; ?></h3>
                    <p>Registration Status</p>
                </div>
            </div>

            <div class="stat-orb">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-link"></i>
                </div>
                <div class="stat-details">
                    <h3><a href="../register.php" target="_blank" style="color: inherit; text-decoration: none;">Link</a></h3>
                    <p>Registration Page</p>
                </div>
            </div>
        </div>

        <!-- Pending Registrations -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-hourglass-half"></i> Pending Registrations (<?php echo count($pending); ?>)</h2>
            </div>

            <?php if (empty($pending)): ?>
                <p class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No pending registrations at the moment.
                </p>
            <?php else: ?>
                <?php foreach ($pending as $user): ?>
                    <div class="registration-card">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong style="font-size: 18px;">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </strong>
                                    <p style="color: #64748b; margin-top: 5px;">
                                        <i class="fas fa-at"></i> @<?php echo htmlspecialchars($user['username']); ?> •
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <span class="badge badge-<?php
                                                            echo $user['role'] === 'student' ? 'info' : ($user['role'] === 'teacher' ? 'primary' : 'success');
                                                            ?>">
                                    <i class="fas fa-<?php
                                                        echo $user['role'] === 'student' ? 'user-graduate' : ($user['role'] === 'teacher' ? 'chalkboard-teacher' : 'user-friends');
                                                        ?>"></i>
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                            <?php if (isset($user['phone_number']) && $user['phone_number']): ?>
                                <div>
                                    <strong>Phone:</strong>
                                    <p style="color: #64748b;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($user['role'] === 'student' && isset($user['grade_level']) && $user['grade_level']): ?>
                                <div>
                                    <strong>Grade:</strong>
                                    <p style="color: #64748b;"><i class="fas fa-graduation-cap"></i> Grade <?php echo $user['grade_level']; ?></p>
                                </div>
                            <?php endif; ?>

                            <div>
                                <strong>Registered:</strong>
                                <p style="color: #64748b;"><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="approve" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <button onclick="showRejectModal(<?php echo $user['id']; ?>)" class="btn btn-danger" style="flex: 1;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recently Approved -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Recently Approved Users</h2>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                            <p style="font-size: 12px; color: #64748b;">@<?php echo htmlspecialchars($user['username']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php
                                                                echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'primary' : ($user['role'] === 'student' ? 'info' : 'success'));
                                                                ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check"></i> Active
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">Reject Registration</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="user_id" id="rejectUserId">

                <div class="form-group">
                    <label for="reason">Reason for Rejection (Optional)</label>
                    <textarea name="reason" id="reason" rows="4" class="form-control"
                        placeholder="Please provide a reason for rejecting this registration..."></textarea>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="submit" name="reject" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <button type="button" onclick="closeRejectModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectModal(userId) {
            document.getElementById('rejectUserId').value = userId;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) {
                closeRejectModal();
            }
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
        </div>
    </div>
</body>

</html>