<?php
session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/database.php';
require_once 'includes/email-helper.php';

$message = '';
$message_type = '';
$step = 'email'; // email, otp, reset

const OTP_LENGTH = 6;
const OTP_TTL_SECONDS = 900; // 15 minutes
const OTP_RESEND_COOLDOWN_SECONDS = 60;
const OTP_MAX_REQUESTS_PER_HOUR = 5;
const OTP_MAX_VERIFY_ATTEMPTS = 5;
const OTP_LOCKOUT_SECONDS = 900; // 15 minutes

$otp_info = null;

function otp_rate_key($email)
{
    return strtolower(trim((string)$email));
}

function get_otp_rate_state($email)
{
    $key = otp_rate_key($email);
    $all = $_SESSION['otp_rate_limit'] ?? [];
    return $all[$key] ?? [
        'window_start' => time(),
        'request_count' => 0,
        'last_sent_at' => 0,
        'verify_attempts' => 0,
        'locked_until' => 0
    ];
}

function set_otp_rate_state($email, $state)
{
    $key = otp_rate_key($email);
    if (!isset($_SESSION['otp_rate_limit'])) {
        $_SESSION['otp_rate_limit'] = [];
    }
    $_SESSION['otp_rate_limit'][$key] = $state;
}

function check_otp_request_limit($email)
{
    $state = get_otp_rate_state($email);
    $now = time();

    if (($state['locked_until'] ?? 0) > $now) {
        return [false, max(1, (int)$state['locked_until'] - $now), $state];
    }

    if (($state['window_start'] ?? 0) + 3600 <= $now) {
        $state['window_start'] = $now;
        $state['request_count'] = 0;
    }

    if (($state['request_count'] ?? 0) >= OTP_MAX_REQUESTS_PER_HOUR) {
        $state['locked_until'] = $now + OTP_LOCKOUT_SECONDS;
        set_otp_rate_state($email, $state);
        return [false, OTP_LOCKOUT_SECONDS, $state];
    }

    $next_allowed = ($state['last_sent_at'] ?? 0) + OTP_RESEND_COOLDOWN_SECONDS;
    if ($next_allowed > $now) {
        return [false, $next_allowed - $now, $state];
    }

    return [true, 0, $state];
}

function seconds_to_human($seconds)
{
    $seconds = max(0, (int)$seconds);
    $minutes = intdiv($seconds, 60);
    $remainingSeconds = $seconds % 60;
    if ($minutes > 0) {
        return $minutes . 'm ' . $remainingSeconds . 's';
    }
    return $remainingSeconds . 's';
}

function mark_otp_sent($email, $state)
{
    $now = time();
    $state['last_sent_at'] = $now;
    $state['request_count'] = (int)($state['request_count'] ?? 0) + 1;
    $state['verify_attempts'] = 0;
    $state['locked_until'] = 0;
    set_otp_rate_state($email, $state);
}

function mark_otp_verify_failed($email)
{
    $state = get_otp_rate_state($email);
    $state['verify_attempts'] = (int)($state['verify_attempts'] ?? 0) + 1;
    if ($state['verify_attempts'] >= OTP_MAX_VERIFY_ATTEMPTS) {
        $state['locked_until'] = time() + OTP_LOCKOUT_SECONDS;
    }
    set_otp_rate_state($email, $state);
    return $state;
}

function reset_otp_verify_attempts($email)
{
    $state = get_otp_rate_state($email);
    $state['verify_attempts'] = 0;
    set_otp_rate_state($email, $state);
}

function users_column_exists($column_name)
{
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $schema = db()->fetchAll("SHOW COLUMNS FROM users");
        foreach ($schema as $col) {
            $columns[$col['Field']] = true;
        }
    }
    return isset($columns[$column_name]);
}

function otp_log_table_exists()
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $row = db()->fetchOne("SHOW TABLES LIKE 'otp_verification_log'");
    $exists = (bool)$row;
    return $exists;
}

