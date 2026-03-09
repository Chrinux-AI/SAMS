<?php
/**
 * Account Confirmation Page
 * Handle OTP verification for accounts created by admin or AI system
 * User enters their OTP and sets their own password securely
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

$message = '';
$message_type = '';
$show_form = true;
$user_email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$token = trim($_GET['token'] ?? '');

// If token provided but no email, try to extract email from token lookup
if ($token !== '' && $user_email === '') {
    $lookup = db()->fetchOne(
        "SELECT email FROM users WHERE verification_token = ? AND token_expiry > NOW()",
        [$token]
    );
    if ($lookup) {
        $user_email = $lookup['email'];
    }
}

// Handle OTP verification POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = preg_replace('/\D/', '', (string)($_POST['otp'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($user_email === '' || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email address is required';
        $message_type = 'error';
    } elseif (strlen($otp) !== 6) {
        $message = 'Please enter a valid 6-digit OTP';
        $message_type = 'error';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        $message_type = 'error';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $message = 'Password must contain uppercase, lowercase, and a number';
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'error';
    } else {
        // Find user by email with a pending CONFIRM token
        $user = db()->fetchOne("
            SELECT id, email, full_name, verification_token, token_expiry
            FROM users
            WHERE email = ?
            AND verification_token LIKE 'CONFIRM:%'
            AND token_expiry > NOW()
        ", [$user_email]);

        if ($user && preg_match('/^CONFIRM:(\d{6}):(\d+)$/', $user['verification_token'], $matches)) {
            $stored_otp = $matches[1];
            $expires_unix = (int)$matches[2];

            if (hash_equals($stored_otp, $otp) && time() < $expires_unix) {
                // Activate account and set password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $update_data = [
                    'verification_token' => null,
                    'is_active' => 1,
                    'email_verified' => 1
                ];
                // Use correct password column
                if (table_has_column('users', 'password')) {
                    $update_data['password'] = $hashed_password;
                }
                if (table_has_column('users', 'password_hash')) {
                    $update_data['password_hash'] = $hashed_password;
                }
                if (table_has_column('users', 'token_expiry')) {
                    $update_data['token_expiry'] = null;
                }
                if (table_has_column('users', 'password_set_at')) {
                    $update_data['password_set_at'] = date('Y-m-d H:i:s');
                }

                update_flexible('users', $update_data, 'id = ?', [$user['id']]);

                // Log successful activation
                try {
                    insert_flexible('account_activations', [
                        'user_id' => $user['id'],
                        'activation_method' => 'otp_confirmation',
                        'activated_at' => date('Y-m-d H:i:s'),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
                    ]);
                } catch (Throwable $e) {
                    error_log('account_activations log failed: ' . $e->getMessage());
                }

                log_activity($user['id'], 'account_confirmed', 'users', $user['id']);

                $message = 'Account activated successfully! Redirecting to login...';
                $message_type = 'success';
                $show_form = false;
                header("refresh:3;url=login.php");
            } else {
                $message = 'Invalid or expired verification code. Please request a new one.';
                $message_type = 'error';
            }
        } else {
            $message = 'No pending confirmation found for this email. The code may have expired.';
            $message_type = 'error';
        }
    }
}

// Verify for GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $user_email !== '') {
    $user = db()->fetchOne("
        SELECT id, email, full_name
        FROM users
        WHERE email = ? AND verification_token LIKE 'CONFIRM:%' AND token_expiry > NOW()
    ", [$user_email]);

    if (!$user) {
        $message = 'No pending confirmation found for this email, or the link has expired.';
        $message_type = 'error';
        $show_form = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Account - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/professional-ui.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/images/icons/favicon.svg">
    <link rel="apple-touch-icon" href="assets/images/icons/icon-192x192.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .confirm-wrapper {
            width: 100%;
            max-width: 480px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: white;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #fbbf24;
        }

        .confirm-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .confirm-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .confirm-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 16px;
            background: linear-gradient(135deg, #10B981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
        }

        .confirm-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }

        .confirm-subtitle {
            color: #6B7280;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .alert-success {
            background: #D1FAE5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            color: #1F2937;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #10B981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .otp-digit {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            color: #1F2937;
            font-family: 'Courier New', monospace;
            transition: all 0.2s;
        }

        .otp-digit:focus {
            outline: none;
            border-color: #10B981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #10B981, #059669);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .security-info {
            background: #F0F9FF;
            border-left: 4px solid #3B82F6;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #1E40AF;
        }

        @media (max-width: 768px) {
            .confirm-wrapper {
                padding: 16px;
            }

            .confirm-card {
                padding: 30px 20px;
            }

            .otp-input-group {
                gap: 6px;
            }

            .otp-digit {
                width: 45px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="confirm-wrapper">
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>

        <div class="confirm-card">
            <div class="confirm-header">
                <div class="ai-badge">
                    <i class="fas fa-robot"></i>
                    AI-Generated Account
                </div>
                <div class="confirm-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <h1 class="confirm-title">Confirm Your Account</h1>
                <p class="confirm-subtitle">
                    Your account was created using our AI system. Please verify your identity and set your password.
                </p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($show_form && (empty($message) || $message_type !== 'success')): ?>
                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
                    <?php if ($user_email === ''): ?>
                    <div class="form-group">
                        <label for="email_input">
                            <i class="fas fa-envelope"></i> Your Email Address
                        </label>
                        <input type="email" id="email_input" name="email" required
                               placeholder="Enter the email used for your account"
                               value="<?php echo htmlspecialchars($user_email); ?>">
                    </div>
                    <?php else: ?>
                    <div style="background:#f0f9ff;border-left:4px solid #3b82f6;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                        <p style="margin:0;color:#1e40af;font-size:0.9rem;"><i class="fas fa-envelope"></i> Confirming: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="otp">
                            <i class="fas fa-shield-alt"></i> Verification Code
                        </label>
                        <div class="otp-input-group">
                            <input type="text" maxlength="1" class="otp-digit" id="otp1" inputmode="numeric" required>
                            <input type="text" maxlength="1" class="otp-digit" id="otp2" inputmode="numeric" required>
                            <input type="text" maxlength="1" class="otp-digit" id="otp3" inputmode="numeric" required>
                            <input type="text" maxlength="1" class="otp-digit" id="otp4" inputmode="numeric" required>
                            <input type="text" maxlength="1" class="otp-digit" id="otp5" inputmode="numeric" required>
                            <input type="text" maxlength="1" class="otp-digit" id="otp6" inputmode="numeric" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Create Password <small style="color:#9ca3af;font-weight:400;">(min <?php echo PASSWORD_MIN_LENGTH; ?> chars, upper+lower+number)</small>
                        </label>
                        <input type="password" id="password" name="password" required
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                               placeholder="Min <?php echo PASSWORD_MIN_LENGTH; ?> characters">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                               placeholder="Re-enter password">
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i>
                        Confirm Account & Set Password
                    </button>
                </form>

                <div class="security-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Security Notice:</strong> This account was created using AI technology.
                    If you did not request this account, please contact support immediately.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // OTP Input Handling
        const otpInputs = document.querySelectorAll('.otp-digit');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                pasteData.split('').forEach((char, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = char;
                    }
                });
                if (pasteData.length === 6) {
                    otpInputs[5].focus();
                }
            });
        });

        // Focus first OTP input
        otpInputs[0].focus();

        // Form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'otp';
            hiddenInput.value = otp;
            e.target.appendChild(hiddenInput);
        });
    </script>
</body>
</html>
