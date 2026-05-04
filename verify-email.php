<?php
session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';

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
  <?php include_once "frontend/includes/favicon-loader.php"; ?>
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

    @keyframes fadeInUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
    .animate-stagger { opacity: 0; animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .delay-100 { animation-delay: 100ms; }
    .delay-200 { animation-delay: 200ms; }
    .delay-300 { animation-delay: 300ms; }
    
    /* Removed float animations */
    .glass-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
  </style>
</head>

<body class="bg-background font-body text-on-surface flex flex-col min-h-screen">
  <!-- Clean Background -->
  <div class="fixed inset-0 -z-10 bg-background pointer-events-none"></div>

  <main class="relative z-10 flex-grow flex items-center justify-center p-6">
    <div class="w-full max-w-[440px]">
      <div class="text-center mb-12 animate-stagger">
        <h1 class="font-headline text-3xl font-extrabold tracking-tighter text-primary mb-2 drop-shadow-sm"><?php echo APP_NAME; ?></h1>
        <p class="font-label text-on-surface-variant text-sm tracking-[0.2em] uppercase font-bold">Academic Sentinel</p>
      </div>

      <div class="glass-card rounded-2xl p-8 relative overflow-hidden text-center animate-stagger delay-100">
        <!-- inner glow edge -->
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>

        <div class="mb-6 flex justify-center animate-stagger delay-100">
          <?php if ($message_type === 'success'): ?>
            <div class="w-20 h-20 rounded-full flex items-center justify-center bg-emerald-50 text-emerald-600 shadow-sm border border-emerald-100">
              <span class="material-symbols-outlined text-4xl">check_circle</span>
            </div>
          <?php elseif ($message_type === 'error'): ?>
            <div class="w-20 h-20 rounded-full flex items-center justify-center bg-red-50 text-red-600 shadow-sm border border-red-100">
              <span class="material-symbols-outlined text-4xl">cancel</span>
            </div>
          <?php else: ?>
            <div class="w-20 h-20 rounded-full flex items-center justify-center bg-blue-50 text-blue-600 shadow-sm border border-blue-100">
              <span class="material-symbols-outlined text-4xl">info</span>
            </div>
          <?php endif; ?>
        </div>

        <h2 class="font-headline text-2xl font-bold text-on-surface mb-4 animate-stagger delay-200">
          <?php echo $message_type === 'success' ? 'Verification Complete' : ($message_type === 'error' ? 'Verification Failed' : 'Email Status'); ?>
        </h2>
        
        <p class="text-on-surface-variant text-sm leading-relaxed mb-8 animate-stagger delay-200">
          <?php echo htmlspecialchars($message); ?>
        </p>

        <a class="premium-gradient w-full py-4 px-6 rounded-lg text-white font-headline font-bold text-sm tracking-tight shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all duration-300 transform active:scale-[0.98] flex items-center justify-center gap-2 group animate-stagger delay-300" href="login.php">
          <span>Go to Login</span>
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
