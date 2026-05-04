<?php

if (!function_exists('render_accountant_atlas_shell')) {
  /**
   * Render shared Atlas Modern shell for accountant pages.
   *
   * @param string $pageTitle
   * @param string $activeTab one of: dashboard|ledger|expenses|income|payroll|reports|settings|support
   * @param string $contentHtml pre-rendered page content
   * @param string $fullName
   * @param string $pageSubtitle
   * @param string $pageIcon
   */
  function render_accountant_atlas_shell(
    string $pageTitle,
    string $activeTab,
    string $contentHtml,
    string $fullName = 'Accountant',
    string $pageSubtitle = '',
    string $pageIcon = 'account_balance'
  ): void {
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

    $primaryTabs = [
      'dashboard' => ['href' => $accountantBase . 'index.php?page=dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection' => ['href' => $accountantBase . 'index.php?page=team-selection', 'icon' => 'groups', 'label' => 'Team Selection'],
      'ledger' => ['href' => $accountantBase . 'index.php?page=ledger', 'icon' => 'menu_book', 'label' => 'General Ledger'],
      'expenses' => ['href' => $accountantBase . 'index.php?page=expenses', 'icon' => 'receipt', 'label' => 'Expenses'],
      'income' => ['href' => $accountantBase . 'index.php?page=income', 'icon' => 'savings', 'label' => 'Income'],
      'payroll' => ['href' => $accountantBase . 'index.php?page=payroll', 'icon' => 'account_balance', 'label' => 'Payroll'],
      'budget' => ['href' => $accountantBase . 'index.php?page=budget', 'icon' => 'pie_chart', 'label' => 'Budget'],
      'reports' => ['href' => $accountantBase . 'index.php?page=reports', 'icon' => 'bar_chart', 'label' => 'Reports'],
    ];

    $reportingTabs = [
      'balance-sheet' => ['href' => $accountantBase . 'index.php?page=balance-sheet', 'icon' => 'account_balance_wallet', 'label' => 'Balance Sheet'],
      'profit-loss' => ['href' => $accountantBase . 'index.php?page=profit-loss', 'icon' => 'monitoring', 'label' => 'Profit & Loss'],
      'tax-reports' => ['href' => $accountantBase . 'index.php?page=tax-reports', 'icon' => 'receipt_long', 'label' => 'Tax Reports'],
      'audit-trail' => ['href' => $accountantBase . 'index.php?page=audit-trail', 'icon' => 'history', 'label' => 'Audit Trail'],
      'project-goals' => ['href' => $accountantBase . 'index.php?page=project-goals', 'icon' => 'checklist', 'label' => 'Project Goals'],
    ];

    $utilityTabs = [
      'settings' => ['href' => $accountantBase . 'index.php?page=settings', 'icon' => 'settings', 'label' => 'Settings'],
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
          "SELECT id, title, message, type as category, is_read, created_at
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
      <?php require_once __DIR__ . '/../../includes/theme-manager.php';
      themeInjectFaviconMeta(); ?>
      <meta charset="utf-8" />
      <meta content="width=device-width, initial-scale=1.0" name="viewport" />
      <meta name="csrf-token" content="<?php echo htmlspecialchars((string)$csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars((string)APP_NAME); ?></title>
      <script src="<?php echo htmlspecialchars($root . '/assets/js/theme-loader.js'); ?>"></script>
      <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet" />
      <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
      <?php themeInjectTailwindConfig(); ?>
      <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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

        html.dark .text-gray-500,
        html.dark .text-gray-600,
        html.dark .text-gray-700,
        html.dark .text-gray-800,
        html.dark .text-slate-400,
        html.dark .text-slate-500,
        html[data-theme="dark"] .text-gray-500,
        html[data-theme="dark"] .text-gray-600,
        html[data-theme="dark"] .text-gray-700,
        html[data-theme="dark"] .text-gray-800,
        html[data-theme="dark"] .text-slate-400,
        html[data-theme="dark"] .text-slate-500 {
          color: #cbd5e1 !important;
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

        html.dark .bg-gray-50,
        html.dark .bg-gray-100,
        html.dark .bg-slate-100,
        html[data-theme="dark"] .bg-gray-50,
        html[data-theme="dark"] .bg-gray-100,
        html[data-theme="dark"] .bg-slate-100 {
          background: #1f2937 !important;
        }

        html.dark .border-gray-100,
        html.dark .border-gray-200,
        html.dark .divide-gray-100,
        html[data-theme="dark"] .border-gray-100,
        html[data-theme="dark"] .border-gray-200,
        html[data-theme="dark"] .divide-gray-100 {
          border-color: rgba(148, 163, 184, 0.28) !important;
        }

        html.dark .hover\:bg-gray-50:hover,
        html.dark .hover\:bg-slate-100:hover,
        html.dark .hover\:bg-white:hover,
        html[data-theme="dark"] .hover\:bg-gray-50:hover,
        html[data-theme="dark"] .hover\:bg-slate-100:hover,
        html[data-theme="dark"] .hover\:bg-white:hover {
          background: rgba(30, 41, 59, 0.92) !important;
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

        .accountant-profile-trigger {
          box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
        }

        .accountant-profile-trigger:hover {
          border-color: rgba(24, 104, 219, 0.24);
          background: #ffffff;
          box-shadow: 0 20px 34px rgba(15, 23, 42, 0.12);
        }

        .accountant-profile-menu {
          box-shadow: 0 28px 52px rgba(15, 23, 42, 0.18);
        }

        .accountant-profile-menu__hero {
          background:
            radial-gradient(circle at top left, rgba(24, 104, 219, 0.16), transparent 55%),
            linear-gradient(180deg, rgba(214, 228, 255, 0.58), rgba(255, 255, 255, 0.96));
        }

        .accountant-profile-item:hover {
          background: rgba(214, 228, 255, 0.52);
        }

        .accountant-profile-item__icon {
          width: 2.25rem;
          height: 2.25rem;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          border-radius: 9999px;
          background: rgba(24, 104, 219, 0.1);
        }

        .accountant-profile-item-danger:hover {
          background: rgba(255, 217, 214, 0.88);
        }

        .accountant-profile-item-danger .accountant-profile-item__icon {
          background: rgba(186, 26, 26, 0.12);
          color: #ba1a1a;
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

          -webkit- opacity: 0;
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

        html.dark .accountant-profile-trigger,
        html[data-theme="dark"] .accountant-profile-trigger {
          background: rgba(15, 23, 42, 0.82);
          border-color: rgba(100, 116, 139, 0.32);
          box-shadow: 0 18px 32px rgba(0, 0, 0, 0.34);
        }

        html.dark .accountant-profile-trigger:hover,
        html[data-theme="dark"] .accountant-profile-trigger:hover {
          background: rgba(15, 23, 42, 0.95);
          border-color: rgba(147, 197, 253, 0.32);
          box-shadow: 0 22px 38px rgba(0, 0, 0, 0.42);
        }

        html.dark .accountant-profile-menu,
        html[data-theme="dark"] .accountant-profile-menu {
          box-shadow: 0 30px 52px rgba(0, 0, 0, 0.55);
        }

        html.dark .accountant-profile-menu__hero,
        html[data-theme="dark"] .accountant-profile-menu__hero {
          background:
            radial-gradient(circle at top left, rgba(59, 130, 246, 0.24), transparent 55%),
            linear-gradient(180deg, rgba(30, 41, 59, 0.96), rgba(17, 24, 39, 0.98));
        }

        html.dark .accountant-profile-item:hover,
        html[data-theme="dark"] .accountant-profile-item:hover {
          background: rgba(30, 41, 59, 0.92);
        }

        html.dark .accountant-profile-item__icon,
        html[data-theme="dark"] .accountant-profile-item__icon {
          background: rgba(59, 130, 246, 0.16);
          color: #bfdbfe;
        }

        html.dark .accountant-profile-item-danger:hover,
        html[data-theme="dark"] .accountant-profile-item-danger:hover {
          background: rgba(127, 29, 29, 0.34);
        }

        html.dark .accountant-profile-item-danger .accountant-profile-item__icon,
        html[data-theme="dark"] .accountant-profile-item-danger .accountant-profile-item__icon {
          background: rgba(248, 113, 113, 0.16);
          color: #fca5a5;
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

        <nav class="flex-1 space-y-5 overflow-y-auto pr-1">
          <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-[0.24em] text-outline">Workspace</p>
            <?php foreach ($primaryTabs as $key => $item):
              $isActive = $activeTab === $key;
            ?>
              <a class="flex items-center gap-3 px-4 py-3 transition-all group <?php echo $isActive ? 'bg-primary-container text-on-primary-container rounded-lg font-bold' : 'text-secondary hover:bg-surface-container rounded-lg'; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                <span class="material-symbols-outlined <?php echo $isActive ? 'text-primary' : ''; ?>" <?php echo $isActive ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>><?php echo htmlspecialchars($item['icon']); ?></span>
                <span class="text-sm <?php echo $isActive ? 'font-bold' : 'font-medium'; ?>"><?php echo htmlspecialchars($item['label']); ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-[0.24em] text-outline">Reporting</p>
            <?php foreach ($reportingTabs as $key => $item):
              $isActive = $activeTab === $key;
            ?>
              <a class="flex items-center gap-3 px-4 py-3 transition-all group <?php echo $isActive ? 'bg-primary-container text-on-primary-container rounded-lg font-bold' : 'text-secondary hover:bg-surface-container rounded-lg'; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                <span class="material-symbols-outlined <?php echo $isActive ? 'text-primary' : ''; ?>" <?php echo $isActive ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>><?php echo htmlspecialchars($item['icon']); ?></span>
                <span class="text-sm <?php echo $isActive ? 'font-bold' : 'font-medium'; ?>"><?php echo htmlspecialchars($item['label']); ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="space-y-1">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-[0.24em] text-outline">Account</p>
            <?php foreach ($utilityTabs as $key => $item):
              $isActive = $activeTab === $key;
            ?>
              <a class="flex items-center gap-3 px-4 py-3 transition-all group <?php echo $isActive ? 'bg-primary-container text-on-primary-container rounded-lg font-bold' : 'text-secondary hover:bg-surface-container rounded-lg'; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                <span class="material-symbols-outlined <?php echo $isActive ? 'text-primary' : ''; ?>" <?php echo $isActive ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>><?php echo htmlspecialchars($item['icon']); ?></span>
                <span class="text-sm <?php echo $isActive ? 'font-bold' : 'font-medium'; ?>"><?php echo htmlspecialchars($item['label']); ?></span>
              </a>
            <?php endforeach; ?>

            <a class="accountant-side-action flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all" href="<?php echo htmlspecialchars(site_url('frontend/notices.php')); ?>">
              <span class="material-symbols-outlined">support_agent</span>
              <span class="text-sm font-medium">Support</span>
            </a>
            <a class="accountant-side-action accountant-side-action-danger flex items-center gap-3 px-4 py-3 text-secondary hover:bg-surface-container rounded-lg transition-all" href="<?php echo htmlspecialchars(site_url('logout.php')); ?>">
              <span class="material-symbols-outlined">logout</span>
              <span class="text-sm font-medium">Logout</span>
            </a>
          </div>
        </nav>

      </aside>

      <main class="ml-64 min-h-screen flex flex-col">
        <header class="accountant-topbar sticky top-0 bg-white backdrop-blur-md border-b border-outline-variant/20 min-h-16 flex justify-between items-center w-full px-8 py-4 gap-6">
          <div class="flex items-center gap-4 flex-1 min-w-0">
            <div class="hidden lg:flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-container text-primary shadow-sm shrink-0">
              <span class="material-symbols-outlined"><?php echo htmlspecialchars($pageIcon); ?></span>
            </div>
            <div class="min-w-0">
              <p class="truncate text-lg font-extrabold text-on-surface"><?php echo htmlspecialchars($pageTitle); ?></p>
              <?php if ($pageSubtitle !== ''): ?>
                <p class="truncate text-sm text-secondary"><?php echo htmlspecialchars($pageSubtitle); ?></p>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex items-center gap-4 flex-1 justify-end">
            <div class="relative w-full max-w-md hidden xl:block">
              <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
              <input class="w-full bg-surface-container-low border-none rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20 placeholder:text-outline" placeholder="Search transactions, ledgers..." type="text" />
            </div>
          </div>
          <div class="flex items-center gap-4">
            <div class="relative flex items-center gap-2 text-slate-500" data-accountant-toolbar>
              <?php $role = "accountant";
              $show_icon = true;
              include __DIR__ . '/../../includes/theme-navbar-template.php'; ?>

              <button type="button" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-colors" title="More actions" aria-label="More actions" aria-expanded="false" aria-controls="accountant-more-menu" data-accountant-dropdown-toggle="accountant-more-menu">
                <span class="material-symbols-outlined text-xl">apps</span>
              </button>

              <div id="accountant-notifications-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-[23rem] overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
                <div class="flex items-center justify-between px-4 py-3 border-b border-outline-variant/10 bg-surface-container-low">
                  <div>
                    <p class="text-sm font-extrabold text-on-surface">Notifications</p>
                    <p class="text-[10px] uppercase tracking-[0.24em] text-secondary font-bold"><?php echo $accountantUnreadNotifications > 0 ? (int) $accountantUnreadNotifications . ' unread' : 'All caught up'; ?></p>
                  </div>
                  <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=settings#notifications'); ?>" class="text-xs font-bold text-primary hover:underline">Preferences</a>
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
                      <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=settings#notifications'); ?>" class="block px-4 py-3 transition-colors hover:bg-surface-container-low <?php echo $isUnread ? 'bg-primary-container' : ''; ?>">
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
                      <p class="mt-1 text-sm leading-relaxed">When alerts arrive, theyâ€™ll appear here.</p>
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

              <div id="accountant-theme-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-48 overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
                <div class="p-2 grid gap-1">
                  <button type="button" class="accountant-theme-option w-full flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors" data-accountant-theme-choice="light">
                    <span class="material-symbols-outlined text-[18px]">light_mode</span>
                    Light mode
                  </button>
                  <button type="button" class="accountant-theme-option w-full flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors" data-accountant-theme-choice="dark">
                    <span class="material-symbols-outlined text-[18px]">dark_mode</span>
                    Dark mode
                  </button>
                </div>
              </div>

              <div id="accountant-more-menu" class="accountant-dropdown-card hidden absolute right-0 top-full mt-3 w-56 overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50" data-accountant-dropdown-menu>
                <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=expenses'); ?>" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                  <span class="material-symbols-outlined text-secondary">receipt_long</span>
                  Record expense
                </a>
              </div>
            </div>

            <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=expenses'); ?>" class="hidden md:flex ml-2 bg-[#1868DB] hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-bold active:scale-95 duration-200 transition-all shadow-sm">
              Add Expense
            </a>

            <?php include __DIR__ . '/avatar-dropdown.php'; ?>
          </div>
        </header>


        <div class="p-8 space-y-8">
          <?php echo $contentHtml; ?>
        </div>

        <div class="md:hidden sticky bottom-0 w-full bg-white border-t border-outline-variant/20 flex justify-around p-4 z-50">
          <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=dashboard'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'dashboard' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'dashboard' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>dashboard</span></a>
          <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=reports'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'reports' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'reports' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>bar_chart</span></a>
          <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=expenses'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'expenses' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'expenses' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>receipt_long</span></a>
          <a href="<?php echo htmlspecialchars($accountantBase . 'index.php?page=settings'); ?>"><span class="material-symbols-outlined <?php echo $activeTab === 'settings' ? 'text-primary' : 'text-secondary'; ?>" <?php echo $activeTab === 'settings' ? "style=\"font-variation-settings: 'FILL' 1\"" : ''; ?>>person</span></a>
        </div>
      </main>
      <div class="accountant-overlay-scrim" data-accountant-menu-overlay></div>
      <?php if (function_exists("themeGetInitScript")) {
        echo themeGetInitScript("accountant", ["basePath" => $root]);
      } ?>
    </body>

    </html>
<?php
  }
}
