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
<html class="light" lang="en">
<head>
  <?php include_once "includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification | <?php echo APP_NAME; ?> Academic Sentinel</title>
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

  <main class="relative z-10 flex-grow flex items-center justify-center p-6">
    <div class="w-full max-w-[440px]">
      <div class="text-center mb-12">
        <h1 class="font-headline text-3xl font-extrabold tracking-tighter text-primary mb-2"><?php echo APP_NAME; ?></h1>
        <p class="font-label text-on-surface-variant text-sm tracking-wide uppercase">Academic Sentinel</p>
      </div>

      <div class="bg-surface-container-lowest rounded-xl p-8 shadow-[0_20px_40px_rgba(0,7,103,0.06)] border border-outline-variant/10 text-center">
        <div class="mb-6 flex justify-center">
          <?php if ($message_type === 'success'): ?>
            <div class="w-20 h-20 rounded-full flex items-center justify-center bg-emerald-50 text-emerald-600">
              <span class="material-symbols-outlined text-4xl">check_circle</span>
            </div>
          <?php elseif ($message_type === 'error'): ?>
            <div class="w-20 h-20 rounded-full flex items-center justify-center bg-red-50 text-red-600">
              <span class="material-symbols-outlined text-4xl">cancel</span>
            </div>
          <?php else: ?>
            <div class="w-20 h-20 rounded-full flex items-center justify-center bg-blue-50 text-blue-600">
              <span class="material-symbols-outlined text-4xl">info</span>
            </div>
          <?php endif; ?>
        </div>

        <h2 class="font-headline text-2xl font-bold text-on-surface mb-4">
          <?php echo $message_type === 'success' ? 'Verification Complete' : ($message_type === 'error' ? 'Verification Failed' : 'Email Status'); ?>
        </h2>
        
        <p class="text-on-surface-variant text-sm leading-relaxed mb-8">
          <?php echo htmlspecialchars($message); ?>
        </p>

        <a class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 group" href="login.php">
          Go to Login
          <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">login</span>
        </a>
      </div>
    </div>
  </main>

  <footer class="relative z-10 py-8 px-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="flex items-center gap-6">
      <span class="text-xs font-label text-outline">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></span>
      <div class="h-1 w-1 rounded-full bg-outline-variant"></div>
      <a class="text-xs font-label text-on-surface-variant hover:text-primary transition-colors" href="#">Privacy Policy</a>
      <div class="h-1 w-1 rounded-full bg-outline-variant"></div>
      <a class="text-xs font-label text-on-surface-variant hover:text-primary transition-colors" href="#">Security Standards</a>
    </div>
  </footer>
</body>
</html>
