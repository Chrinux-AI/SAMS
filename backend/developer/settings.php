<?php

/**
 * Developer Settings — Tab-Based Configuration Panel
 *
 * Each tab loads a module dynamically from developer/tabs/.
 * No tab allowed without backend logic.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'Developer Settings';
$page_icon = 'fas fa-cog';
$page_subtitle = 'System Configuration';

$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

// Tab routing
$allowedTabs = ['general', 'security', 'ai', 'themes', 'integrations'];
$tab = $_GET['tab'] ?? 'general';
if (!in_array($tab, $allowedTabs, true)) {
  $tab = 'general';
}

// Handle POST actions
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
  if (verify_csrf_token($_POST['csrf_token'])) {
    $tabFile = __DIR__ . "/tabs/{$tab}.php";
    if (is_file($tabFile)) {
      // Tab files handle their own POST processing
    }
  }
}

ob_start();
?>

<style>
  .settings-tabs {
    display: flex;
    gap: .5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    border-bottom: 2px solid rgba(255, 255, 255, .08);
    padding-bottom: .8rem;
  }

  .settings-tabs a {
    padding: .6rem 1.2rem;
    border-radius: 8px 8px 0 0;
    text-decoration: none;
    color: var(--text-secondary, #8899aa);
    font-size: .9rem;
    font-weight: 500;
    transition: all .2s;
    border: 1px solid transparent;
    border-bottom: none;
  }

  .settings-tabs a:hover {
    color: var(--text-primary, #fff);
    background: rgba(0, 229, 255, .05);
  }

  .settings-tabs a.active {
    color: #00e5ff;
    background: rgba(0, 229, 255, .1);
    border-color: rgba(0, 229, 255, .2);
  }

  .settings-panel {
    background: var(--card-bg, #1a1a2e);
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 1.5rem;
    min-height: 400px;
  }

  .form-group {
    margin-bottom: 1.2rem;
  }

  .form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: .4rem;
    font-size: .85rem;
  }

  .form-group input,
  .form-group select,
  .form-group textarea {
    width: 100%;
    padding: .6rem .8rem;
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 8px;
    background: rgba(0, 0, 0, .3);
    color: inherit;
    font-size: .9rem;
  }

  .form-group input:focus,
  .form-group select:focus {
    border-color: #00e5ff;
    outline: none;
  }

  .btn-save {
    padding: .6rem 1.5rem;
    background: linear-gradient(135deg, #00e5ff, #00b8d4);
    color: #000;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: .9rem;
  }

  .btn-save:hover {
    opacity: .9;
  }

  .status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: .75rem;
    font-weight: 600;
  }

  .status-ok {
    background: rgba(0, 255, 65, .15);
    color: #00ff41;
  }

  .status-warn {
    background: rgba(255, 170, 0, .15);
    color: #ffaa00;
  }

  .status-err {
    background: rgba(255, 68, 68, .15);
    color: #ff4444;
  }
</style>

<div class="settings-tabs">
  <?php foreach ($allowedTabs as $t): ?>
    <a href="<?= dev_route("settings.php?tab={$t}") ?>" class="<?= $tab === $t ? 'active' : '' ?>">
      <?= ucfirst($t) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="settings-panel">
  <?php
  $tabFile = __DIR__ . "/tabs/{$tab}.php";
  if (is_file($tabFile)) {
    include $tabFile;
  } else {
    echo '<p style="opacity:.6;">Tab module not found. Create <code>developer/tabs/' . htmlspecialchars($tab) . '.php</code> to activate.</p>';
  }
  ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
