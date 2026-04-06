<?php

/**
 * TrustBoundary — Zero-Trust Institutional Federation Security
 *
 * Enforces:
 *   - Zero raw data export
 *   - Encrypted federation traffic
 *   - Signed pattern exchange
 *   - Tenant identity verification
 */
class TrustBoundary
{
  private static string $signatureAlgo = 'sha256';

  /**
   * Validate that data is safe for federation (no raw records).
   */
  public static function validateExport(array $data): array
  {
    $violations = [];

    // Block raw student/staff data
    $blocked = [
      'student_name',
      'email',
      'phone',
      'password',
      'address',
      'ssn',
      'national_id',
      'guardian_name',
      'ip_address',
      'token',
      'session_id',
      'api_key',
      'secret'
    ];

    $flat = json_encode($data);
    foreach ($blocked as $field) {
      if (stripos($flat, $field) !== false) {
        $violations[] = "Blocked field detected: {$field}";
      }
    }

    // Block if data contains identifiable record counts below threshold
    if (isset($data['records']) && is_array($data['records'])) {
      if (count($data['records']) < 5) {
        $violations[] = 'Too few records — re-identification risk';
      }
    }

    return [
      'safe'       => empty($violations),
      'violations' => $violations,
    ];
  }

  /**
   * Sign a pattern payload for federation exchange.
   */
  public static function signPayload(array $payload, string $key): string
  {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    return hash_hmac(self::$signatureAlgo, $json, $key);
  }

  /**
   * Verify a signed pattern payload.
   */
  public static function verifySignature(array $payload, string $signature, string $key): bool
  {
    $expected = self::signPayload($payload, $key);
    return hash_equals($expected, $signature);
  }

  /**
   * Verify tenant identity token.
   */
  public static function verifyTenant(int $tenantId, string $token): bool
  {
    try {
      $row = db()->fetchOne(
        "SELECT id, encryption_key, status FROM tenants WHERE id = ? AND status = 'active'",
        [$tenantId]
      );
      if (!$row) return false;

      $expected = hash_hmac('sha256', (string)$tenantId, $row['encryption_key']);
      return hash_equals($expected, $token);
    } catch (\Throwable $e) {
      ErrorCollector::log('ecosystem', 'Tenant verification error: ' . $e->getMessage(), 'HIGH');
      return false;
    }
  }

  /**
   * Generate a tenant identity token.
   */
  public static function generateTenantToken(int $tenantId, string $encryptionKey): string
  {
    return hash_hmac('sha256', (string)$tenantId, $encryptionKey);
  }

  /**
   * Encrypt data for federation transport.
   */
  public static function encryptForTransport(array $data, string $key): string
  {
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($json, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
  }

  /**
   * Decrypt federation transport data.
   */
  public static function decryptFromTransport(string $encrypted, string $key): ?array
  {
    $raw = base64_decode($encrypted);
    $parts = explode('::', $raw, 2);
    if (count($parts) !== 2) return null;

    $iv = $parts[0];
    $decrypted = openssl_decrypt($parts[1], 'aes-256-cbc', $key, 0, $iv);
    if ($decrypted === false) return null;

    return json_decode($decrypted, true);
  }

  /**
   * Full federation safety check.
   */
  public static function federationCheck(array $payload, int $fromTenant, string $token, string $signature): array
  {
    // 1. Verify tenant identity
    if (!self::verifyTenant($fromTenant, $token)) {
      return ['allowed' => false, 'reason' => 'Tenant identity verification failed'];
    }

    // 2. Validate export safety
    $exportCheck = self::validateExport($payload);
    if (!$exportCheck['safe']) {
      return ['allowed' => false, 'reason' => 'Data safety violation: ' . implode('; ', $exportCheck['violations'])];
    }

    // 3. Verify payload signature
    $tenant = db()->fetchOne("SELECT encryption_key FROM tenants WHERE id = ?", [$fromTenant]);
    if (!$tenant || !self::verifySignature($payload, $signature, $tenant['encryption_key'])) {
      return ['allowed' => false, 'reason' => 'Payload signature verification failed'];
    }

    return ['allowed' => true, 'reason' => 'All checks passed'];
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    return [
      'model'          => 'Zero-Trust Institutional Federation',
      'encryption'     => 'AES-256-CBC',
      'signing'        => 'HMAC-SHA256',
      'blocked_fields' => 12,
      'status'         => 'active',
    ];
  }
}
