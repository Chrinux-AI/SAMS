<?php

/**
 * SAMS Login Page — Academic Sentinel
 * Stitch UI: sams_secure_login_1
 */

session_start();

require_once 'frontend/includes/config.php';
require_once 'frontend/includes/database.php';
require_once 'frontend/includes/functions.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';

$error = '';
$success = '';

$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$appBase = '';
if (preg_match('#^(.*?)/frontend(?:/.*)?$#', $scriptName, $m)) {
  $appBase = (string)($m[1] ?? '');
} elseif (preg_match('#^(.*?)/login\.php$#', $scriptName, $m)) {
  $appBase = (string)($m[1] ?? '');
}
if ($appBase === '') {
  $appBase = '/attendance';
}
$logoLightUrl = rtrim($appBase, '/') . '/assets/logo/logo5.png';
$logoDarkUrl = rtrim($appBase, '/') . '/assets/logo/logo4.png';

// Show timeout message
if (isset($_GET['timeout'])) {
  $error = 'Your session has expired due to inactivity. Please log in again.';
} elseif (isset($_GET['logged_out'])) {
  $success = 'You have been logged out successfully.';
}

// If an authenticated session is already active, do not allow role switching from this browser.
if (is_logged_in()) {
  $activeRole = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''));

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $error = 'You are already logged in in this browser. Please log out first before switching roles.';
  } else {
    $dashboardPath = get_role_dashboard_path($activeRole ?: 'student');
    if (!preg_match('#^https?://#i', $dashboardPath)) {
      $dashboardPath = site_url($dashboardPath);
    }
    header('Location: ' . $dashboardPath);
    exit;
  }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
  // CSRF protection
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Please try again.';
  } else {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
      $error = 'Please enter both email and password';
    } else {
      $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

      // IP-based rate limiting: 20 login attempts per 15 minutes per IP
      $ip_limit = rate_limiter()->check('login_ip', $client_ip, 20, 900);
      if (!$ip_limit['allowed']) {
        $error = 'Too many login attempts from your IP. Please try again later.';
      } else {
        // Per-account rate limiting: 5 attempts then 5-minute lockout
        $acct_limit = rate_limiter()->check('login_acct', md5($email), 5, 300);
        if (!$acct_limit['allowed']) {
          $wait = ceil($acct_limit['retry_after'] / 60);
          $error = "Too many failed attempts. Please try again in {$wait} minute(s).";
        } else {

          $user = db()->fetchOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
          );

          $hash = $user['password_hash'] ?? $user['password'] ?? '';
          if ($user && password_verify($password, $hash)) {
            // Check user status
            if (isset($user['email_verified']) && $user['email_verified'] == 0) {
              $error = 'Please verify your email address before logging in. Check your inbox for the verification link.';
            } elseif (isset($user['approved']) && $user['approved'] == 0) {
              $error = 'Your account is pending admin approval. You will receive an email once approved.';
            } elseif (($user['status'] ?? 'active') !== 'active') {
              $error = 'Your account is not active. Please contact the administrator.';
            } else {
              // Clear rate limiters on success
              rate_limiter()->clear('login_acct', md5($email));

              // Regenerate session ID to prevent session fixation
              regenerate_session();

              // Update last login time and reset failed attempts
              db()->update('users', [
                'last_login' => date('Y-m-d H:i:s'),
                'failed_login_attempts' => 0,
                'locked_until' => null
              ], 'id = ?', [$user['id']]);

              $_SESSION['user_id'] = $user['id'];
              $_SESSION['email'] = $user['email'];
              $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
              $_SESSION['role'] = $user['role'];
              $_SESSION['user_role'] = $user['role'];
              $_SESSION['assigned_id'] = $user['assigned_id'];
              $_SESSION['last_login'] = $user['last_login'];
              $_SESSION['last_activity'] = time();
              $_SESSION['login_time'] = time();
              set_user_tenant_session((int)$user['id']);
              $_SESSION['school_id'] = $_SESSION['tenant_id'] ?? AdvancedSAMS::resolveUserTenantId((int)$user['id']);

              [$canAccess, $restrictionMessage] = AdvancedSAMS::userCanAccess();
              if (!$canAccess) {
                session_destroy();
                $error = $restrictionMessage ?: 'Access denied.';
              } else {

                // Log the login activity
                log_activity($user['id'], 'login', 'users', $user['id']);
                try {
                  AuditLogger::log('login_success', 'users', 'User logged in: ' . $user['email'], $user['id']);
                } catch (Throwable $e) {
                }

                // Redirect based on centralized role mapping
                $dest = get_role_dashboard_path((string)$user['role']);
                if (!preg_match('#^https?://#i', $dest)) {
                  $dest = site_url($dest);
                }
                header('Location: ' . $dest);
                exit;
              }
            }
          } else {
            // Record failed attempt in rate limiter (IP + account)
            rate_limiter()->record('login_ip', $client_ip);
            rate_limiter()->record('login_acct', md5($email));
            try {
              AuditLogger::log('login_failed', 'users', 'Failed login attempt for: ' . $email);
            } catch (Throwable $e) {
            }

            // Also update DB-level failed count if user exists
            if ($user) {
              db()->query("UPDATE users SET failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1 WHERE id = ?", [$user['id']]);
            }

            $remaining = max(0, ($acct_limit['remaining'] ?? 1) - 1);
            if ($remaining <= 0) {
              $error = 'Too many failed attempts. Account temporarily locked for 5 minutes.';
              if ($user) {
                db()->query(
                  "UPDATE users SET locked_until = ? WHERE id = ?",
                  [date('Y-m-d H:i:s', time() + 300), $user['id']]
                );
                log_activity($user['id'], 'account_lockout', 'users', $user['id']);
              }
            } else {
              $error = "Invalid credentials. {$remaining} attempt(s) remaining.";
            }
          }
        } // end account rate limit
      } // end IP rate limit
    } // end CSRF check
  }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
  <?php include_once "frontend/includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="manifest" href="/attendance/manifest.json">
  <meta name="theme-color" content="#000666">
  <title>Login | <?php echo APP_NAME; ?> Academic Sentinel</title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Tailwind CSS -->
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

    .bg-primary-gradient {
      background: linear-gradient(135deg, #000666 0%, #1a237e 100%);
    }

    .ghost-border {
      outline: 1px solid rgba(198, 197, 212, 0.2);
    }

    .shadow-tint {
      box-shadow: 0 20px 40px rgba(0, 7, 103, 0.08);
    }

    /* Staggered entry animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(24px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-stagger {
      opacity: 0;
      animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .delay-100 {
      animation-delay: 100ms;
    }

    .delay-200 {
      animation-delay: 200ms;
    }

    .delay-300 {
      animation-delay: 300ms;
    }

    .delay-400 {
      animation-delay: 400ms;
    }

    .delay-500 {
      animation-delay: 500ms;
    }

    /* Floating orbs removed for cleaner UI */

    /* Solid Card */
    .glass-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
    }

    .dark .glass-card {
      background: #1e293b;
      border: 1px solid #334155;
    }

    .input-glow:focus {
      box-shadow: 0 0 0 4px rgba(0, 6, 102, 0.1);
    }
  </style>
  <script src="frontend/assets/js/biometric-auth.js"></script>
