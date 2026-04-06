<?php
/**
 * SAMS AI Password Setup Page
 * Handles password creation after OTP verification
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Get parameters
$userId = $_GET['user_id'] ?? null;
$entityType = $_GET['type'] ?? null;
$token = $_GET['token'] ?? null;
$email = $_GET['email'] ?? null;

// Validate parameters
if (empty($userId) || empty($entityType)) {
    die('Invalid parameters. Please use the verification link from your email.');
}

// Verify user exists and is not active
$db = db();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 0 AND email_verified = 0", [$userId]);

if (!$user) {
    die('Invalid or expired verification link.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate password
    $errors = [];
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        // Update user password and activate account
        $db->update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => 1,
            'password_set_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$userId]);
        
        // Log password set
        error_log("Password set for user ID: $userId");
        
        // Redirect to success page
        header('Location: ' . BASE_URL . '/admin/ai/setup-success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - SAMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .header {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .form-container {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }
        
        .password-strength-bar.weak {
            background: #ef4444;
            width: 33%;
        }
        
        .password-strength-bar.medium {
            background: #f59e0b;
            width: 66%;
        }
        
        .password-strength-bar.strong {
            background: #10b981;
            width: 100%;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .error-messages {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            color: #dc2626;
        }
        
        .error-messages ul {
            list-style: none;
            padding: 0;
        }
        
        .error-messages li {
            margin-bottom: 4px;
        }
        
        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }
        
        .password-requirements h4 {
            font-size: 14px;
            margin-bottom: 12px;
            color: #374151;
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
        }
        
        .password-requirements li {
            font-size: 13px;
            margin-bottom: 6px;
            color: #6b7280;
            padding-left: 20px;
            position: relative;
        }
        
        .password-requirements li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
        
        .password-requirements li.invalid:before {
            content: "✗";
            color: #ef4444;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8fafc;
            color: #6b7280;
            font-size: 14px;
        }
        
        .footer a {
            color: #4F46E5;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 10px;
            }
            
            .form-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Set Your Password</h1>
            <p>Create a secure password for your <?php echo ucfirst($entityType); ?> account</p>
        </div>
        
        <div class="form-container">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="passwordForm">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                </div>
                
                <button type="submit" class="btn" id="submitBtn">Set Password</button>
            </form>
            
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li id="req-length">At least 8 characters</li>
                    <li id="req-uppercase">One uppercase letter</li>
                    <li id="req-lowercase">One lowercase letter</li>
                    <li id="req-number">One number</li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p>Already have an account? <a href="<?php echo BASE_URL; ?>/login.php">Sign In</a></p>
        </div>
    </div>
    
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const strengthBar = document.getElementById('strengthBar');
        const submitBtn = document.getElementById('submitBtn');
        
        const requirements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number')
        };
        
        function checkPasswordStrength(password) {
            let strength = 0;
            let checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password)
            };
            
            // Update requirement indicators
            Object.keys(checks).forEach(key => {
                if (checks[key]) {
                    requirements[key].classList.remove('invalid');
                } else {
                    requirements[key].classList.add('invalid');
                }
            });
            
            // Calculate strength
            Object.values(checks).forEach(check => {
                if (check) strength++;
            });
            
            // Update strength bar
            strengthBar.className = 'password-strength-bar';
            if (strength <= 1) {
                strengthBar.classList.add('weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
            
            return strength;
        }
        
        function validateForm() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            const isPasswordValid = checkPasswordStrength(password) >= 4;
            const isPasswordMatch = password === confirmPassword;
            
            submitBtn.disabled = !isPasswordValid || !isPasswordMatch;
        }
        
        passwordInput.addEventListener('input', validateForm);
        confirmPasswordInput.addEventListener('input', validateForm);
        
        // Initial validation
        validateForm();
        
        // Form submission
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Setting Password...';
        });
    </script>
</body>
</html>
