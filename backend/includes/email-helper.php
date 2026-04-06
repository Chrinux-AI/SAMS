<?php

/**
 * Email Helper Functions - PHPMailer Implementation
 */

require_once __DIR__ . '/config.php';

/**
 * Send email using PHPMailer (Gmail SMTP)
 */
function send_smtp_email($to, $subject, $message, $isHTML = true)
{
    // Check if PHPMailer is installed
    $autoload_path = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        error_log('PHPMailer not installed. Email cannot be sent.');
        return false;
    }

    require_once $autoload_path;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION; // 'tls'
        $mail->Port       = SMTP_PORT; // 587

        // Disable SSL verification (for localhost/development only)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Content
        $mail->isHTML($isHTML);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Quick send email wrapper
 */
function quick_send_email($to, $subject, $html_message)
{
    return send_smtp_email($to, $subject, $html_message, true);
}

/**
 * Send OTP account confirmation email (passwordless onboarding)
 * Used when admin/AI creates accounts — user sets their own password via OTP
 */
function send_account_otp_email($email, $name, $otp, $assigned_id = null, $role = 'student')
{
    $confirm_url = APP_URL . '/confirm-account.php?email=' . urlencode($email);
    $role_label = ucfirst($role);
    $id_type = $role === 'student' ? 'Student ID' : ($role === 'teacher' ? 'Employee ID' : 'User ID');
    $year = date('Y');

    $id_section = '';
    if ($assigned_id) {
        $id_section = "
            <div style='background:#e0f2fe;padding:20px;border-radius:12px;margin:20px 0;border-left:4px solid #0ea5e9;'>
                <h4 style='color:#0369a1;margin:0 0 8px 0;'>Your Assigned ID</h4>
                <div style='font-size:28px;font-weight:800;color:#1e40af;letter-spacing:2px;'>$id_type: $assigned_id</div>
                <p style='margin:8px 0 0 0;color:#0369a1;font-size:13px;'>Save this ID. You need it for attendance and login.</p>
            </div>";
    }

    $subject = "Confirm Your $role_label Account - " . APP_NAME;
    $body = "
    <div style='max-width:560px;margin:0 auto;font-family:Inter,Arial,sans-serif;'>
        <div style='background:linear-gradient(135deg,#10B981,#059669);color:white;padding:32px;text-align:center;border-radius:16px 16px 0 0;'>
            <div style='font-size:40px;margin-bottom:12px;'>🔐</div>
            <h1 style='margin:0;font-size:22px;'>Account Confirmation</h1>
            <p style='margin:8px 0 0 0;opacity:0.9;font-size:14px;'>Your $role_label account has been created</p>
        </div>
        <div style='background:white;padding:32px;border:1px solid #e5e7eb;'>
            <p style='color:#374151;font-size:15px;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p style='color:#6b7280;font-size:14px;line-height:1.6;'>
                A $role_label account has been created for you. To activate your account and set your password,
                use the verification code below:
            </p>

            <div style='background:#f0fdf4;border:2px dashed #10b981;border-radius:12px;padding:24px;text-align:center;margin:24px 0;'>
                <p style='color:#065f46;font-size:13px;margin:0 0 8px 0;font-weight:600;'>Your 6-digit verification code:</p>
                <div style='font-size:36px;font-weight:800;letter-spacing:8px;color:#059669;font-family:\"Courier New\",monospace;'>$otp</div>
                <p style='color:#6b7280;font-size:12px;margin:8px 0 0 0;'>Valid for 24 hours</p>
            </div>

            $id_section

            <p style='text-align:center;margin:28px 0;'>
                <a href='$confirm_url' style='display:inline-block;background:linear-gradient(135deg,#10B981,#059669);color:white;padding:14px 36px;text-decoration:none;border-radius:10px;font-weight:700;font-size:15px;'>
                    Confirm My Account
                </a>
            </p>

            <p style='color:#9ca3af;font-size:12px;text-align:center;'>Or go to: <span style='word-break:break-all;'>$confirm_url</span></p>

            <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:14px;border-radius:8px;margin-top:20px;'>
                <p style='margin:0;color:#92400e;font-size:13px;'>
                    <strong>⚠️ Security:</strong> Never share this code. We will never ask for it by phone or chat.
                    If you did not request this account, please ignore this email.
                </p>
            </div>
        </div>
        <div style='background:#f9fafb;padding:20px;text-align:center;color:#9ca3af;font-size:11px;border-radius:0 0 16px 16px;border:1px solid #e5e7eb;border-top:0;'>
            <p style='margin:0;'>&copy; $year " . APP_NAME . ". All rights reserved.</p>
        </div>
    </div>";

    return send_smtp_email($email, $subject, $body, true);
}
