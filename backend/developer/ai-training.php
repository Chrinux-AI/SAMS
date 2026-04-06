<?php

/**
 * AI Training Center — AI service management, model overview, and diagnostics.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// AI services inventory
$aiServices = [
  ['name' => 'CoreAIService',    'class' => 'CoreAIService',    'role' => 'core',    'icon' => 'fas fa-brain'],
  ['name' => 'AdminAIService',   'class' => 'AdminAIService',   'role' => 'admin',   'icon' => 'fas fa-user-shield'],
  ['name' => 'TeacherAIService', 'class' => 'TeacherAIService', 'role' => 'teacher', 'icon' => 'fas fa-chalkboard-teacher'],
  ['name' => 'StudentAIService', 'class' => 'StudentAIService', 'role' => 'student', 'icon' => 'fas fa-user-graduate'],
  ['name' => 'ParentAIService',  'class' => 'ParentAIService',  'role' => 'parent',  'icon' => 'fas fa-users'],
  ['name' => 'PublicAIService',  'class' => 'PublicAIService',  'role' => 'public',  'icon' => 'fas fa-globe'],
  ['name' => 'SecurityAI',      'class' => 'SecurityAI',       'role' => 'security', 'icon' => 'fas fa-shield-alt'],
  ['name' => 'BackupVerifierAI', 'class' => 'BackupVerifierAI', 'role' => 'backup',  'icon' => 'fas fa-hdd'],
];

// Check service availability
$serviceStatus = [];
foreach ($aiServices as $svc) {
  $available = class_exists($svc['class']);
  $methods = [];
  if ($available) {
    try {
      $ref = new ReflectionClass($svc['class']);
      $methods = array_map(fn($m) => $m->getName(), $ref->getMethods(ReflectionMethod::IS_PUBLIC));
    } catch (\Throwable $e) {
    }
  }
  $serviceStatus[] = array_merge($svc, [
    'available' => $available,
    'methods'   => $methods,
    'method_count' => count($methods),
  ]);
}

$activeCount = count(array_filter($serviceStatus, fn($s) => $s['available']));
$totalCount  = count($serviceStatus);

// AI Router health
$routerActive = class_exists('AIRouter');

// Cognitive/Intelligence kernel status
$cogStatus = null;
try {
  $cogFile = BASE_PATH . '/storage/cognitive-summary.json';
  if (is_file($cogFile)) {
    $cogStatus = json_decode(file_get_contents($cogFile), true);
  }
} catch (\Throwable $e) {
}

$intStatus = null;
try {
  $intFile = BASE_PATH . '/storage/intelligence-summary.json';
  if (is_file($intFile)) {
    $intStatus = json_decode(file_get_contents($intFile), true);
  }
} catch (\Throwable $e) {
}

// AIC brain status
$aicStatus = null;
try {
  $aicFile = BASE_PATH . '/storage/aic-summary.json';
  if (is_file($aicFile)) {
    $aicStatus = json_decode(file_get_contents($aicFile), true);
  }
} catch (\Throwable $e) {
}

$aiScore = $totalCount > 0 ? round($activeCount / $totalCount * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>AI Training Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #080c14;
      --card: #0d1117;
      --border: rgba(171, 71, 188, .15);
      --text: #e0e0e0;
      --accent: #ab47bc;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 20px;
    }

    .top-bar {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
    }

    .top-bar h1 {
      font-size: 1.6rem;
      color: var(--accent);
      margin: 0;
    }

    .back-btn {
      color: var(--accent);
      text-decoration: none;
      font-size: .9rem;
      padding: 6px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .back-btn:hover {
      background: rgba(171, 71, 188, .1);
      color: #fff;
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 18px;
      text-align: center;
    }

    .stat-val {
      font-size: 1.8rem;
      font-weight: 700;
    }

    .stat-label {
      font-size: .8rem;
      opacity: .6;
      margin-top: 4px;
    }

    .panel {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
    }

    .panel h3 {
      color: var(--accent);
      font-size: 1.1rem;
      margin: 0 0 14px;
    }

    .svc-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 14px;
    }

    .svc-card {
      padding: 16px;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: rgba(255, 255, 255, .01);
      position: relative;
    }

    .svc-card.online {
      border-color: rgba(0, 255, 65, .3);
    }

    .svc-card.offline {
      border-color: rgba(255, 68, 68, .3);
    }

    .svc-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }

    .svc-header i {
      font-size: 1.2rem;
      color: var(--accent);
    }

    .svc-header .name {
      font-weight: 600;
      font-size: .95rem;
    }

    .svc-status {
      position: absolute;
      top: 12px;
      right: 14px;
      font-size: .72rem;
      padding: 3px 8px;
      border-radius: 4px;
    }

    .svc-status.up {
      background: #00ff4122;
      color: #00ff41;
    }

    .svc-status.down {
      background: #ff444422;
      color: #ff4444;
    }

    .svc-meta {
      font-size: .8rem;
      opacity: .5;
    }

    .method-list {
      margin-top: 8px;
      font-size: .78rem;
      opacity: .6;
      line-height: 1.6;
      max-height: 80px;
      overflow: hidden;
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <a href="<?= route('developer/index.php') ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Portal</a>
    <h1><i class="fas fa-graduation-cap"></i> AI Training Center</h1>
    <span class="stat-val" style="margin-left:auto;font-size:1.2rem;color:<?= $aiScore >= 80 ? '#00ff41' : '#ffaa00' ?>"><?= $aiScore ?>%</span>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-val" style="color:#00ff41"><?= $activeCount ?>/<?= $totalCount ?></div>
      <div class="stat-label">Services Active</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:<?= $routerActive ? '#00ff41' : '#ff4444' ?>"><?= $routerActive ? 'ACTIVE' : 'OFF' ?></div>
      <div class="stat-label">AI Router</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#e040fb"><?= $cogStatus ? ($cogStatus['cognitive_score'] ?? 0) : '—' ?></div>
      <div class="stat-label">Cognitive Score</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#00d4ff"><?= $aicStatus ? ($aicStatus['health_score'] ?? 0) : '—' ?></div>
      <div class="stat-label">AIC Health</div>
    </div>
  </div>

  <div class="panel">
    <h3><i class="fas fa-robot"></i> AI Service Registry</h3>
    <div class="svc-grid">
      <?php foreach ($serviceStatus as $svc): ?>
        <div class="svc-card <?= $svc['available'] ? 'online' : 'offline' ?>">
          <span class="svc-status <?= $svc['available'] ? 'up' : 'down' ?>"><?= $svc['available'] ? 'ONLINE' : 'OFFLINE' ?></span>
          <div class="svc-header">
            <i class="<?= htmlspecialchars($svc['icon']) ?>"></i>
            <span class="name"><?= htmlspecialchars($svc['name']) ?></span>
          </div>
          <div class="svc-meta">Role: <?= htmlspecialchars($svc['role']) ?> &middot; <?= $svc['method_count'] ?> public methods</div>
          <?php if ($svc['methods']): ?>
            <div class="method-list"><?= htmlspecialchars(implode(', ', array_slice($svc['methods'], 0, 12))) ?><?= count($svc['methods']) > 12 ? '...' : '' ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($cogStatus): ?>
    <div class="panel">
      <h3><i class="fas fa-brain"></i> Cognitive Engine Summary</h3>
      <div class="stats-row" style="margin-bottom:0">
        <div class="stat-card">
          <div class="stat-val" style="color:#e040fb;font-size:1.3rem"><?= $cogStatus['cognitive_score'] ?? 0 ?></div>
          <div class="stat-label">Cognitive Score</div>
        </div>
        <div class="stat-card">
          <div class="stat-val" style="color:#00d4ff;font-size:1rem"><?= $cogStatus['timestamp'] ?? '—' ?></div>
          <div class="stat-label">Last Cycle</div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($intStatus): ?>
    <div class="panel">
      <h3><i class="fas fa-chart-network"></i> Intelligence Kernel Summary</h3>
      <pre style="color:var(--accent);font-size:.8rem;background:rgba(0,0,0,.3);padding:12px;border-radius:8px;max-height:200px;overflow:auto"><?= htmlspecialchars(json_encode($intStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
