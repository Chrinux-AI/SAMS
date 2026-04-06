<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin();

$full_name = $_SESSION['full_name'];
$admin_id = $_SESSION['user_id'];

// Handle disapprove action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disapprove_user'])) {
    $user_id = intval($_POST['user_id']);

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        db()->update(
            'users',
            [
                'approved' => 0,
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null
            ],
            'id = ?',
            ['id' => $user_id]
        );

        if ($user['role'] === 'student') {
            db()->update('students', ['status' => 'pending'], 'user_id = ?', ['user_id' => $user_id]);
        } elseif ($user['role'] === 'teacher') {
            db()->update('teachers', ['status' => 'pending'], 'user_id = ?', ['user_id' => $user_id]);
        }

        log_activity($admin_id, 'disapprove_user', 'users', $user_id, "Disapproved user: {$user['email']}");

        $success_msg = "User disapproved successfully.";
    }
}

// Handle re-approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reapprove_user'])) {
    $user_id = intval($_POST['user_id']);

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        db()->update(
            'users',
            [
                'approved' => 1,
                'approved_by' => $admin_id,
                'approved_at' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ],
            'id = ?',
            ['id' => $user_id]
        );

        if ($user['role'] === 'student') {
            db()->update('students', ['status' => 'active'], 'user_id = ?', ['user_id' => $user_id]);
        } elseif ($user['role'] === 'teacher') {
            db()->update('teachers', ['status' => 'active'], 'user_id = ?', ['user_id' => $user_id]);
        }

        log_activity($admin_id, 'reapprove_user', 'users', $user_id, "Re-approved user: {$user['email']}");

        $success_msg = "User re-approved successfully.";
    }
}

// Handle permanent delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        log_activity($admin_id, 'delete_unapproved_user', 'users', $user_id, "Permanently deleted unapproved user: {$user['email']}");

        // Delete from role-specific tables first
        if ($user['role'] === 'student') {
            db()->delete('students', 'user_id = ?', [$user_id]);
        } elseif ($user['role'] === 'teacher') {
            db()->delete('teachers', 'user_id = ?', [$user_id]);
        }

        // Delete user
        db()->delete('users', 'id = ?', [$user_id]);

        $success_msg = "User permanently deleted.";
    }
}

// Get unapproved users (approved = 0)
$unapproved_users = db()->fetchAll("
    SELECT u.*,
        CASE
            WHEN u.role = 'student' THEN s.admission_number
            WHEN u.role = 'teacher' THEN t.teacher_id
            ELSE NULL
        END as assigned_id,
        CASE
            WHEN u.role = 'student' THEN IF(s.is_active = 1, 'active', 'inactive')
            WHEN u.role = 'teacher' THEN t.status
            ELSE u.status
        END as role_status
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.approved = 0 AND u.email_verified = 1
    ORDER BY u.created_at DESC
");
if (!$unapproved_users) $unapproved_users = [];

// Get approved users for comparison
$approved_users = db()->fetchAll("
    SELECT u.*,
        CASE
            WHEN u.role = 'student' THEN s.admission_number
            WHEN u.role = 'teacher' THEN t.teacher_id
            ELSE NULL
        END as assigned_id
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.approved = 1
    ORDER BY u.approved_at DESC
    LIMIT 50
");
if (!$approved_users) $approved_users = [];

$page_title = 'Unapproved Users';
$page_icon = 'user-times';
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
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <a href="approve-users.php" class="cyber-btn primary">
                        <i class="fas fa-user-check"></i> Back to Pending Approvals
                    </a>
                    <div class="stat-badge" style="background:rgba(255,0,0,0.1);border:1px solid red;padding:8px 15px;border-radius:8px;">
                        <i class="fas fa-user-times"></i> <?php echo count($unapproved_users); ?> Unapproved
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <?php if (isset($success_msg)): ?>
                    <div style="padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:12px;background:rgba(0,255,127,0.1);border:1px solid var(--neon-green);color:var(--neon-green);">
                        <i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Unapproved Users -->
                <div class="holo-card" style="margin-bottom:30px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-user-times"></i>
                            <span>Unapproved Users (<?php echo count($unapproved_users); ?>)</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($unapproved_users) > 0): ?>
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Assigned ID</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unapproved_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="status-badge <?php echo $user['role']; ?>"><?php echo strtoupper($user['role']); ?></span></td>
                                            <td>
                                                <?php if ($user['assigned_id']): ?>
                                                    <code style="color:var(--cyber-cyan);"><?php echo htmlspecialchars($user['assigned_id']); ?></code>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge" style="background:rgba(255,0,0,0.2);color:red;">
                                                    Unapproved
                                                </span>
                                            </td>
                                            <td style="font-size:0.85rem;"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="reapprove_user" class="cyber-btn success sm" style="margin-right:5px;" title="Re-approve this user">
                                                        <i class="fas fa-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="cyber-btn danger sm"
                                                        onclick="return confirm('Are you sure you want to permanently delete this user?');"
                                                        title="Permanently delete this user">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">
                                <i class="fas fa-check-circle" style="font-size:3rem;color:var(--neon-green);display:block;margin-bottom:15px;"></i>
                                No unapproved users found. All registered users are either approved or pending email verification.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recently Approved Users -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-check-circle"></i>
                            <span>Recently Approved Users (Last 50)</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($approved_users) > 0): ?>
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Assigned ID</th>
                                        <th>Approved At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="status-badge <?php echo $user['role']; ?>"><?php echo strtoupper($user['role']); ?></span></td>
                                            <td>
                                                <?php if ($user['assigned_id']): ?>
                                                    <code style="color:var(--neon-green);"><?php echo htmlspecialchars($user['assigned_id']); ?></code>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:0.85rem;">
                                                <?php echo $user['approved_at'] ? date('M j, Y g:i A', strtotime($user['approved_at'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="disapprove_user" class="cyber-btn warning sm"
                                                        onclick="return confirm('Are you sure you want to disapprove this user?');"
                                                        title="Move to unapproved list">
                                                        <i class="fas fa-times-circle"></i> Disapprove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">No approved users yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>