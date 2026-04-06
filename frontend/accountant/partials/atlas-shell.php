<?php

if (!function_exists('render_accountant_atlas_shell')) {
  /**
   * Render shared Atlas Modern shell for accountant pages.
   *
   * @param string $pageTitle
   * @param string $activeTab one of: dashboard|ledger|expenses|income|payroll|reports|settings|support
   * @param string $contentHtml pre-rendered page content
   * @param string $fullName
   */
  function render_accountant_atlas_shell(string $pageTitle, string $activeTab, string $contentHtml, string $fullName = 'Accountant'): void
  {
    $initial = strtoupper(substr(trim($fullName), 0, 1));
    if ($initial === '') {
      $initial = 'A';
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $appBase = '';
    if (preg_match('#^(.*?)/frontend(?:/.*)?$#', $scriptName, $m)) {
      $appBase = (string)($m[1] ?? '');
    }
    if ($appBase === '') {
      $appBase = '/attendance';
    }
    $root = rtrim($appBase, '/');
    $accountantBase = $root . '/frontend/accountant/';

    $tabs = [
      'dashboard' => ['href' => $accountantBase . 'dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard'],
      'ledger' => ['href' => $accountantBase . 'ledger.php', 'icon' => 'account_balance', 'label' => 'General Ledger'],
      'expenses' => ['href' => $accountantBase . 'expenses.php', 'icon' => 'receipt_long', 'label' => 'Expenses'],
      'income' => ['href' => $accountantBase . 'income.php', 'icon' => 'payments', 'label' => 'Income'],
      'payroll' => ['href' => $accountantBase . 'payroll.php', 'icon' => 'group', 'label' => 'Payroll'],
      'reports' => ['href' => $accountantBase . 'reports.php', 'icon' => 'analytics', 'label' => 'Reports'],
    ];

    $csrfToken = generate_csrf_token();
    $savedTheme = null;
    try {
      $accountantUserId = (int)($_SESSION['user_id'] ?? 0);
      if ($accountantUserId > 0 && function_exists('table_has_column') && table_has_column('users', 'theme')) {
        $themeRow = db()->fetchOne("SELECT theme FROM users WHERE id = ? LIMIT 1", [$accountantUserId]);
        $candidateTheme = strtolower((string)($themeRow['theme'] ?? ''));
        if (in_array($candidateTheme, ['light', 'dark'], true)) {
          $savedTheme = $candidateTheme;
        }
      }
    } catch (Throwable $e) {
      error_log('Accountant shell theme read error: ' . $e->getMessage());
    }

    $accountantNotifications = [];
    $accountantUnreadNotifications = 0;
    try {
      $accountantUserId = (int)($_SESSION['user_id'] ?? 0);
      if ($accountantUserId > 0 && function_exists('table_exists') && table_exists('notifications')) {
        $accountantNotifications = db()->fetchAll(
          "SELECT id, title, message, category, is_read, created_at
           FROM notifications
           WHERE user_id = ?
           ORDER BY created_at DESC
           LIMIT 5",
          [$accountantUserId]
        ) ?: [];
        $accountantUnreadNotifications = (int) ((db()->fetchOne(
          "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0",
          [$accountantUserId]
        )['c'] ?? 0));
      }
    } catch (Throwable $e) {
      error_log('Accountant shell notification load error: ' . $e->getMessage());
    }

?>
    <!doctype html>
    <html class="light" lang="en">

    <head>
      <meta charset="utf-8" />
      <meta content="width=device-width, initial-scale=1.0" name="viewport" />
      <meta name="csrf-token" content="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars((string)APP_NAME); ?></title>
      <script src="<?php echo htmlspecialchars($root . '/assets/js/theme-loader.js'); ?>"></script>
      <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
      <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
      <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
      <script>
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                primary: "#1868DB",
                "primary-container": "#D6E4FF",
                "on-primary": "#FFFFFF",
                "on-primary-container": "#001B3D",
                secondary: "#545F71",
                "secondary-container": "#D9E3F8",
                "on-secondary": "#FFFFFF",
                "on-secondary-container": "#111C2B",
                tertiary: "#8F4C00",
                "tertiary-container": "#FFDCC0",
                "on-tertiary": "#FFFFFF",
                error: "#BA1A1A",
                "error-container": "#FFDAD6",
                surface: "#FDFBFF",
                "surface-container-low": "#F7F9FF",
                "surface-container": "#F1F4FA",
                "surface-container-high": "#EBEFF5",
                "surface-container-highest": "#E2E7EF",
                outline: "#73777F",
                "outline-variant": "#C3C7CF",
                background: "#FDFBFF",
                "on-background": "#1A1C1E",
                "on-surface": "#1A1C1E",
                "on-surface-variant": "#43474E"
              },
              borderRadius: {
                DEFAULT: "0.75rem",
                lg: "1rem",
                xl: "1.25rem",
                full: "9999px"
              },
              fontFamily: {
                headline: ["Manrope", "sans-serif"],
                body: ["Manrope", "sans-serif"],
                label: ["Manrope", "sans-serif"]
              }
            }
          }
        };
      </script>
      <style>
        body {
          font-family: 'Manrope', sans-serif;
        }

        .material-symbols-outlined {
          font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }

        .tabular-nums {
          font-variant-numeric: tabular-nums;
        }

        .rounded-atlas {
          border-radius: 1rem;
        }

        html.dark body,
        html[data-theme="dark"] body {
          background: #0b1220 !important;
          color: #e5e7eb !important;
        }

        html.dark .bg-surface,
        html[data-theme="dark"] .bg-surface {
          background: #0b1220 !important;
        }

        html.dark .bg-white,
        html[data-theme="dark"] .bg-white {
          background: #111827 !important;
        }

        html.dark .text-on-surface,
        html.dark .text-on-primary-container,
        html.dark .text-on-secondary-container,
        html[data-theme="dark"] .text-on-surface,
        html[data-theme="dark"] .text-on-primary-container,
        html[data-theme="dark"] .text-on-secondary-container {
          color: #e5e7eb !important;
        }

        html.dark .text-secondary,
        html.dark .text-outline,
        html.dark .text-on-surface-variant,
        html[data-theme="dark"] .text-secondary,
        html[data-theme="dark"] .text-outline,
        html[data-theme="dark"] .text-on-surface-variant {
          color: #9ca3af !important;
        }

        html.dark [class*="bg-surface-container"],
        html[data-theme="dark"] [class*="bg-surface-container"] {
          background: #1f2937 !important;
        }

        html.dark [class*="border-outline-variant"],
        html[data-theme="dark"] [class*="border-outline-variant"] {
          border-color: rgba(156, 163, 175, 0.35) !important;
        }

        html.dark .shadow-sm,
        html.dark .shadow-md,
        html.dark .shadow-lg,
        html[data-theme="dark"] .shadow-sm,
        html[data-theme="dark"] .shadow-md,
        html[data-theme="dark"] .shadow-lg {
          box-shadow: 0 10px 24px rgba(0, 0, 0, 0.45) !important;
        }

        html.dark .bg-primary-container,
        html[data-theme="dark"] .bg-primary-container {
          background: rgba(37, 99, 235, 0.32) !important;
        }

        html.dark .text-on-primary-container,
        html[data-theme="dark"] .text-on-primary-container {
          color: #eff6ff !important;
        }

        html.dark .text-primary,
        html[data-theme="dark"] .text-primary {
          color: #93c5fd !important;
        }

        .text-on-error-container {
          color: #410e0b;
        }

        .bg-error-container {
          background: #ffd9d6;
        }

        .accountant-theme-option {
          border: 1px solid rgba(195, 199, 207, 0.6);
        }

        .accountant-theme-option.active {
          border-color: rgba(24, 104, 219, 0.55);
          background: rgba(214, 228, 255, 0.45);
        }

        .accountant-icon-btn {
          width: 2.5rem;
          height: 2.5rem;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          border-radius: 9999px;
          border: 1px solid transparent;
          background: transparent;
          transition: all 160ms ease;
        }

        .accountant-icon-btn:hover {
          background: #eef3ff;
          border-color: rgba(24, 104, 219, 0.2);
          color: #1868db;
        }

        .accountant-dropdown-card {
          background: #ffffff;
          border: 1px solid rgba(195, 199, 207, 0.5);
          box-shadow: 0 24px 44px rgba(15, 23, 42, 0.16);
        }

        .accountant-side-action {
          position: relative;
          overflow: hidden;
          border: 1px solid transparent;
          border-radius: 0.85rem;
          background: transparent;
        }

        .accountant-side-action::before {
          content: '';
          position: absolute;
          inset: 0;
          opacity: 0;
          transition: opacity 160ms ease;
          background: linear-gradient(135deg, rgba(24, 104, 219, 0.12), rgba(24, 104, 219, 0.02));
          pointer-events: none;
        }

        .accountant-side-action:hover {
          border-color: rgba(24, 104, 219, 0.25);
          color: #1868db !important;
        }

        .accountant-side-action:hover::before {
          opacity: 1;
        }

        .accountant-side-action>* {
          position: relative;
          z-index: 1;
        }

        .accountant-side-action.accountant-side-action-danger:hover {
          border-color: rgba(186, 26, 26, 0.25);
          color: #ba1a1a !important;
        }

        .accountant-side-action.accountant-side-action-danger::before {
          background: linear-gradient(135deg, rgba(186, 26, 26, 0.16), rgba(186, 26, 26, 0.03));
        }

        .accountant-overlay-scrim {
          position: fixed;
          inset: 0;
          background: rgba(15, 23, 42, 0.2);
          backdrop-filter: blur(2px);
          -webkit-backdrop-filter: blur(2px);
          opacity: 0;
          pointer-events: none;
          transition: opacity 160ms ease;
          z-index: 35;
        }

        .accountant-overlay-scrim.active {
          opacity: 1;
          pointer-events: auto;
        }

        .accountant-topbar {
          z-index: 80;
          isolation: isolate;
          border-bottom-color: rgba(148, 163, 184, 0.28);
          box-shadow: 0 8px 24px rgba(2, 6, 23, 0.14);
        }

        html.dark .accountant-icon-btn:hover,
        html[data-theme="dark"] .accountant-icon-btn:hover {
          background: rgba(55, 65, 81, 0.82);
          border-color: rgba(148, 163, 184, 0.35);
          color: #bfdbfe;
        }

        html.dark .accountant-icon-btn,
        html[data-theme="dark"] .accountant-icon-btn {
          background: rgba(15, 23, 42, 0.34);
          border-color: rgba(100, 116, 139, 0.28);
          color: #cbd5e1;
        }

        html.dark .accountant-topbar,
        html[data-theme="dark"] .accountant-topbar {
          background: rgba(15, 23, 42, 0.94) !important;
          border-color: rgba(148, 163, 184, 0.24) !important;
        }

        html.dark .accountant-dropdown-card,
        html[data-theme="dark"] .accountant-dropdown-card {
          background: #111827;
          border-color: rgba(148, 163, 184, 0.3);
          box-shadow: 0 26px 48px rgba(0, 0, 0, 0.5);
        }

        html.dark .accountant-side-action:hover,
        html[data-theme="dark"] .accountant-side-action:hover {
          border-color: rgba(147, 197, 253, 0.35);
          color: #bfdbfe !important;
        }

        html.dark .accountant-side-action::before,
        html[data-theme="dark"] .accountant-side-action::before {
          background: linear-gradient(135deg, rgba(59, 130, 246, 0.22), rgba(59, 130, 246, 0.05));
        }

        html.dark .accountant-side-action.accountant-side-action-danger:hover,
        html[data-theme="dark"] .accountant-side-action.accountant-side-action-danger:hover {
          border-color: rgba(248, 113, 113, 0.35);
          color: #fca5a5 !important;
        }

        html.dark .accountant-overlay-scrim,
        html[data-theme="dark"] .accountant-overlay-scrim {
          background: rgba(2, 6, 23, 0.32);
        }

        html.dark .bg-error-container,
        html[data-theme="dark"] .bg-error-container {
          background: rgba(127, 29, 29, 0.36) !important;
        }

        html.dark .text-on-error-container,
        html[data-theme="dark"] .text-on-error-container {
          color: #fecaca !important;
        }

        html.dark .text-error,
        html[data-theme="dark"] .text-error {
          color: #fca5a5 !important;
        }

        html.dark .accountant-theme-option.active,
        html[data-theme="dark"] .accountant-theme-option.active {
          border-color: rgba(191, 219, 254, 0.6);
          background: rgba(37, 99, 235, 0.5);
        }
      </style>
    </head>

    <body class="bg-surface text-on-surface antialiased">
      <aside class="h-screen w-64 fixed left-0 top-0 border-r border-outline-variant/30 bg-white flex flex-col p-4 space-y-2 z-50">
        <div class="px-2 py-4 mb-4">
          <div class="flex items-center gap-3">
            <picture class="block w-12 h-12 shrink-0 overflow-hidden rounded-2xl">
              <source media="(prefers-color-scheme: dark)" srcset="<?php echo htmlspecialchars($root . '/assets/logo/logo4.png'); ?>" />
              <img src="<?php echo htmlspecialchars($root . '/assets/logo/logo5.png'); ?>" alt="SAMS Logo" class="w-full h-full object-cover scale-[1.18] origin-center" data-accountant-brand-img data-brand-light="<?php echo htmlspecialchars($root . '/assets/logo/logo5.png'); ?>" data-brand-dark="<?php echo htmlspecialchars($root . '/assets/logo/logo4.png'); ?>" />
            </picture>
            <div>
              <h1 class="font-headline font-extrabold text-on-surface leading-tight tracking-tight">SAMS</h1>
              <p class="text-[10px] text-primary font-bold uppercase tracking-widest">Financial Architect</p>
            </div>
          </div>
        </div>

        <nav class="flex-1 space-y-1">
          <?php foreach ($tabs as $key => $item):
            $isActive = $activeTab === $key;
          ?>
            <a class="flex items-center gap-3 px-4 py-3 transition-all group <?php echo $isActive ? 'bg-primary-container text-on-primary-container rounded-lg font-bold' : 'text-secondary hover:bg-surface-container rounded-lg'; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
              <span class="material-symbols-outlined <?php echo $isActive ? 'text-primary' : ''; ?>" <?php echo $isActive ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>><?php echo htmlspecialchars($item['icon']); ?></span>
              <span class="text-sm <?php echo $isActive ? 'font-bold' : 'font-medium'; ?>"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

        <div class="mt-auto border-t border-outline-variant/20 pt-4 space-y-1">
          <a class="accountant-side-action flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?php echo $activeTab === 'settings' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-secondary hover:bg-surface-container'; ?>" href="<?php echo htmlspecialchars($accountantBase . 'settings.php'); ?>">
            <span class="material-symbols-outlined" <?php echo $activeTab === 'settings' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>settings</span>
            <span class="text-sm font-medium">Settings</span>
          </a>
          <a class="accountant-side-action flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?php echo $activeTab === 'support' ? 'bg-primary-container text-on-primary-container font-bold' : 'text-secondary hover:bg-surface-container'; ?>" href="<?php echo htmlspecialchars($root . '/frontend/notices.php'); ?>">
            <span class="material-symbols-outlined">contact_support</span>
            <span class="text-sm font-medium">Support</span>
          </a>
          <a class="accountant-side-action accountant-side-action-danger flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all" href="<?php echo htmlspecialchars($root . '/logout.php'); ?>">
            <span class="material-symbols-outlined">logout</span>
            <span class="text-sm font-medium">Logout</span>
          </a>
        </div>
      </aside>

      <main class="ml-64 min-h-screen flex flex-col">
        <header class="accountant-topbar sticky top-0 bg-white/95 backdrop-blur-md border-b border-outline-variant/20 h-16 flex justify-between items-center w-full px-8">
          <div class="flex items-center gap-4 flex-1">
            <div class="relative w-full max-w-md">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
              <input class="w-full bg-surface-container-low border-none rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 placeholder:text-outline" placeholder="Search transactions, ledgers..." type="text" />
            </div>
          </div>
          <div class="flex items-center gap-6">
            <div class="relative flex items-center gap-1 text-secondary" data-accountant-toolbar>
              <button type="button" class="accountant-icon-btn relative p-2 hover:bg-surface-container rounded-full transition-all" title="Notifications" aria-label="Notifications" aria-expanded="false" aria-controls="accountant-notifications-menu" data-accountant-dropdown-toggle="accountant-notifications-menu">
                <span class="material-symbols-outlined">notifications</span>
                <?php if ($accountantUnreadNotifications > 0): ?>
                  <span class="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-error text-white text-[9px] font-bold flex items-center justify-center leading-none">
                    <?php echo $accountantUnreadNotifications > 9 ? '9+' : (int) $accountantUnreadNotifications; ?>
                  </span>
                <?php endif; ?>
              </button>
              <button type="button" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="Theme mode" aria-label="Theme mode" aria-expanded="false" aria-controls="accountant-theme-menu" data-accountant-dropdown-toggle="accountant-theme-menu">
                <span class="material-symbols-outlined" data-accountant-theme-icon>light_mode</span>
              </button>
              <a href="<?php echo htmlspecialchars($accountantBase . 'settings.php'); ?>" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="Settings" aria-label="Settings">
                <span class="material-symbols-outlined">settings</span>
              </a>
              <button type="button" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="More actions" aria-label="More actions" aria-expanded="false" aria-controls="accountant-more-menu" data-accountant-dropdown-toggle="accountant-more-menu">
                <span class="material-symbols-outlined">more_vert</span>
              </button>
              <a href="<?php echo htmlspecialchars($root . '/frontend/notices.php'); ?>" class="accountant-icon-btn p-2 hover:bg-surface-container rounded-full transition-all" title="Help" aria-label="Help">
                <span class="material-symbols-outlined">help_outline</span>
              </a>

              <div id="accountant-notifications-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-[23rem] overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
                <div class="flex items-center justify-between px-4 py-3 border-b border-outline-variant/10 bg-surface-container-low">
                  <div>
                    <p class="text-sm font-extrabold text-on-surface">Notifications</p>
                    <p class="text-[10px] uppercase tracking-[0.24em] text-secondary font-bold"><?php echo $accountantUnreadNotifications > 0 ? (int) $accountantUnreadNotifications . ' unread' : 'All caught up'; ?></p>
                  </div>
                  <a href="<?php echo htmlspecialchars($accountantBase . 'settings.php#notifications'); ?>" class="text-xs font-bold text-primary hover:underline">Preferences</a>
                </div>
                <div class="max-h-80 overflow-y-auto divide-y divide-outline-variant/10">
                  <?php if (!empty($accountantNotifications)): ?>
                    <?php foreach ($accountantNotifications as $notification):
                      $notificationTitle = trim((string)($notification['title'] ?? 'Notification'));
                      $notificationMessage = trim((string)($notification['message'] ?? ''));
                      $notificationCategory = strtolower((string)($notification['category'] ?? 'info'));
                      $notificationIcon = match ($notificationCategory) {
                        'success' => 'check_circle',
                        'warning' => 'warning',
                        'error', 'danger' => 'error',
                        'payment' => 'payments',
                        'approval' => 'task_alt',
                        default => 'notifications',
                      };
                      $notificationTime = !empty($notification['created_at']) ? date('M j, g:i A', strtotime((string)$notification['created_at'])) : 'Just now';
                      $isUnread = (int)($notification['is_read'] ?? 0) === 0;
                    ?>
                      <a href="<?php echo htmlspecialchars($accountantBase . 'settings.php#notifications'); ?>" class="block px-4 py-3 transition-colors hover:bg-surface-container-low <?php echo $isUnread ? 'bg-primary-container/20' : ''; ?>">
                        <div class="flex items-start gap-3">
                          <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full <?php echo $isUnread ? 'bg-primary-container text-primary' : 'bg-surface-container text-secondary'; ?>">
                            <span class="material-symbols-outlined text-[20px]"><?php echo htmlspecialchars($notificationIcon); ?></span>
                          </div>
                          <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                              <p class="truncate text-sm font-bold text-on-surface"><?php echo htmlspecialchars($notificationTitle); ?></p>
                              <?php if ($isUnread): ?>
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                              <?php endif; ?>
                            </div>
                            <?php if ($notificationMessage !== ''): ?>
                              <p class="mt-1 text-sm text-secondary leading-snug line-clamp-2"><?php echo htmlspecialchars($notificationMessage); ?></p>
                            <?php endif; ?>
                            <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.2em] text-outline"><?php echo htmlspecialchars($notificationTime); ?></p>
                          </div>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="px-4 py-6 text-sm text-secondary">
                      <p class="font-bold text-on-surface">No notifications yet.</p>
                      <p class="mt-1 text-sm leading-relaxed">When alerts arrive, they’ll appear here with a custom accountant dropdown.</p>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="border-t border-outline-variant/10 bg-surface-container-low px-4 py-3">
                  <a href="<?php echo htmlspecialchars($root . '/frontend/notices.php'); ?>" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-primary hover:underline">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                    View all notices
                  </a>
                </div>
              </div>

              <div id="accountant-theme-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-[22rem] overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
                <div class="px-4 py-3 border-b border-outline-variant/10 bg-surface-container-low">
                  <p class="text-sm font-extrabold text-on-surface">Theme Selection</p>
                  <p class="text-[10px] uppercase tracking-[0.24em] text-secondary font-bold">Unified across all accountant tabs</p>
                </div>
                <div class="p-3 grid grid-cols-2 gap-3">
                  <button type="button" class="accountant-theme-option w-full flex flex-col items-start gap-2 rounded-xl px-3 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors" data-accountant-theme-choice="light">
                    <span class="material-symbols-outlined text-[18px]">light_mode</span>
                    <span class="text-left">Light mode</span>
                    <span class="material-symbols-outlined text-primary hidden" data-accountant-theme-check>check</span>
                  </button>
                  <button type="button" class="accountant-theme-option w-full flex flex-col items-start gap-2 rounded-xl px-3 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors" data-accountant-theme-choice="dark">
                    <span class="material-symbols-outlined text-[18px]">dark_mode</span>
                    <span class="text-left">Dark mode</span>
                    <span class="material-symbols-outlined text-primary hidden" data-accountant-theme-check>check</span>
                  </button>
                </div>
              </div>

              <div id="accountant-more-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-56 overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
                <a href="<?php echo htmlspecialchars($accountantBase . 'expenses.php'); ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                  <span class="material-symbols-outlined text-secondary">receipt_long</span>
                  Record expense
                </a>
                <a href="<?php echo htmlspecialchars($accountantBase . 'reports.php'); ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                  <span class="material-symbols-outlined text-secondary">analytics</span>
                  Open reports
                </a>
                <a href="<?php echo htmlspecialchars($accountantBase . 'settings.php#notifications'); ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                  <span class="material-symbols-outlined text-secondary">tune</span>
                  Notification settings
                </a>
                <a href="<?php echo htmlspecialchars($root . '/logout.php'); ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-error hover:bg-error-container/50">
                  <span class="material-symbols-outlined text-error">logout</span>
                  Sign out
                </a>
              </div>
            </div>
            <a href="<?php echo htmlspecialchars($accountantBase . 'expenses.php'); ?>" class="bg-primary text-on-primary px-5 py-2 rounded-lg text-sm font-bold hover:opacity-90 transition-all shadow-md shadow-primary/10">Add Expense</a>
            <a href="<?php echo htmlspecialchars($accountantBase . 'settings.php'); ?>" class="inline-flex items-center gap-3 pl-4 border-l border-outline-variant/30 hover:opacity-90" title="Edit Profile">
              <div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-primary font-bold text-xs"><?php echo htmlspecialchars($initial); ?></div>
              <div class="hidden lg:block text-left">
                <p class="text-xs font-bold text-on-surface leading-none"><?php echo htmlspecialchars($fullName); ?></p>
                <p class="text-[10px] text-secondary font-semibold uppercase tracking-tighter mt-1">Edit Profile</p>
              </div>
            </a>
          </div>
        </header>

        <div class="p-8 space-y-8">
          <?php echo $contentHtml; ?>
        </div>

        <div class="md:hidden sticky bottom-0 w-full bg-white border-t border-outline-variant/20 flex justify-around p-4 z-50">
          <a href="<?php echo htmlspecialchars($accountantBase . 'dashboard.php'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'dashboard' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'dashboard' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>dashboard</span></a>
          <a href="<?php echo htmlspecialchars($accountantBase . 'income.php'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'income' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'income' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>payments</span></a>
          <a href="<?php echo htmlspecialchars($accountantBase . 'expenses.php'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'expenses' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'expenses' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>receipt_long</span></a>
          <a href="<?php echo htmlspecialchars($accountantBase . 'settings.php'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'settings' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'settings' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>person</span></a>
        </div>
      </main>
      <div class="accountant-overlay-scrim" data-accountant-menu-overlay></div>
      <script>
        (() => {
          const toolbar = document.querySelector('[data-accountant-toolbar]');
          if (!toolbar) {
            return;
          }

          const toggles = Array.from(toolbar.querySelectorAll('[data-accountant-dropdown-toggle]'));
          const menus = Array.from(toolbar.querySelectorAll('[data-accountant-dropdown-menu]'));
          const themeChoices = Array.from(toolbar.querySelectorAll('[data-accountant-theme-choice]'));
          const themeIcon = toolbar.querySelector('[data-accountant-theme-icon]');
          const overlay = document.querySelector('[data-accountant-menu-overlay]');
          const brandImg = document.querySelector('[data-accountant-brand-img]');
          const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
          const themeApiUrl = '<?php echo htmlspecialchars($root . '/api/save-theme.php', ENT_QUOTES, 'UTF-8'); ?>';
          const serverTheme = <?php echo json_encode($savedTheme); ?>;
          const iconBase = '<?php echo htmlspecialchars($root . '/assets/images/icons/', ENT_QUOTES, 'UTF-8'); ?>';
          const lightIcon = iconBase + 'logo5.png';
          const darkIcon = iconBase + 'logo4.png';
          let scrollLockY = 0;
          let previousBodyPaddingRight = '';

          const normalizeTheme = (theme) => theme === 'dark' ? 'dark' : 'light';

          const readTheme = () => {
            try {
              const preferred = localStorage.getItem('sams_theme') || localStorage.getItem('sams-theme');
              if (preferred) {
                return normalizeTheme(preferred);
              }
            } catch (error) {
              // ignore storage read issues
            }

            if (serverTheme) {
              return normalizeTheme(serverTheme);
            }

            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
              return 'dark';
            }

            return 'light';
          };

          const persistTheme = (theme) => {
            try {
              localStorage.setItem('sams_theme', theme);
              localStorage.setItem('sams-theme', theme);
            } catch (error) {
              // ignore storage write issues
            }

            fetch(themeApiUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({
                theme: theme,
                csrf_token: csrfToken
              })
            }).catch(() => {
              // local persistence is still valid
            });
          };

          const setOrCreateHeadLink = (rel, href) => {
            let link = document.querySelector('link[rel="' + rel + '"]');
            if (!link) {
              link = document.createElement('link');
              link.rel = rel;
              document.head.appendChild(link);
            }
            link.href = href;
          };

          const syncThemeIcons = (theme) => {
            const isDark = theme === 'dark';
            const targetIcon = isDark ? darkIcon : lightIcon;
            setOrCreateHeadLink('icon', targetIcon);
            setOrCreateHeadLink('shortcut icon', targetIcon);
            setOrCreateHeadLink('apple-touch-icon', targetIcon);

            if (brandImg) {
              const lightBrand = brandImg.getAttribute('data-brand-light') || '';
              const darkBrand = brandImg.getAttribute('data-brand-dark') || '';
              brandImg.src = isDark ? (darkBrand || targetIcon) : (lightBrand || targetIcon);
            }
          };

          const updateThemeButtons = (theme) => {
            themeChoices.forEach((button) => {
              const selected = button.getAttribute('data-accountant-theme-choice') === theme;
              button.classList.toggle('bg-primary-container', selected);
              button.classList.toggle('text-on-primary-container', selected);
              button.classList.toggle('text-on-surface', !selected);
              button.classList.toggle('active', selected);
              const check = button.querySelector('[data-accountant-theme-check]');
              if (check) {
                check.classList.toggle('hidden', !selected);
              }
            });

            if (themeIcon) {
              themeIcon.textContent = theme === 'dark' ? 'dark_mode' : 'light_mode';
            }
          };

          const applyTheme = (theme) => {
            const normalizedTheme = normalizeTheme(theme);
            document.documentElement.setAttribute('data-theme', normalizedTheme);
            document.documentElement.classList.toggle('dark', normalizedTheme === 'dark');
            document.documentElement.classList.toggle('light', normalizedTheme !== 'dark');
            persistTheme(normalizedTheme);
            updateThemeButtons(normalizedTheme);
            syncThemeIcons(normalizedTheme);
          };

          const lockPageScroll = () => {
            if (document.body.classList.contains('accountant-scroll-locked')) {
              return;
            }

            scrollLockY = window.scrollY || window.pageYOffset || 0;
            previousBodyPaddingRight = document.body.style.paddingRight || '';
            const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);

            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.top = `-${scrollLockY}px`;
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
            if (scrollbarWidth > 0) {
              document.body.style.paddingRight = `${scrollbarWidth}px`;
            }
            document.body.classList.add('accountant-scroll-locked');
          };

          const unlockPageScroll = () => {
            if (!document.body.classList.contains('accountant-scroll-locked')) {
              return;
            }

            document.body.classList.remove('accountant-scroll-locked');
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            document.body.style.paddingRight = previousBodyPaddingRight;
            window.scrollTo(0, scrollLockY);
          };

          const closeMenus = () => {
            toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
            menus.forEach((menu) => menu.classList.add('hidden'));
            if (overlay) {
              overlay.classList.remove('active');
            }
            unlockPageScroll();
          };

          const openMenu = (menuId) => {
            const menu = document.getElementById(menuId);
            if (!menu) {
              return;
            }

            const isHidden = menu.classList.contains('hidden');
            closeMenus();
            if (isHidden) {
              menu.classList.remove('hidden');
              const toggle = toolbar.querySelector('[data-accountant-dropdown-toggle="' + menuId + '"]');
              if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
              }
              if (overlay) {
                overlay.classList.add('active');
              }
              lockPageScroll();
            }
          };

          toggles.forEach((toggle) => {
            toggle.addEventListener('click', (event) => {
              event.preventDefault();
              event.stopPropagation();
              openMenu(toggle.getAttribute('data-accountant-dropdown-toggle'));
            });
          });

          themeChoices.forEach((choice) => {
            choice.addEventListener('click', (event) => {
              event.preventDefault();
              event.stopPropagation();
              applyTheme(choice.getAttribute('data-accountant-theme-choice') || 'light');
              closeMenus();
            });
          });

          applyTheme(readTheme());

          document.addEventListener('click', (event) => {
            if (!toolbar.contains(event.target)) {
              closeMenus();
            }
          });

          document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
              closeMenus();
            }
          });

          if (overlay) {
            overlay.addEventListener('click', () => {
              closeMenus();
            });
          }
        })();
      </script>
    </body>

    </html>
<?php
  }
}
