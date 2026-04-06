<?php

/**
 * Performance Dashboard — System performance metrics and optimization.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// Gather performance data
$perf = [
  'php_version'    => PHP_VERSION,
  'memory_limit'   => ini_get('memory_limit'),
  'max_exec_time'  => ini_get('max_execution_time'),
  'upload_max'     => ini_get('upload_max_filesize'),
  'opcache'        => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
  'memory_usage'   => round(memory_get_usage(true) / 1048576, 2),
  'peak_memory'    => round(memory_get_peak_usage(true) / 1048576, 2),
];

// Disk usage
$storagePath = BASE_PATH . '/storage';
$perf['disk_free'] = is_dir($storagePath) ? round(disk_free_space($storagePath) / 1073741824, 2) : 0;
$perf['disk_total'] = is_dir($storagePath) ? round(disk_total_space($storagePath) / 1073741824, 2) : 0;
$perf['disk_pct'] = $perf['disk_total'] > 0 ? round(($perf['disk_total'] - $perf['disk_free']) / $perf['disk_total'] * 100, 1) : 0;

// DevOps performance data
$devopsPerf = [];
$responseMetrics = [];
try {
  $dd = DevOpsKernel::getDashboardData();
  $devopsPerf = $dd['performance'] ?? [];
  $responseMetrics = $dd['metrics'] ?? [];
} catch (\Throwable $e) {
}

// Cache stats
$cacheDir = BASE_PATH . '/cache';
$cacheFiles = 0;
$cacheSize = 0;
if (is_dir($cacheDir)) {
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) {
    if ($f->isFile()) {
      $cacheFiles++;
      $cacheSize += $f->getSize();
    }
  }
}
$perf['cache_files'] = $cacheFiles;
$perf['cache_size'] = round($cacheSize / 1024, 1);

// OPcache details
$opcacheStats = null;
if ($perf['opcache'] && function_exists('opcache_get_status')) {
  $opcacheStats = opcache_get_status(false);
}

// Score: higher = better
$perfScore = 100;
if ($perf['memory_usage'] > 64) $perfScore -= 15;
if ($perf['disk_pct'] > 85) $perfScore -= 20;
if (!$perf['opcache']) $perfScore -= 10;
if ($cacheFiles > 500) $perfScore -= 5;

function perfColor(int $s): string
{
  return $s >= 90 ? '#00ff41' : ($s >= 70 ? '#ffa726' : '#ff4444');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Performance — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #080c14;
      --card: #0d1117;
      --border: rgba(255, 167, 38, .12);
      --text: #e0e0e0;
      --accent: #ffa726;
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
      background: rgba(255, 167, 38, .1);
      color: #fff;
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
      font-size: 1.6rem;
      font-weight: 700;
    }

    .stat-label {
      font-size: .78rem;
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

    .config-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }

    .config-item {
      padding: 10px 14px;
      background: rgba(255, 255, 255, .02);
      border-radius: 8px;
      font-size: .85rem;
      display: flex;
      justify-content: space-between;
    }

    .config-item .label {
      opacity: .6;
    }

    .config-item .value {
      font-weight: 600;
      color: var(--accent);
    }

    .meter {
      height: 8px;
      background: rgba(255, 255, 255, .08);
      border-radius: 4px;
      overflow: hidden;
      margin-top: 6px;
    }

    .meter-fill {
      height: 100%;
      border-radius: 4px;
      transition: width .3s;
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <a href="<?= route('developer/index.php') ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Portal</a>
    <h1><i class="fas fa-tachometer-alt"></i> Performance</h1>
    <span class="stat-val" style="margin-left:auto;font-size:1.2rem;color:<?= perfColor($perfScore) ?>"><?= $perfScore ?>/100</span>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-val" style="color:#00d4ff"><?= $perf['php_version'] ?></div>
      <div class="stat-label">PHP Version</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#e040fb"><?= $perf['memory_usage'] ?> MB</div>
      <div class="stat-label">Memory Usage</div>
      <div class="meter">
        <div class="meter-fill" style="width:<?= min(100, $perf['memory_usage'] / (int)$perf['memory_limit'] * 100) ?>%;background:#e040fb"></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#00ff41"><?= $perf['peak_memory'] ?> MB</div>
      <div class="stat-label">Peak Memory</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:<?= $perf['disk_pct'] > 85 ? '#ff4444' : '#00ff41' ?>"><?= $perf['disk_pct'] ?>%</div>
      <div class="stat-label">Disk Used</div>
      <div class="meter">
        <div class="meter-fill" style="width:<?= $perf['disk_pct'] ?>%;background:<?= $perf['disk_pct'] > 85 ? '#ff4444' : '#00ff41' ?>"></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:<?= $perf['opcache'] ? '#00ff41' : '#ff4444' ?>"><?= $perf['opcache'] ? 'ON' : 'OFF' ?></div>
      <div class="stat-label">OPcache</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#ffa726"><?= $perf['cache_files'] ?></div>
      <div class="stat-label">Cache Files (<?= $perf['cache_size'] ?> KB)</div>
    </div>
  </div>

  <div class="panel">
    <h3><i class="fas fa-cog"></i> PHP Configuration</h3>
    <div class="config-grid">
      <div class="config-item"><span class="label">Memory Limit</span><span class="value"><?= htmlspecialchars($perf['memory_limit']) ?></span></div>
      <div class="config-item"><span class="label">Max Execution Time</span><span class="value"><?= htmlspecialchars($perf['max_exec_time']) ?>s</span></div>
      <div class="config-item"><span class="label">Upload Max Size</span><span class="value"><?= htmlspecialchars($perf['upload_max']) ?></span></div>
      <div class="config-item"><span class="label">Disk Free</span><span class="value"><?= $perf['disk_free'] ?> GB</span></div>
      <div class="config-item"><span class="label">Disk Total</span><span class="value"><?= $perf['disk_total'] ?> GB</span></div>
      <div class="config-item"><span class="label">SAPI</span><span class="value"><?= htmlspecialchars(php_sapi_name()) ?></span></div>
      <div class="config-item"><span class="label">OS</span><span class="value"><?= htmlspecialchars(PHP_OS) ?></span></div>
      <div class="config-item"><span class="label">Extensions</span><span class="value"><?= count(get_loaded_extensions()) ?> loaded</span></div>
    </div>
  </div>

  <?php if ($opcacheStats): ?>
    <div class="panel">
      <h3><i class="fas fa-bolt"></i> OPcache Statistics</h3>
      <div class="config-grid">
        <div class="config-item"><span class="label">Cached Scripts</span><span class="value"><?= $opcacheStats['opcache_statistics']['num_cached_scripts'] ?? 0 ?></span></div>
        <div class="config-item"><span class="label">Hit Rate</span><span class="value"><?= round($opcacheStats['opcache_statistics']['opcache_hit_rate'] ?? 0, 1) ?>%</span></div>
        <div class="config-item"><span class="label">Memory Used</span><span class="value"><?= round(($opcacheStats['memory_usage']['used_memory'] ?? 0) / 1048576, 1) ?> MB</span></div>
        <div class="config-item"><span class="label">Memory Free</span><span class="value"><?= round(($opcacheStats['memory_usage']['free_memory'] ?? 0) / 1048576, 1) ?> MB</span></div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($devopsPerf): ?>
    <div class="panel">
      <h3><i class="fas fa-chart-line"></i> DevOps Performance Metrics</h3>
      <div class="config-grid">
        <?php foreach ($devopsPerf as $key => $val): ?>
          <div class="config-item">
            <span class="label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></span>
            <span class="value"><?= htmlspecialchars(is_bool($val) ? ($val ? 'Yes' : 'No') : (is_array($val) ? count($val) . ' items' : $val)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
