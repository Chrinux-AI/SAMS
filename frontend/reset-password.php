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
<html class="light" lang="en">

<head>
  <?php include_once "includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | <?php echo APP_NAME; ?> Academic Sentinel</title>
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
  <!-- Hero Background Texture (Visual Soul) -->
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
          <span class="material-symbols-outlined text-primary text-3xl">lock_reset</span>
        </div>
        <h1 class="font-headline text-3xl font-extrabold tracking-tighter text-primary mb-2"><?php echo APP_NAME; ?></h1>
        <p class="font-label text-on-surface-variant text-sm tracking-wide uppercase">Academic Sentinel</p>
      </div>

      <!-- Recovery Card -->
      <div class="bg-surface-container-lowest rounded-xl p-8 shadow-[0_20px_40px_rgba(0,7,103,0.06)] border border-outline-variant/10">
        <div class="mb-8">
          <h2 class="font-headline text-xl font-bold text-on-surface mb-2">Reset Password</h2>
          <p class="text-on-surface-variant text-sm leading-relaxed">
            Enter your new password below to regain access to your account.
          </p>
        </div>

        <?php if ($message): ?>
          <?php $is_success = ($message_type === 'success'); ?>
          <div class="flex items-start gap-3 p-4 rounded-xl <?php echo $is_success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?> text-sm mb-6">
            <span class="material-symbols-outlined text-lg mt-0.5"><?php echo $is_success ? 'check_circle' : 'error'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
          </div>
        <?php endif; ?>

        <?php if (empty($message) || $message_type !== 'success'): ?>
        <form method="POST" class="space-y-6">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
          
          <div class="space-y-2">
            <label class="block font-label text-xs font-semibold text-on-surface-variant uppercase tracking-widest" for="new_password">New Password</label>
            <div class="relative">
              <input class="w-full bg-surface-container-low border-none rounded-lg py-3.5 px-4 pr-12 text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/60 text-on-surface" id="new_password" name="new_password" type="password" placeholder="Min <?php echo PASSWORD_MIN_LENGTH; ?> characters" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
              <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('new_password', this)">
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

          <!-- Primary Action -->
          <button class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 group" type="submit">
            Reset Password
            <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_forward</span>
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
    </div>
  </main>

  <!-- Footer Context -->
  <footer class="relative z-10 py-8 px-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="flex items-center gap-6">
      <span class="text-xs font-label text-outline">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></span>
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
