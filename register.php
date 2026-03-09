<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

$message = '';
$message_type = '';

// Check if registration is enabled
$registration_enabled = true;
$settings_table = db()->fetchOne("SHOW TABLES LIKE 'system_settings'");
if ($settings_table) {
    $setting = db()->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'");
    $registration_enabled = $setting ? (bool)$setting['setting_value'] : true;
}

if (!$registration_enabled) {
    $message = 'Registration is currently disabled. Please contact the administrator.';
    $message_type = 'error';
}

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && $registration_enabled) {
    $errors = [];

    try {
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $role = sanitize($_POST['role'] ?? '');

        // Block admin role registration
        if ($role === 'admin') {
            $errors[] = 'Admin registration is not allowed. Contact system administrator.';
        }
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($username)) $errors[] = 'Username is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (empty($password)) $errors[] = 'Password is required';
        if (empty($first_name)) $errors[] = 'First name is required';
        if (empty($last_name)) $errors[] = 'Last name is required';
        if (empty($role)) $errors[] = 'Role is required';

        if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
        if (!in_array($role, ['student', 'parent', 'teacher'])) $errors[] = 'Invalid role selected';

        if ($role === 'student') {
            if (empty($_POST['date_of_birth'])) $errors[] = 'Date of birth is required for students';
            if (empty($_POST['grade_level'])) $errors[] = 'Grade level is required for students';
        }

        $existing = db()->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) $errors[] = 'Username already exists';

        $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) $errors[] = 'Email already registered';

        if (empty($errors)) {
            // Generate verification token with 10-minute expiration
            $verification_token = bin2hex(random_bytes(32));
            $token_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $user_data = [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => $role,
                'phone' => $phone,
                'status' => 'pending',
                'email_verified' => 0,
                'email_verification_token' => $verification_token,
                'token_expires_at' => $token_expires_at,
                'approved' => 0
            ];

            $user_id = db()->insert('users', $user_data);

            if ($user_id) {
                attach_user_to_tenant((int)$user_id);

                // Generate student ID with YEAR+sequential format
                $assigned_id = null; // Initialize assigned ID variable

                if ($role === 'student') {
                    $year = date('Y');
                    $count = db()->count('students') + 1;
                    $student_id = $year . str_pad($count, 4, '0', STR_PAD_LEFT);
                    $assigned_id = 'STU' . $student_id; // Format for display

                    $student_data = [
                        'user_id' => $user_id,
                        'student_id' => $student_id,
                        'assigned_student_id' => $student_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => $phone,
                        'date_of_birth' => $_POST['date_of_birth'],
                        'grade_level' => (int)$_POST['grade_level'],
                        'status' => 'pending'
                    ];
                    db()->insert('students', $student_data);
                } elseif ($role === 'teacher') {
                    // Generate teacher employee ID
                    $year = date('Y');
                    $count = db()->count('teachers') + 1;
                    $teacher_id = $year . str_pad($count, 4, '0', STR_PAD_LEFT);
                    $assigned_id = 'EMP' . $teacher_id; // Format for display

                    // Teachers table insert would happen here (if exists)
                }

                // Send verification email
                $verification_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify-email.php?token=" . $verification_token;

                $to = $email;
                $subject = "Verify Your Email - Attendance System";
                $year = date('Y');
                $email_message = <<<HTML
<html>
<head><?php include_once "includes/favicon-loader.php"; ?>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .button { display: inline-block; background: #00BFFF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Email Verification</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$first_name} {$last_name}</strong>,</p>
            <p>Thank you for registering with the Attendance Management System!</p>
            <p>Please verify your email address by clicking the button below:</p>
            <p style="text-align: center;">
                <a href="{$verification_link}" class="button">Verify My Email</a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="background: #f9f9f9; padding: 10px; border-radius: 5px; word-break: break-all; font-size: 12px;">{$verification_link}</p>
            <p><strong>This link expires in 10 minutes.</strong> Please verify soon!</p>
            <p><strong>Important:</strong> After email verification, your account must be approved by an administrator before you can login.</p>
        </div>
        <div class="footer">
            <p>If you didn't register for this account, please ignore this email.</p>
            <p>&copy; {$year} Attendance System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

                // Send verification email using proper function
                $email_sent = send_verification_email($email, $first_name . ' ' . $last_name, $verification_token, $assigned_id, $role);

                if ($email_sent) {
                    $message = 'Registration successful! Please check your email (' . $email . ') to verify your account. ⏱️ Verification link expires in 10 minutes.';
                    $message_type = 'success';
                } else {
                    $message = 'Registration successful but verification email failed to send. Please contact administrator.';
                    $message_type = 'warning';
                }
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    } catch (Exception $e) {
        $errors[] = 'An error occurred: ' . $e->getMessage();
    }

    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F3F4F6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .register-wrapper {
            width: 100%;
            max-width: 640px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6B7280;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #4F46E5;
        }

        .register-card {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        }

        .register-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .register-header .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #fff;
            margin-bottom: 14px;
        }

        .register-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }

        .register-header p {
            color: #6B7280;
            font-size: 0.9rem;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .alert-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        .alert-warning {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            color: #92400E;
        }

        .alert i {
            margin-top: 2px;
            flex-shrink: 0;
        }

        .role-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 24px;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 12px;
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .role-label:hover {
            border-color: #C7D2FE;
            background: #EEF2FF;
        }

        .role-option input:checked+.role-label {
            border-color: #4F46E5;
            background: #EEF2FF;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .role-label i {
            font-size: 1.6rem;
            color: #6B7280;
            transition: color 0.2s;
        }

        .role-option input:checked+.role-label i {
            color: #4F46E5;
        }

        .role-label span {
            color: #374151;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-group label i {
            color: #9CA3AF;
            margin-right: 4px;
            font-size: 0.75rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            background: #F9FAFB;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            color: #1F2937;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4F46E5;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-group input::placeholder {
            color: #9CA3AF;
        }

        .pw-wrapper {
            position: relative;
            display: flex;
        }

        .pw-wrapper .pw-input {
            flex: 1;
            padding-right: 42px;
        }

        .pw-wrapper .pw-toggle {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 40px;
            background: none;
            border: none;
            color: #9CA3AF;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .pw-wrapper .pw-toggle:hover {
            color: #4F46E5;
        }

        .conditional-fields {
            display: none;
            grid-column: span 2;
        }

        .conditional-fields.active {
            display: block;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #4F46E5;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #4338CA;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #6B7280;
            font-size: 0.88rem;
        }

        .login-link a {
            color: #4F46E5;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: #4338CA;
            text-decoration: underline;
        }

        @media(max-width:600px) {
            .register-card {
                padding: 28px 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }

            .conditional-fields {
                grid-column: span 1;
            }

            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="register-wrapper">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>
        <div class="register-card">
            <div class="register-header">
                <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
                <h1>Create Account</h1>
                <p>Register for <?php echo APP_NAME; ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'error'); ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-circle' : 'exclamation-triangle'); ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($registration_enabled && (!$message || $message_type !== 'success')): ?>
                <form method="POST">
                    <div class="role-selector">
                        <div class="role-option">
                            <input type="radio" id="student" name="role" value="student" required <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'checked' : ''; ?>>
                            <label for="student" class="role-label">
                                <i class="fas fa-user-graduate"></i>
                                <span>Student</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="parent" name="role" value="parent" <?php echo (isset($_POST['role']) && $_POST['role'] === 'parent') ? 'checked' : ''; ?>>
                            <label for="parent" class="role-label">
                                <i class="fas fa-user-friends"></i>
                                <span>Parent</span>
                            </label>
                        </div>
                        <div class="role-option">
                            <input type="radio" id="teacher" name="role" value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'checked' : ''; ?>>
                            <label for="teacher" class="role-label">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Teacher</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                            <input type="text" id="first_name" name="first_name" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" id="last_name" name="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" id="email" name="email" placeholder="john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username"><i class="fas fa-at"></i> Username</label>
                            <input type="text" id="username" name="username" placeholder="johndoe" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                            <input type="tel" id="phone" name="phone" placeholder="+1234567890" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password</label>
                            <div class="pw-wrapper">
                                <input type="password" id="password" name="password" class="pw-input" placeholder="Min 8 characters" required>
                                <button type="button" class="pw-toggle" onclick="togglePassword('password',this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                            <div class="pw-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="pw-input" placeholder="Repeat password" required>
                                <button type="button" class="pw-toggle" onclick="togglePassword('confirm_password',this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div id="student_fields" class="conditional-fields">
                            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;">
                                <div class="form-group">
                                    <label for="date_of_birth"><i class="fas fa-calendar"></i> Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth">
                                </div>
                                <div class="form-group">
                                    <label for="grade_level"><i class="fas fa-graduation-cap"></i> Level</label>
                                    <select id="grade_level" name="grade_level">
                                        <option value="">Select Level</option>
                                        <option value="100">100 Level</option>
                                        <option value="200">200 Level</option>
                                        <option value="300">300 Level</option>
                                        <option value="400">400 Level</option>
                                        <option value="500">500 Level</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <button type="submit" name="register" class="btn-submit">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>

    <script>
        const roleInputs = document.querySelectorAll('input[name="role"]');
        const studentFields = document.getElementById('student_fields');
        roleInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value === 'student') {
                    studentFields.classList.add('active');
                    document.getElementById('date_of_birth').required = true;
                    document.getElementById('grade_level').required = true;
                } else {
                    studentFields.classList.remove('active');
                    document.getElementById('date_of_birth').required = false;
                    document.getElementById('grade_level').required = false;
                }
            });
            // Trigger on load for pre-selected role
            if (input.checked) input.dispatchEvent(new Event('change'));
        });

        function togglePassword(fieldId, btn) {
            var input = document.getElementById(fieldId);
            var icon = btn.querySelector('i');
            if (!input || !icon) return;
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>

</html>
