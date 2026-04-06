<?php

/**
 * AI Execution Controller
 * Orchestrates parser -> registry -> permission -> validation -> confirmation -> execution -> audit -> response.
 */
class AICopilotExecutionController
{
  private $parser;
  private $permission;
  private $validator;
  private $confirmation;
  private $gateway;
  private $audit;

  public function __construct()
  {
    $this->parser = new AICopilotIntentParser();
    $this->permission = new AICopilotPermissionEngine();
    $this->validator = new AICopilotValidationService();
    $this->confirmation = new AICopilotConfirmationWorkflowManager();
    $this->gateway = new AICopilotSecureApiGateway();
    $this->audit = new AICopilotAuditLoggingService();
  }

  /**
   * @param string $message
   * @param array<string,mixed> $explicitPayload
   * @return array<string,mixed>
   */
  public function handle(string $message, array $explicitPayload = []): array
  {
    $confirmationToken = isset($explicitPayload['confirmation_token']) && is_string($explicitPayload['confirmation_token'])
      ? $explicitPayload['confirmation_token']
      : null;

    $parsed = $this->parser->parse($message, $explicitPayload);
    if (!$parsed['ok']) {
      return $this->respondAndAudit($parsed, false, $parsed['error'] ?? 'Intent rejected', false);
    }

    $intentPacket = [
      'intent' => (string)$parsed['intent'],
      'parameters' => is_array($parsed['parameters'] ?? null) ? $parsed['parameters'] : []
    ];

    $perm = $this->permission->authorize($intentPacket);
    if (!$perm['allowed']) {
      return $this->respondAndAudit($intentPacket, false, (string)$perm['reason'], false, (int)$perm['status']);
    }

    $valid = $this->validator->validate($intentPacket);
    if (!$valid['ok']) {
      return $this->respondAndAudit($intentPacket, false, (string)$valid['message'], false, (int)$valid['status'], $valid['issues'] ?? []);
    }

    $confirm = $this->confirmation->requireOrValidate($intentPacket, $confirmationToken);
    if (!$confirm['ok']) {
      $res = [
        'success' => false,
        'status' => (int)$confirm['status'],
        'intent' => $intentPacket['intent'],
        'requires_confirmation' => (bool)($confirm['requires_confirmation'] ?? false),
        'confirmation_token' => $confirm['confirmation_token'] ?? null,
        'expires_in' => $confirm['expires_in'] ?? null,
        'message' => (string)($confirm['message'] ?? 'Confirmation required.')
      ];
      $this->audit->log([
        'tenant_id' => $perm['tenant_id'] ?? 0,
        'user_id' => $perm['user_id'] ?? 0,
        'role' => $perm['role'] ?? 'unknown',
        'intent' => $intentPacket['intent'],
        'parameters' => $intentPacket['parameters'],
        'status' => 'confirmation_required',
        'approval_confirmed' => false,
        'message' => $res['message']
      ]);
      return $res;
    }

    $exec = $this->gateway->execute($intentPacket);
    $ok = (bool)($exec['ok'] ?? false);

    return $this->respondAndAudit(
      $intentPacket,
      $ok,
      (string)($exec['message'] ?? ($ok ? 'Action completed.' : 'Action failed.')),
      (bool)($confirm['confirmed'] ?? false),
      (int)($exec['status'] ?? ($ok ? 200 : 500)),
      $exec['issues'] ?? [],
      $exec['data'] ?? []
    );
  }

  /**
   * @param array<string,mixed> $intentPacket
   * @param mixed $issues
   * @param mixed $data
   * @return array<string,mixed>
   */
  private function respondAndAudit(
    array $intentPacket,
    bool $success,
    string $message,
    bool $approvalConfirmed,
    int $status = 200,
    $issues = [],
    $data = []
  ): array {
    $role = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? 'unknown'));
    $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $this->audit->log([
      'tenant_id' => $tenantId,
      'user_id' => $userId,
      'role' => $role,
      'intent' => (string)($intentPacket['intent'] ?? 'UNKNOWN'),
      'parameters' => (array)($intentPacket['parameters'] ?? []),
      'status' => $success ? 'success' : 'failed',
      'approval_confirmed' => $approvalConfirmed,
      'message' => $message
    ]);

    return [
      'success' => $success,
      'status' => $status,
      'intent' => (string)($intentPacket['intent'] ?? 'UNKNOWN'),
      'message' => $message,
      'issues' => $issues,
      'data' => $data,
      'ui_refresh_hint' => true,
      'timestamp' => date('Y-m-d H:i:s')
    ];
  }
}
