<?php
session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';

$message = '';
$message_type = '';

// Check if registration is enabled
$registration_enabled = true;
$settings_table = db()->fetchOne("SHOW TABLES LIKE 'system_settings'");
if ($settings_table) {
  $setting = db()->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_enabled'");
  $registration_enabled = $setting ? (bool)$setting['setting_value'] : true;
}

if (!$registration_enabled) {
  $message = 'Registration is currently disabled. Please contact the administrator.';
  $message_type = 'error';
}

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && $registration_enabled) {
  $errors = [];

  try {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $role = sanitize($_POST['role'] ?? '');

    // Block admin role registration
    if (in_array($role, ['admin', 'teacher'], true)) {
      $errors[] = 'This role uses school-issued onboarding. Please use an invite link from your school administrator.';
    }
    $phone = sanitize($_POST['phone'] ?? '');

    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($role)) $errors[] = 'Role is required';

    if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (!in_array($role, ['student', 'parent', 'teacher'])) $errors[] = 'Invalid role selected';

    if ($role === 'student') {
      if (empty($_POST['date_of_birth'])) $errors[] = 'Date of birth is required for students';
      if (empty($_POST['grade_level'])) $errors[] = 'Grade level is required for students';
    }

    $existing = db()->fetch("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing) $errors[] = 'Username already exists';

    $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) $errors[] = 'Email already registered';

    if (empty($errors)) {
      // Generate verification token with 10-minute expiration
      $verification_token = bin2hex(random_bytes(32));
      $token_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

      $user_data = [
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role,
        'phone' => $phone,
        'status' => 'pending',
        'email_verified' => 0,
        'email_verification_token' => $verification_token,
        'token_expires_at' => $token_expires_at,
        'approved' => 0
      ];

      $user_id = db()->insert('users', $user_data);

      if ($user_id) {
        attach_user_to_tenant((int)$user_id);

        $assigned_id = null;

        if ($role === 'student') {
          $year = date('Y');
          $count = db()->count('students') + 1;
          $student_id = $year . str_pad($count, 4, '0', STR_PAD_LEFT);
          $assigned_id = 'STU' . $student_id;

          $student_data = [
            'user_id' => $user_id,
            'student_id' => $student_id,
            'assigned_student_id' => $student_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $_POST['date_of_birth'],
            'grade_level' => (int)$_POST['grade_level'],
            'status' => 'pending'
          ];
          db()->insert('students', $student_data);
        } elseif ($role === 'teacher') {
          $year = date('Y');
          $count = db()->count('teachers') + 1;
          $teacher_id = $year . str_pad($count, 4, '0', STR_PAD_LEFT);
          $assigned_id = 'EMP' . $teacher_id;
        }

        // Send verification email
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify-email.php?token=" . $verification_token;

        $email_sent = send_verification_email($email, $first_name . ' ' . $last_name, $verification_token, $assigned_id, $role);

        if ($email_sent) {
          $message = 'Registration successful! Please check your email (' . $email . ') to verify your account. ⏱️ Verification link expires in 10 minutes.';
          $message_type = 'success';
        } else {
          $message = 'Registration successful but verification email failed to send. Please contact administrator.';
          $message_type = 'warning';
        }
      } else {
        $errors[] = 'Registration failed. Please try again.';
      }
    }
  } catch (Exception $e) {
    $errors[] = 'An error occurred: ' . $e->getMessage();
  }

  if (!empty($errors)) {
    $message = implode('<br>', $errors);
    $message_type = 'error';
  }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
  <?php include_once "frontend/includes/favicon-loader.php"; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | <?php echo APP_NAME; ?> Academic Sentinel</title>
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

    .premium-gradient {
      background: linear-gradient(135deg, #000666 0%, #1a237e 100%);
    }

    .ghost-border {
      outline: 1px solid rgba(198, 197, 212, 0.2);
    }
  </style>
</head>

<body class="bg-background font-body text-on-background antialiased min-h-screen flex flex-col">
  <!-- Top Navigation Bar -->
  <header class="bg-white border-b border-outline/30 text-primary font-headline text-sm font-medium tracking-tight fixed top-0 z-50 flex justify-between items-center w-full px-6 py-3 shadow-[0_4px_6px_-1px_rgba(0,0,0,0.05)]">
    <div class="flex items-center gap-8">
      <a href="index.php" class="text-xl font-bold tracking-tighter text-primary"><?php echo APP_NAME; ?></a>
      <nav class="hidden md:flex items-center gap-6">
        <a class="text-slate-500 hover:text-primary transition-colors duration-200" href="login.php">Login</a>
        <a class="text-primary font-bold border-b-2 border-primary pb-0.5" href="#">Register</a>
      </nav>
    </div>
    <div class="flex items-center gap-4">
      <a href="login.php" class="text-sm font-semibold text-primary hover:underline transition-all">Already registered? Log in</a>
    </div>
  </header>

  <main class="flex-grow flex items-center justify-center px-4 py-16 mt-12">
    <!-- Registration Container -->
    <div class="w-full max-w-4xl grid md:grid-cols-12 gap-0 shadow-[0_20px_40px_rgba(0,7,103,0.06)] rounded-xl overflow-hidden bg-surface-container-lowest">

      <!-- Left Side: Editorial Context -->
      <div class="md:col-span-4 premium-gradient p-10 flex flex-col justify-between text-white relative overflow-hidden">
        <div class="relative z-10">
          <h1 class="font-headline text-3xl font-extrabold tracking-tighter mb-4">Academic Sentinel</h1>
          <p class="text-on-primary-container text-sm leading-relaxed opacity-90">
            Empowering educational excellence through sophisticated management systems. Join your institution today.
          </p>
        </div>
        <div class="relative z-10 space-y-6 mt-8">
          <div class="flex items-center gap-4">
            <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">
              <span class="material-symbols-outlined text-white">verified_user</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-widest text-white/60">Secure</p>
              <p class="text-sm">End-to-end encryption</p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">
              <span class="material-symbols-outlined text-white">speed</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-widest text-white/60">Real-time</p>
              <p class="text-sm">Instant attendance tracking</p>
            </div>
          </div>
        </div>
        <!-- Abstract Decorative Element -->
      </div>

      <!-- Right Side: Registration Form -->
      <div class="md:col-span-8 p-10 md:p-14 bg-surface-container-lowest">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
          <picture>
            <source media="(prefers-color-scheme: dark)" srcset="assets/logo/logo4.png">
            <img src="frontend/assets/logo/logo5.png" alt="SAMS Logo" class="h-14 w-auto rounded-2xl object-contain" style="max-width: 160px;">
          </picture>
        </div>

        <div class="mb-10">
          <span class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-primary mb-2 block">Institutional Onboarding</span>
          <h2 class="font-headline text-4xl font-extrabold text-primary tracking-tight">Create Account</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-4 mb-8">
          <a href="school-register.php" class="block rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 hover:border-primary/40 transition-colors">
            <span class="material-symbols-outlined text-primary text-2xl">domain_add</span>
            <h3 class="font-headline text-lg font-bold text-primary mt-3">Register a School</h3>
            <p class="text-sm text-on-surface-variant mt-2">Use the school-first flow to create the tenant and first admin account.</p>
          </a>
          <a href="invite-register.php" class="block rounded-xl border border-outline-variant/30 bg-surface-container-low p-5 hover:border-primary/40 transition-colors">
            <span class="material-symbols-outlined text-primary text-2xl">mark_email_read</span>
            <h3 class="font-headline text-lg font-bold text-primary mt-3">Redeem an Invite</h3>
            <p class="text-sm text-on-surface-variant mt-2">Teachers and staff should enter through school-issued invite registration.</p>
          </a>
        </div>

        <?php if ($message): ?>
          <?php
          $alert_map = [
            'success' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'icon' => 'check_circle'],
            'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'icon' => 'warning'],
            'error'   => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'icon' => 'error'],
          ];
          $a = $alert_map[$message_type] ?? $alert_map['error'];
          ?>
          <div class="flex items-start gap-3 p-4 rounded-xl <?php echo $a['bg'] . ' ' . $a['text']; ?> text-sm mb-6">
            <span class="material-symbols-outlined text-lg mt-0.5"><?php echo $a['icon']; ?></span>
            <span><?php echo $message; ?></span>
          </div>
        <?php endif; ?>

        <?php if ($registration_enabled && (!$message || $message_type !== 'success')): ?>
          <form method="POST" class="space-y-8">
            <!-- Section: Select Role -->
            <div class="space-y-4">
              <h3 class="font-headline text-xs font-bold uppercase tracking-widest text-on-surface-variant flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">badge</span> 01. Select Your Role
              </h3>
              <div class="grid grid-cols-3 gap-3">
                <?php
                $roles = [
                  'student' => ['icon' => 'school', 'label' => 'Student'],
                  'parent'  => ['icon' => 'family_restroom', 'label' => 'Parent'],
                  'teacher' => ['icon' => 'person', 'label' => 'Teacher (Invite Only)'],
                ];
                foreach ($roles as $val => $r):
                  $checked = (isset($_POST['role']) && $_POST['role'] === $val) ? 'checked' : '';
                ?>
                  <label class="relative cursor-pointer group">
                    <input type="radio" name="role" value="<?php echo $val; ?>" class="peer sr-only" required <?php echo $checked; ?>>
                    <div class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-outline-variant/30 bg-surface-container-low peer-checked:border-primary peer-checked:bg-primary-fixed/30 hover:border-primary/40 transition-all">
                      <span class="material-symbols-outlined text-2xl text-on-surface-variant peer-checked:text-primary group-hover:text-primary transition-colors"><?php echo $r['icon']; ?></span>
                      <span class="text-xs font-bold text-on-surface"><?php echo $r['label']; ?></span>
                    </div>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Section: Personal Details -->
            <div class="space-y-4">
              <h3 class="font-headline text-xs font-bold uppercase tracking-widest text-on-surface-variant flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">person</span> 02. Personal Details
              </h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">First Name</label>
                  <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm" type="text" name="first_name" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Last Name</label>
                  <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm" type="text" name="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Username</label>
                  <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm" type="text" name="username" placeholder="johndoe" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Phone Number</label>
                  <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm" type="tel" name="phone" placeholder="+234 xxx xxx xxxx" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="flex flex-col gap-1.5 md:col-span-2">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Email Address</label>
                  <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm" type="email" name="email" placeholder="john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
              </div>
            </div>

            <!-- Section: Security Credentials -->
            <div class="bg-surface-container-low p-6 rounded-xl space-y-4 ghost-border">
              <h3 class="font-headline text-xs font-bold uppercase tracking-widest text-on-surface-variant flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">lock</span> 03. Security Credentials
              </h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Password</label>
                  <div class="relative">
                    <input class="w-full px-4 py-3 bg-surface-container-lowest border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm shadow-sm pr-12" type="password" id="password" name="password" placeholder="Min 8 characters" required>
                    <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('password', this)">
                      <span class="material-symbols-outlined text-xl">visibility</span>
                    </button>
                  </div>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Confirm Password</label>
                  <div class="relative">
                    <input class="w-full px-4 py-3 bg-surface-container-lowest border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm shadow-sm pr-12" type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                    <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" onclick="togglePassword('confirm_password', this)">
                      <span class="material-symbols-outlined text-xl">visibility</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Student-Only Fields -->
            <div id="student_fields" class="hidden space-y-4">
              <h3 class="font-headline text-xs font-bold uppercase tracking-widest text-on-surface-variant flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">school</span> 04. Student Information
              </h3>
              <div class="grid md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Date of Birth</label>
                  <input class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm" type="date" id="date_of_birth" name="date_of_birth">
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-on-surface-variant ml-1">Level</label>
                  <select class="w-full px-4 py-3 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-surface-tint transition-all text-sm appearance-none" id="grade_level" name="grade_level">
                    <option value="">Select Level</option>
                    <option value="100">100 Level</option>
                    <option value="200">200 Level</option>
                    <option value="300">300 Level</option>
                    <option value="400">400 Level</option>
                    <option value="500">500 Level</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- CTA Actions -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 pt-4 border-t border-outline-variant/20">
              <a class="text-sm font-semibold text-primary hover:underline transition-all" href="login.php">Already registered? Log in</a>
              <button class="premium-gradient text-white px-10 py-4 rounded-xl font-bold text-sm tracking-wide shadow-lg active:scale-95 transition-all w-full md:w-auto" type="submit" name="register">
                Complete Registration
              </button>
            </div>
            <p class="text-xs text-on-surface-variant leading-relaxed">School-managed roles such as teachers, accountants, and administrators should use school-first or invite-based onboarding instead of public signup.</p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Simple Page Footer -->
  <footer class="py-8 text-center">
    <p class="text-[0.65rem] font-bold uppercase tracking-widest text-slate-400">
      © <?php echo date('Y'); ?> <?php echo APP_NAME; ?> • Academic Sentinel Management Systems
    </p>
  </footer>

  <script>
    // Role selector: show/hide student fields
    const roleInputs = document.querySelectorAll('input[name="role"]');
    const studentFields = document.getElementById('student_fields');
    roleInputs.forEach(input => {
      input.addEventListener('change', function() {
        if (this.value === 'student') {
          studentFields.classList.remove('hidden');
          document.getElementById('date_of_birth').required = true;
          document.getElementById('grade_level').required = true;
        } else {
          studentFields.classList.add('hidden');
          document.getElementById('date_of_birth').required = false;
          document.getElementById('grade_level').required = false;
        }
      });
      if (input.checked) input.dispatchEvent(new Event('change'));
    });

    function togglePassword(fieldId, btn) {
      var input = document.getElementById(fieldId);
      var icon = btn.querySelector('.material-symbols-outlined');
      if (!input || !icon) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
    }
  </script>
</body>

</html>
