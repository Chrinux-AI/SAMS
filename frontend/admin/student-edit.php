<?php

/**
 * Edit Student
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$student_id = $_GET['id'] ?? 0;

// Get student details
$student = db()->fetchOne("
    SELECT s.*, u.email, u.first_name, u.last_name, u.full_name, u.phone, u.status, u.id as user_id,
           c.grade_level, c.class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
", [$student_id]);

if (!$student) {
    header('Location: students.php');
    exit;
}

$page_title = 'Edit Student';
$page_icon = 'user-edit';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    // Check if email already exists for other users
    if (empty($errors)) {
        $existing = db()->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $student['user_id']]);
        if ($existing) {
            $errors[] = 'Email already exists';
        }
    }

    if (empty($errors)) {
        try {
            db()->query("START TRANSACTION");

            // Update user account
            $user_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'full_name' => $first_name . ' ' . $last_name,
                'email' => $email,
                'phone' => $phone ?: null,
                'status' => $status
            ];

            // Update password if provided
            if (!empty($password)) {
                $user_data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            // Update student record (only columns that exist in students table)
            $student_data = [
                'date_of_birth' => $date_of_birth ?: null,
                'gender' => $gender ?: null,
            ];

            // AdminEditGuarantee pipeline: Write → Verify → Broadcast → Log
            $result = AdminEditGuarantee::execute('users', (int)$student['user_id'], $user_data, function ($data) use ($student, $student_data, $student_id) {
                db()->update('users', $data, 'id = ?', ['id' => $student['user_id']]);
                db()->update('students', $student_data, 'id = ?', ['id' => $student_id]);
                return true;
            });

            db()->query("COMMIT");

            $success = $result['message'] ?? "Student updated successfully!";

            // Refresh student data
            $student = db()->fetchOne("
                SELECT s.*, u.email, u.first_name, u.last_name, u.full_name, u.phone, u.status, u.id as user_id,
                       c.grade_level, c.class_name
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN classes c ON s.class_id = c.id
                WHERE s.id = ?
            ", [$student_id]);
        } catch (Exception $e) {
            db()->query("ROLLBACK");
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
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
                    <div class="page-icon-orb">
                        <i class="fas fa-<?php echo $page_icon; ?>"></i>
                    </div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <a href="student-view.php?id=<?php echo $student_id; ?>" class="cyber-btn cyber-btn-secondary">
                        <i class="fas fa-eye"></i>
                        <span>View Student</span>
                    </a>
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
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="first_name">
                                        <i class="fas fa-user"></i> First Name *
                                    </label>
                                    <input type="text" id="first_name" name="first_name" class="cyber-input" required
                                        value="<?php echo htmlspecialchars($student['first_name']); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="last_name">
                                        <i class="fas fa-user"></i> Last Name *
                                    </label>
                                    <input type="text" id="last_name" name="last_name" class="cyber-input" required
                                        value="<?php echo htmlspecialchars($student['last_name']); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="email">
                                        <i class="fas fa-envelope"></i> Email *
                                    </label>
                                    <input type="email" id="email" name="email" class="cyber-input" required
                                        value="<?php echo htmlspecialchars($student['email']); ?>">
                                </div>

                                <div class="form-group pw-wrapper">
                                    <label class="form-label" for="password">
                                        <i class="fas fa-lock"></i> New Password
                                    </label>
                                    <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePassword('password', this)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#00BFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="12" cy="12" r="3" stroke="#00BFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                    <input type="password" id="password" name="password" class="cyber-input pw-input" placeholder="Leave blank to keep current password">
                                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Only fill if you want to change the password</small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="date_of_birth">
                                        <i class="fas fa-calendar"></i> Date of Birth
                                    </label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="gender">
                                        <i class="fas fa-venus-mars"></i> Gender
                                    </label>
                                    <select id="gender" name="gender" class="cyber-input">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo $student['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $student['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo $student['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="phone">
                                        <i class="fas fa-phone"></i> Phone
                                    </label>
                                    <input type="tel" id="phone" name="phone" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="grade_level">
                                        <i class="fas fa-layer-group"></i> Grade Level
                                    </label>
                                    <select id="grade_level" name="grade_level" class="cyber-input">
                                        <option value="">Select Grade</option>
                                        <option value="100" <?php echo $student['grade_level'] === '100' ? 'selected' : ''; ?>>Level 100</option>
                                        <option value="200" <?php echo $student['grade_level'] === '200' ? 'selected' : ''; ?>>Level 200</option>
                                        <option value="300" <?php echo $student['grade_level'] === '300' ? 'selected' : ''; ?>>Level 300</option>
                                        <option value="400" <?php echo $student['grade_level'] === '400' ? 'selected' : ''; ?>>Level 400</option>
                                        <option value="500" <?php echo $student['grade_level'] === '500' ? 'selected' : ''; ?>>Level 500</option>
                                    </select>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label" for="address">
                                        <i class="fas fa-map-marker-alt"></i> Address
                                    </label>
                                    <textarea id="address" name="address" class="cyber-input" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
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
                                        value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="parent_email">
                                        <i class="fas fa-envelope"></i> Parent Email
                                    </label>
                                    <input type="email" id="parent_email" name="parent_email" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="parent_phone">
                                        <i class="fas fa-phone"></i> Parent Phone
                                    </label>
                                    <input type="tel" id="parent_phone" name="parent_phone" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="status">
                                        <i class="fas fa-toggle-on"></i> Status
                                    </label>
                                    <select id="status" name="status" class="cyber-input">
                                        <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-actions" style="margin-top: 30px;">
                                <button type="submit" class="cyber-btn cyber-btn-primary">
                                    <i class="fas fa-save"></i>
                                    <span>Update Student</span>
                                </button>
                                <a href="student-view.php?id=<?php echo $student_id; ?>" class="cyber-btn cyber-btn-secondary">
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
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.94 17.94L6.06 6.06" stroke="#00BFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7 1.7 0 3.31-.33 4.78-.93" stroke="#00BFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#00BFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="#00BFFF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
    }
</script>

</html>
