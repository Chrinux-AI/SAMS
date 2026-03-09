<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            throw new Exception('First name, last name, and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        // Update user information
        db()->update(
            'users',
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone
            ],
            'id = ?',
            [$student_id]
        );

        // Update student information
        db()->update(
            'students',
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone
            ],
            'user_id = ?',
            [$student_id]
        );

        $_SESSION['full_name'] = $first_name . ' ' . $last_name;
        $full_name = $_SESSION['full_name'];
        $success_message = 'Profile updated successfully!';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get student information
$student_info = db()->fetchOne("SELECT s.*, u.username, u.email as user_email
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?", [$student_id]);

if (!$student_info) {
    $student_info = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$student_id]);
}

$page_title = 'My Profile';
$page_icon = 'user-circle';
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

</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
                </div>
            </header>
            <div class="app-layout">

                <?php if ($success_message): ?>
                    <div class="alert-success" style="padding:15px 20px;border-radius:10px;margin-bottom:20px;background:rgba(0,255,127,0.1);border:1px solid var(--neon-green);color:var(--neon-green);">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert-error" style="padding:15px 20px;border-radius:10px;margin-bottom:20px;background:rgba(255,69,0,0.1);border:1px solid var(--cyber-red);color:var(--cyber-red);">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-user-edit"></i> <span>Personal Information</span></div>
                    </div>
                    <div class="card-body">
                        <form method="POST" style="max-width:800px;">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
                                <div class="form-group">
                                    <label for="first_name" class="form-label"><i class="fas fa-user"></i> First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student_info['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name" class="form-label"><i class="fas fa-user"></i> Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student_info['last_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" id="email" name="email" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student_info['email'] ?? $student_info['user_email'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone" class="form-label"><i class="fas fa-phone"></i> Phone</label>
                                    <input type="tel" id="phone" name="phone" class="cyber-input"
                                        value="<?php echo htmlspecialchars($student_info['phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group" style="margin-top:20px;">
                                <label for="student_id" class="form-label"><i class="fas fa-id-card"></i> Student ID</label>
                                <input type="text" id="student_id" class="cyber-input"
                                    value="<?php echo htmlspecialchars($student_info['student_id'] ?? 'Not Assigned'); ?>" disabled>
                            </div>

                            <div style="margin-top:30px;display:flex;gap:15px;">
                                <button type="submit" class="cyber-btn" style="background:var(--neon-green);">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="dashboard.php" class="cyber-btn cyber-btn-outline">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="holo-card" style="margin-top:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-lock"></i> <span>Change Password</span></div>
                    </div>
                    <div class="card-body">
                        <p style="color:var(--text-muted);margin-bottom:15px;">
                            <i class="fas fa-info-circle"></i> To change your password, please contact your administrator or use the password reset feature on the login page.
                        </p>
                        <a href="../reset-password.php" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-key"></i> Reset Password
                        </a>
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