function store_reset_otp_for_email($email, $otp, $otp_expiry)
{
    if (users_column_exists('reset_otp') && users_column_exists('reset_otp_expiry')) {
        return db()->query(
            "UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE email = ?",
            [$otp, $otp_expiry, $email]
        );
    }

    // Fallback schema: persist OTP inside verification_token as OTP:<code>:<unix_expiry>
    if (users_column_exists('verification_token')) {
        $token_payload = 'OTP:' . $otp . ':' . strtotime($otp_expiry);
        return db()->query(
            "UPDATE users SET verification_token = ?, updated_at = NOW() WHERE email = ?",
            [$token_payload, $email]
        );
    }

    return false;
}

function get_current_otp_info($email)
{
    if (users_column_exists('reset_otp') && users_column_exists('reset_otp_expiry')) {
        return db()->fetchOne(
            "SELECT reset_otp, reset_otp_expiry FROM users WHERE email = ? AND reset_otp IS NOT NULL",
            [$email]
        );
    }

    if (users_column_exists('verification_token')) {
        $user = db()->fetchOne("SELECT verification_token FROM users WHERE email = ? AND verification_token IS NOT NULL", [$email]);
        if ($user && preg_match('/^OTP:(\d{6}):(\d+)$/', $user['verification_token'], $m)) {
            return [
                'reset_otp' => $m[1],
                'reset_otp_expiry' => date('Y-m-d H:i:s', (int)$m[2])
            ];
        }
    }

    return null;
}

