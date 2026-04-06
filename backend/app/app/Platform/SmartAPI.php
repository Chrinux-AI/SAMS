<?php

/**
 * SmartAPI — Intelligence-Driven API Layer
 *
 * Exposes platform intelligence as secure JSON endpoints:
 *   /api/intelligence/health
 *   /api/intelligence/predictions
 *   /api/intelligence/context
 *   /api/intelligence/recommendations
 *
 * External systems can integrate safely via API key auth.
 */
class SmartAPI
{
  /**
   * Handle an incoming API request.
   *
   * @param string $endpoint  e.g. 'health', 'predictions', 'context', 'recommendations', 'metrics', 'graph'
   * @param array  $params    Request parameters
   * @return array  Response payload
   */
  public static function handle(string $endpoint, array $params = []): array
  {
    try {
      switch ($endpoint) {
        case 'health':
          return self::healthEndpoint();
        case 'predictions':
          return self::predictionsEndpoint();
        case 'context':
          return self::contextEndpoint();
        case 'recommendations':
          return self::recommendationsEndpoint();
        case 'metrics':
          return self::metricsEndpoint($params);
        case 'graph':
          return self::graphEndpoint($params);
        case 'decisions':
          return self::decisionsEndpoint($params);
        case 'workflows':
          return self::workflowsEndpoint();
        case 'status':
          return self::statusEndpoint();
        default:
          return ['error' => 'Unknown endpoint', 'available' => self::getAvailableEndpoints()];
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'SmartAPI error on /' . $endpoint . ': ' . $e->getMessage(), 'HIGH');
      return ['error' => 'Internal API error'];
    }
  }

  /**
   * Authenticate an API request.
   *
   * @param string $apiKey  Provided API key
   * @return bool  Whether the key is valid
   */
  public static function authenticate(string $apiKey): bool
  {
    if (empty($apiKey)) return false;

    // Check against configured API key
    $configuredKey = defined('INTELLIGENCE_API_KEY') ? INTELLIGENCE_API_KEY : '';
    if (!empty($configuredKey) && hash_equals($configuredKey, $apiKey)) {
      return true;
    }

    // Also allow admin session-based access
    if (function_exists('is_logged_in') && is_logged_in()) {
      $role = $_SESSION['role'] ?? '';
      return in_array($role, ['admin', 'super_admin', 'developer']);
    }

    return false;
  }

  /**
   * Get list of available endpoints.
   */
  public static function getAvailableEndpoints(): array
  {
    return [
      'health'          => 'System health overview',
      'predictions'     => 'AI-generated forecasts',
      'context'         => 'Current operational context',
      'recommendations' => 'Intelligent recommendations',
      'metrics'         => 'System metrics (param: hours)',
      'graph'           => 'Knowledge graph stats',
      'decisions'       => 'Recent AI decisions',
      'workflows'       => 'Available workflows',
      'status'          => 'Platform intelligence status',
    ];
  }

  // ── Endpoints ──

  private static function healthEndpoint(): array
  {
    $devopsLast = DevOpsKernel::getLastRun();
    $incidents = IncidentResponder::getSummary();

    return [
      'system_score'    => $devopsLast['system_score'] ?? 0,
      'health_score'    => $devopsLast['health_score'] ?? 0,
      'security_score'  => $devopsLast['security_score'] ?? 0,
      'deployment_safe' => $devopsLast['deployment_safe'] ?? true,
      'safe_mode'       => $incidents['safe_mode'] ?? false,
      'incidents'       => $incidents['incident_count'] ?? 0,
      'uptime'          => self::getUptime(),
      'timestamp'       => date('c'),
    ];
  }

  private static function predictionsEndpoint(): array
  {
    $result = PredictionEngine::predict();
    return [
      'risk_level'  => $result['risk_level'],
      'predictions' => array_map(fn($p) => [
        'type'       => $p['type'],
        'severity'   => $p['severity'],
        'detail'     => $p['detail'],
        'confidence' => $p['confidence'],
        'timeframe'  => $p['timeframe'] ?? null,
      ], $result['predictions']),
      'count'       => count($result['predictions']),
      'generated'   => $result['generated'],
    ];
  }

  private static function contextEndpoint(): array
  {
    $result = ContextEngine::evaluate();
    return [
      'primary'  => $result['primary'],
      'contexts' => array_map(fn($c) => [
        'type'       => $c['type'],
        'label'      => $c['label'],
        'severity'   => $c['severity'],
        'confidence' => $c['confidence'],
      ], $result['contexts']),
      'signals'  => $result['signals'],
      'total'    => $result['total'],
    ];
  }

  private static function recommendationsEndpoint(): array
  {
    $decisions = DecisionEngine::decide();
    $recommendations = [];

    foreach ($decisions['decisions'] as $d) {
      if ($d['status'] === 'recommended' || $d['status'] === 'vetoed') {
        $recommendations[] = [
          'action'    => $d['action'],
          'type'      => $d['action_type'],
          'risk'      => $d['risk_score'],
          'reasoning' => $d['reasoning'],
          'status'    => $d['status'],
        ];
      }
    }

    return [
      'recommendations' => $recommendations,
      'auto_executed'   => $decisions['actions_taken'],
      'vetoed'          => $decisions['vetoed'],
      'context'         => $decisions['context'],
    ];
  }

  private static function metricsEndpoint(array $params): array
  {
    $hours = min(168, max(1, (int) ($params['hours'] ?? 24)));
    $metrics = ResourceMonitor::getLatestAll();

    return [
      'current'   => $metrics,
      'period'    => "{$hours} hours",
      'timestamp' => date('c'),
    ];
  }

  private static function graphEndpoint(array $params): array
  {
    return KnowledgeGraph::getStats();
  }

  private static function decisionsEndpoint(array $params): array
  {
    $limit = min(50, max(5, (int) ($params['limit'] ?? 20)));
    $decisions = DecisionEngine::getRecentDecisions($limit);
    $summary = DecisionEngine::getSummary();

    return [
      'summary'   => $summary,
      'recent'    => array_map(fn($d) => [
        'signal_type'  => $d['signal_type'],
        'action'       => $d['action_taken'],
        'reasoning'    => $d['reasoning'],
        'risk_score'   => (float) $d['risk_score'],
        'outcome'      => $d['outcome'],
        'created_at'   => $d['created_at'],
      ], $decisions),
    ];
  }

  private static function workflowsEndpoint(): array
  {
    return [
      'available' => WorkflowOrchestrator::getAvailable(),
      'history'   => array_slice(WorkflowOrchestrator::getHistory(10), 0, 10),
    ];
  }

  private static function statusEndpoint(): array
  {
    return [
      'platform'     => 'SAMS Platform Intelligence Layer',
      'version'      => '1.0.0',
      'engines'      => [
        'knowledge_graph'      => 'active',
        'context_engine'       => 'active',
        'behavior_analyzer'    => 'active',
        'prediction_engine'    => 'active',
        'decision_engine'      => 'active',
        'workflow_orchestrator' => 'active',
        'device_bridge'        => 'standby',
      ],
      'api_endpoints' => count(self::getAvailableEndpoints()),
      'uptime'        => self::getUptime(),
      'timestamp'     => date('c'),
    ];
  }

  /**
   * Get system uptime.
   */
  private static function getUptime(): string
  {
    $uptime = time() - (int) ($_SERVER['REQUEST_TIME'] ?? time());
    if ($uptime < 60) return "{$uptime}s";
    if ($uptime < 3600) return round($uptime / 60) . 'm';
    return round($uptime / 3600, 1) . 'h';
  }
}
