<?php

/**
 * Master Dashboard Layout — SAMS Academic Sentinel
 *
 * ALL role dashboards MUST extend this layout. No panel should define
 * its own HTML skeleton, CSS imports, or sidebar include independently.
 *
 * Usage (in any role dashboard):
 *   $page_title = 'Dashboard';
 *   $page_icon  = 'dashboard';            // Material Symbols icon name
 *   $page_subtitle = 'Welcome back, Admin';
 *   $page_css = ['custom-page.css'];       // optional extra CSS
 *   $page_js  = ['custom-page.js'];        // optional extra JS
 *   $hide_header = false;                   // optional: hide top header
 *
 *   ob_start();
 *   // ... page content ...
 *   $page_content = ob_get_clean();
 *
 *   require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
 */

// Ensure config is loaded
if (!defined('BASE_PATH')) {
  require_once dirname(__DIR__, 3) . '/includes/config.php';
}

// Layout variables with safe defaults
$page_title     = $page_title ?? 'Dashboard';
$page_icon      = $page_icon ?? 'dashboard';
$page_subtitle  = $page_subtitle ?? '';
$page_css       = $page_css ?? [];
$page_js        = $page_js ?? [];
$page_content   = $page_content ?? '';
$hide_header    = $hide_header ?? false;
$body_class     = $body_class ?? '';

// Resolve user info from session
$_layout_name = $_SESSION['full_name'] ?? ($_SESSION['first_name'] ?? 'User') . ' ' . ($_SESSION['last_name'] ?? '');
$_layout_role = $_SESSION['role'] ?? 'user';
$_layout_initials = strtoupper(substr($_layout_name, 0, 2));

