<?php

/**
 * Developer — Themes Manager
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'Theme Manager';
$page_icon = 'fas fa-palette';
$page_subtitle = 'Visual Customization';
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

$themesDir = BASE_PATH . '/assets/theme';
$themes = [];
if (is_dir($themesDir)) {
  foreach (glob($themesDir . '/*.css') as $f) {
    $themes[] = [
      'name' => basename($f, '.css'),
      'file' => basename($f),
      'size' => filesize($f),
      'modified' => date('Y-m-d H:i', filemtime($f)),
      'lines' => count(file($f)),
    ];
  }
}

ob_start();
?>

<style>
  .theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.2rem;
  }

  .theme-card {
    background: var(--card-bg, #1a1a2e);
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 1.5rem;
  }

  .theme-card h3 {
    margin: 0 0 .5rem;
    font-size: 1rem;
  }

  .theme-meta {
    font-size: .8rem;
    opacity: .6;
  }

  .theme-meta span {
    margin-right: 1rem;
  }
</style>

<div class="theme-grid">
  <?php foreach ($themes as $t): ?>
    <div class="theme-card">
      <h3><i class="fas fa-paint-brush"></i> <?= htmlspecialchars($t['name']) ?></h3>
      <div class="theme-meta">
        <span><i class="fas fa-file"></i> <?= htmlspecialchars($t['file']) ?></span>
        <span><i class="fas fa-weight"></i> <?= number_format($t['size'] / 1024, 1) ?> KB</span>
        <span><i class="fas fa-code"></i> <?= $t['lines'] ?> lines</span>
      </div>
      <div class="theme-meta" style="margin-top:.4rem;">
        <span><i class="fas fa-clock"></i> <?= $t['modified'] ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($themes)): ?>
    <p style="opacity:.6;">No theme files found.</p>
  <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
