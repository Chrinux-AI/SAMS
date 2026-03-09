<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

$message = '';
$message_type = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // Find user with this token
    $user = db()->fetchOne("SELECT * FROM users WHERE email_verification_token = ?", [$token]);

    if ($user) {
        // Check if token has expired (10 minutes)
        if ($user['token_expires_at'] && strtotime($user['token_expires_at']) < time()) {
            $message = 'Verification link has expired. Please request a new verification email.';
            $message_type = 'error';
        } elseif ($user['email_verified'] == 1) {
            $message = 'Your email has already been verified. Please wait for admin approval.';
            $message_type = 'info';
        } else {
            // Verify the email
            $updated = db()->update(
                'users',
                ['email_verified' => 1, 'email_verification_token' => null, 'token_expires_at' => null],
                'id = ?',
                [$user['id']]
            );

            if ($updated) {
                $message = 'Email verified successfully! Your account is now pending admin approval. You will receive an email once approved.';
                $message_type = 'success';
            } else {
                $message = 'Failed to verify email. Please try again or contact support.';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'Invalid or expired verification token.';
        $message_type = 'error';
    }
} else {
    $message = 'No verification token provided.';
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo APP_NAME; ?></title>
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

        .verify-wrapper {
            width: 100%;
            max-width: 480px;
            text-align: center;
        }

        .verify-card {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 48px 36px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        }

        .verify-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .verify-icon.success {
            background: #ECFDF5;
            color: #059669;
        }

        .verify-icon.error {
            background: #FEF2F2;
            color: #DC2626;
        }

        .verify-icon.info {
            background: #EEF2FF;
            color: #4F46E5;
        }

        .verify-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }

        .verify-message {
            color: #6B7280;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: #4F46E5;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .btn-primary:hover {
            background: #4338CA;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>

<body>
    <div class="verify-wrapper">
        <div class="verify-card">
            <?php if ($message_type === 'success'): ?>
                <div class="verify-icon success"><i class="fas fa-check-circle"></i></div>
            <?php elseif ($message_type === 'error'): ?>
                <div class="verify-icon error"><i class="fas fa-times-circle"></i></div>
            <?php else: ?>
                <div class="verify-icon info"><i class="fas fa-info-circle"></i></div>
            <?php endif; ?>

            <h1 class="verify-title"><?php echo $message_type === 'success' ? 'Verification Complete' : ($message_type === 'error' ? 'Verification Failed' : 'Email Status'); ?></h1>
            <p class="verify-message"><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php" class="btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
        </div>
    </div>
</body>

</html>
