<?php
/**
 * Verify OTP and Set Password
 * Step 2: Verify OTP and allow password creation
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/AccountActivation.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $otp = implode('', $_POST['otp'] ?? []);
    $step = $_POST['step'] ?? 'verify';
    
    $activation = new SAMS_AccountActivation();
    
    if ($step === 'verify') {
        // Verify OTP
        $result = $activation->verifyOTP($token, $otp);
        
        if ($result['success']) {
            // Show password form
            $show_password_form = true;
            $verify_token = $token;
        } else {
            $message = $result['error'];
            $message_type = 'error';
        }
    } elseif ($step === 'set_password') {
        // Set password
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $result = $activation->createPassword($token, $password, $confirm_password);
        
        if ($result['success']) {
            // Redirect to login with success message
            header('Location: login.php?message=account_activated');
            exit;
        } else {
            $message = $result['error'];
            $message_type = 'error';
            $show_password_form = true;
            $verify_token = $token;
        }
    }
}

$page_title = 'Set Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .activation-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            text-align: center;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.5rem;
        }
        h1 { color: #1e293b; font-size: 1.75rem; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; margin-bottom: 2rem; }
        .form-group { text-align: left; margin-bottom: 1.5rem; }
        .form-group label { display: block; color: #374151; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input {
            width: 100%; padding: 0.75rem 1rem; border: 2px solid #e2e8f0;
            border-radius: 10px; font-size: 1rem; transition: all 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        .btn {
            width: 100%; padding: 1rem; border: none; border-radius: 10px;
            font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.2s;
            background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3); }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; text-align: left; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .password-requirements {
            text-align: left; background: #f8fafc; padding: 1rem; border-radius: 10px;
            margin-bottom: 1.5rem; font-size: 0.875rem;
        }
        .password-requirements h4 { color: #1e40af; margin-bottom: 0.5rem; }
        .password-requirements ul { margin: 0; padding-left: 1.25rem; color: #3b82f6; }
        .password-requirements li { margin-bottom: 0.25rem; }
        .success-icon { width: 100px; height: 100px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: #166534; font-size: 3rem; }
    </style>
</head>
<body>
    <div class="activation-container">
        <?php if (isset($show_password_form) && $show_password_form): ?>
            <div class="logo">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Create Password</h1>
            <p class="subtitle">Set a secure password for your account</p>
            
            <?php if ($message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <div class="password-requirements">
                <h4><i class="fas fa-shield-alt"></i> Password Requirements</h4>
                <ul>
                    <li>At least 8 characters long</li>
                    <li>Contains uppercase and lowercase letters</li>
                    <li>Contains at least one number</li>
                    <li>Contains at least one special character (!@#$%^&*)</li>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($verify_token) ?>">
                <input type="hidden" name="step" value="set_password">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-check"></i> Complete Activation
                </button>
            </form>
            
        <?php else: ?>
            <div class="logo">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h1>Verify Code</h1>
            <p class="subtitle">Enter the 6-digit code sent to your email</p>
            
            <?php if ($message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_POST['token'] ?? $_GET['token'] ?? '') ?>">
                <input type="hidden" name="step" value="verify">
                
                <div style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1.5rem;">
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required style="width: 50px; height: 60px; text-align: center; font-size: 1.5rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required style="width: 50px; height: 60px; text-align: center; font-size: 1.5rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required style="width: 50px; height: 60px; text-align: center; font-size: 1.5rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required style="width: 50px; height: 60px; text-align: center; font-size: 1.5rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required style="width: 50px; height: 60px; text-align: center; font-size: 1.5rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required style="width: 50px; height: 60px; text-align: center; font-size: 1.5rem; border: 2px solid #e2e8f0; border-radius: 10px;">
                </div>
                
                <button type="submit" class="btn">Verify Code</button>
            </form>
            
            <script>
                const inputs = document.querySelectorAll('input[name="otp[]"]');
                inputs.forEach((input, index) => {
                    input.addEventListener('input', () => {
                        if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
                    });
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace' && !input.value && index > 0) inputs[index - 1].focus();
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
