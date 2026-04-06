<?php
/**
 * Teachers Management - Nature UI
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_post_csrf_if_present()) {
        $message = 'Security validation failed. Please refresh and try again.';
        $message_type = 'error';
    } elseif (isset($_POST['add_teacher'])) {
        $user_id = false;
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $qualification = sanitize($_POST['qualification'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $message = 'First name and last name are required.';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid teacher email address.';
            $message_type = 'error';
        } else {
            $exists = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if ($exists) {
                $message = 'Teacher email already exists.';
                $message_type = 'error';
            } else {
                try {
                    db()->query('START TRANSACTION');

                    // Generate OTP for account confirmation (no password created)
                    $otp = sprintf('%06d', random_int(100000, 999999));
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $otp_token = 'CONFIRM:' . $otp . ':' . strtotime($otp_expiry);

                    // Generate employee ID
                    $teacher_count = db()->count('users', 'role = ?', ['teacher']);
                    $employee_id = 'TCH' . date('Y') . str_pad($teacher_count + 1, 4, '0', STR_PAD_LEFT);

                    $payload = build_user_payload([
                        'email' => $email,
                        'password' => bin2hex(random_bytes(32)),
                        'role' => 'teacher',
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'full_name' => $first_name . ' ' . $last_name,
                        'status' => 'active',
                        'approved' => 1,
                        'email_verified' => 0,
                        'assigned_id' => $employee_id
                    ]);
                    // Override: account stays inactive until OTP confirmed
                    $payload['is_active'] = 0;
                    $payload['email_verified'] = 0;
                    $payload['verification_token'] = $otp_token;
                    if (table_has_column('users', 'token_expiry')) {
                        $payload['token_expiry'] = $otp_expiry;
                    }
                    if ($phone !== '' && table_has_column('users', 'phone')) {
                        $payload['phone'] = $phone;
                    }

                    $user_id = insert_flexible('users', $payload);
                    if (!$user_id) {
                        throw new Exception('User insert failed');
                    }

                    // Create teacher profile record
                    insert_flexible('teachers', [
                        'user_id' => $user_id,
                        'employee_id' => $employee_id,
                        'qualification' => $qualification !== '' ? $qualification : null,
                        'specialization' => $specialization !== '' ? $specialization : null,
                        'date_joined' => date('Y-m-d'),
                        'is_class_teacher' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    attach_user_to_tenant((int)$user_id, current_tenant_id());
                    db()->query('COMMIT');

                    log_activity($_SESSION['user_id'], 'create', 'users', $user_id);

                    // Send OTP confirmation email
                    send_account_otp_email($email, $first_name . ' ' . $last_name, $otp, $employee_id, 'teacher');

                    $message = "Teacher added! Employee ID: $employee_id. A confirmation link with OTP has been sent to their email.";
                    $message_type = 'success';
                } catch (Throwable $e) {
                    db()->query('ROLLBACK');
                    $user_id = false;
                    $message = 'Error adding teacher: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    } elseif (isset($_POST['delete_teacher'])) {
        $id = (int)$_POST['teacher_id'];
        if (user_in_current_tenant($id)) {
            db()->delete('users', 'id = ? AND role = ?', [$id, 'teacher']);
            log_activity($_SESSION['user_id'], 'delete', 'users', $id);
            $message = 'Teacher deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Access denied for that teacher record.';
            $message_type = 'error';
        }
    } elseif (isset($_POST['assign_class'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        $class_id = (int)$_POST['class_id'];
        $teacherColumn = table_has_column('classes', 'class_teacher_id') ? 'class_teacher_id' : 'teacher_id';
        update_flexible('classes', [$teacherColumn => $teacher_id], 'id = ?', [$class_id]);
        $message = 'Class assigned successfully!';
        $message_type = 'success';
    }
}

// Get all teachers with stats
$teachers_sql = "
    SELECT u.*,
           (SELECT COUNT(*) FROM classes WHERE " . (table_has_column('classes', 'class_teacher_id') ? 'class_teacher_id' : 'teacher_id') . " = u.id) as class_count
    FROM users u
    WHERE u.role = 'teacher'
";
$teachers_params = [];
if (table_exists('tenant_users')) {
    $teachers_sql .= " AND u.id IN (SELECT user_id FROM tenant_users WHERE tenant_id = :tenant_id AND is_active = 1)";
    $teachers_params['tenant_id'] = current_tenant_id();
}
$teachers_sql .= " ORDER BY u.last_name, u.first_name";
$teachers = db()->fetchAll($teachers_sql, $teachers_params);

// Get all classes for assignment
$all_classes = db()->fetchAll("
    SELECT c.*,
           c.class_name AS name
    FROM classes c
    ORDER BY c.class_name
");

$page_title = 'Teachers Management';
$page_icon = 'chalkboard-teacher';
$full_name = $_SESSION['full_name'];
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta name="theme-color" content="#00BFFF">
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
                    <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
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
                    <div class="cyber-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($teachers); ?></div>
                            <div class="stat-label">Total Teachers</div>
                            <div class="stat-trend up">
                                <i class="fas fa-users"></i>
                                <span>Active Staff</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($all_classes); ?></div>
                            <div class="stat-label">Total Classes</div>
                            <div class="stat-trend up">
                                <i class="fas fa-check-circle"></i>
                                <span>All Levels</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Add Teacher Form -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-user-plus"></i> <span>Add New Teacher</span></div>
                    </div>
                    <div class="card-body">
                        <form method="POST" style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-user"></i> FIRST NAME</label>
                                <input type="text" name="first_name" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-user"></i> LAST NAME</label>
                                <input type="text" name="last_name" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-envelope"></i> EMAIL</label>
                                <input type="email" name="email" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-phone"></i> PHONE</label>
                                <input type="tel" name="phone" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-graduation-cap"></i> QUALIFICATION</label>
                                <input type="text" name="qualification" placeholder="e.g. B.Ed, M.Sc" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-book"></i> SPECIALIZATION</label>
                                <input type="text" name="specialization" placeholder="e.g. Mathematics, Physics" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                            <div style="grid-column:span 2;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:16px;">
                                <p style="color:var(--cyber-cyan);font-size:0.85rem;margin:0;"><i class="fas fa-info-circle"></i> <strong>No password needed.</strong> A confirmation link with OTP will be sent to the teacher's email. They will set their own password securely.</p>
                            </div>
                            <div style="grid-column:span 2;display:flex;justify-content:flex-end;">
                                <button type="submit" name="add_teacher" class="cyber-btn primary"><i class="fas fa-paper-plane"></i> Add Teacher & Send Invitation</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Teachers List -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-users"></i> <span>All Teachers</span></div>
                        <div class="card-badge cyan"><?php echo count($teachers); ?> Teachers</div>
                    </div>
                    <div class="card-body">
                        <div class="holo-table-wrapper">
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Employee ID</th>
                                        <th>Classes</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:12px;">
                                                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--cyber-cyan),var(--hologram-purple));display:flex;align-items:center;justify-content:center;font-weight:700;color:white;">
                                                        <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight:600;color:var(--cyber-cyan);">
                                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td><span class="cyber-badge cyan"><?php echo htmlspecialchars($teacher['assigned_id'] ?? 'N/A'); ?></span></td>
                                            <td><span class="cyber-badge cyan"><?php echo $teacher['class_count']; ?> Classes</span></td>
                                            <td><span class="cyber-badge success">Active</span></td>
                                            <td>
                                                <div style="display:flex;gap:8px;">
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this teacher?');">
                                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                                        <button type="submit" name="delete_teacher" class="cyber-btn danger" style="padding:8px 12px;font-size:0.85rem;">
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
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = <?php echo json_encode($csrf_token); ?>;
            document.querySelectorAll('form').forEach((form) => {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = csrfToken;
                    form.appendChild(input);
                }
            });
        });
    </script>
</body>
</html>
