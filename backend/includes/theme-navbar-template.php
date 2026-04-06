<?php

/**
 * SAMS Theme Navbar Template — Reusable Theme Toggle UI
 *
 * Provides standardized HTML markup for theme toggle menu
 * Works with all dashboard roles via $role parameter
 *
 * Usage:
 *   $role = 'admin'; // or 'student', 'teacher', etc.
 *   <?php include __DIR__ . '/theme-navbar-template.php'; ?>
 *
 * Variables:
 *   $role (string, required): Dashboard role for data attribute naming
 *   $show_icon (bool, default: true): Show theme icon in toolbar
 *   $icon_position (string, default: 'right'): Position for icon ('left' or 'right')
 */

// Safe defaults
$role = $role ?? 'general';
$show_icon = $show_icon ?? true;
$icon_position = $icon_position ?? 'right';
$role_attr = htmlspecialchars($role);

?>
<!-- Theme Toggle Button: Icon in Toolbar -->
<?php if ($show_icon): ?>
  <button type="button"
    class="p-2 hover:bg-surface-container rounded-full transition-all"
    title="Theme mode"
    aria-label="Theme mode"
    aria-expanded="false"
    aria-controls="<?php echo $role_attr; ?>-theme-menu"
    data-<?php echo $role_attr; ?>-dropdown-toggle="<?php echo $role_attr; ?>-theme-menu">
    <span class="material-symbols-outlined" data-<?php echo $role_attr; ?>-theme-icon>light_mode</span>
  </button>
<?php endif; ?>

<!-- Theme Toggle Menu: Dropdown -->
<div id="<?php echo $role_attr; ?>-theme-menu"
  class="hidden absolute right-0 top-full mt-3 w-64 overflow-hidden rounded-2xl border border-outline-variant/20 bg-white shadow-xl shadow-black/10 z-50"
  data-<?php echo $role_attr; ?>-dropdown-menu>

  <!-- Menu Header -->
  <div class="px-4 py-3 border-b border-outline-variant/10 bg-surface-container-low">
    <p class="text-sm font-extrabold text-on-surface">Theme Selection</p>
    <p class="text-[10px] uppercase tracking-[0.24em] text-secondary font-bold">Light / Dark only</p>
  </div>

  <!-- Theme Options -->
  <div class="p-2 space-y-1">
    <!-- Light Mode Option -->
    <button type="button"
      class="w-full flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors"
      data-<?php echo $role_attr; ?>-theme-choice="light">
      <span class="material-symbols-outlined text-[18px]">light_mode</span>
      <span class="flex-1 text-left">Light mode</span>
      <span class="material-symbols-outlined text-primary hidden" data-<?php echo $role_attr; ?>-theme-check>check</span>
    </button>

    <!-- Dark Mode Option -->
    <button type="button"
      class="w-full flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-low transition-colors"
      data-<?php echo $role_attr; ?>-theme-choice="dark">
      <span class="material-symbols-outlined text-[18px]">dark_mode</span>
      <span class="flex-1 text-left">Dark mode</span>
      <span class="material-symbols-outlined text-primary hidden" data-<?php echo $role_attr; ?>-theme-check>check</span>
    </button>
  </div>
</div>
