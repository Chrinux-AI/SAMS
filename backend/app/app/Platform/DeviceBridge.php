<?php

/**
 * DeviceBridge — Hardware Communication Layer
 *
 * Allows SAMS to communicate with external devices:
 *   biometric scanners, smart attendance devices,
 *   classroom tablets, IoT sensors
 *
 * Protocol-ready: REST, WebSocket-ready, MQTT-ready architecture.
 * Current implementation: REST polling + event queue.
 */
class DeviceBridge
{
  /** Registered device types */
  private const DEVICE_TYPES = [
    'biometric_scanner',
    'attendance_terminal',
    'classroom_tablet',
    'iot_sensor',
    'smart_display',
  ];

  /**
   * Ensure device tables exist.
   */
  public static function ensureTables(): void
  {
    try {
      $pdo = db()->getConnection();

      $pdo->exec("CREATE TABLE IF NOT EXISTS bridge_devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(100) NOT NULL UNIQUE,
        device_type VARCHAR(50) NOT NULL,
        label VARCHAR(255) DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        status ENUM('online','offline','error') DEFAULT 'offline',
        last_heartbeat TIMESTAMP NULL DEFAULT NULL,
        config JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type (device_type),
        INDEX idx_status (status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $pdo->exec("CREATE TABLE IF NOT EXISTS bridge_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(100) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        payload JSON DEFAULT NULL,
        processed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device (device_id),
        INDEX idx_unprocessed (processed, created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'DeviceBridge table creation failed: ' . $e->getMessage(), 'HIGH');
    }
  }

  /**
   * Register a new device.
   */
  public static function registerDevice(string $deviceId, string $type, string $label = '', string $location = '', array $config = []): bool
  {
    if (!in_array($type, self::DEVICE_TYPES)) return false;

    try {
      self::ensureTables();
      $existing = db()->fetchOne("SELECT id FROM bridge_devices WHERE device_id = ?", [$deviceId]);
      if ($existing) {
        db()->query(
          "UPDATE bridge_devices SET device_type = ?, label = ?, location = ?, config = ?, status = 'offline' WHERE device_id = ?",
          [$type, $label, $location, json_encode($config), $deviceId]
        );
      } else {
        db()->query(
          "INSERT INTO bridge_devices (device_id, device_type, label, location, config) VALUES (?, ?, ?, ?, ?)",
          [$deviceId, $type, $label, $location, json_encode($config)]
        );
      }
      ErrorCollector::log('platform', "Device registered: {$deviceId} ({$type})", 'INFO');
      return true;
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Device registration failed: ' . $e->getMessage(), 'HIGH');
      return false;
    }
  }

  /**
   * Record a heartbeat from a device.
   */
  public static function heartbeat(string $deviceId): bool
  {
    try {
      self::ensureTables();
      $stmt = db()->query(
        "UPDATE bridge_devices SET status = 'online', last_heartbeat = NOW() WHERE device_id = ?",
        [$deviceId]
      );
      return $stmt && $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Push an event from a device into the event queue.
   *
   * @param string $deviceId   Source device
   * @param string $eventType  e.g. 'attendance_scan', 'sensor_reading'
   * @param array  $payload    Event data
   */
  public static function pushEvent(string $deviceId, string $eventType, array $payload = []): bool
  {
    try {
      self::ensureTables();

      // Validate device exists
      $device = db()->fetchOne("SELECT id, status FROM bridge_devices WHERE device_id = ?", [$deviceId]);
      if (!$device) return false;

      db()->query(
        "INSERT INTO bridge_events (device_id, event_type, payload) VALUES (?, ?, ?)",
        [$deviceId, $eventType, json_encode($payload)]
      );

      // Auto-process attendance scans
      if ($eventType === 'attendance_scan') {
        self::processAttendanceScan($deviceId, $payload);
      }

      return true;
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Device event push failed: ' . $e->getMessage(), 'MEDIUM');
      return false;
    }
  }

  /**
   * Process unhandled events from the queue.
   */
  public static function processQueue(int $limit = 50): array
  {
    $processed = 0;
    $errors = 0;

    try {
      self::ensureTables();
      $events = db()->fetchAll(
        "SELECT * FROM bridge_events WHERE processed = 0 ORDER BY created_at ASC LIMIT ?",
        [$limit]
      );

      foreach ($events as $event) {
        try {
          $payload = json_decode($event['payload'] ?? '{}', true) ?: [];

          switch ($event['event_type']) {
            case 'attendance_scan':
              self::processAttendanceScan($event['device_id'], $payload);
              break;
            case 'sensor_reading':
              // Store for analytics
              ErrorCollector::log('platform', "Sensor reading from {$event['device_id']}", 'INFO');
              break;
          }

          db()->query("UPDATE bridge_events SET processed = 1 WHERE id = ?", [$event['id']]);
          $processed++;
        } catch (\Throwable $e) {
          $errors++;
        }
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Queue processing error: ' . $e->getMessage(), 'HIGH');
    }

    return ['processed' => $processed, 'errors' => $errors];
  }

  /**
   * Process an attendance scan event.
   */
  private static function processAttendanceScan(string $deviceId, array $payload): void
  {
    $studentId = $payload['student_id'] ?? null;
    $classId = $payload['class_id'] ?? null;

    if (!$studentId) return;

    try {
      // Check if attendance already marked today
      $existing = db()->fetchOne(
        "SELECT id FROM attendance WHERE student_id = ? AND date = CURDATE()" .
          ($classId ? " AND class_id = ?" : ""),
        $classId ? [$studentId, $classId] : [$studentId]
      );

      if (!$existing) {
        $data = [
          'student_id' => $studentId,
          'date'       => date('Y-m-d'),
          'status'     => 'present',
          'check_in_time' => date('H:i:s'),
        ];
        if ($classId) $data['class_id'] = $classId;

        db()->insert('attendance', $data);
        ErrorCollector::log('platform', "Biometric attendance: student #{$studentId} from device {$deviceId}", 'INFO');
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Attendance scan processing failed: ' . $e->getMessage(), 'MEDIUM');
    }
  }

  /**
   * Get all registered devices.
   */
  public static function getDevices(): array
  {
    try {
      self::ensureTables();
      return db()->fetchAll("SELECT * FROM bridge_devices ORDER BY status DESC, label ASC");
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Check for offline devices (no heartbeat in 5 minutes).
   */
  public static function checkOfflineDevices(): array
  {
    $offline = [];
    try {
      self::ensureTables();
      // Mark devices as offline if no heartbeat in 5 minutes
      db()->query(
        "UPDATE bridge_devices SET status = 'offline'
         WHERE status = 'online' AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
      );

      $offline = db()->fetchAll(
        "SELECT device_id, label, device_type, last_heartbeat
         FROM bridge_devices WHERE status = 'offline' AND last_heartbeat IS NOT NULL
         ORDER BY last_heartbeat DESC"
      );
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $offline;
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    try {
      self::ensureTables();
      $total = db()->fetchOne("SELECT COUNT(*) AS cnt FROM bridge_devices");
      $online = db()->fetchOne("SELECT COUNT(*) AS cnt FROM bridge_devices WHERE status = 'online'");
      $pending = db()->fetchOne("SELECT COUNT(*) AS cnt FROM bridge_events WHERE processed = 0");
      return [
        'total_devices'  => (int) ($total['cnt'] ?? 0),
        'online'         => (int) ($online['cnt'] ?? 0),
        'pending_events' => (int) ($pending['cnt'] ?? 0),
      ];
    } catch (\Throwable $e) {
      return ['total_devices' => 0, 'online' => 0, 'pending_events' => 0];
    }
  }
}
