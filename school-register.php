<?php
session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_school'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $message = 'Invalid request token. Please refresh and try again.';
    $message_type = 'error';
  } else {
    try {
      AdvancedSAMS::createSchoolRegistration($_POST);
      $message = 'School registration submitted. The first admin account has been created in pending state.';
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
  <title>Register School | <?php echo APP_NAME; ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="bg-background font-body text-on-background min-h-screen py-12 px-4">
  <main class="max-w-5xl mx-auto grid lg:grid-cols-12 rounded-xl overflow-hidden shadow-[0_20px_40px_rgba(0,7,103,0.06)] bg-surface-container-lowest">
    <section class="lg:col-span-4 bg-gradient-to-br from-[#000666] to-[#1a237e] text-white p-10">
      <span class="material-symbols-outlined text-4xl">domain</span>
      <h1 class="font-headline text-3xl font-extrabold mt-4">School-First Registration</h1>
      <p class="text-sm text-white/80 mt-4 leading-relaxed">This carries the stronger tenant lifecycle from `SAMS 2` and `attendance-2` into the main `attendance` system.</p>
    </section>
    <section class="lg:col-span-8 p-8 md:p-12">
      <a href="login.php" class="text-sm font-semibold text-primary hover:underline">Back to login</a>
      <h2 class="font-headline text-4xl font-extrabold text-primary tracking-tight mt-3 mb-8">Register Your School</h2>
      <?php if ($message): ?>
        <div class="mb-6 rounded-xl p-4 text-sm <?php echo $message_type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?>"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="text-xs font-semibold text-on-surface-variant ml-1">School Name</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="text" name="school_name" required></div>
          <div><label class="text-xs font-semibold text-on-surface-variant ml-1">School Slug</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="text" name="school_slug" required></div>
          <div><label class="text-xs font-semibold text-on-surface-variant ml-1">Admin First Name</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="text" name="admin_first_name" required></div>
          <div><label class="text-xs font-semibold text-on-surface-variant ml-1">Admin Last Name</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="text" name="admin_last_name" required></div>
          <div class="md:col-span-2"><label class="text-xs font-semibold text-on-surface-variant ml-1">Admin Email</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="email" name="admin_email" required></div>
          <div class="md:col-span-2"><label class="text-xs font-semibold text-on-surface-variant ml-1">Password</label><input class="w-full mt-1 px-4 py-3 bg-surface-container-low rounded-lg border-0" type="password" name="password" required></div>
        </div>
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between pt-4 border-t border-outline-variant/30">
          <a href="invite-register.php" class="text-sm font-semibold text-primary hover:underline">Already invited by a school?</a>
          <button class="premium-gradient text-white px-8 py-3 rounded-xl font-bold text-sm" type="submit" name="register_school">Create School Tenant</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
