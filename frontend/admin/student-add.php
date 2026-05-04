<?php

/**
 * Add Student
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$page_title = 'Add New Student';
$page_icon = 'user-plus';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_post_csrf_if_present()) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    }

    if (empty($errors)) {
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $grade_level = $_POST['grade_level'] ?? '';
    $status = $_POST['status'] ?? 'active';

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $errors[] = 'First name, last name, and email are required';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    // Check if email already exists
    if (empty($errors)) {
        $existing = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'Email already exists';
        }
    }

    if (empty($errors)) {
        try {
            db()->query("START TRANSACTION");

            // Generate student ID
            $student_count = db()->count('students');
            $student_id = date('Y') . str_pad($student_count + 1, 4, '0', STR_PAD_LEFT);

            // Create user account (schema-compatible)
            $user_payload = build_user_payload([
                'email' => $email,
                'password' => $password,
                'role' => 'student',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'full_name' => $first_name . ' ' . $last_name,
                'status' => $status,
                'approved' => 1,
                'email_verified' => 1,
                'assigned_id' => $student_id
            ]);
            $user_payload['approved_by'] = $_SESSION['user_id'];
            $user_payload['approved_at'] = date('Y-m-d H:i:s');
            $user_id = insert_flexible('users', $user_payload);

            if ($user_id) {
                attach_user_to_tenant((int)$user_id, current_tenant_id());

                // Create student record (schema-compatible)
                $student_data = [
                    'user_id' => $user_id,
                    'admission_number' => $student_id,
                    'assigned_student_id' => $student_id,
                    'date_of_birth' => $date_of_birth ?: null,
                    'gender' => $gender ?: null,
                    'emergency_contact' => trim($parent_name . ' | ' . $parent_phone . ' | ' . $parent_email),
                    'is_active' => $status === 'active' ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $student_inserted = insert_flexible('students', $student_data);
                if (!$student_inserted) {
                    throw new Exception('Failed to create student profile record.');
                }

                db()->query("COMMIT");

                // Send welcome email
                $to = $email;
                $subject = "Welcome to Attendance System - Your Account Details";
                $current_year = date('Y');
                $login_url = 'http://' . $_SERVER['HTTP_HOST'] . '/attendance/login.php';
                $email_message = <<<HTML
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Attendance System</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 10px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .id-box { background: #f0fdf4; border: 2px solid #10b981; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: center; }
        .student-id { font-size: 32px; font-weight: bold; color: #10b981; letter-spacing: 2px; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Attendance System</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$first_name} {$last_name}</strong>,</p>
            <p>Your student account has been created successfully.</p>

            <div class="id-box">
                <h3 style="color:#059669;margin-bottom:10px;">Your Student ID</h3>
                <div class="student-id">{$student_id}</div>
            </div>

            <h3>Login Credentials:</h3>
            <ul>
                <li><strong>Email:</strong> {$email}</li>
                <li><strong>Password:</strong> (as provided by admin)</li>
            </ul>

            <p style="text-align:center;margin-top:30px;">
                <a href="{$login_url}" style="display:inline-block;background:#10b981;color:#fff;padding:12px 30px;text-decoration:none;border-radius:5px;">Login Now</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {$current_year} Attendance System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Attendance System <christolabiyi35@gmail.com>" . "\r\n";

                @mail($to, $subject, $email_message, $headers);

                $success = "Student added successfully! Student ID: {$student_id}";

                // Redirect after 2 seconds
                header("refresh:2;url=students.php");
            } else {
                db()->query("ROLLBACK");
                $errors[] = 'Failed to create user account';
            }
        } catch (Exception $e) {
            db()->query("ROLLBACK");
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    }
}
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


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
                    <a href="students.php" class="cyber-btn cyber-btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Students</span>
                    </a>
                </div>
            </header>

            <div class="cyber-content">
                <?php if (!empty($errors)): ?>
                    <div class="cyber-alert cyber-alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="cyber-alert cyber-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <p><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="cyber-panel">
                    <div class="cyber-panel-header">
                        <h2><i class="fas fa-user-graduate"></i> Student Information</h2>
                    </div>
                    <div class="cyber-panel-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="first_name">
                                        <i class="fas fa-user"></i> First Name *
                                    </label>
                                    <input type="text" id="first_name" name="first_name" class="cyber-input" required
                                        value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="last_name">
                                        <i class="fas fa-user"></i> Last Name *
                                    </label>
                                    <input type="text" id="last_name" name="last_name" class="cyber-input" required
                                        value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="email">
                                        <i class="fas fa-envelope"></i> Email *
                                    </label>
                                    <input type="email" id="email" name="email" class="cyber-input" required
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>

                                <div class="form-group pw-wrapper">
                                    <label class="form-label" for="password">
                                        <i class="fas fa-lock"></i> Password *
                                    </label>
                                    <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePassword('password', this)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="12" cy="12" r="3" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                    <input type="password" id="password" name="password" class="cyber-input pw-input" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="date_of_birth">
                                        <i class="fas fa-calendar"></i> Date of Birth
                                    </label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="cyber-input"
                                        value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="gender">
                                        <i class="fas fa-venus-mars"></i> Gender
                                    </label>
                                    <select id="gender" name="gender" class="cyber-input">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="phone">
                                        <i class="fas fa-phone"></i> Phone
                                    </label>
                                    <input type="tel" id="phone" name="phone" class="cyber-input"
                                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="grade_level">
                                        <i class="fas fa-layer-group"></i> Grade Level
                                    </label>
                                    <select id="grade_level" name="grade_level" class="cyber-input">
                                        <option value="">Select Grade</option>
                                        <option value="100" <?php echo ($_POST['grade_level'] ?? '') === '100' ? 'selected' : ''; ?>>Level 100</option>
                                        <option value="200" <?php echo ($_POST['grade_level'] ?? '') === '200' ? 'selected' : ''; ?>>Level 200</option>
                                        <option value="300" <?php echo ($_POST['grade_level'] ?? '') === '300' ? 'selected' : ''; ?>>Level 300</option>
                                        <option value="400" <?php echo ($_POST['grade_level'] ?? '') === '400' ? 'selected' : ''; ?>>Level 400</option>
                                        <option value="500" <?php echo ($_POST['grade_level'] ?? '') === '500' ? 'selected' : ''; ?>>Level 500</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label" for="address">
                                        <i class="fas fa-map-marker-alt"></i> Address
                                    </label>
                                    <textarea id="address" name="address" class="cyber-input" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="cyber-panel-header" style="margin-top: 30px;">
                                <h2><i class="fas fa-users"></i> Parent/Guardian Information</h2>
                            </div>
                            <div class="form-grid" style="margin-top: 20px;">
                                <div class="form-group">
                                    <label class="form-label" for="parent_name">
                                        <i class="fas fa-user-tie"></i> Parent/Guardian Name
                                    </label>
                                    <input type="text" id="parent_name" name="parent_name" class="cyber-input"
                                        value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="parent_email">
                                        <i class="fas fa-envelope"></i> Parent Email
                                    </label>
                                    <input type="email" id="parent_email" name="parent_email" class="cyber-input"
                                        value="<?php echo htmlspecialchars($_POST['parent_email'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="parent_phone">
                                        <i class="fas fa-phone"></i> Parent Phone
                                    </label>
                                    <input type="tel" id="parent_phone" name="parent_phone" class="cyber-input"
                                        value="<?php echo htmlspecialchars($_POST['parent_phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="status">
                                        <i class="fas fa-toggle-on"></i> Status
                                    </label>
                                    <select id="status" name="status" class="cyber-input">
                                        <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-actions" style="margin-top: 30px;">
                                <button type="submit" class="cyber-btn cyber-btn-primary">
                                    <i class="fas fa-save"></i>
                                    <span>Add Student</span>
                                </button>
                                <a href="students.php" class="cyber-btn cyber-btn-secondary">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel</span>
                                </a>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

<script>
    function togglePassword(fieldId, btn) {
        var input = document.getElementById(fieldId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.94 17.94L6.06 6.06" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7 1.7 0 3.31-.33 4.78-.93" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="#10b981" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
    }
</script>

</html>
