<?php

/**
 * Developer — System Health Page
 * Checks: DB connection, session engine, cron jobs, AI services, mail, queue.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'System Health';
$page_icon = 'fas fa-heartbeat';
$page_subtitle = 'Infrastructure Health Checks';
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

// Run health checks
$checks = [];

// 1. Database
try {
  $row = db()->fetchOne("SELECT 1 AS ok");
  $checks[] = ['name' => 'Database Connection', 'icon' => 'fas fa-database', 'status' => 'ok', 'detail' => 'MySQL connected'];
} catch (\Throwable $e) {
  $checks[] = ['name' => 'Database Connection', 'icon' => 'fas fa-database', 'status' => 'error', 'detail' => $e->getMessage()];
}

// 2. Session engine
$checks[] = [
  'name' => 'Session Engine',
  'icon' => 'fas fa-cookie',
  'status' => session_status() === PHP_SESSION_ACTIVE ? 'ok' : 'warn',
  'detail' => 'Handler: ' . ini_get('session.save_handler') . ' | Path: ' . ini_get('session.save_path'),
];

// 3. Cron jobs (check last run files)
$cronFiles = [
  'Cognitive' => BASE_PATH . '/storage/cognitive-summary.json',
  'Ecosystem' => BASE_PATH . '/storage/ecosystem-summary.json',
  'Intelligence' => BASE_PATH . '/storage/intelligence-summary.json',
];
foreach ($cronFiles as $name => $file) {
  if (is_file($file)) {
    $age = time() - filemtime($file);
    $status = $age < 7200 ? 'ok' : ($age < 86400 ? 'warn' : 'error');
    $checks[] = ['name' => "Cron: {$name}", 'icon' => 'fas fa-clock', 'status' => $status, 'detail' => "Last run: " . date('Y-m-d H:i', filemtime($file))];
  } else {
    $checks[] = ['name' => "Cron: {$name}", 'icon' => 'fas fa-clock', 'status' => 'warn', 'detail' => 'Never run'];
  }
}

// 4. AI services
$aiClasses = ['CognitiveKernel', 'IntelligenceKernel', 'EcosystemKernel', 'EthicalGuard'];
foreach ($aiClasses as $cls) {
  $checks[] = [
    'name' => "AI: {$cls}",
    'icon' => 'fas fa-brain',
    'status' => class_exists($cls) ? 'ok' : 'error',
    'detail' => class_exists($cls) ? 'Class loaded' : 'Class not found',
  ];
}

// 5. Mail delivery
$mailConfigured = defined('SMTP_HOST') && defined('SMTP_USERNAME') && !empty(SMTP_USERNAME);
$checks[] = [
  'name' => 'Mail Delivery',
  'icon' => 'fas fa-envelope',
  'status' => $mailConfigured ? 'ok' : 'warn',
  'detail' => $mailConfigured ? 'SMTP configured: ' . SMTP_HOST : 'SMTP not configured',
];

// 6. Storage writable
$storagePath = BASE_PATH . '/storage';
$checks[] = [
  'name' => 'Storage Directory',
  'icon' => 'fas fa-folder',
  'status' => is_writable($storagePath) ? 'ok' : 'error',
  'detail' => is_writable($storagePath) ? 'Writable' : 'Not writable',
];

// 7. PHP extensions
$required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
foreach ($required as $ext) {
  $checks[] = [
    'name' => "Extension: {$ext}",
    'icon' => 'fas fa-puzzle-piece',
    'status' => extension_loaded($ext) ? 'ok' : 'error',
    'detail' => extension_loaded($ext) ? 'Loaded' : 'Missing',
  ];
}

// Calculate overall health
$okCount = count(array_filter($checks, fn($c) => $c['status'] === 'ok'));
$total = count($checks);
$healthPercent = $total > 0 ? round($okCount / $total * 100) : 0;

ob_start();
?>

<style>
  .health-hero {
    text-align: center;
    padding: 2rem;
    background: linear-gradient(135deg, rgba(0, 255, 65, .08), rgba(0, 229, 255, .08));
    border-radius: 16px;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(0, 255, 65, .15);
  }

  .health-hero .score {
    font-size: 3.5rem;
    font-weight: 800;
    color: <?= $healthPercent >= 80 ? '#00ff41' : ($healthPercent >= 60 ? '#ffaa00' : '#ff4444') ?>;
  }

  .health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
  }

  .health-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--card-bg, #1a1a2e);
    border: 1px solid rgba(255, 255, 255, .06);
    border-radius: 10px;
  }

  .health-item .hi-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
  }

  .health-item .hi-icon.ok {
    background: rgba(0, 255, 65, .12);
    color: #00ff41;
  }

  .health-item .hi-icon.warn {
    background: rgba(255, 170, 0, .12);
    color: #ffaa00;
  }

  .health-item .hi-icon.error {
    background: rgba(255, 68, 68, .12);
    color: #ff4444;
  }

  .health-item .hi-info h4 {
    margin: 0 0 .2rem;
    font-size: .9rem;
  }

  .health-item .hi-info p {
    margin: 0;
    font-size: .78rem;
    opacity: .6;
  }
</style>

<div class="health-hero">
  <div class="score"><?= $healthPercent ?>%</div>
  <div style="font-size:.9rem;opacity:.7;margin-top:.3rem;"><?= $okCount ?>/<?= $total ?> checks passed</div>
</div>

<div class="health-grid">
  <?php foreach ($checks as $c): ?>
    <div class="health-item">
      <div class="hi-icon <?= $c['status'] ?>"><i class="<?= $c['icon'] ?>"></i></div>
      <div class="hi-info">
        <h4><?= htmlspecialchars($c['name']) ?></h4>
        <p><?= htmlspecialchars($c['detail']) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
