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
<html class="light" lang="en">
<head>
  <?php include_once "includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirm Account | <?php echo APP_NAME; ?> Academic Sentinel</title>
  <meta name="theme-color" content="#000666">

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
            "primary": "#000666", "primary-container": "#1a237e", "on-primary": "#ffffff",
            "on-primary-container": "#8690ee", "on-primary-fixed": "#000767",
            "surface-tint": "#4c56af", "background": "#f8f9fa", "on-background": "#191c1d",
            "surface": "#f8f9fa", "on-surface": "#191c1d", "on-surface-variant": "#454652",
            "surface-container-lowest": "#ffffff", "surface-container-low": "#f3f4f5",
            "surface-container": "#edeeef", "surface-container-high": "#e7e8e9",
            "outline": "#767683", "outline-variant": "#c6c5d4",
            "primary-fixed": "#e0e0ff", "error": "#ba1a1a",
            "secondary-container": "#cfe6f2"
          },
          fontFamily: { "headline": ["Manrope"], "body": ["Inter"], "label": ["Inter"] },
          borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "2xl": "0.75rem", "3xl": "1rem"},
        },
      },
    }
  </script>
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #000666 0%, #1a237e 100%); }
  </style>
</head>

<body class="bg-background font-body text-on-surface flex flex-col min-h-screen">
  <!-- Hero Background Texture -->
  <div class="fixed inset-0 z-0 pointer-events-none opacity-40">
    <div class="absolute top-[-10%] right-[-5%] w-[40%] h-[40%] rounded-full blur-[120px] bg-primary/10"></div>
    <div class="absolute bottom-[-5%] left-[-5%] w-[30%] h-[30%] rounded-full blur-[100px] bg-secondary-container/20"></div>
  </div>

  <main class="relative z-10 flex-grow flex items-center justify-center p-6 mt-12 mb-12">
    <div class="w-full max-w-[480px]">
      
      <!-- Center Branding -->
      <div class="text-center mb-8">
        <h1 class="font-headline text-3xl font-extrabold tracking-tighter text-primary mb-2"><?php echo APP_NAME; ?></h1>
        <p class="font-label text-on-surface-variant text-sm tracking-wide uppercase">Academic Sentinel</p>
      </div>

      <!-- Main Card -->
      <div class="bg-surface-container-lowest rounded-xl p-8 shadow-[0_20px_40px_rgba(0,7,103,0.06)] border border-outline-variant/10">
        
        <div class="mb-8 text-center">
          <div class="inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full border border-primary/20 bg-primary/5 text-primary text-xs font-bold tracking-wide">
            <span class="material-symbols-outlined text-sm">robot_2</span> AI-Assisted Provisioning
          </div>
          <h2 class="font-headline text-2xl font-bold text-on-surface mb-2">Confirm Account</h2>
          <p class="text-on-surface-variant text-sm leading-relaxed max-w-sm mx-auto">
            Your account was provisioned securely. Please verify your identity and finalize your login credentials.
          </p>
        </div>

        <?php if ($message): ?>
          <div class="flex items-start gap-3 p-4 rounded-xl <?php echo $message_type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?> text-sm mb-6">
            <span class="material-symbols-outlined text-lg mt-0.5"><?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
          </div>
        <?php endif; ?>

        <?php if ($show_form && (empty($message) || $message_type !== 'success')): ?>
          <form method="POST" class="space-y-6">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
            
            <?php if ($user_email === ''): ?>
              <div class="space-y-2">
                <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="email_input">Registered Email</label>
                <div class="relative">
                  <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-xl">mail</span>
                  <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 pl-12 pr-4 text-sm focus:ring-2 focus:ring-primary/20 transition-all text-on-surface" id="email_input" name="email" type="email" placeholder="name@institution.edu" required value="<?php echo htmlspecialchars($user_email); ?>">
                </div>
              </div>
            <?php else: ?>
              <div class="p-4 bg-surface-container-low border-l-4 border-primary rounded-r-xl text-sm mb-6">
                <span class="text-on-surface-variant">Confirming:</span>
                <span class="font-bold text-on-surface ml-1"><?php echo htmlspecialchars($user_email); ?></span>
              </div>
            <?php endif; ?>

            <div class="space-y-3">
              <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="email_input">OTP Verification Code</label>
              <div class="flex justify-between gap-2">
                <?php for ($i = 0; $i < 6; $i++): ?>
                  <input type="text" maxlength="1" inputmode="numeric" required class="w-12 h-14 text-center text-xl font-bold bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all font-mono otp-digit text-on-surface">
                <?php endfor; ?>
              </div>
            </div>

            <div class="space-y-2 pt-4 border-t border-outline-variant/10">
              <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="password">Create Password <span class="text-[0.6rem] ml-1 lowercase text-outline-variant font-normal">(min <?php echo PASSWORD_MIN_LENGTH; ?> chars, upper+lower+num)</span></label>
              <div class="relative">
                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm focus:ring-2 focus:ring-primary/20 transition-all text-on-surface" id="password" name="password" type="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('password', this)">
                  <span class="material-symbols-outlined text-xl">visibility</span>
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="confirm_password">Confirm Password</label>
              <div class="relative">
                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm focus:ring-2 focus:ring-primary/20 transition-all text-on-surface" id="confirm_password" name="confirm_password" type="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('confirm_password', this)">
                  <span class="material-symbols-outlined text-xl">visibility</span>
                </button>
              </div>
            </div>

            <button type="submit" class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 group mt-6">
              <span class="material-symbols-outlined text-lg">check_circle</span>
              Finalize Account
            </button>
          </form>
          
          <script>
            // Handle OTP inputs
            const otpInputs = document.querySelectorAll('.otp-digit');
            otpInputs.forEach((input, index) => {
              input.addEventListener('input', () => {
                if (input.value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
              });
              input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) otpInputs[index - 1].focus();
              });
              input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                pasteData.split('').forEach((char, i) => {
                  if (otpInputs[i]) otpInputs[i].value = char;
                });
                if (pasteData.length === 6) otpInputs[5].focus();
              });
            });
            if (otpInputs.length > 0) otpInputs[0].focus();

            // Form submission intercept for OTP
            document.querySelector('form').addEventListener('submit', function(e) {
              const otp = Array.from(otpInputs).map(input => input.value).join('');
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = 'otp';
              hiddenInput.value = otp;
              e.target.appendChild(hiddenInput);
            });
          </script>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-outline-variant/10 text-center">
          <a class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:text-primary-container transition-colors group" href="login.php">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Login
          </a>
        </div>
      </div>
      
      <!-- Safe AI Footer Notice -->
      <div class="mt-8 p-4 bg-blue-50/50 rounded-xl text-xs text-blue-900 flex items-start gap-3 border border-blue-100">
        <span class="material-symbols-outlined text-xl shrink-0 mt-0.5 text-blue-500">verified_user</span>
        <p class="leading-relaxed"><strong>Security Guardian:</strong> This terminal link securely bonds your provisioned AI identity with manual cryptography. If you didn't trigger this flow, contact support.</p>
      </div>

    </div>
  </main>
  
  <footer class="relative z-10 py-8 px-6 flex flex-col items-center gap-4 text-center">
    <span class="text-xs font-label text-outline">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> Academic Sentinel</span>
  </footer>

  <script>
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