</head>

<body class="bg-background font-body text-on-background min-h-screen flex flex-col items-center justify-center p-4">
  <!-- Minimal Background -->
  <div class="fixed inset-0 -z-10 bg-background pointer-events-none"></div>

  <main class="w-full max-w-md relative z-10">
    <!-- Logo Section -->
    <div class="flex flex-col items-center mb-10 space-y-2 animate-stagger">
      <div class="flex items-center space-y-1 flex-col">
        <span class="material-symbols-outlined text-primary text-5xl mb-2 drop-shadow-md">school</span>
        <h1 class="font-headline font-extrabold text-4xl tracking-tight text-primary drop-shadow-sm"><?php echo APP_NAME; ?></h1>
        <p class="font-headline text-on-surface-variant text-sm tracking-[0.2em] uppercase font-bold">Academic Sentinel</p>
      </div>
    </div>

    <!-- Login Card -->
    <div class="glass-card rounded-2xl shadow-tint p-8 ghost-border animate-stagger delay-100 relative overflow-hidden">
      <!-- subtle inner glow edge -->
      <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>
      <!-- Logo -->
      <div class="flex justify-center mb-6">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="<?php echo htmlspecialchars($logoDarkUrl); ?>">
          <img src="<?php echo htmlspecialchars($logoLightUrl); ?>" alt="SAMS Logo" class="h-16 w-auto rounded-2xl object-contain" style="max-width: 180px;">
        </picture>
      </div>

      <header class="mb-8 animate-stagger delay-200">
        <h2 class="font-headline text-2xl font-bold text-on-surface mb-1">Welcome back</h2>
        <p class="text-on-surface-variant text-sm">Access your institutional dashboard</p>
      </header>

      <?php if ($error): ?>
        <div class="flex items-start gap-3 p-3.5 rounded-xl bg-red-50 text-red-700 text-sm mb-6">
          <span class="material-symbols-outlined text-lg mt-0.5">error</span>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="flex items-start gap-3 p-3.5 rounded-xl bg-emerald-50 text-emerald-700 text-sm mb-6">
          <span class="material-symbols-outlined text-lg mt-0.5">check_circle</span>
          <span><?php echo htmlspecialchars($success); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" class="space-y-6 animate-stagger delay-300">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <!-- Credentials Group -->
        <div class="space-y-4">
          <div class="space-y-2">
            <label class="font-label text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1" for="email">Email Address</label>
            <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg input-glow text-on-surface text-sm transition-all duration-300" id="email" name="email" placeholder="e.g. admin@sams.edu" type="email" required autocomplete="email">
          </div>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <label class="font-label text-xs font-semibold uppercase tracking-wider text-on-surface-variant ml-1" for="password">Password</label>
              <a class="text-xs text-primary font-bold hover:text-primary-container hover:underline transition-colors" href="forgot-password.php">Forgot password?</a>
            </div>
            <div class="relative">
              <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg input-glow text-on-surface text-sm transition-all duration-300 pr-12" id="password" name="password" placeholder="••••••••" type="password" required>
              <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('password', this)">
                <span class="material-symbols-outlined text-xl">visibility</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Primary Action -->
        <button class="w-full bg-primary-gradient text-on-primary py-3.5 rounded-lg font-bold text-sm tracking-wide shadow-lg shadow-primary/25 hover:shadow-primary/40 hover:-translate-y-0.5 transition-all duration-300 transform active:scale-[0.98] flex items-center justify-center gap-2 group" type="submit" name="login">
          <span>Login to Sentinel</span>
          <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </button>
      </form>

      <!-- Biometric Divider -->
      <div class="relative my-8 animate-stagger delay-400">
        <div class="absolute inset-0 flex items-center">
          <span class="w-full border-t border-outline-variant/20"></span>
        </div>
        <div class="relative flex justify-center text-xs uppercase">
          <span class="bg-surface-container-lowest/80 backdrop-blur-md px-4 text-on-surface-variant font-label font-bold tracking-widest rounded-full">or continue with</span>
        </div>
      </div>

      <!-- Biometric Login -->
      <button type="button" onclick="performBiometricLogin()" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-surface-container-low border-none rounded-lg text-on-surface-variant text-sm font-bold hover:bg-surface-container hover:text-on-surface hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 animate-stagger delay-500">
        <span class="material-symbols-outlined text-primary">fingerprint</span>
        Sign in with Biometrics
      </button>

      <!-- Registration Footer -->
      <footer class="mt-8 pt-6 border-t border-outline-variant/10 text-center animate-stagger delay-500">
        <p class="text-on-surface-variant text-sm">
          Don't have an institutional account?
          <a class="text-primary font-bold hover:text-primary-container hover:underline ml-1 transition-colors" href="register.php">Join an existing school</a>
        </p>
        <p class="text-on-surface-variant text-xs mt-3">
          New institution?
          <a class="text-primary font-bold hover:text-primary-container hover:underline ml-1 transition-colors" href="school-register.php">Register your school first</a>
        </p>
      </footer>
    </div>

    <!-- System Status Info -->
    <div class="mt-8 flex justify-between items-center px-4 animate-stagger delay-[600ms]">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-green-500"></span>
        <span class="text-[10px] font-label text-on-surface-variant uppercase tracking-tighter">System Operational</span>
      </div>
      <div class="flex gap-4">
        <a class="text-[10px] font-label text-on-surface-variant uppercase hover:text-primary transition-colors" href="#">Support</a>
        <a class="text-[10px] font-label text-on-surface-variant uppercase hover:text-primary transition-colors" href="#">Security</a>
        <a class="text-[10px] font-label text-on-surface-variant uppercase hover:text-primary transition-colors" href="#">Terms</a>
      </div>
    </div>
  </main>

  <!-- App Version / Copyright -->
  <footer class="mt-12 mb-6">
    <p class="font-headline text-[10px] tracking-widest uppercase text-outline opacity-60">
      v2.0.4 © <?php echo date('Y'); ?> <?php echo APP_NAME; ?> Academic Sentinel
    </p>
  </footer>

  <script>
    async function performBiometricLogin() {
      const btn = event.target.closest('button');
      const originalHTML = btn.innerHTML;
      if (!window.biometricAuth || !window.biometricAuth.supported) {
        alert('Biometric authentication is not supported on this browser/device.');
        return;
      }
      try {
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Scanning...';
        btn.disabled = true;
        const result = await window.biometricAuth.login();
        if (result.success) {
          btn.innerHTML = '<span class="material-symbols-outlined text-emerald-600">check_circle</span> Authenticated';
          btn.classList.add('bg-emerald-50', 'text-emerald-700');
          setTimeout(() => {
            window.location.href = result.redirect;
          }, 1000);
        } else {
          throw new Error(result.error || 'Authentication failed');
        }
      } catch (error) {
        btn.innerHTML = '<span class="material-symbols-outlined text-red-500">cancel</span> Failed';
        btn.classList.add('bg-red-50', 'text-red-700');
        setTimeout(() => {
          alert(error.message || 'Biometric authentication failed.');
          btn.innerHTML = originalHTML;
          btn.className = btn.className.replace(/bg-(emerald|red)-\d+|text-(emerald|red)-\d+/g, '').trim();
          btn.disabled = false;
        }, 1500);
      }
    }

    function togglePassword(fieldId, btn) {
      var input = document.getElementById(fieldId);
      var icon = btn.querySelector('.material-symbols-outlined');
      if (!input || !icon) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
    }

    document.getElementById('email').focus();
  </script>
</body>

</html>
