<?php

/**
 * Developer — AI Center
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'AI Center';
$page_icon = 'fas fa-robot';
$page_subtitle = 'AI Services Overview';
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

// Load scores
$scores = [];
$summaryFiles = [
  'Cognitive'    => BASE_PATH . '/storage/cognitive-summary.json',
  'Intelligence' => BASE_PATH . '/storage/intelligence-summary.json',
  'Ecosystem'    => BASE_PATH . '/storage/ecosystem-summary.json',
];
foreach ($summaryFiles as $label => $file) {
  if (is_file($file)) {
    $data = json_decode(file_get_contents($file), true);
    $scores[$label] = $data;
  } else {
    $scores[$label] = null;
  }
}

// AI architecture layers
$layers = [
  ['name' => 'Ecosystem Kernel', 'class' => 'EcosystemKernel', 'phase' => 10, 'color' => '#00e5ff'],
  ['name' => 'Cognitive Kernel', 'class' => 'CognitiveKernel', 'phase' => 9, 'color' => '#e040fb'],
  ['name' => 'Intelligence Kernel', 'class' => 'IntelligenceKernel', 'phase' => 8, 'color' => '#7c4dff'],
  ['name' => 'DevOps Kernel', 'class' => 'DevOpsKernel', 'phase' => 7, 'color' => '#ff6b35'],
  ['name' => 'Autonomous Fix Loop', 'class' => 'AutonomousFixLoop', 'phase' => 6, 'color' => '#ffab00'],
];

ob_start();
?>

<style>
  .layer-stack {
    display: flex;
    flex-direction: column;
    gap: .8rem;
    margin-bottom: 2rem;
  }

  .layer-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: var(--card-bg, #1a1a2e);
    border-radius: 12px;
    border-left: 4px solid;
  }

  .layer-item .layer-phase {
    font-size: .7rem;
    opacity: .5;
    font-weight: 600;
    text-transform: uppercase;
  }

  .layer-item .layer-name {
    font-size: 1rem;
    font-weight: 600;
  }

  .score-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .sc {
    text-align: center;
    padding: 1.5rem;
    background: var(--card-bg, #1a1a2e);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, .06);
  }

  .sc .val {
    font-size: 2.5rem;
    font-weight: 800;
  }

  .sc .lbl {
    font-size: .8rem;
    opacity: .6;
    margin-top: .3rem;
  }
</style>

<div class="score-cards">
  <?php
  $scoreMap = [
    'Cognitive' => ['key' => 'cognitive_score', 'color' => '#e040fb'],
    'Intelligence' => ['key' => 'intelligence_score', 'color' => '#7c4dff'],
    'Ecosystem' => ['key' => 'ecosystem_score', 'color' => '#00e5ff'],
  ];
  foreach ($scoreMap as $label => $info):
    $val = $scores[$label][$info['key']] ?? '—';
  ?>
    <div class="sc">
      <div class="val" style="color:<?= $info['color'] ?>"><?= $val ?></div>
      <div class="lbl"><?= $label ?> Score</div>
    </div>
  <?php endforeach; ?>
</div>

<h3 style="margin-bottom:1rem;"><i class="fas fa-layer-group"></i> AI Architecture Stack</h3>
<div class="layer-stack">
  <?php foreach ($layers as $l): ?>
    <div class="layer-item" style="border-left-color:<?= $l['color'] ?>">
      <div>
        <div class="layer-phase">Phase <?= $l['phase'] ?></div>
        <div class="layer-name"><?= htmlspecialchars($l['name']) ?></div>
      </div>
      <div style="margin-left:auto;">
        <span class="status-badge <?= class_exists($l['class']) ? 'status-ok' : 'status-err' ?>">
          <?= class_exists($l['class']) ? 'ACTIVE' : 'MISSING' ?>
        </span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
