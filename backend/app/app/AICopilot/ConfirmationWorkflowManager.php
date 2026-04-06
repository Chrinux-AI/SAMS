<?php

/**
 * Confirmation Workflow Manager
 * Required for destructive actions. Uses signed session-bound tokens.
 */
class AICopilotConfirmationWorkflowManager
{
  private const SESSION_KEY = 'ai_copilot_confirmations';
  private const TTL_SECONDS = 300;

  /**
   * @param array<string,mixed> $intentPacket
   * @return array<string,mixed>
   */
  public function requireOrValidate(array $intentPacket, ?string $confirmationToken): array
  {
    $intent = (string)($intentPacket['intent'] ?? '');
    $meta = AICopilotActionRegistry::get($intent);
    if (!$meta) {
      return ['ok' => false, 'status' => 403, 'message' => 'Unregistered intent.'];
    }

    if (!(bool)($meta['destructive'] ?? false)) {
      return ['ok' => true, 'status' => 200, 'requires_confirmation' => false];
    }

    if ($confirmationToken) {
      return $this->verifyToken($intentPacket, $confirmationToken);
    }

    $token = $this->issueToken($intentPacket);
    return [
      'ok' => false,
      'status' => 409,
      'requires_confirmation' => true,
      'message' => 'This action is destructive and requires explicit confirmation.',
      'confirmation_token' => $token,
      'expires_in' => self::TTL_SECONDS
    ];
  }

  /**
   * @param array<string,mixed> $intentPacket
   */
  private function issueToken(array $intentPacket): string
  {
    if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
      $_SESSION[self::SESSION_KEY] = [];
    }

    $nonce = bin2hex(random_bytes(16));
    $hash = hash('sha256', json_encode([
      'intent' => $intentPacket['intent'] ?? '',
      'parameters' => $intentPacket['parameters'] ?? [],
      'user' => $_SESSION['user_id'] ?? 0,
      'tenant' => $_SESSION['tenant_id'] ?? 0,
      'nonce' => $nonce,
    ]));

    $_SESSION[self::SESSION_KEY][$hash] = [
      'intent' => $intentPacket['intent'] ?? '',
      'parameters' => $intentPacket['parameters'] ?? [],
      'created_at' => time(),
      'user_id' => (int)($_SESSION['user_id'] ?? 0),
      'tenant_id' => (int)($_SESSION['tenant_id'] ?? 0),
    ];

    return $hash;
  }

  /**
   * @param array<string,mixed> $intentPacket
   */
  private function verifyToken(array $intentPacket, string $token): array
  {
    $store = $_SESSION[self::SESSION_KEY] ?? [];
    $record = is_array($store) ? ($store[$token] ?? null) : null;

    if (!$record || !is_array($record)) {
      return ['ok' => false, 'status' => 409, 'message' => 'Invalid confirmation token.'];
    }

    if ((time() - (int)$record['created_at']) > self::TTL_SECONDS) {
      unset($_SESSION[self::SESSION_KEY][$token]);
      return ['ok' => false, 'status' => 409, 'message' => 'Confirmation token expired.'];
    }

    if ((int)$record['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
      return ['ok' => false, 'status' => 409, 'message' => 'Confirmation token user mismatch.'];
    }

    if ((int)$record['tenant_id'] !== (int)($_SESSION['tenant_id'] ?? 0)) {
      return ['ok' => false, 'status' => 409, 'message' => 'Confirmation token tenant mismatch.'];
    }

    if (($record['intent'] ?? '') !== ($intentPacket['intent'] ?? '')) {
      return ['ok' => false, 'status' => 409, 'message' => 'Confirmation token intent mismatch.'];
    }

    // one-time use
    unset($_SESSION[self::SESSION_KEY][$token]);

    return ['ok' => true, 'status' => 200, 'requires_confirmation' => false, 'confirmed' => true];
  }
}
