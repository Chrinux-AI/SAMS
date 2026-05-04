<?php
/**
 * Verify OTP and Set Password
 * Step 2: Verify OTP and allow password creation
 */

session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/database.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/AccountActivation.php';

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
<html class="light" lang="en">
<head>
  <?php include_once "frontend/includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?> | <?php echo APP_NAME; ?> Academic Sentinel</title>
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

    @keyframes fadeInUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
    .animate-stagger { opacity: 0; animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .delay-100 { animation-delay: 100ms; }
    .delay-200 { animation-delay: 200ms; }
    .delay-300 { animation-delay: 300ms; }
    .delay-400 { animation-delay: 400ms; }
    
    /* Removed float animations */
    .glass-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .input-glow:focus {
      box-shadow: 0 0 0 4px rgba(0, 6, 102, 0.1);
    }
  </style>
</head>

<body class="bg-background font-body text-on-surface flex flex-col min-h-screen">
  <!-- Clean Background -->
  <div class="fixed inset-0 -z-10 bg-background pointer-events-none"></div>

  <main class="relative z-10 flex-grow flex items-center justify-center p-6">
    <div class="w-full max-w-[440px]">
      
      <!-- Center Branding -->
      <div class="text-center mb-10 animate-stagger">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-surface-container-lowest/80 backdrop-blur-sm shadow-md border border-white/50 mb-6 relative overflow-hidden">
          <div class="absolute inset-0 bg-primary/5"></div>
          <span class="material-symbols-outlined text-primary text-3xl relative z-10"><?php echo isset($show_password_form) && $show_password_form ? 'lock_open' : 'domain_verification'; ?></span>
        </div>
        <h1 class="font-headline text-3xl font-extrabold tracking-tighter text-primary mb-2 drop-shadow-sm"><?php echo APP_NAME; ?></h1>
        <p class="font-label text-on-surface-variant text-sm tracking-[0.2em] uppercase font-bold">Academic Sentinel</p>
      </div>

      <!-- Main Card -->
      <div class="glass-card rounded-2xl p-8 relative overflow-hidden animate-stagger delay-100">
        <!-- inner glow edge -->
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>
        
        <?php if (isset($show_password_form) && $show_password_form): ?>
          <!-- SET PASSWORD FLOW -->
          <div class="mb-8 animate-stagger delay-100">
            <h2 class="font-headline text-xl font-bold text-on-surface mb-2">Create Password</h2>
            <p class="text-on-surface-variant text-sm leading-relaxed">
              Set a secure password to complete your account activation.
            </p>
          </div>

          <?php if ($message): ?>
            <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 text-red-700 text-sm mb-6 animate-stagger delay-200">
              <span class="material-symbols-outlined text-lg mt-0.5">error</span>
              <span><?php echo htmlspecialchars($message); ?></span>
            </div>
          <?php endif; ?>

          <div class="mb-6 p-4 bg-surface-container-low/70 rounded-xl text-xs text-primary animate-stagger delay-200">
            <h4 class="font-bold flex items-center gap-1 mb-2"><span class="material-symbols-outlined text-sm">shield</span> Requirements</h4>
            <ul class="list-disc pl-5 space-y-1">
              <li>At least 8 characters long</li>
              <li>Contains uppercase and lowercase letters</li>
              <li>Contains at least one number</li>
              <li>Contains at least one special character (!@#$%^&*)</li>
            </ul>
          </div>

          <form method="POST" class="space-y-6 animate-stagger delay-300">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($verify_token); ?>">
            <input type="hidden" name="step" value="set_password">
            
            <div class="space-y-2">
              <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="password">New Password</label>
              <div class="relative">
                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm input-glow transition-all text-on-surface" id="password" name="password" type="password" required minlength="8">
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('password', this)">
                  <span class="material-symbols-outlined text-xl">visibility</span>
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="confirm_password">Confirm Password</label>
              <div class="relative">
                <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm input-glow transition-all text-on-surface" id="confirm_password" name="confirm_password" type="password" required>
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('confirm_password', this)">
                  <span class="material-symbols-outlined text-xl">visibility</span>
                </button>
              </div>
            </div>

            <button type="submit" class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-2 group">
              <span>Complete Activation</span>
              <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_forward</span>
            </button>
          </form>

        <?php else: ?>
          <!-- VERIFY OTP FLOW -->
          <div class="mb-8 animate-stagger delay-100">
            <h2 class="font-headline text-xl font-bold text-on-surface mb-2">Verify Code</h2>
            <p class="text-on-surface-variant text-sm leading-relaxed">
              Enter the 6-digit verification code provided to you.
            </p>
          </div>

          <?php if ($message): ?>
            <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 text-red-700 text-sm mb-6 animate-stagger delay-200">
              <span class="material-symbols-outlined text-lg mt-0.5">error</span>
              <span><?php echo htmlspecialchars($message); ?></span>
            </div>
          <?php endif; ?>

          <form method="POST" class="space-y-6 animate-stagger delay-200">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_POST['token'] ?? $_GET['token'] ?? ''); ?>">
            <input type="hidden" name="step" value="verify">
            
            <div class="flex justify-between gap-2 my-6">
              <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" required class="w-12 h-14 text-center text-xl font-bold bg-surface-container-low border-none rounded-lg input-glow transition-all font-mono otp-digit text-on-surface">
              <?php endfor; ?>
            </div>

            <button type="submit" class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-2 group">
              <span>Verify Code</span>
              <span class="material-symbols-outlined text-lg">verified</span>
            </button>
          </form>

          <script>
            const otpInputs = document.querySelectorAll('input[name="otp[]"]');
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
          </script>
        <?php endif; ?>
        
        <div class="mt-8 pt-6 border-t border-outline-variant/10 text-center animate-stagger delay-400">
          <a class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:text-primary-container transition-colors group" href="login.php">
            <span class="material-symbols-outlined text-base group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Back to Login
          </a>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="relative z-10 py-8 px-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="flex items-center gap-6">
      <span class="text-xs font-label text-outline">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></span>
      <div class="h-1 w-1 rounded-full bg-outline-variant"></div>
      <a class="text-xs font-label text-on-surface-variant hover:text-primary transition-colors" href="#">Privacy Policy</a>
    </div>
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
