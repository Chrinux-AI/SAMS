<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

$message = '';
$message_type = '';
$token = $_GET['token'] ?? '';

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = sanitize($_POST['token']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'error';
    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        $message_type = 'error';
    } else {
        // Verify token
        $user = db()->fetchOne("
            SELECT id, email FROM users
            WHERE verification_token = ? AND token_expiry > NOW()
        ", [$token]);

        if ($user) {
            // Update password and clear token
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            db()->update('users', [
                'password_hash' => $hashed_password,
                'verification_token' => null,
                'token_expiry' => null
            ], 'id = ?', [$user['id']]);

            $message = 'Password reset successful! You can now login.';
            $message_type = 'success';

            // Redirect to login after 2 seconds
            header("refresh:2;url=login.php");
        } else {
            $message = 'Invalid or expired reset token';
            $message_type = 'error';
        }
    }
}

// Verify token exists for GET request
if (!empty($token) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = db()->fetchOne("
        SELECT id FROM users
        WHERE verification_token = ? AND token_expiry > NOW()
    ", [$token]);

    if (!$user) {
        $message = 'Invalid or expired reset link';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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

        .reset-wrapper {
            width: 100%;
            max-width: 440px;
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

        .reset-card {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        }

        .reset-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .reset-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #fff;
        }

        .reset-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }

        .reset-subtitle {
            color: #6B7280;
            font-size: 0.88rem;
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

        .alert i {
            margin-top: 2px;
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 18px;
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

        .form-group input {
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

        .form-group input:focus {
            outline: none;
            border-color: #4F46E5;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-group input::placeholder {
            color: #9CA3AF;
        }

        .btn-primary {
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

        .btn-primary:hover {
            background: #4338CA;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>

<body>
    <div class="reset-wrapper">
        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to login</a>
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-icon"><i class="fas fa-key"></i></div>
                <h1 class="reset-title">Reset Password</h1>
                <p class="reset-subtitle">Enter your new password</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($message) || $message_type !== 'success'): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" placeholder="Min <?php echo PASSWORD_MIN_LENGTH; ?> characters">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" placeholder="Re-enter password">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
