<?php

/**
 * SAMS Head Bootstrap
 *
 * Universal head setup for all SAMS pages - ensures consistent CSS/JS loading.
 * Call this in the <head> section of any page that uses professional-ui.css
 *
 * Usage:
 *   <?php include INCLUDES_PATH . '/sams-head-bootstrap.php'; ?>
 *
 * Or for standalone pages:
 *   <?php include '../includes/sams-head-bootstrap.php'; ?>
 */

if (!function_exists('sams_emit_core_styles')) {
  function sams_emit_core_styles(): void
  {
    static $emitted = false;
    if ($emitted) {
      return;
    }
    $emitted = true;

    // Compute relative path to assets
    $basePath = function_exists('site_url') ? site_url('assets/css/') : '../assets/css/';

    // Core CSS
    echo '<link rel="stylesheet" href="' . htmlspecialchars($basePath . 'sams-core.css', ENT_QUOTES, 'UTF-8') . '">';

    // Material Symbols (Google Fonts)
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">';
  }
}

sams_emit_core_styles();
