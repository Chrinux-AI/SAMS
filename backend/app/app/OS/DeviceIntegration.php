<?php

/**
 * DeviceIntegration — Smart School Device APIs
 *
 * Manages integration with biometric scanners, tablets, kiosks,
 * and other IoT devices. Provides sync endpoints and device registry.
 */
class DeviceIntegration
{
  /**
   * Get sync status of all registered devices.
   */
  public static function syncStatus(): array
  {
    $devices = self::getRegisteredDevices();
    $online  = 0;
    $offline = 0;

    foreach ($devices as $d) {
      $lastSeen = strtotime($d['last_sync'] ?? '2000-01-01');
      if ((time() - $lastSeen) < 300) {
        $online++;
      } else {
        $offline++;
      }
    }

    return [
      'total'   => count($devices),
      'online'  => $online,
      'offline' => $offline,
    ];
  }

  /**
   * Register a new device.
   */
  public static function registerDevice(string $deviceId, string $type, string $name, array $meta = []): array
  {
    try {
      self::ensureTable();

      // Check for duplicate
      $existing = db()->fetchOne("SELECT id FROM devices WHERE device_id = ?", [$deviceId]);
      if ($existing) {
        return ['success' => false, 'error' => 'Device already registered'];
      }

      db()->insert('devices', [
        'device_id'  => $deviceId,
        'type'       => $type,
        'name'       => $name,
        'meta'       => json_encode($meta),
        'status'     => 'active',
        'last_sync'  => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
      ]);

      EventBus::dispatch('devices', 'device_registered', [
        'device_id' => $deviceId,
        'type'      => $type,
        'name'      => $name,
      ]);

      return ['success' => true];
    } catch (\Throwable $e) {
      ErrorCollector::log('device_integration', 'Register failed: ' . $e->getMessage(), 'MEDIUM');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Sync data from a device (e.g., biometric attendance).
   */
  public static function syncDevice(string $deviceId, array $data): array
  {
    try {
      self::ensureTable();

      // Update last sync time
      $pdo = db()->getConnection();
      $stmt = $pdo->prepare("UPDATE devices SET last_sync = NOW() WHERE device_id = ?");
      $stmt->execute([$deviceId]);

      // Process sync data based on type
      $device = db()->fetchOne("SELECT * FROM devices WHERE device_id = ?", [$deviceId]);
      if (!$device) {
        return ['success' => false, 'error' => 'Device not found'];
      }

      $processed = 0;
      $type = $device['type'];

      if ($type === 'biometric' && isset($data['attendance'])) {
        foreach ($data['attendance'] as $record) {
          $studentId = (int) ($record['student_id'] ?? 0);
          $status  = $record['status'] ?? 'present';
          $classId = (int) ($record['class_id'] ?? 0);
          if ($studentId && $classId) {
            AcademicRuntime::recordAttendance($studentId, $status, $classId);
            $processed++;
          }
        }
      }

      EventBus::dispatch('devices', 'device_synced', [
        'device_id' => $deviceId,
        'type'      => $type,
        'records'   => $processed,
      ]);

      return ['success' => true, 'processed' => $processed];
    } catch (\Throwable $e) {
      ErrorCollector::log('device_integration', 'Sync failed: ' . $e->getMessage(), 'MEDIUM');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Get all registered devices.
   */
  public static function getRegisteredDevices(): array
  {
    try {
      if (!table_exists('devices')) return [];
      return db()->fetchAll("SELECT * FROM devices ORDER BY last_sync DESC");
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get device stats.
   */
  public static function getStats(): array
  {
    $devices = self::getRegisteredDevices();
    $byType = [];
    foreach ($devices as $d) {
      $t = $d['type'] ?? 'unknown';
      $byType[$t] = ($byType[$t] ?? 0) + 1;
    }
    return [
      'total'   => count($devices),
      'by_type' => $byType,
    ];
  }

  /**
   * Ensure device table exists.
   */
  private static function ensureTable(): void
  {
    if (table_exists('devices')) return;
    $pdo = db()->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS devices (
      id INT AUTO_INCREMENT PRIMARY KEY,
      device_id VARCHAR(100) NOT NULL UNIQUE,
      type VARCHAR(50) NOT NULL DEFAULT 'generic',
      name VARCHAR(255) NOT NULL,
      meta JSON,
      status VARCHAR(20) DEFAULT 'active',
      last_sync DATETIME DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_dev_type (type),
      INDEX idx_dev_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}
