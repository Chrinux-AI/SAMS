<?php

/**
 * Cyberpunk Login Page - Futuristic Authentication
 */

session_start();

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // CSRF protection
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            // Rate limiting – enforce MAX_LOGIN_ATTEMPTS / LOCKOUT_DURATION
            $lockout_key = 'login_attempts_' . md5($email);
            $attempts = $_SESSION[$lockout_key] ?? ['count' => 0, 'first_at' => 0, 'locked_until' => 0];

            if ($attempts['locked_until'] > time()) {
                $wait = ceil(($attempts['locked_until'] - time()) / 60);
                $error = "Too many failed attempts. Please try again in {$wait} minute(s).";
            } else {
                // Reset if lockout has expired
                if ($attempts['locked_until'] > 0 && $attempts['locked_until'] <= time()) {
                    $attempts = ['count' => 0, 'first_at' => 0, 'locked_until' => 0];
                }

                $user = db()->fetchOne(
                    "SELECT * FROM users WHERE email = ?",
                    [$email]
                );

        $hash = $user['password_hash'] ?? $user['password'] ?? '';
        if ($user && password_verify($password, $hash)) {
            // Check user status
            if (isset($user['email_verified']) && $user['email_verified'] == 0) {
                $error = 'Please verify your email address before logging in. Check your inbox for the verification link.';
            } elseif (isset($user['approved']) && $user['approved'] == 0) {
                $error = 'Your account is pending admin approval. You will receive an email once approved.';
            } elseif (($user['status'] ?? 'active') !== 'active') {
                $error = 'Your account is not active. Please contact the administrator.';
            } else {
                // Clear rate limiter on success
                unset($_SESSION[$lockout_key]);

                // Update last login time
                db()->update('users', [
                    'last_login' => date('Y-m-d H:i:s')
                ], 'id = ?', [$user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_role'] = $user['role'];  // For compatibility with has_role() function
                $_SESSION['assigned_id'] = $user['assigned_id'];
                $_SESSION['last_login'] = $user['last_login'];  // Store previous login time
                set_user_tenant_session((int)$user['id']);

                // Log the login activity
                log_activity($user['id'], 'login', 'users', $user['id']);

                // Redirect based on centralized role mapping
                $dest = get_role_dashboard_path((string)$user['role']);
                header('Location: ' . $dest);
                exit;
            }
        } else {
            // Track failed attempt
            $attempts['count']++;
            if ($attempts['first_at'] === 0) {
                $attempts['first_at'] = time();
            }
            if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
                $attempts['locked_until'] = time() + LOCKOUT_DURATION;
                $error = 'Too many failed attempts. Account temporarily locked for ' . (LOCKOUT_DURATION / 60) . ' minutes.';
                // Log lockout event
                if ($user) {
                    log_activity($user['id'], 'account_lockout', 'users', $user['id']);
                }
            } else {
                $remaining = MAX_LOGIN_ATTEMPTS - $attempts['count'];
                $error = "Invalid credentials. {$remaining} attempt(s) remaining.";
            }
            $_SESSION[$lockout_key] = $attempts;
        }
            } // end lockout check
        } // end CSRF check
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include_once "includes/favicon-loader.php"; ?>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <title>Sign In - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/professional-ui.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-wrapper { width: 100%; max-width: 440px; }
        .login-card { background: #fff; border-radius: 16px; padding: 48px 40px; box-shadow: 0 25px 50px rgba(0,0,0,0.15); }
        .login-brand { text-align: center; margin-bottom: 32px; }
        .login-brand .brand-icon { width: 72px; height: 72px; background: var(--primary); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .login-brand .brand-icon i { font-size: 2rem; color: #fff; }
        .login-brand h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
        .login-brand p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 0.875rem; }
        .alert.error { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; }
        .alert.success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #16A34A; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .form-group label i { margin-right: 6px; color: var(--text-muted); }
        .input-wrapper { position: relative; }
        .input-wrapper input { width: 100%; padding: 12px 16px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.95rem; font-family: 'Inter', sans-serif; background: #F9FAFB; transition: all 0.2s; box-sizing: border-box; }
        .input-wrapper input:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .pw-toggle-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; }
        .pw-toggle-btn:hover { color: var(--primary); }

        .btn-login { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-login:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,0.3); }
        .btn-login:active { transform: translateY(0); }

        .login-footer { text-align: center; margin-top: 24px; }
        .login-footer a { color: var(--primary); text-decoration: none; font-weight: 500; font-size: 0.875rem; }
        .login-footer a:hover { text-decoration: underline; }
        .login-footer .register-text { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); color: var(--text-muted); font-size: 0.875rem; }
        .login-footer .register-text a { color: var(--primary); font-weight: 600; }

        .biometric-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #F59E0B, #D97706); color: #fff; border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s; }
        .biometric-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(245,158,11,0.3); }

        .divider { display: flex; align-items: center; margin: 20px 0; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
        .divider span { padding: 0 12px; }

        @media (max-width: 480px) { .login-card { padding: 32px 24px; } }
    </style>
    <script src="assets/js/biometric-auth.js"></script>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
                <h1><?php echo APP_NAME; ?></h1>
                <p>Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert error"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success); ?></span></div>
            <?php endif; ?>

            <button type="button" class="biometric-btn" onclick="performBiometricLogin()">
                <i class="fas fa-fingerprint"></i> Sign in with Biometrics
            </button>

            <div class="divider"><span>or sign in with email</span></div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="pw-toggle-btn" onclick="togglePassword('password', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" name="login" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In</button>
            </form>

            <div class="login-footer">
                <a href="forgot-password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                <div class="register-text">Don't have an account? <a href="register.php">Create Account</a></div>
            </div>
        </div>
    </div>

    <script>
        async function performBiometricLogin() {
            const btn = event.target.closest('.biometric-btn');
            const originalHTML = btn.innerHTML;
            if (!window.biometricAuth || !window.biometricAuth.supported) {
                alert('Biometric authentication is not supported on this browser/device.');
                return;
            }
            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
                btn.disabled = true;
                const result = await window.biometricAuth.login();
                if (result.success) {
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Authenticated';
                    btn.style.background = 'linear-gradient(135deg, #16A34A, #15803D)';
                    setTimeout(() => { window.location.href = result.redirect; }, 1000);
                } else { throw new Error(result.error || 'Authentication failed'); }
            } catch (error) {
                btn.innerHTML = '<i class="fas fa-times-circle"></i> Failed';
                btn.style.background = 'linear-gradient(135deg, #DC2626, #B91C1C)';
                setTimeout(() => {
                    alert(error.message || 'Biometric authentication failed.');
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 1500);
            }
        }
        function togglePassword(fieldId, btn) {
            var input = document.getElementById(fieldId);
            var icon = btn.querySelector('i');
            if (!input || !icon) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        }
        document.getElementById('email').focus();
    </script>
</body>
</html>
