<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';
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
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
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

        .forgot-wrapper {
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

        .forgot-card {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .forgot-icon {
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

        .forgot-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }

        .forgot-subtitle {
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

        .pw-wrapper {
            position: relative;
            display: flex;
        }

        .pw-wrapper input {
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
        }

        .pw-wrapper .pw-toggle:hover {
            color: #4F46E5;
        }

        .otp-input-group {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 20px 0;
        }

        .otp-digit {
            width: 46px;
            height: 54px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            background: #F9FAFB;
            border: 2px solid #D1D5DB;
            border-radius: 10px;
            color: #1F2937;
            font-family: 'Courier New', monospace;
            transition: all 0.2s;
        }

        .otp-digit:focus {
            outline: none;
            border-color: #4F46E5;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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

        .btn-outline {
            width: 100%;
            padding: 10px;
            background: #fff;
            border: 1px solid #D1D5DB;
            border-radius: 10px;
            color: #374151;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
        }

        .btn-outline:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }

        .otp-limits {
            margin-top: 12px;
            font-size: 0.8rem;
            color: #6B7280;
            line-height: 1.5;
            text-align: center;
        }

        .steps {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .step {
            width: 32px;
            height: 4px;
            border-radius: 4px;
            background: #E5E7EB;
            transition: background 0.3s;
        }

        .step.active {
            background: #4F46E5;
        }

        .step.done {
            background: #10B981;
        }
    </style>
</head>

<body>
    <div class="forgot-wrapper">
        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to login</a>
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="forgot-icon">
                    <i class="fas fa-<?php echo $step === 'reset' ? 'lock-open' : ($step === 'otp' ? 'shield-alt' : 'key'); ?>"></i>
                </div>
                <h1 class="forgot-title">
                    <?php echo $step === 'reset' ? 'Reset Password' : ($step === 'otp' ? 'Verify OTP' : 'Forgot Password'); ?>
                </h1>
                <p class="forgot-subtitle">
                    <?php echo $step === 'reset' ? 'Enter your new password' : ($step === 'otp' ? 'Enter the 6-digit code sent to your email' : 'Enter your email to receive a reset code'); ?>
                </p>
            </div>

            <!-- Step indicators -->
            <div class="steps">
                <div class="step <?php echo $step === 'email' ? 'active' : 'done'; ?>"></div>
                <div class="step <?php echo $step === 'otp' ? 'active' : ($step === 'reset' ? 'done' : ''); ?>"></div>
                <div class="step <?php echo $step === 'reset' ? 'active' : ''; ?>"></div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($step === 'email'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="your@email.com">
                    </div>
                    <button type="submit" name="send_otp" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reset Code
                    </button>
                </form>

            <?php elseif ($step === 'otp'): ?>
                <form method="POST" id="otpForm">
                    <div class="otp-input-group">
                        <input type="text" maxlength="1" class="otp-digit" id="otp1" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-digit" id="otp2" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-digit" id="otp3" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-digit" id="otp4" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-digit" id="otp5" inputmode="numeric" required>
                        <input type="text" maxlength="1" class="otp-digit" id="otp6" inputmode="numeric" required>
                    </div>
                    <input type="hidden" name="otp" id="otpValue">
                    <button type="submit" name="verify_otp" class="btn-primary">
                        <i class="fas fa-check-circle"></i> Verify Code
                    </button>
                    <button type="submit" name="resend_otp" class="btn-outline">
                        <i class="fas fa-redo"></i> Resend Code
                    </button>
                    <?php if ($otp_info): ?>
                        <div class="otp-limits">
                            <div><i class="fas fa-info-circle"></i> <strong>Security Information:</strong></div>
                            <div>Requests remaining this hour: <?php echo (int)$otp_info['remaining_requests']; ?>/<?php echo (int)$otp_info['max_requests']; ?></div>
                            <div>Maximum verify attempts per OTP: <?php echo (int)$otp_info['max_attempts']; ?></div>
                            <?php if ((int)$otp_info['resend_in'] > 0): ?>
                                <div><i class="fas fa-clock"></i> Resend available in <?php echo htmlspecialchars(seconds_to_human((int)$otp_info['resend_in'])); ?></div>
                            <?php endif; ?>
                            <?php if ((int)$otp_info['lockout_in'] > 0): ?>
                                <div><i class="fas fa-lock"></i> Locked for <?php echo htmlspecialchars(seconds_to_human((int)$otp_info['lockout_in'])); ?></div>
                            <?php endif; ?>
                            <div style="margin-top: 8px; padding: 8px; background: #f0f9ff; border-radius: 6px; font-size: 0.75rem;">
                                <i class="fas fa-shield-alt"></i> For your security, OTPs expire after 15 minutes
                            </div>
                        </div>
                    <?php endif; ?>
                </form>

            <?php elseif ($step === 'reset'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> New Password</label>
                        <div class="pw-wrapper">
                            <input type="password" id="password" name="password" required placeholder="Min <?php echo PASSWORD_MIN_LENGTH; ?> characters" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                            <button type="button" class="pw-toggle" onclick="togglePassword('password',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                        <div class="pw-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                            <button type="button" class="pw-toggle" onclick="togglePassword('confirm_password',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" name="reset_password" class="btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

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
                if (!submitter || submitter.name !== 'verify_otp') {
                    return;
                }
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
            const icon = btn.querySelector('i');
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
