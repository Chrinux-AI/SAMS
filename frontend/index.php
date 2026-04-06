<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

if (is_logged_in()) {
  $role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'student';
  $dashboardPath = get_role_dashboard_path($role);
  if (strpos($dashboardPath, '/attendance/') === 0) {
    $dashboardPath = substr($dashboardPath, strlen('/attendance/'));
  }
  if ($dashboardPath === '' || $dashboardPath === 'login.php') {
    $dashboardPath = 'login.php';
  }
  header('Location: ' . $dashboardPath);
  exit;
}

// Pull real statistics via LandingContentService if available
if (class_exists('LandingContentService')) {
  $stats = LandingContentService::getStats();
} else {
  $stats = ['students' => 0, 'teachers' => 0, 'classes' => 0, 'parents' => 0, 'attendance_today' => 0, 'attendance_rate' => 0];
}
$stat_students = $stats['students'];
$stat_teachers = $stats['teachers'];
$stat_classes = $stats['classes'];
$stat_parents = $stats['parents'];
$stat_today = $stats['attendance_today'];
$stat_rate = $stats['attendance_rate'];
?>
<!DOCTYPE html>
<html class="light scroll-smooth" lang="en">

<head>
  <?php include_once "includes/favicon-loader.php"; ?>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title><?php echo APP_NAME; ?> | Smart School Attendance Management</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
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
            "background": "#fcfcfd",
            "on-background": "#191c1d",
            "surface": "#ffffff",
            "on-surface": "#191c1d",
            "on-surface-variant": "#454652",
            "surface-container-lowest": "#ffffff",
            "surface-container-low": "#f8f9fa",
            "surface-container": "#edeeef",
            "surface-container-high": "#e7e8e9",
            "outline": "#e5e7eb",
            "outline-variant": "#c6c5d4",
            "primary-fixed": "#e0e0ff",
            "error": "#ba1a1a",
            "secondary-container": "#cfe6f2"
          },
          fontFamily: {
            "headline": ["Manrope", "sans-serif"],
            "body": ["Inter", "sans-serif"],
            "label": ["Inter", "sans-serif"]
          },
          borderRadius: {
            "DEFAULT": "0.5rem",
            "lg": "0.75rem",
            "xl": "1rem",
            "2xl": "1.5rem",
            "3xl": "2rem"
          },
          animation: {
            'float': 'float 6s ease-in-out infinite',
            'float-delayed': 'float 6s ease-in-out 3s infinite',
            'spin-slow': 'spin 12s linear infinite',
            'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            'typing': 'blink 1s step-end infinite',
          },
          keyframes: {
            float: {
              '0%, 100%': {
                transform: 'translateY(0)'
              },
              '50%': {
                transform: 'translateY(-20px)'
              },
            },
            blink: {
              '0%, 100%': {
                opacity: '1'
              },
              '50%': {
                opacity: '0'
              },
            }
          }
        },
      },
    }
  </script>
  <style>
    body {
      background-color: #fcfcfd;
      color: #191c1d;
    }

    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    .premium-gradient {
      background: linear-gradient(135deg, #000666 0%, #303f9f 100%);
    }

    .text-gradient {
      background: linear-gradient(135deg, #000666 0%, #4c56af 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .glass-header {
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .glass-card {
      background: #ffffff;
      border: 1px solid rgba(0, 7, 103, 0.06);
      box-shadow: 0 10px 40px -10px rgba(0, 7, 103, 0.08);
    }

    .mesh-bg {
      background-image:
        radial-gradient(at 0% 0%, rgba(26, 35, 126, 0.04) 0px, transparent 40%),
        radial-gradient(at 100% 0%, rgba(76, 86, 175, 0.04) 0px, transparent 40%),
        radial-gradient(at 50% 100%, rgba(0, 6, 102, 0.03) 0px, transparent 50%);
    }

    /* Reveal Animations */
    .reveal {
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .reveal.active {
      opacity: 1;
      transform: translateY(0);
    }

    .reveal-left {
      opacity: 0;
      transform: translateX(-30px);
      transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .reveal-left.active {
      opacity: 1;
      transform: translateX(0);
    }

    .reveal-right {
      opacity: 0;
      transform: translateX(30px);
      transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .reveal-right.active {
      opacity: 1;
      transform: translateX(0);
    }

    /* Magnetic Button Reset */
    .magnetic {
      display: inline-flex;
      position: relative;
      z-index: 10;
    }

    .magnetic-inner {
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    /* Features Grid Lines */
    .grid-lines {
      background-size: 40px 40px;
      background-image: linear-gradient(to right, rgba(0, 0, 0, 0.02) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
    }
  </style>
</head>

<body class="font-body mesh-bg overflow-x-hidden">

  <!-- Navigation Bar -->
  <header class="fixed top-0 w-full z-50 glass-header flex transition-all duration-300 transform translate-y-0" id="navbar">
    <nav class="flex justify-between items-center px-6 py-4 max-w-[1400px] w-full mx-auto">
      <div class="flex items-center gap-3 magnetic" data-magnetic>
        <div class="magnetic-inner flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl premium-gradient flex items-center justify-center text-white shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined">school</span>
          </div>
          <div class="text-xl font-headline font-extrabold text-primary tracking-tight"><?php echo APP_NAME; ?></div>
        </div>
      </div>

      <div class="hidden md:flex items-center gap-8 font-label text-sm font-semibold tracking-wide">
        <a class="text-on-surface-variant hover:text-primary transition-colors hover:-translate-y-0.5" href="#features">Features</a>
        <a class="text-on-surface-variant hover:text-primary transition-colors hover:-translate-y-0.5" href="#project-goals">Project Goals</a>
        <a class="text-on-surface-variant hover:text-primary transition-colors hover:-translate-y-0.5" href="#public-ai">SAMS AI</a>
        <a class="text-on-surface-variant hover:text-primary transition-colors hover:-translate-y-0.5" href="#stats">Impact</a>
      </div>

      <div class="flex items-center gap-4">
        <div class="hidden sm:block magnetic" data-magnetic>
          <a href="login.php" class="magnetic-inner px-4 py-2 text-primary font-headline text-sm font-bold tracking-wide rounded-lg hover:bg-primary/5 transition-all">Log In</a>
        </div>
        <div class="magnetic" data-magnetic>
          <a href="register.php" class="magnetic-inner px-6 py-2.5 premium-gradient text-white font-headline text-sm font-bold tracking-wide rounded-[14px] hover:shadow-xl hover:shadow-primary/20 hover:-translate-y-1 transition-all duration-300 flex items-center gap-2">
            Get Started <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </a>
        </div>
      </div>
    </nav>
  </header>

  <main class="w-full">

    <!-- Hero Section -->
    <section class="relative pt-40 pb-20 px-6 max-w-[1400px] mx-auto min-h-[90vh] flex flex-col justify-center overflow-hidden">
      <!-- Abstract Flow Background Elements -->
      <div class="absolute top-20 right-[10%] w-[500px] h-[500px] bg-indigo-100 rounded-full blur-[100px] opacity-60 -z-10 animate-pulse-slow"></div>
      <div class="absolute bottom-20 left-[10%] w-[400px] h-[400px] bg-blue-100 rounded-full blur-[80px] opacity-50 -z-10 animate-float"></div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div class="reveal max-w-2xl">
          <div class="inline-flex items-center gap-2 px-4 py-1.5 mb-6 rounded-full border border-primary/15 bg-white shadow-sm text-primary text-xs font-label font-bold tracking-widest uppercase">
            <span class="material-symbols-outlined text-sm text-blue-600">electric_bolt</span>
            Academic SaaS OS v2.0
          </div>

          <h1 class="text-5xl md:text-7xl font-headline font-extrabold text-on-surface tracking-tighter mb-6 leading-[1.1]">
            The Ecosystem for <br />
            <span class="text-gradient min-h-[5rem] inline-block" id="typewriter-text"></span><span class="animate-typing border-r-4 border-primary ml-1 h-[1em] inline-block"></span>
          </h1>

          <p class="text-lg md:text-xl text-on-surface-variant mb-10 leading-relaxed font-body">
            SAMS (Smart Attendance Management System) transforms educational institutions with real-time biometric tracking, unified AI assistance, and role-optimized portals. Say goodbye to spreadsheets.
          </p>

          <div class="flex flex-col sm:flex-row items-center gap-4">
            <div class="magnetic w-full sm:w-auto" data-magnetic>
              <a href="register.php" class="magnetic-inner w-full sm:w-auto px-8 py-4 premium-gradient text-white font-headline font-bold text-lg rounded-2xl shadow-xl shadow-primary/20 hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">domain_add</span>
                Register Institution
              </a>
            </div>
            <div class="magnetic w-full sm:w-auto" data-magnetic>
              <a href="login.php" class="magnetic-inner w-full sm:w-auto px-8 py-4 bg-white text-primary border border-outline-variant/30 font-headline font-bold text-lg rounded-2xl hover:bg-surface-container-low transition-all duration-300 shadow-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">login</span>
                Access Portal
              </a>
            </div>
          </div>

          <div class="mt-8 flex items-center gap-4 text-sm text-on-surface-variant font-medium">
            <div class="flex -space-x-3">
              <img class="w-10 h-10 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=Admin+School&background=0D8ABC&color=fff" alt="User">
              <img class="w-10 h-10 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=Teacher+A&background=2E7D32&color=fff" alt="User">
              <img class="w-10 h-10 rounded-full border-2 border-white" src="https://ui-avatars.com/api/?name=Student+B&background=F9A825&color=fff" alt="User">
              <div class="w-10 h-10 rounded-full border-2 border-white bg-surface-container-high flex items-center justify-center text-xs">+1k</div>
            </div>
            <p>Trusted by premier institutions</p>
          </div>
        </div>

        <!-- Hero Visual Composition -->
        <div class="relative h-[600px] hidden lg:block reveal-right">
          <!-- Main Dashboard Card -->
          <div class="absolute right-0 top-1/2 -translate-y-1/2 w-[550px] glass-card rounded-[2rem] p-6 shadow-2xl z-20 animate-float border border-white/50 bg-white/80">
            <div class="flex items-center justify-between mb-6">
              <div class="flex gap-2">
                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                <div class="w-3 h-3 rounded-full bg-green-400"></div>
              </div>
              <div class="px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full">Admin Sentinel</div>
            </div>
            <div class="flex gap-4 mb-6">
              <div class="flex-1 bg-surface-container-low p-4 rounded-xl border border-outline/50">
                <span class="material-symbols-outlined text-green-600 mb-2">trending_up</span>
                <div class="text-2xl font-bold font-headline">96.4%</div>
                <div class="text-xs text-on-surface-variant mt-1">Daily Attendance</div>
              </div>
              <div class="flex-1 bg-surface-container-low p-4 rounded-xl border border-outline/50">
                <span class="material-symbols-outlined text-blue-600 mb-2">groups</span>
                <div class="text-2xl font-bold font-headline">1,402</div>
                <div class="text-xs text-on-surface-variant mt-1">Active Students</div>
              </div>
            </div>
            <div class="h-32 bg-surface-container-low rounded-xl border border-outline/50 p-4 relative overflow-hidden">
              <!-- decorative chart lines -->
              <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between h-20 gap-2">
                <div class="w-full bg-primary/20 rounded-t-sm h-[40%]"></div>
                <div class="w-full bg-primary/40 rounded-t-sm h-[60%]"></div>
                <div class="w-full bg-primary/60 rounded-t-sm h-[80%]"></div>
                <div class="w-full bg-primary rounded-t-sm h-[100%]"></div>
                <div class="w-full bg-primary/80 rounded-t-sm h-[70%]"></div>
                <div class="w-full bg-primary/50 rounded-t-sm h-[50%]"></div>
                <div class="w-full bg-green-500 rounded-t-sm h-[90%] relative">
                  <div class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold bg-white px-2 py-0.5 rounded shadow">Today</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Floating Elements -->
          <div class="absolute left-0 top-20 w-48 glass-card rounded-2xl p-4 shadow-xl z-30 animate-float-delayed rotate-[-5deg] bg-white border border-white/60">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">fingerprint</span>
              </div>
              <div>
                <div class="text-sm font-bold">Bio-Sync</div>
                <div class="text-xs text-emerald-600">Secure Match</div>
              </div>
            </div>
          </div>

          <div class="absolute right-10 bottom-10 w-56 glass-card rounded-2xl p-4 shadow-xl z-30 animate-float rotate-[3deg] bg-white border border-white/60">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">smart_toy</span>
              </div>
              <div>
                <div class="text-sm font-bold">Public AI Active</div>
                <div class="text-xs text-blue-600">Monitoring System</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Live Stats Banner -->
    <section class="py-12 border-y border-outline/30 bg-white grid-lines" id="stats">
      <div class="max-w-[1400px] mx-auto px-6">
        <div class="flex flex-wrap justify-between gap-8 sm:gap-4 reveal">
          <div class="flex flex-col">
            <h4 class="text-4xl lg:text-5xl font-headline font-extrabold text-primary mb-2"><?php echo number_format($stat_students); ?>+</h4>
            <p class="text-sm font-label text-on-surface-variant uppercase tracking-widest font-semibold">Active Students</p>
          </div>
          <div class="flex flex-col">
            <h4 class="text-4xl lg:text-5xl font-headline font-extrabold text-primary mb-2"><?php echo number_format($stat_teachers); ?></h4>
            <p class="text-sm font-label text-on-surface-variant uppercase tracking-widest font-semibold">Faculty Members</p>
          </div>
          <div class="flex flex-col">
            <h4 class="text-4xl lg:text-5xl font-headline font-extrabold text-emerald-600 mb-2"><?php echo number_format($stat_today); ?></h4>
            <p class="text-sm font-label text-on-surface-variant uppercase tracking-widest font-semibold">Checked In Today</p>
          </div>
          <div class="flex flex-col">
            <h4 class="text-4xl lg:text-5xl font-headline font-extrabold text-blue-600 mb-2"><?php echo number_format((float)$stat_rate, 1); ?>%</h4>
            <p class="text-sm font-label text-on-surface-variant uppercase tracking-widest font-semibold">Daily Average</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Project Goals Section -->
    <section class="py-24 px-6 max-w-[1400px] mx-auto" id="project-goals">
      <div class="text-center mb-14 reveal">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 mb-6 rounded-full border border-primary/10 bg-primary/5 text-primary text-xs font-label font-bold tracking-widest uppercase">
          <span class="material-symbols-outlined text-[14px]">flag</span> Strategic Direction
        </div>
        <h2 class="text-4xl md:text-5xl font-headline font-extrabold text-on-surface mb-4 tracking-tight">Project Goals & Our Mindset</h2>
        <p class="text-on-surface-variant max-w-3xl mx-auto text-lg leading-relaxed">Built for global scalability with dynamic regional configuration. We are implementing a secure, scalable school platform utilizing an <strong>Institution-First Registration</strong> approach, paired with immutable audit proofing and role-aware session controls.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 reveal">
        <div class="glass-card p-6 rounded-2xl border border-primary/20 bg-primary/5">
          <span class="material-symbols-outlined text-primary mb-4 text-3xl">domain_add</span>
          <h3 class="font-headline font-bold text-xl mb-2">School-First Registration</h3>
          <p class="text-sm text-on-surface-variant">Schools must register their institution first. Once their tenant environment is provisioned, admins obtain access to a portal where they can begin registering individuals.</p>
        </div>
        <div class="glass-card p-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/5">
          <span class="material-symbols-outlined text-emerald-600 mb-4 text-3xl">public</span>
          <h3 class="font-headline font-bold text-xl mb-2">Dynamic Localization</h3>
          <p class="text-sm text-on-surface-variant">Flexible settings support multiple countries and currencies from day one. Schools configure their specific regional standards, maintaining agility for global operations.</p>
        </div>
        <div class="glass-card p-6 rounded-2xl border border-amber-500/20 bg-amber-500/5">
          <span class="material-symbols-outlined text-amber-600 mb-4 text-3xl">fingerprint</span>
          <h3 class="font-headline font-bold text-xl mb-2">Biometric Assurance</h3>
          <p class="text-sm text-on-surface-variant">Biometric verification for sensitive actions, ensuring privacy-first template storage to eradicate bypasses in attendance and financial tasks.</p>
        </div>
        <div class="glass-card p-6 rounded-2xl border border-blue-500/20 bg-blue-500/5">
          <span class="material-symbols-outlined text-blue-600 mb-4 text-3xl">account_tree</span>
          <h3 class="font-headline font-bold text-xl mb-2">Blockchain Audit Trail</h3>
          <p class="text-sm text-on-surface-variant">Hash-chain proofs for financial transactions across the institution, protecting transparency to investors and governance oversight.</p>
        </div>
      </div>

      <div class="mt-10 flex flex-col sm:flex-row justify-center items-center gap-4 reveal">
        <a href="<?php echo APP_URL; ?>/PROJECT_BLOCKCHAIN_GOALS.md" class="px-6 py-3 rounded-xl premium-gradient text-white font-headline font-bold text-sm flex items-center gap-2">
          <span class="material-symbols-outlined text-sm">description</span>
          Read Full Goals Document
        </a>
        <a href="login.php" class="px-6 py-3 rounded-xl border border-outline-variant text-primary font-headline font-bold text-sm flex items-center gap-2 hover:bg-primary/5">
          <span class="material-symbols-outlined text-sm">login</span>
          Log in to Manage Goals
        </a>
      </div>
    </section>

    <!-- Core Features / Why SAMS SaaS -->
    <section class="py-32 px-6 max-w-[1400px] mx-auto overflow-hidden" id="features">
      <div class="text-center mb-20 reveal">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 mb-6 rounded-full border border-primary/10 bg-primary/5 text-primary text-xs font-label font-bold tracking-widest uppercase">
          <span class="material-symbols-outlined text-[14px]">grid_view</span> SaaS Infrastructure
        </div>
        <h2 class="text-4xl md:text-5xl font-headline font-extrabold text-on-surface mb-6 tracking-tight">An Ecosystem Built to Scale</h2>
        <p class="text-on-surface-variant max-w-2xl mx-auto text-lg leading-relaxed">Everything you need to modernize your school’s daily operations, bundled into one powerful, role-centric software-as-a-service.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

        <!-- Feature Card 1 -->
        <div class="glass-card p-8 rounded-[2rem] hover:-translate-y-3 transition-all duration-500 group reveal-left">
          <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
            <span class="material-symbols-outlined text-3xl">fingerprint</span>
          </div>
          <h3 class="text-2xl font-headline font-bold text-on-surface mb-3">Enterprise Biometrics</h3>
          <p class="text-on-surface-variant leading-relaxed mb-6 font-medium">
            Integrate directly with fingerprint and facial recognition hardware to guarantee 100% attendance accuracy. No more buddy punching or lost registers.
          </p>
          <a href="#" class="text-blue-600 font-semibold text-sm flex items-center gap-1 group-hover:gap-2 transition-all">Learn more <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
        </div>

        <!-- Feature Card 2 -->
        <div class="glass-card p-8 rounded-[2rem] hover:-translate-y-3 transition-all duration-500 group reveal">
          <div class="w-16 h-16 bg-indigo-50 text-primary rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-primary group-hover:text-white transition-colors duration-300">
            <span class="material-symbols-outlined text-3xl">account_tree</span>
          </div>
          <h3 class="text-2xl font-headline font-bold text-on-surface mb-3">Role-Optimized Portals</h3>
          <p class="text-on-surface-variant leading-relaxed mb-6 font-medium">
            Admins manage policy, Teachers manage classes, Parents monitor children, and Students view their own progress in highly customized, tailored views.
          </p>
          <a href="#" class="text-primary font-semibold text-sm flex items-center gap-1 group-hover:gap-2 transition-all">Explore portals <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
        </div>

        <!-- Feature Card 3 -->
        <div class="glass-card p-8 rounded-[2rem] hover:-translate-y-3 transition-all duration-500 group reveal-right">
          <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-8 shadow-sm group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
            <span class="material-symbols-outlined text-3xl">monitoring</span>
          </div>
          <h3 class="text-2xl font-headline font-bold text-on-surface mb-3">Real-Time Telemetry</h3>
          <p class="text-on-surface-variant leading-relaxed mb-6 font-medium">
            Gain executive-level insights instantly. Export compliance reports, view demographic performance, and predict dropout risks effortlessly.
          </p>
          <a href="#" class="text-emerald-600 font-semibold text-sm flex items-center gap-1 group-hover:gap-2 transition-all">View analytics <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
        </div>

      </div>
    </section>

    <!-- Public AI Showcase Section (Fixing the request) -->
    <section class="py-24 relative overflow-hidden bg-primary text-white" id="public-ai">
      <!-- Background Decor -->
      <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-400/20 via-primary to-primary"></div>
      <div class="absolute top-0 right-0 w-[800px] h-[800px] bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.05)_0,transparent_60%)]"></div>

      <div class="max-w-[1400px] mx-auto px-6 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
          <div class="reveal-left">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 mb-8 rounded-full border border-blue-400/30 bg-blue-500/10 text-blue-300 text-xs font-label font-bold tracking-widest uppercase">
              <span class="material-symbols-outlined text-[14px]">smart_toy</span> SAMS Public AI
            </div>
            <h2 class="text-4xl md:text-5xl font-headline font-extrabold mb-6 tracking-tight">Your Dedicated <br /><span class="text-blue-400">Security Copilot</span></h2>
            <p class="text-indigo-100/80 max-w-xl text-lg leading-relaxed font-medium mb-8">
              The SAMS Public AI has been fully restored and upgraded. Seamlessly converse with the system to generate complex attendance reports, audit security logs, or securely provision accounts via natural language instead of database queries.
            </p>

            <ul class="space-y-4 mb-10 text-indigo-100">
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-blue-400 mt-1">check_circle</span>
                <span class="font-medium">Immutable Audit Logging ensures every AI action is tracked.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-blue-400 mt-1">check_circle</span>
                <span class="font-medium">Zero-Trust Permission Engine halts unauthorized commands.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-blue-400 mt-1">check_circle</span>
                <span class="font-medium">Automatic intelligent geofencing configuration.</span>
              </li>
            </ul>

            <div class="magnetic" data-magnetic>
              <a href="login.php" class="magnetic-inner px-8 py-4 bg-white text-primary font-headline font-bold text-lg rounded-2xl shadow-[0_0_30px_rgba(255,255,255,0.2)] hover:scale-105 transition-all duration-300 flex items-center justify-center gap-2 w-fit">
                Try the Copilot Now
              </a>
            </div>
          </div>

          <!-- AI Chat Mockup -->
          <div class="relative reveal-right">
            <div class="bg-[#0a0f2c] rounded-3xl border border-white/10 shadow-2xl overflow-hidden shadow-[0_30px_60px_-15px_rgba(0,0,0,0.5)]">
              <div class="px-6 py-4 bg-[#11183c] border-b border-white/5 flex items-center gap-3">
                <div class="flex gap-2">
                  <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                  <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                  <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                </div>
                <div class="text-sm font-label text-indigo-300 ml-4 font-semibold tracking-wide">SAMS AI Terminal</div>
              </div>
              <div class="p-6 space-y-6 min-h-[300px]">
                <div class="flex items-end gap-3 opacity-90">
                  <div class="w-8 h-8 rounded-full bg-indigo-600/30 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-sm text-indigo-300">person</span>
                  </div>
                  <div class="bg-[#11183c] border border-white/5 rounded-2xl rounded-bl-sm px-4 py-3 text-sm text-indigo-100 max-w-[85%] font-medium">
                    Can you generate an attendance report for Grade 10 Science this week?
                  </div>
                </div>
                <div class="flex items-end gap-3 justify-end opacity-100">
                  <div class="premium-gradient rounded-2xl rounded-br-sm px-4 py-3 text-sm text-white max-w-[85%] font-medium shadow-lg">
                    Certainly. I've aggregated the biometric logs for Grade 10 Science. Attendance averages 94.2%. I noticed 3 recurrent absentees. I have generated the PDF report and placed it in your dashboard documents.
                  </div>
                  <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center shrink-0 shadow-[0_0_15px_rgba(59,130,246,0.5)]">
                    <span class="material-symbols-outlined text-sm text-white">smart_toy</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Modules Grid & Secondary Features -->
    <section class="py-24 px-6 bg-surface-container-low border-b border-outline/20">
      <div class="max-w-[1400px] mx-auto text-center reveal">
        <h2 class="text-3xl md:text-5xl font-headline font-extrabold text-on-surface mb-16 tracking-tight">Complete Institutional Control</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-left">
          <div class="bg-white p-6 rounded-2xl border border-outline/50 hover:shadow-xl transition-shadow duration-300">
            <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-4"><span class="material-symbols-outlined">forum</span></div>
            <h4 class="font-bold text-on-surface mb-2 font-headline text-lg">Communication Hub</h4>
            <p class="text-sm text-on-surface-variant font-medium">Direct SMS & push notifications to parents regarding lateness or emergencies.</p>
          </div>
          <div class="bg-white p-6 rounded-2xl border border-outline/50 hover:shadow-xl transition-shadow duration-300">
            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mb-4"><span class="material-symbols-outlined">payments</span></div>
            <h4 class="font-bold text-on-surface mb-2 font-headline text-lg">Finance Module</h4>
            <p class="text-sm text-on-surface-variant font-medium">Integrated ledger access for Bursar and Accounting staff. Track fee payments securely.</p>
          </div>
          <div class="bg-white p-6 rounded-2xl border border-outline/50 hover:shadow-xl transition-shadow duration-300">
            <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center mb-4"><span class="material-symbols-outlined">local_library</span></div>
            <h4 class="font-bold text-on-surface mb-2 font-headline text-lg">Library System</h4>
            <p class="text-sm text-on-surface-variant font-medium">Automated book circulation tracking tied directly to student ID and bio-data.</p>
          </div>
          <div class="bg-white p-6 rounded-2xl border border-outline/50 hover:shadow-xl transition-shadow duration-300">
            <div class="w-10 h-10 bg-pink-50 text-pink-600 rounded-xl flex items-center justify-center mb-4"><span class="material-symbols-outlined">directions_bus</span></div>
            <h4 class="font-bold text-on-surface mb-2 font-headline text-lg">Transport Tracking</h4>
            <p class="text-sm text-on-surface-variant font-medium">Monitor school bus routes and student boarding logs in real-time on a map.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Final Advanced CTA -->
    <section class="py-24 px-6 relative overflow-hidden">
      <div class="absolute inset-0 mesh-bg -z-10"></div>
      <div class="max-w-[1200px] mx-auto">
        <div class="glass-card rounded-[2.5rem] p-12 lg:p-20 text-center relative overflow-hidden reveal shadow-2xl border-white/80 bg-white/80 backdrop-blur-xl">
          <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-primary/5 rounded-bl-[100%] pointer-events-none"></div>
          <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-emerald-500/5 rounded-tr-[100%] pointer-events-none"></div>

          <h2 class="text-4xl md:text-6xl font-headline font-extrabold text-on-surface mb-6 tracking-tighter relative z-10">Deploy SAMS for Your Campus</h2>
          <p class="text-on-surface-variant text-lg md:text-xl mb-12 max-w-2xl mx-auto font-medium relative z-10">Join elite institutions globally. Elevate security, automate attendance, and harness the power of AI to supercharge your management today.</p>

          <div class="flex flex-col sm:flex-row justify-center items-center gap-6 relative z-10">
            <div class="magnetic w-full sm:w-auto" data-magnetic>
              <a href="register.php" class="magnetic-inner w-full sm:w-auto px-10 py-5 premium-gradient text-white font-headline font-extrabold rounded-2xl text-lg shadow-xl shadow-primary/20 hover:scale-[1.03] transition-all duration-300 flex justify-center uppercase tracking-wide">
                Initiate Deployment
              </a>
            </div>
            <div class="magnetic w-full sm:w-auto" data-magnetic>
              <a href="login.php" class="magnetic-inner w-full sm:w-auto px-10 py-5 bg-surface-container-low text-primary border-2 border-primary/10 font-headline font-bold rounded-2xl text-lg hover:border-primary/30 transition-all duration-300 flex justify-center">
                Request Demo
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Footer -->
  <footer class="bg-[#040822] text-white pt-20 pb-10 border-t-4 border-primary">
    <div class="max-w-[1400px] mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
      <div class="md:col-span-1">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white">
            <span class="material-symbols-outlined">domain</span>
          </div>
          <div class="font-headline font-extrabold text-2xl tracking-tight"><?php echo APP_NAME; ?></div>
        </div>
        <p class="text-indigo-200/60 leading-relaxed font-body text-sm font-medium">
          Developing resilient, high-availability software ecosystems for modern educational institutions worldwide.
        </p>
      </div>

      <div>
        <h4 class="font-headline font-bold text-lg mb-6">Product</h4>
        <ul class="space-y-4 text-sm text-indigo-200/70 font-medium">
          <li><a href="#" class="hover:text-white transition-colors">Biometric Core</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Role Portals</a></li>
          <li><a href="#" class="hover:text-white transition-colors">SAMS Public AI</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Analytics Engine</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-headline font-bold text-lg mb-6">Resources</h4>
        <ul class="space-y-4 text-sm text-indigo-200/70 font-medium">
          <li><a href="#" class="hover:text-white transition-colors">Documentation</a></li>
          <li><a href="#" class="hover:text-white transition-colors">API Reference</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Hardware Setup</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Case Studies</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-headline font-bold text-lg mb-6">Company</h4>
        <ul class="space-y-4 text-sm text-indigo-200/70 font-medium">
          <li><a href="#" class="hover:text-white transition-colors">About Us</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Security</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
          <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
        </ul>
      </div>
    </div>

    <div class="max-w-[1400px] mx-auto px-6 pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
      <div class="text-sm font-label text-indigo-200/40 font-medium">
        © <?php echo date('Y'); ?> Academic Sentinel Systems. All rights reserved.
      </div>
      <div class="flex gap-6">
        <span class="material-symbols-outlined text-indigo-200/40 hover:text-white transition-colors cursor-pointer">language</span>
        <span class="material-symbols-outlined text-indigo-200/40 hover:text-white transition-colors cursor-pointer">shield</span>
        <span class="material-symbols-outlined text-indigo-200/40 hover:text-white transition-colors cursor-pointer">verified_user</span>
      </div>
    </div>
  </footer>

  <script>
    // 1. Navigation Scroll Effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        navbar.classList.add('shadow-md');
        navbar.classList.add('bg-white/90');
        navbar.classList.remove('glass-header');
      } else {
        navbar.classList.remove('shadow-md');
        navbar.classList.remove('bg-white/90');
        navbar.classList.add('glass-header');
      }
    });

    // 2. Typing Animation
    document.addEventListener('DOMContentLoaded', function() {
      const textElement = document.getElementById('typewriter-text');
      const words = ["Premium Academies.", "Advanced Campuses.", "Global Universities.", "Future Leaders."];
      let wordIndex = 0;
      let charIndex = 0;
      let isDeleting = false;
      let typingSpeed = 100;

      function type() {
        const currentWord = words[wordIndex];

        if (isDeleting) {
          textElement.textContent = currentWord.substring(0, charIndex - 1);
          charIndex--;
          typingSpeed = 50;
        } else {
          textElement.textContent = currentWord.substring(0, charIndex + 1);
          charIndex++;
          typingSpeed = 150;
        }

        if (!isDeleting && charIndex === currentWord.length) {
          isDeleting = true;
          typingSpeed = 2000; // Pause at end of word
        } else if (isDeleting && charIndex === 0) {
          isDeleting = false;
          wordIndex = (wordIndex + 1) % words.length;
          typingSpeed = 500; // Pause before next word
        }

        setTimeout(type, typingSpeed);
      }

      // Start typing
      setTimeout(type, 1000);
    });

    // 3. Scroll Reveal Animation (Intersection Observer)
    const revealElements = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    const revealOptions = {
      threshold: 0.1,
      rootMargin: "0px 0px -50px 0px"
    };

    const revealOnScroll = new IntersectionObserver(function(entries, observer) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('active');
          observer.unobserve(entry.target); // Only animate once
        }
      });
    }, revealOptions);

    revealElements.forEach(el => revealOnScroll.observe(el));

    // 4. Magnetic Hover Effect
    const magnetics = document.querySelectorAll('[data-magnetic]');

    magnetics.forEach(magnetic => {
      const inner = magnetic.querySelector('.magnetic-inner');

      magnetic.addEventListener('mousemove', e => {
        const rect = magnetic.getBoundingClientRect();
        const h = rect.width / 2;
        const w = rect.height / 2;

        // Calculate mouse position relative to center of element
        const x = e.clientX - rect.left - h;
        const y = e.clientY - rect.top - w;

        inner.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
      });

      magnetic.addEventListener('mouseleave', () => {
        inner.style.transform = `translate(0px, 0px)`;
      });
    });
  </script>

  <!-- Floating AI Chatbot Toggle -->
  <button id="chatbotToggle" class="fixed bottom-6 right-6 w-14 h-14 premium-gradient text-white rounded-full flex items-center justify-center shadow-2xl hover:scale-110 transition-transform duration-300 z-[9999]" aria-label="SAMS AI Chatbot">
    <span class="material-symbols-outlined text-[28px]">robot_2</span>
  </button>

  <!-- Chatbot Widget -->
  <div id="chatbot-widget" class="fixed bottom-24 right-6 w-[350px] lg:w-[400px] h-[500px] bg-white border border-outline/30 rounded-2xl shadow-2xl flex-col z-[9999] overflow-hidden transform transition-all duration-300 translate-y-4 opacity-0 pointer-events-none hidden">
    <div class="px-5 py-4 premium-gradient text-white flex justify-between items-center shadow-md">
      <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-white text-[24px]">smart_toy</span>
        <span class="font-headline font-bold text-lg tracking-tight">SAMS Assistant</span>
      </div>
      <button id="chatbotClose" class="text-white hover:text-red-200 transition-colors" aria-label="Close Chat">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <div id="chatbotMessages" class="flex-1 p-5 overflow-y-auto flex flex-col gap-4 bg-[#f8f9fa]">
      <div class="bg-white text-on-surface self-start rounded-2xl rounded-tl-sm px-4 py-3 text-sm font-medium border border-outline/20 shadow-sm max-w-[85%]">
        Hi! I'm your SAMS Public Assistant. How can I help you today?
      </div>
    </div>

    <div class="p-4 border-t border-outline/20 bg-white flex gap-2">
      <input type="text" id="chatbotInput" placeholder="Message SAMS AI..." class="flex-1 px-4 py-2 bg-surface-container-lowest rounded-xl border border-outline/30 text-sm focus:ring-2 focus:ring-primary focus:border-primary transition-all shadow-inner outline-none">
      <button id="chatbotSend" class="w-10 h-10 premium-gradient text-white rounded-xl flex items-center justify-center hover:shadow-lg transition-all" aria-label="Send Message">
        <span class="material-symbols-outlined text-[20px]">send</span>
      </button>
    </div>
  </div>

  <script>
    // Chatbot Widget Logic
    document.addEventListener('DOMContentLoaded', function() {
      const toggle = document.getElementById('chatbotToggle');
      const widget = document.getElementById('chatbot-widget');
      const close = document.getElementById('chatbotClose');
      const send = document.getElementById('chatbotSend');
      const input = document.getElementById('chatbotInput');
      const messages = document.getElementById('chatbotMessages');

      function toggleChatbot() {
        if (widget.classList.contains('hidden')) {
          widget.classList.remove('hidden');
          // trigger reflow
          void widget.offsetWidth;
          widget.classList.remove('translate-y-4', 'opacity-0', 'pointer-events-none');
          widget.classList.add('flex');
          input.focus();
        } else {
          widget.classList.add('translate-y-4', 'opacity-0', 'pointer-events-none');
          setTimeout(() => {
            widget.classList.add('hidden');
            widget.classList.remove('flex');
          }, 300);
        }
      }

      toggle.addEventListener('click', toggleChatbot);
      close.addEventListener('click', toggleChatbot);

      function sendMessage() {
        const text = input.value.trim();
        if (!text) return;

        // Add User Message
        const userDiv = document.createElement('div');
        userDiv.className = 'premium-gradient text-white self-end rounded-2xl rounded-tr-sm px-4 py-3 text-sm font-medium shadow-md max-w-[85%]';
        userDiv.textContent = text;
        messages.appendChild(userDiv);
        input.value = '';
        messages.scrollTop = messages.scrollHeight;

        // Add typing indicator
        const typingDiv = document.createElement('div');
        typingDiv.className = 'text-outline-variant self-start px-2 py-1 text-[11px] uppercase tracking-widest font-bold font-label animate-pulse';
        typingDiv.textContent = 'SAMS is processing...';
        messages.appendChild(typingDiv);
        messages.scrollTop = messages.scrollHeight;

        fetch('/api/chatbot.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              message: text
            })
          })
          .then(res => res.json())
          .then(data => {
            messages.removeChild(typingDiv);
            const botDiv = document.createElement('div');
            botDiv.className = 'bg-white text-on-surface self-start rounded-2xl rounded-tl-sm px-4 py-3 text-sm font-medium border border-outline/20 shadow-sm max-w-[85%]';
            botDiv.textContent = data.response || "I'm not sure how to help with that right now.";
            messages.appendChild(botDiv);
            messages.scrollTop = messages.scrollHeight;
          })
          .catch(err => {
            messages.removeChild(typingDiv);
            const errDiv = document.createElement('div');
            errDiv.className = 'bg-red-50 text-red-600 self-start rounded-2xl rounded-tl-sm px-4 py-3 text-sm font-medium max-w-[85%] border border-red-200';
            errDiv.textContent = "Sorry, I'm having trouble connecting to the network right now.";
            messages.appendChild(errDiv);
            messages.scrollTop = messages.scrollHeight;
          });
      }

      send.addEventListener('click', sendMessage);
      input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
      });
    });
  </script>
</body>

</html>