function verify_reset_otp_for_email($email, $otp)
{
    $otp = trim((string)$otp);
    if ($otp === '') {
        return false;
    }

    // Log verification attempt for security monitoring
    error_log("OTP verification attempt for email: " . substr($email, 0, 3) . "*** with OTP: " . substr($otp, 0, 2) . "***");

    if (users_column_exists('reset_otp') && users_column_exists('reset_otp_expiry')) {
        // Check for recent failed attempts to prevent brute force (if optional log table exists)
        $recent_attempts = 0;
        if (otp_log_table_exists()) {
            $recent_attempts = db()->fetchOne(
                "SELECT COUNT(*) as count FROM otp_verification_log
                 WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND success = 0",
                [$email]
            )['count'] ?? 0;
        }

        if ($recent_attempts >= 10) {
            error_log("Too many OTP verification attempts for email: " . $email);
            return false;
        }

        $user = db()->fetchOne(
            "SELECT id, email, reset_otp, reset_otp_expiry FROM users
             WHERE email = ? AND reset_otp IS NOT NULL",
            [$email]
        );

        if ($user) {
            // Log this attempt (optional table)
            $success = hash_equals($user['reset_otp'], $otp) && strtotime($user['reset_otp_expiry']) > time();
            if (otp_log_table_exists()) {
                db()->insert('otp_verification_log', [
                    'email' => $email,
                    'otp_used' => $otp,
                    'success' => $success ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            if ($success) {
                return ['id' => $user['id'], 'email' => $user['email']];
            }
        }

        return false;
    }

    if (users_column_exists('verification_token')) {
        $user = db()->fetchOne("SELECT id, email, verification_token FROM users WHERE email = ? AND verification_token IS NOT NULL", [$email]);
        if (!$user || empty($user['verification_token'])) {
            return false;
        }

        // Check for recent failed attempts (if optional log table exists)
        $recent_attempts = 0;
        if (otp_log_table_exists()) {
            $recent_attempts = db()->fetchOne(
                "SELECT COUNT(*) as count FROM otp_verification_log
                 WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND success = 0",
                [$email]
            )['count'] ?? 0;
        }

        if ($recent_attempts >= 10) {
            error_log("Too many OTP verification attempts for email: " . $email);
            return false;
        }

        if (preg_match('/^OTP:(\d{6}):(\d+)$/', $user['verification_token'], $m)) {
            $stored_otp = $m[1];
            $expires_unix = (int)$m[2];

            $success = hash_equals($stored_otp, $otp) && time() < $expires_unix;

            // Log this attempt (optional table)
            if (otp_log_table_exists()) {
                db()->insert('otp_verification_log', [
                    'email' => $email,
                    'otp_used' => $otp,
                    'success' => $success ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            if ($success) {
                return ['id' => $user['id'], 'email' => $user['email']];
            }
        }
    }

    return false;
}

function clear_reset_otp_for_email($email)
{
    if (users_column_exists('reset_otp') && users_column_exists('reset_otp_expiry')) {
        return db()->query(
            "UPDATE users SET reset_otp = NULL, reset_otp_expiry = NULL WHERE email = ?",
            [$email]
        );
    }

    if (users_column_exists('verification_token')) {
        return db()->query(
            "UPDATE users SET verification_token = NULL WHERE email = ?",
            [$email]
        );
    }

    return false;
}

function send_otp_to_user($user, $email)
{
    [$allowed, $waitSeconds, $state] = check_otp_request_limit($email);
    if (!$allowed) {
        return [false, "Too many OTP requests. Try again in {$waitSeconds} seconds."];
    }

    $otp = sprintf("%0" . OTP_LENGTH . "d", mt_rand(1, 999999));
    $otp_expiry = date('Y-m-d H:i:s', time() + OTP_TTL_SECONDS);
    $stored = store_reset_otp_for_email($email, $otp, $otp_expiry);
    if (!$stored) {
        return [false, 'Failed to store OTP. Please contact support.'];
    }

    $subject = "Password Reset OTP - Attendance System";
    $firstName = htmlspecialchars((string)($user['first_name'] ?? $user['full_name'] ?? 'User'));
    $year = date('Y');
    $email_body = <<<HTML
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #00BFFF, #8A2BE2); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .otp-box { background: #f0f0f0; border: 2px dashed #00BFFF; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0; }
        .otp-code { font-size: 32px; font-weight: bold; color: #00BFFF; letter-spacing: 8px; font-family: 'Courier New', monospace; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$firstName}</strong>,</p>
            <p>You have requested to reset your password. Please use the OTP code below to verify your identity:</p>
            <div class="otp-box">
                <div style="color: #666; font-size: 14px; margin-bottom: 10px;">Your OTP Code</div>
                <div class="otp-code">{$otp}</div>
                <div style="color: #999; font-size: 12px; margin-top: 10px;">Valid for 15 minutes</div>
            </div>
            <p><strong>Security Notice:</strong></p>
            <ul>
                <li>Do not share this code with anyone</li>
                <li>This code expires in 15 minutes</li>
                <li>If you didn't request this, please ignore this email</li>
            </ul>
        </div>
        <div class="footer">
            <p>This is an automated message from the Attendance Management System.</p>
            <p>&copy; {$year} Attendance System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

    if (!quick_send_email($email, $subject, $email_body)) {
        return [false, 'Failed to send OTP. Please try again.'];
    }

    mark_otp_sent($email, $state);
    return [true, 'OTP sent successfully! Check your email.'];
}

function consume_otp_request_for_unknown_email($email)
{
    [$allowed, $waitSeconds, $state] = check_otp_request_limit($email);
    if (!$allowed) {
        return [false, "Too many OTP requests. Try again in " . seconds_to_human($waitSeconds) . "."];
    }

    // Intentionally consume limit for unknown emails to prevent user enumeration abuse.
    mark_otp_sent($email, $state);
    return [true, 'If this email exists, an OTP has been sent.'];
}

// Handle email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $message_type = 'error';
    } else {
        $user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

        if ($user) {
            [$ok, $msg] = send_otp_to_user($user, $email);
            $message = $msg;
            $message_type = $ok ? 'success' : 'error';
            if ($ok) {
                $_SESSION['reset_email'] = $email;
                $step = 'otp';
            }
        } else {
            // Don't reveal if email exists for security; still consume request limit.
            [$ok, $msg] = consume_otp_request_for_unknown_email($email);
            $_SESSION['reset_email'] = $email;
            $message = $msg;
            $message_type = $ok ? 'success' : 'error';
            if ($ok) {
                $step = 'otp';
            }
        }
    }
}

// Handle OTP resend from OTP step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $email = $_SESSION['reset_email'] ?? '';
    if (!$email) {
        $message = 'Session expired. Please start again.';
        $message_type = 'error';
        $step = 'email';
    } else {
        // Clear any existing OTP before sending new one
        clear_reset_otp_for_email($email);

        $user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        if ($user) {
            [$ok, $msg] = send_otp_to_user($user, $email);
            $message = $msg;
            $message_type = $ok ? 'success' : 'error';

            if ($ok) {
                // Reset verification attempts when new OTP is sent
                reset_otp_verify_attempts($email);
            }
        } else {
            [$ok, $msg] = consume_otp_request_for_unknown_email($email);
            $message = $msg;
            $message_type = $ok ? 'success' : 'error';
        }
        $step = 'otp';
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $email = $_SESSION['reset_email'] ?? '';
    if (!$email) {
        $message = 'Session expired. Please start again.';
        $message_type = 'error';
        $step = 'email';
    } else {
        $step = 'otp';
    }
    $otp = preg_replace('/\D/', '', (string)($_POST['otp'] ?? ''));

    if ($step === 'otp' && strlen($otp) !== OTP_LENGTH) {
        $message = 'Please enter a valid 6-digit OTP.';
        $message_type = 'error';
        $step = 'otp';
    } elseif ($step === 'otp') {
        $rateState = get_otp_rate_state($email);
        if (($rateState['locked_until'] ?? 0) > time()) {
            $wait = (int)$rateState['locked_until'] - time();
            $message = 'Too many failed attempts. Please wait ' . seconds_to_human($wait) . ' before trying again.';
            $message_type = 'error';
            $step = 'otp';
        } else {
            $user = verify_reset_otp_for_email($email, $otp);

            if ($user) {
                reset_otp_verify_attempts($email);
                $_SESSION['verified_email'] = $email;
                $_SESSION['verified_user_id'] = $user['id'];
                $step = 'reset';
                $message = 'OTP verified! Please set your new password.';
                $message_type = 'success';

                // Clear OTP after successful verification
                clear_reset_otp_for_email($email);
            } else {
                $state = mark_otp_verify_failed($email);

                // Get current OTP info to provide better feedback
                $current_otp_info = get_current_otp_info($email);

                if (($state['locked_until'] ?? 0) > time()) {
                    clear_reset_otp_for_email($email);
                    $wait = (int)$state['locked_until'] - time();
                    $message = 'Too many invalid attempts. OTP invalidated. Request a new OTP in ' . seconds_to_human($wait) . '.';
                } else {
                    $remaining = max(0, OTP_MAX_VERIFY_ATTEMPTS - (int)($state['verify_attempts'] ?? 0));

                    // Check if OTP has expired
                    if ($current_otp_info && strtotime($current_otp_info['expiry']) < time()) {
                        $message = 'OTP has expired. Please request a new one.';
                    } else {
                        $message = "Invalid OTP. {$remaining} attempt(s) remaining.";
                    }
                }
                $message_type = 'error';
                $step = 'otp';
            }
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = $_SESSION['verified_email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        $message_type = 'error';
        $step = 'reset';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'error';
        $step = 'reset';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        db()->query(
            "UPDATE users SET password_hash = ? WHERE email = ?",
            [$password_hash, $email]
        );
        clear_reset_otp_for_email($email);

        $message = 'Password reset successfully! You can now login.';
        $message_type = 'success';

        // Clear session
        unset($_SESSION['reset_email']);
        unset($_SESSION['verified_email']);

        // Redirect to login after 3 seconds
        header("refresh:3;url=login.php");
    }
}

// Determine current step from session
if (isset($_SESSION['verified_email'])) {
    $step = 'reset';
} elseif (isset($_SESSION['reset_email'])) {
    $step = 'otp';
}

if ($step === 'otp' && !empty($_SESSION['reset_email'])) {
    $otpEmail = $_SESSION['reset_email'];
    $rateState = get_otp_rate_state($otpEmail);
    $now = time();
    $remainingRequests = max(0, OTP_MAX_REQUESTS_PER_HOUR - (int)($rateState['request_count'] ?? 0));
    $resendAvailableIn = max(0, (($rateState['last_sent_at'] ?? 0) + OTP_RESEND_COOLDOWN_SECONDS) - $now);
    $lockoutRemaining = max(0, ((int)($rateState['locked_until'] ?? 0)) - $now);

    $otp_info = [
        'remaining_requests' => $remainingRequests,
        'max_requests' => OTP_MAX_REQUESTS_PER_HOUR,
        'resend_in' => $resendAvailableIn,
        'lockout_in' => $lockoutRemaining,
        'max_attempts' => OTP_MAX_VERIFY_ATTEMPTS
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <?php include_once "frontend/includes/favicon-loader.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?php echo APP_NAME; ?> Academic Sentinel</title>
    <meta name="theme-color" content="#000666">

    <!-- Logo & Favicon -->
    <link rel="icon" type="image/png" href="frontend/assets/logo/favicon.png">
    <link rel="shortcut icon" href="frontend/assets/logo/favicon.png">
    <link rel="apple-touch-icon" href="frontend/assets/logo/favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#000666",
                        "primary-container": "#1a237e",
                        "on-primary": "#ffffff",
                        "on-primary-container": "#8690ee",
                        "on-primary-fixed": "#000767",
                        "surface-tint": "#4c56af",
                        "background": "#f8f9fa",
                        "on-background": "#191c1d",
                        "surface": "#f8f9fa",
                        "on-surface": "#191c1d",
                        "on-surface-variant": "#454652",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-low": "#f3f4f5",
                        "surface-container": "#edeeef",
                        "surface-container-high": "#e7e8e9",
                        "outline": "#767683",
                        "outline-variant": "#c6c5d4",
                        "primary-fixed": "#e0e0ff",
                        "error": "#ba1a1a",
                        "secondary-container": "#cfe6f2"
                    },
                    fontFamily: {
                        "headline": ["Manrope"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "2xl": "0.75rem",
                        "3xl": "1rem"
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .premium-gradient {
            background: linear-gradient(135deg, #000666 0%, #1a237e 100%);
        }
    </style>
</head>

<body class="bg-background font-body text-on-surface flex flex-col min-h-screen">
    <!-- Hero Background Texture -->
    <div class="fixed inset-0 z-0 pointer-events-none opacity-40">
        <div class="absolute top-[-10%] right-[-5%] w-[40%] h-[40%] rounded-full blur-[120px] bg-primary/10"></div>
        <div class="absolute bottom-[-5%] left-[-5%] w-[30%] h-[30%] rounded-full blur-[100px] bg-secondary-container/20"></div>
    </div>

    <!-- Main Content Canvas -->
    <main class="relative z-10 flex-grow flex items-center justify-center p-6">
        <div class="w-full max-w-[440px]">
            <!-- Branding Anchor -->
            <div class="text-center mb-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-surface-container-lowest shadow-[0_20px_40px_rgba(0,7,103,0.06)] mb-6">
                    <span class="material-symbols-outlined text-primary text-3xl"><?php echo $step === 'reset' ? 'lock_open' : ($step === 'otp' ? 'shield' : 'key'); ?></span>
                </div>
                <h1 class="font-headline text-3xl font-extrabold tracking-tighter text-primary mb-2"><?php echo APP_NAME; ?></h1>
                <p class="font-label text-on-surface-variant text-sm tracking-wide uppercase">Academic Sentinel</p>
            </div>

            <!-- Step Progress Bar -->
            <div class="flex justify-center gap-2 mb-8">
                <div class="h-1 w-10 rounded-full <?php echo $step === 'email' ? 'bg-primary' : 'bg-emerald-500'; ?> transition-all"></div>
                <div class="h-1 w-10 rounded-full <?php echo $step === 'otp' ? 'bg-primary' : ($step === 'reset' ? 'bg-emerald-500' : 'bg-outline-variant/30'); ?> transition-all"></div>
                <div class="h-1 w-10 rounded-full <?php echo $step === 'reset' ? 'bg-primary' : 'bg-outline-variant/30'; ?> transition-all"></div>
            </div>

            <!-- Recovery Card -->
            <div class="bg-surface-container-lowest rounded-xl p-8 shadow-[0_20px_40px_rgba(0,7,103,0.06)] border border-outline-variant/10">
                <div class="mb-8">
                    <h2 class="font-headline text-xl font-bold text-on-surface mb-2">
                        <?php echo $step === 'reset' ? 'Reset Password' : ($step === 'otp' ? 'Verify OTP' : 'Password Recovery'); ?>
                    </h2>
                    <p class="text-on-surface-variant text-sm leading-relaxed">
                        <?php echo $step === 'reset' ? 'Enter your new password below.' : ($step === 'otp' ? 'Enter the 6-digit code sent to your email.' : 'Enter your institutional email address. We will send a secure code to reset your access.'); ?>
                    </p>
                </div>

                <?php if ($message): ?>
                    <?php $is_success = ($message_type === 'success'); ?>
                    <div class="flex items-start gap-3 p-4 rounded-xl <?php echo $is_success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?> text-sm mb-6">
                        <span class="material-symbols-outlined text-lg mt-0.5"><?php echo $is_success ? 'check_circle' : 'error'; ?></span>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'email'): ?>
                    <form method="POST" class="space-y-6">
                        <div class="space-y-2">
                            <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="email">Institution Email</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-xl">mail</span>
                                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-4 text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/60 text-on-surface" id="email" name="email" placeholder="name@institution.edu" type="email" required autofocus>
                            </div>
                        </div>
                        <button class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 group" type="submit" name="send_otp">
                            Send Recovery Code
                            <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </button>
                    </form>

                <?php elseif ($step === 'otp'): ?>
                    <form method="POST" id="otpForm" class="space-y-6">
                        <div class="flex gap-2 justify-center my-4">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="text" maxlength="1" inputmode="numeric"
                                    class="w-12 h-14 text-center text-lg font-bold bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all font-mono otp-digit"
                                    id="otp<?php echo $i; ?>" required>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="otp" id="otpValue">
                        <button type="submit" name="verify_otp" class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">verified</span>
                            Verify Code
                        </button>
                        <button type="submit" name="resend_otp" class="w-full py-3 px-6 rounded-lg bg-surface-container-low text-on-surface-variant font-medium text-sm hover:bg-surface-container transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">refresh</span>
                            Resend Code
                        </button>

                        <?php if ($otp_info): ?>
                            <div class="mt-4 p-4 rounded-xl bg-surface-container-low text-xs text-on-surface-variant space-y-2">
                                <div class="flex items-center gap-2 font-bold">
                                    <span class="material-symbols-outlined text-sm">info</span>
                                    Security Information
                                </div>
                                <div>Requests remaining this hour: <?php echo (int)$otp_info['remaining_requests']; ?>/<?php echo (int)$otp_info['max_requests']; ?></div>
                                <div>Maximum verify attempts per OTP: <?php echo (int)$otp_info['max_attempts']; ?></div>
                                <?php if ((int)$otp_info['resend_in'] > 0): ?>
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">schedule</span>
                                        Resend available in <?php echo htmlspecialchars(seconds_to_human((int)$otp_info['resend_in'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ((int)$otp_info['lockout_in'] > 0): ?>
                                    <div class="flex items-center gap-1 text-red-600">
                                        <span class="material-symbols-outlined text-sm">lock</span>
                                        Locked for <?php echo htmlspecialchars(seconds_to_human((int)$otp_info['lockout_in'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2 p-2 bg-blue-50 rounded-lg text-blue-700 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">shield</span>
                                    For your security, OTPs expire after 15 minutes
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                <?php elseif ($step === 'reset'): ?>
                    <form method="POST" class="space-y-6">
                        <div class="space-y-2">
                            <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="password">New Password</label>
                            <div class="relative">
                                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/60 text-on-surface" id="password" name="password" type="password" placeholder="Min <?php echo PASSWORD_MIN_LENGTH; ?> characters" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('password', this)">
                                    <span class="material-symbols-outlined text-xl">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="confirm_password">Confirm Password</label>
                            <div class="relative">
                                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/60 text-on-surface" id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter password" required>
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('confirm_password', this)">
                                    <span class="material-symbols-outlined text-xl">visibility</span>
                                </button>
                            </div>
                        </div>
                        <button class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2" type="submit" name="reset_password">
                            <span class="material-symbols-outlined text-lg">save</span>
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Secondary Link -->
                <div class="mt-8 pt-6 border-t border-outline-variant/10 text-center">
                    <a class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-container transition-colors group" href="login.php">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to Login
                    </a>
                </div>
            </div>

            <!-- Footer Context -->
            <div class="mt-12 text-center">
                <p class="text-xs text-outline font-label tracking-tight">
                    Locked out? Contact your <span class="font-semibold text-on-surface-variant">System Administrator</span> for manual reset assistance.
                </p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 py-8 px-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-6">
            <span class="text-xs font-label text-outline">Â© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></span>
            <div class="h-1 w-1 rounded-full bg-outline-variant"></div>
            <a class="text-xs font-label text-on-surface-variant hover:text-primary transition-colors" href="#">Privacy Policy</a>
            <div class="h-1 w-1 rounded-full bg-outline-variant"></div>
            <a class="text-xs font-label text-on-surface-variant hover:text-primary transition-colors" href="#">Security Standards</a>
        </div>
        <div class="flex items-center gap-2 text-on-surface-variant">
            <span class="material-symbols-outlined text-lg">help_outline</span>
            <span class="text-xs font-medium">Need help?</span>
        </div>
    </footer>

    <script>
        <?php if ($step === 'otp'): ?>
            const otpInputs = document.querySelectorAll('.otp-digit');
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    if (e.target.value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
                    updateOTPValue();
                });
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) otpInputs[index - 1].focus();
                });
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                    pasteData.split('').forEach((char, i) => {
                        if (otpInputs[i]) otpInputs[i].value = char;
                    });
                    updateOTPValue();
                    if (pasteData.length === 6) otpInputs[5].focus();
                });
            });

            function updateOTPValue() {
                document.getElementById('otpValue').value = Array.from(otpInputs).map(i => i.value).join('');
            }
            document.getElementById('otpForm').addEventListener('submit', function(e) {
                const submitter = e.submitter;
                if (!submitter || submitter.name !== 'verify_otp') return;
                updateOTPValue();
                if (document.getElementById('otpValue').value.length !== 6) {
                    e.preventDefault();
                    alert('Please enter the complete 6-digit code');
                }
            });
            otpInputs[0].focus();
        <?php endif; ?>

        function togglePassword(fieldId, btn) {
            const input = document.getElementById(fieldId);
            const icon = btn.querySelector('.material-symbols-outlined');
            if (!input || !icon) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
        }
    </script>
</body>

</html>