// Determine depth for relative paths
$_layout_depth = '';
$_script_dir = dirname($_SERVER['SCRIPT_FILENAME']);
$_base_dir = realpath(BASE_PATH);
if ($_script_dir && $_base_dir) {
  $_rel = str_replace('\\', '/', substr($_script_dir, strlen($_base_dir)));
  $_depth_count = substr_count(trim($_rel, '/'), '/');
  $_layout_depth = str_repeat('../', $_depth_count);
}
// Fallback: if we're in a role dir (admin/, teacher/ etc.), depth is ../
if (empty($_layout_depth)) {
  $_layout_depth = '../';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
  <link rel="manifest" href="/attendance/manifest.json">
  <meta name="theme-color" content="#1868DB">

  <!-- Favicon: Light and Dark Mode (logo3=light, logo2=dark) -->
  <link rel="icon" type="image/png" href="<?php echo $_layout_depth; ?>assets/images/icons/logo3.png">
  <link rel="icon" media="(prefers-color-scheme: dark)" type="image/png" href="<?php echo $_layout_depth; ?>assets/images/icons/logo2.png">
  <link rel="shortcut icon" href="<?php echo $_layout_depth; ?>assets/images/icons/logo3.png">
  <link rel="apple-touch-icon" href="<?php echo $_layout_depth; ?>assets/images/icons/logo3.png">

  <!-- Fonts: Manrope (Headlines) + Inter (Body/Label) + Material Symbols -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN with SAMS Core config -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "on-primary": "#ffffff",
            "outline": "#767683",
            "on-primary-fixed": "#000767",
            "on-primary-fixed-variant": "#343d96",
            "surface-bright": "#f8f9fa",
            "secondary-fixed-dim": "#b4cad6",
            "on-background": "#191c1d",
            "on-primary-container": "#8690ee",
            "tertiary": "#380b00",
            "inverse-on-surface": "#f0f1f2",
            "inverse-surface": "#2e3132",
            "secondary-container": "#cfe6f2",
            "background": "#f8f9fa",
            "on-surface-variant": "#454652",
            "surface-dim": "#d9dadb",
            "on-tertiary-fixed-variant": "#7b2e12",
            "inverse-primary": "#bdc2ff",
            "error-container": "#ffdad6",
            "on-tertiary-fixed": "#390c00",
            "outline-variant": "#c6c5d4",
            "on-error-container": "#93000a",
            "surface-variant": "#e1e3e4",
            "secondary": "#4c616c",
            "on-tertiary": "#ffffff",
            "tertiary-fixed-dim": "#ffb59d",
            "surface-container-lowest": "#ffffff",
            "on-error": "#ffffff",
            "primary-fixed": "#e0e0ff",
            "primary-fixed-dim": "#bdc2ff",
            "tertiary-fixed": "#ffdbd0",
            "secondary-fixed": "#cfe6f2",
            "surface-container-low": "#f3f4f5",
            "surface-container-highest": "#e1e3e4",
            "on-secondary-fixed-variant": "#354a53",
            "on-tertiary-container": "#e17c5a",
            "error": "#ba1a1a",
            "on-secondary": "#ffffff",
            "surface-container": "#edeeef",
            "primary": "#000666",
            "tertiary-container": "#5c1800",
            "primary-container": "#1a237e",
            "on-secondary-fixed": "#071e27",
            "on-secondary-container": "#526772",
            "on-surface": "#191c1d",
            "surface": "#f8f9fa",
            "surface-container-high": "#e7e8e9",
            "surface-tint": "#4c56af"
          },
          fontFamily: {
            "headline": ["Manrope", "system-ui", "sans-serif"],
            "body": ["Inter", "system-ui", "sans-serif"],
            "label": ["Inter", "system-ui", "sans-serif"]
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

  <!-- SAMS Core Design System -->
  <link href="<?php echo $_layout_depth; ?>assets/css/sams-core.css" rel="stylesheet">

  <!-- Legacy Font Awesome (for backwards compat with sidebar) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <?php foreach ($page_css as $_css): ?>
    <link href="<?php echo htmlspecialchars($_css); ?>" rel="stylesheet">
  <?php endforeach; ?>

  <!-- Theme loader (early) -->
  <script>
    (function() {
      var t = localStorage.getItem('sams-theme');
      if (t === 'dark') document.documentElement.classList.replace('light', 'dark');
    })();
  </script>
</head>

<body class="bg-background font-body text-on-surface flex min-h-screen <?php echo htmlspecialchars($body_class); ?>">

  <!-- Sidebar -->
  <?php include BASE_PATH . '/includes/sidebar-nav.php'; ?>

  <!-- Sidebar overlay for mobile -->
  <div class="sams-sidebar-overlay" onclick="closeMobileSidebar()"></div>

  <!-- Main Content Area -->
  <main class="sams-main">
    <?php if (!$hide_header): ?>
      <!-- Top Navigation Bar -->
      <header class="sams-topbar">
        <div class="flex items-center gap-4 flex-1">
          <!-- Logo -->
          <picture class="hidden md:block">
            <source media="(prefers-color-scheme: dark)" srcset="<?php echo $_layout_depth; ?>assets/logo/logo4.png">
            <img src="<?php echo $_layout_depth; ?>assets/logo/logo5.png" alt="SAMS Logo" class="h-10 w-auto rounded-2xl object-contain" style="max-width: 140px;">
          </picture>

          <!-- Mobile toggle -->
          <button class="sams-mobile-toggle" onclick="toggleMobileSidebar()" aria-label="Toggle sidebar">
            <span class="material-symbols-outlined">menu</span>
          </button>
          <!-- Search -->
          <div class="search-box">
            <span class="material-symbols-outlined search-icon">search</span>
            <input type="text" placeholder="Search students, faculty, or records..." id="sams-global-search">
          </div>
        </div>
        <div class="topbar-actions">
          <div class="flex items-center gap-1 border-r border-slate-200 pr-4" style="display:flex;gap:0.25rem">
            <button class="icon-btn" title="Notifications">
              <span class="material-symbols-outlined">notifications</span>
            </button>
            <button class="icon-btn" title="Toggle dark mode" onclick="toggleTheme()">
              <span class="material-symbols-outlined">dark_mode</span>
            </button>
            <button class="icon-btn" title="Help">
              <span class="material-symbols-outlined">help_outline</span>
            </button>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem">
            <div class="user-info">
              <div class="user-name"><?php echo htmlspecialchars($_layout_name); ?></div>
              <div class="user-role"><?php echo ucfirst(htmlspecialchars($_layout_role)); ?></div>
            </div>
            <div class="user-avatar"><?php echo $_layout_initials; ?></div>
          </div>
        </div>
      </header>
    <?php endif; ?>

    <!-- Canvas / Content -->
    <div class="sams-canvas sams-fade-in">
      <?php if (!$hide_header): ?>
        <!-- Page Header -->
        <div class="sams-page-header">
          <div>
            <h2 class="page-title"><?php echo htmlspecialchars($page_title); ?></h2>
            <?php if ($page_subtitle): ?>
              <p class="page-subtitle"><?php echo htmlspecialchars($page_subtitle); ?></p>
            <?php endif; ?>
          </div>
          <div class="page-actions" id="page-actions-slot">
            <!-- Pages can inject actions via JS -->
          </div>
        </div>
      <?php endif; ?>

      <?php echo $page_content; ?>
    </div>

    <!-- Footer -->
    <footer class="sams-footer">
      <div>v2.0.4 © <?php echo date('Y'); ?> SAMS Academic Sentinel</div>
      <div style="display:flex;gap:1.5rem">
        <a href="#">Help</a>
        <a href="#">Support</a>
        <a href="#">Privacy</a>
      </div>
    </footer>
  </main>

  <!-- Core JS -->
  <script>
    // Live clock
    setInterval(function() {
      var el = document.getElementById('live-time');
      if (el) el.textContent = new Date().toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
      });
    }, 1000);

    // Theme toggle
    function toggleTheme() {
      var html = document.documentElement;
      var isDark = html.classList.contains('dark');
      html.classList.toggle('dark', !isDark);
      html.classList.toggle('light', isDark);
      localStorage.setItem('sams-theme', isDark ? 'light' : 'dark');
    }

    // Mobile sidebar
    function toggleMobileSidebar() {
      document.querySelector('.sams-sidebar')?.classList.toggle('active');
      document.querySelector('.sams-sidebar-overlay')?.classList.toggle('active');
    }

    function closeMobileSidebar() {
      document.querySelector('.sams-sidebar')?.classList.remove('active');
      document.querySelector('.sams-sidebar-overlay')?.classList.remove('active');
    }
    // expose globally for sidebar-nav.php compat
    window.toggleMobileSidebar = toggleMobileSidebar;
    window.closeMobileSidebar = closeMobileSidebar;

    // AJAX helper
    function samsAjax(url, data, method) {
      method = method || 'GET';
      return fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: data ? JSON.stringify(data) : null
      }).then(function(r) {
        if (!r.ok) throw new Error('Network error');
        return r.json();
      });
    }

    // Notification toasts
    function showNotification(msg, type) {
      type = type || 'info';
      var colorMap = {
        success: '#059669',
        warning: '#d97706',
        danger: '#dc2626',
        info: '#2563eb'
      };
      var el = document.createElement('div');
      el.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;min-width:300px;padding:14px 20px;border-radius:12px;background:white;border-left:4px solid ' + (colorMap[type] || colorMap.info) + ';box-shadow:0 8px 24px rgba(0,0,0,0.12);font-size:0.875rem;animation:samsFadeIn 0.3s ease';
      el.textContent = msg;
      document.body.appendChild(el);
      setTimeout(function() {
        el.remove();
      }, 5000);
    }
  </script>
  <?php foreach ($page_js as $_js): ?>
    <script src="<?php echo htmlspecialchars($_js); ?>"></script>
  <?php endforeach; ?>
  <script src="<?php echo $_layout_depth; ?>assets/js/session-monitor.js"></script>
</body>

</html>
