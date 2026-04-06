<?php

/**
 * ACI — Command API
 * Structured API interface for MCC and external consumers.
 */
class CommandAPI
{
  /**
   * Handle API requests routed from api/aci/*.php endpoints.
   */
  public static function handle(string $endpoint, array $params = []): array
  {
    switch ($endpoint) {
      case 'status':
        return ['ok' => true, 'data' => CommandBrain::getStatus()];

      case 'recommendations':
        return ['ok' => true, 'data' => CommandBrain::getRecommendations()];

      case 'execute':
        $action = $params['action'] ?? '';
        if (!$action) {
          return ['ok' => false, 'error' => 'Missing action parameter'];
        }
        $result = CommandBrain::executeManual($action, $params);
        return ['ok' => $result['success'], 'data' => $result];

      case 'cycle':
        $result = CommandBrain::cycle();
        return ['ok' => true, 'data' => $result];

      case 'learning':
        return ['ok' => true, 'data' => LearningMemory::getAll()];

      default:
        return ['ok' => false, 'error' => "Unknown endpoint: $endpoint"];
    }
  }
}
