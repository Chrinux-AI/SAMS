<?php

/**
 * Developer Portal — Index / Dashboard
 * Central hub for all developer tools.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'Developer Portal';
$page_icon = 'fas fa-terminal';
$page_subtitle = 'System Command Center';

// Load cyberpunk theme for developer
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

// Gather quick stats
$systemScore = 0;
$cognitiveScore = 0;
$ecosystemScore = 0;

try {
  $cogFile = BASE_PATH . '/storage/cognitive-summary.json';
  if (is_file($cogFile)) {
    $cog = json_decode(file_get_contents($cogFile), true);
    $cognitiveScore = $cog['cognitive_score'] ?? 0;
  }
} catch (\Throwable $e) {
}

try {
  $ecoFile = BASE_PATH . '/storage/ecosystem-summary.json';
  if (is_file($ecoFile)) {
    $eco = json_decode(file_get_contents($ecoFile), true);
    $ecosystemScore = $eco['ecosystem_score'] ?? 0;
  }
} catch (\Throwable $e) {
}

$systemScore = $ecosystemScore ?: $cognitiveScore;

// Healing / stability score
$stabilityScore = 0;
try {
  $healFile = BASE_PATH . '/storage/healing-summary.json';
  if (is_file($healFile)) {
    $heal = json_decode(file_get_contents($healFile), true);
    $stabilityScore = $heal['stability_score'] ?? 0;
  }
} catch (\Throwable $e) {
}

// OS health score
$osHealthScore = 0;
try {
  $osFile = BASE_PATH . '/storage/os-summary.json';
  if (is_file($osFile)) {
    $osData = json_decode(file_get_contents($osFile), true);
    $osHealthScore = $osData['os_health'] ?? 0;
  }
} catch (\Throwable $e) {
}

// Module cards
$modules = [
  ['title' => 'Master Control', 'icon' => 'fas fa-satellite-dish', 'link' => dev_route('master-control/'), 'desc' => 'God-mode command center', 'color' => '#ff0055'],
  ['title' => 'Command Intelligence', 'icon' => 'fas fa-brain', 'link' => dev_route('aci-center.php'), 'desc' => 'Autonomous Command Intelligence', 'color' => '#aa00ff'],
  ['title' => 'Institution Intelligence', 'icon' => 'fas fa-university', 'link' => dev_route('aic-center.php'), 'desc' => 'Institutional Consciousness Layer', 'color' => '#00e676'],
  ['title' => 'System Health', 'icon' => 'fas fa-heartbeat', 'link' => dev_route('system-health.php'), 'desc' => 'DB, sessions, cron, services', 'color' => '#00ff41'],
  ['title' => 'System Monitor', 'icon' => 'fas fa-server', 'link' => dev_route('system-monitor.php'), 'desc' => 'Real-time system metrics', 'color' => '#00d4ff'],
  ['title' => 'DevOps Center', 'icon' => 'fas fa-rocket', 'link' => dev_route('devops-center.php'), 'desc' => 'Autonomous DevOps engine', 'color' => '#ff6b35'],
  ['title' => 'Intelligence', 'icon' => 'fas fa-brain', 'link' => dev_route('intelligence-center.php'), 'desc' => 'Platform intelligence layer', 'color' => '#e040fb'],
  ['title' => 'Ecosystem', 'icon' => 'fas fa-globe', 'link' => dev_route('ecosystem-center.php'), 'desc' => 'Distributed ecosystem', 'color' => '#00e5ff'],
  ['title' => 'AutoFix', 'icon' => 'fas fa-wrench', 'link' => dev_route('autofix-center.php'), 'desc' => 'Autonomous fix loop', 'color' => '#ffab00'],
  ['title' => 'Self-Healing', 'icon' => 'fas fa-shield-alt', 'link' => dev_route('healing-center.php'), 'desc' => 'Platform stability & recovery', 'color' => '#00ff41'],
  ['title' => 'OS Center', 'icon' => 'fas fa-microchip', 'link' => dev_route('os-center.php'), 'desc' => 'Autonomous School OS', 'color' => '#ff4081'],
  ['title' => 'Settings', 'icon' => 'fas fa-cog', 'link' => dev_route('settings.php'), 'desc' => 'Developer settings & config', 'color' => '#90a4ae'],
  ['title' => 'Logs', 'icon' => 'fas fa-scroll', 'link' => dev_route('logs.php'), 'desc' => 'System & error logs', 'color' => '#8bc34a'],
  ['title' => 'Modules', 'icon' => 'fas fa-puzzle-piece', 'link' => dev_route('modules.php'), 'desc' => 'Module management', 'color' => '#7c4dff'],
  ['title' => 'Themes', 'icon' => 'fas fa-palette', 'link' => dev_route('themes.php'), 'desc' => 'Theme management', 'color' => '#ff4081'],
  ['title' => 'AI Center', 'icon' => 'fas fa-robot', 'link' => dev_route('ai-center.php'), 'desc' => 'AI services overview', 'color' => '#18ffff'],
  ['title' => 'Debug', 'icon' => 'fas fa-bug', 'link' => dev_route('debug-overlay.php'), 'desc' => 'Debug overlay', 'color' => '#ff5722'],
  ['title' => 'Database Monitor', 'icon' => 'fas fa-database', 'link' => dev_route('database-monitor.php'), 'desc' => 'Database health & optimization', 'color' => '#26c6da'],
  ['title' => 'Security Center', 'icon' => 'fas fa-shield-virus', 'link' => dev_route('security-center.php'), 'desc' => 'Security posture & threats', 'color' => '#ef5350'],
  ['title' => 'Performance', 'icon' => 'fas fa-tachometer-alt', 'link' => dev_route('performance.php'), 'desc' => 'Performance metrics & tuning', 'color' => '#ffa726'],
  ['title' => 'AI Training', 'icon' => 'fas fa-graduation-cap', 'link' => dev_route('ai-training.php'), 'desc' => 'AI model management', 'color' => '#ab47bc'],
];

ob_start();
?>

<style>
  .dev-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.2rem;
    margin-top: 1.5rem;
  }

  .dev-card {
    background: var(--card-bg, #1a1a2e);
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all .3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
    position: relative;
    overflow: hidden;
  }

  .dev-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent, #00e5ff);
    box-shadow: 0 8px 30px rgba(0, 229, 255, .15);
  }

  .dev-card .card-accent {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
  }

  .dev-card .card-icon {
    font-size: 2rem;
    margin-bottom: .8rem;
  }

  .dev-card h3 {
    margin: 0 0 .4rem;
    font-size: 1.1rem;
    font-weight: 600;
  }

  .dev-card p {
    margin: 0;
    font-size: .85rem;
    opacity: .7;
  }

  .score-hero {
    text-align: center;
    padding: 2rem;
    background: linear-gradient(135deg, rgba(0, 229, 255, .1), rgba(224, 64, 251, .1));
    border-radius: 16px;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(0, 229, 255, .15);
  }

  .score-hero .score {
    font-size: 4rem;
    font-weight: 800;
    background: linear-gradient(135deg, #00e5ff, #e040fb);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .score-hero .label {
    font-size: .9rem;
    opacity: .7;
    margin-top: .3rem;
  }

  .score-row {
    display: flex;
    justify-content: center;
    gap: 3rem;
    margin-top: 1rem;
  }

  .score-row .item {
    text-align: center;
  }

  .score-row .val {
    font-size: 1.5rem;
    font-weight: 700;
  }

  .score-row .lbl {
    font-size: .75rem;
    opacity: .6;
  }
</style>

<div class="score-hero">
  <div class="score"><?= $systemScore ?>/100</div>
  <div class="label">Ecosystem Intelligence Score</div>
  <div class="score-row">
    <div class="item">
      <div class="val" style="color:#e040fb"><?= $cognitiveScore ?></div>
      <div class="lbl">Cognitive</div>
    </div>
    <div class="item">
      <div class="val" style="color:#00e5ff"><?= $ecosystemScore ?></div>
      <div class="lbl">Ecosystem</div>
    </div>
    <div class="item">
      <div class="val" style="color:#ff4081"><?= $osHealthScore ?></div>
      <div class="lbl">OS Health</div>
    </div>
  </div>
</div>

<div class="dev-grid">
  <?php foreach ($modules as $m): ?>
    <a href="<?= htmlspecialchars($m['link']) ?>" class="dev-card">
      <div class="card-accent" style="background: <?= $m['color'] ?>"></div>
      <div class="card-icon" style="color: <?= $m['color'] ?>"><i class="<?= $m['icon'] ?>"></i></div>
      <h3><?= htmlspecialchars($m['title']) ?></h3>
      <p><?= htmlspecialchars($m['desc']) ?></p>
    </a>
  <?php endforeach; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
