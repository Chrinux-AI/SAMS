<?php

/**
 * Developer — Modules Overview
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'Modules';
$page_icon = 'fas fa-puzzle-piece';
$page_subtitle = 'System Module Registry';
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

// Scan modules from bootstrap class map
$modules = [
  'Security' => ['SecurityGateway', 'InputSanitizer', 'XSSGuard', 'SQLInjectionGuard', 'AuditLogger', 'RateLimiterService', 'BehaviorMonitor', 'AutoDefense', 'SessionIntelligence', 'PolicyGuard', 'PromptFirewall', 'SecuritySimulator', 'DataConsistencyGuard'],
  'AI' => ['CoreAIService', 'AIRouter', 'SecurityAI', 'BackupVerifierAI'],
  'Events' => ['EventDispatcher', 'SystemEvents', 'EventListeners', 'Broadcaster', 'SecurityEventBus', 'EventBus'],
  'Core' => ['ErrorHandler', 'ErrorCollector', 'SystemScanner', 'AutoRepairEngine', 'ValidationRunner', 'HealthReporter', 'AutonomousFixLoop', 'AdminEditGuarantee', 'AutoSyncEngine', 'QueryValidator'],
  'DevOps' => ['ResourceMonitor', 'PerformanceOptimizer', 'DatabaseOptimizer', 'SecurityHardener', 'DeploymentGuard', 'DriftController', 'IncidentResponder', 'DevOpsKernel'],
  'Platform' => ['KnowledgeGraph', 'ContextEngine', 'BehaviorAnalyzer', 'PredictionEngine', 'DecisionEngine', 'WorkflowOrchestrator', 'SmartAPI', 'DeviceBridge', 'IntelligenceKernel'],
  'Cognitive' => ['InstitutionalMemory', 'EthicalGuard', 'InstitutionalModel', 'PolicyEngine', 'AcademicReasoner', 'AdaptiveLearningEngine', 'HumanInteractionModel', 'InsightGenerator', 'CognitiveKernel'],
  'Ecosystem' => ['EcosystemKernel', 'TenantOrchestrator', 'FederationEngine', 'KnowledgeExchange', 'TrustBoundary', 'DeploymentManager', 'EcosystemAnalytics', 'ConsensusGuard'],
];

ob_start();
?>

<style>
  .mod-section {
    margin-bottom: 1.5rem;
  }

  .mod-section h3 {
    margin: 0 0 .8rem;
    font-size: 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid rgba(255, 255, 255, .08);
  }

  .mod-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
  }

  .mod-chip {
    padding: .35rem .8rem;
    border-radius: 6px;
    font-size: .8rem;
    font-weight: 500;
  }

  .mod-chip.loaded {
    background: rgba(0, 255, 65, .1);
    color: #00ff41;
    border: 1px solid rgba(0, 255, 65, .2);
  }

  .mod-chip.missing {
    background: rgba(255, 68, 68, .1);
    color: #ff4444;
    border: 1px solid rgba(255, 68, 68, .2);
  }

  .mod-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
  }

  .mod-stats .stat {
    text-align: center;
  }

  .mod-stats .stat .val {
    font-size: 2rem;
    font-weight: 700;
  }

  .mod-stats .stat .lbl {
    font-size: .75rem;
    opacity: .6;
  }
</style>

<?php
$totalClasses = 0;
$loadedClasses = 0;
foreach ($modules as $classes) {
  foreach ($classes as $cls) {
    $totalClasses++;
    if (class_exists($cls)) $loadedClasses++;
  }
}
?>

<div class="mod-stats">
  <div class="stat">
    <div class="val" style="color:#00ff41"><?= $loadedClasses ?></div>
    <div class="lbl">Loaded</div>
  </div>
  <div class="stat">
    <div class="val"><?= $totalClasses ?></div>
    <div class="lbl">Total</div>
  </div>
  <div class="stat">
    <div class="val" style="color:<?= $totalClasses - $loadedClasses > 0 ? '#ff4444' : '#00ff41' ?>"><?= $totalClasses - $loadedClasses ?></div>
    <div class="lbl">Missing</div>
  </div>
</div>

<?php foreach ($modules as $group => $classes): ?>
  <div class="mod-section">
    <h3><i class="fas fa-cube"></i> <?= htmlspecialchars($group) ?> (<?= count($classes) ?>)</h3>
    <div class="mod-grid">
      <?php foreach ($classes as $cls): ?>
        <span class="mod-chip <?= class_exists($cls) ? 'loaded' : 'missing' ?>">
          <?= htmlspecialchars($cls) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
