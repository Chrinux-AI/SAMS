<?php
session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';

$message = '';
$message_type = '';
$token = $_GET['token'] ?? ($_POST['invite_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_invite'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $message = 'Invalid request token. Please refresh and try again.';
    $message_type = 'error';
  } else {
    try {
      AdvancedSAMS::redeemInvite($_POST);
      $message = 'Invite redeemed successfully. Your school admin can now review and activate the account.';
      $message_type = 'success';
    } catch (Throwable $e) {
      $message = $e->getMessage();
      $message_type = 'error';
    }
  }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <?php include_once "frontend/includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redeem Invite | <?php echo APP_NAME; ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#000666",
            "primary-container": "#1a237e",
            "on-primary": "#ffffff",
            "on-primary-fixed": "#000767",
            "background": "#f8f9fa",
            "on-background": "#191c1d",
            "surface-container-lowest": "#ffffff",
            "surface-container-low": "#f3f4f5",
            "surface-container": "#edeeef",
            "on-surface": "#191c1d",
            "on-surface-variant": "#5b6070",
            "outline": "#d5d9e2",
            "outline-variant": "#e7e8ee"
          },
          fontFamily: {
            "headline": ["Manrope", "sans-serif"],
            "body": ["Inter", "sans-serif"]
          }
        }
      }
    }
  </script>
  <style>
    body {
      background:
        radial-gradient(circle at top left, rgba(224, 224, 255, 0.85), transparent 28%),
        radial-gradient(circle at bottom right, rgba(207, 230, 242, 0.9), transparent 24%),
        #f8f9fa;
      color: #191c1d;
    }

    .premium-gradient {
      background: linear-gradient(135deg, #000666 0%, #1a237e 100%);
    }

    .invite-shell {
      box-shadow: 0 24px 60px rgba(0, 7, 103, 0.08);
    }

    .field-label {
      display: block;
      margin-bottom: 0.4rem;
      margin-left: 0.25rem;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #5b6070;
    }

    .field-input {
      width: 100%;
      border: 1px solid #d5d9e2;
      background: #ffffff;
      color: #191c1d;
      border-radius: 0.75rem;
      padding: 0.9rem 1rem;
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .field-input::placeholder {
      color: #8c93a5;
    }

    .field-input:focus {
      border-color: #4c56af;
      box-shadow: 0 0 0 3px rgba(76, 86, 175, 0.12);
      background: #ffffff;
    }
  </style>
</head>
<body class="bg-background font-body text-on-background min-h-screen py-12 px-4">
  <main class="invite-shell max-w-5xl mx-auto rounded-2xl overflow-hidden bg-surface-container-lowest grid lg:grid-cols-12 border border-white/60">
    <section class="lg:col-span-4 bg-gradient-to-br from-[#000666] to-[#1a237e] text-white p-10">
      <span class="material-symbols-outlined text-4xl">mail_lock</span>
      <h1 class="font-headline text-3xl font-extrabold mt-4">Invite-Based Entry</h1>
      <p class="text-sm text-white/80 mt-4 leading-relaxed">Staff and other school-managed roles join through school-issued onboarding, not open registration.</p>
      <div class="mt-10 space-y-4 text-sm text-white/85">
        <div class="rounded-xl bg-white/10 px-4 py-3 border border-white/10">
          School-issued token access only
        </div>
        <div class="rounded-xl bg-white/10 px-4 py-3 border border-white/10">
          Account remains school-bound after activation
        </div>
        <div class="rounded-xl bg-white/10 px-4 py-3 border border-white/10">
          Admin review completes the final approval step
        </div>
      </div>
    </section>
    <section class="lg:col-span-8 p-8 md:p-12">
      <a href="login.php" class="text-sm font-semibold text-primary hover:underline">Back to login</a>
      <h2 class="font-headline text-4xl font-extrabold text-primary tracking-tight mt-3 mb-8">Redeem Invite</h2>
      <?php if ($message): ?>
        <div class="mb-6 rounded-xl p-4 text-sm <?php echo $message_type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        <div class="grid md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="field-label">Invite Token</label>
            <input class="field-input" type="text" name="invite_token" value="<?php echo htmlspecialchars($token); ?>" placeholder="Paste your school invite token" required>
          </div>
          <div>
            <label class="field-label">First Name</label>
            <input class="field-input" type="text" name="first_name" placeholder="Enter your first name" required>
          </div>
          <div>
            <label class="field-label">Last Name</label>
            <input class="field-input" type="text" name="last_name" placeholder="Enter your last name" required>
          </div>
          <div class="md:col-span-2">
            <label class="field-label">Email</label>
            <input class="field-input" type="email" name="email" placeholder="Use the invited email address" required>
          </div>
          <div class="md:col-span-2">
            <label class="field-label">Password</label>
            <input class="field-input" type="password" name="password" placeholder="Create your password" required>
          </div>
        </div>
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between pt-4 border-t border-outline-variant/30">
          <a href="school-register.php" class="text-sm font-semibold text-primary hover:underline">Need to register a new school?</a>
          <button class="premium-gradient text-white px-8 py-3 rounded-xl font-bold text-sm" type="submit" name="redeem_invite">Redeem Invite</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
