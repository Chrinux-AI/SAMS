<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class AIBackupChecker
{
  private $backupDir;

  public function __construct()
  {
    $this->backupDir = __DIR__ . '/../backups/';
  }

  /**
   * Scan /backups/ directory and return list of backup files with size and date
   */
  public function checkBackupStatus()
  {
    try {
      $files = glob($this->backupDir . '*.{sql,zip,gz,tar}', GLOB_BRACE);
      if ($files === false) {
        return ['status' => 'error', 'message' => 'Unable to scan backups directory', 'files' => []];
      }

      $backups = [];
      foreach ($files as $file) {
        $backups[] = [
          'filename' => basename($file),
          'size' => filesize($file),
          'size_formatted' => $this->formatBytes(filesize($file)),
          'modified' => date('Y-m-d H:i:s', filemtime($file)),
          'type' => pathinfo($file, PATHINFO_EXTENSION)
        ];
      }

      // Sort by modified date descending
      usort($backups, function ($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
      });

      return [
        'status' => count($backups) > 0 ? 'ok' : 'warning',
        'total_files' => count($backups),
        'files' => $backups
      ];
    } catch (Exception $e) {
      error_log("AIBackupChecker::checkBackupStatus error: " . $e->getMessage());
      return ['status' => 'error', 'message' => $e->getMessage(), 'files' => []];
    }
  }

  /**
   * Get the most recent backup file modification time
   */
  public function getLastBackupTime()
  {
    try {
      $files = glob($this->backupDir . '*.{sql,zip,gz,tar}', GLOB_BRACE);
      if (!$files || count($files) === 0) {
        return ['last_backup' => null, 'message' => 'No backup files found'];
      }

      $latestTime = 0;
      $latestFile = '';
      foreach ($files as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latestTime) {
          $latestTime = $mtime;
          $latestFile = basename($file);
        }
      }

      $hoursAgo = round((time() - $latestTime) / 3600, 1);

      return [
        'last_backup' => date('Y-m-d H:i:s', $latestTime),
        'filename' => $latestFile,
        'hours_ago' => $hoursAgo
      ];
    } catch (Exception $e) {
      error_log("AIBackupChecker::getLastBackupTime error: " . $e->getMessage());
      return ['last_backup' => null, 'error' => $e->getMessage()];
    }
  }

  /**
   * Verify a backup file exists, has size > 0, and is readable
   */
  public function verifyBackupIntegrity($filename)
  {
    try {
      // Prevent directory traversal
      $filename = basename($filename);
      $filePath = $this->backupDir . $filename;

      $checks = [
        'exists' => file_exists($filePath),
        'readable' => is_readable($filePath),
        'size_ok' => false,
        'size' => 0
      ];

      if ($checks['exists']) {
        $size = filesize($filePath);
        $checks['size'] = $size;
        $checks['size_ok'] = $size > 0;
        $checks['size_formatted'] = $this->formatBytes($size);
      }

      $checks['valid'] = $checks['exists'] && $checks['readable'] && $checks['size_ok'];
      $checks['filename'] = $filename;

      return $checks;
    } catch (Exception $e) {
      error_log("AIBackupChecker::verifyBackupIntegrity error: " . $e->getMessage());
      return ['valid' => false, 'filename' => $filename, 'error' => $e->getMessage()];
    }
  }

  /**
   * Return status/message based on last backup age
   */
  public function getBackupRecommendation()
  {
    try {
      $lastBackup = $this->getLastBackupTime();

      if ($lastBackup['last_backup'] === null) {
        return [
          'status' => 'critical',
          'message' => 'No backups found. Create a backup immediately.',
          'action_required' => true
        ];
      }

      $hoursAgo = $lastBackup['hours_ago'];

      if ($hoursAgo > 72) {
        return [
          'status' => 'critical',
          'message' => "Last backup was {$hoursAgo} hours ago. Immediate backup required.",
          'last_backup' => $lastBackup['last_backup'],
          'action_required' => true
        ];
      }

      if ($hoursAgo > 24) {
        return [
          'status' => 'warning',
          'message' => "Last backup was {$hoursAgo} hours ago. Consider running a backup soon.",
          'last_backup' => $lastBackup['last_backup'],
          'action_required' => true
        ];
      }

      return [
        'status' => 'good',
        'message' => "Backups are up to date. Last backup: {$hoursAgo} hours ago.",
        'last_backup' => $lastBackup['last_backup'],
        'action_required' => false
      ];
    } catch (Exception $e) {
      error_log("AIBackupChecker::getBackupRecommendation error: " . $e->getMessage());
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }

  /**
   * Calculate total size of /backups/ directory
   */
  public function calculateStorageUsage()
  {
    try {
      $files = glob($this->backupDir . '*.{sql,zip,gz,tar}', GLOB_BRACE);
      $totalSize = 0;
      $fileCount = 0;

      if ($files) {
        foreach ($files as $file) {
          $totalSize += filesize($file);
          $fileCount++;
        }
      }

      return [
        'total_size' => $totalSize,
        'total_size_formatted' => $this->formatBytes($totalSize),
        'file_count' => $fileCount,
        'directory' => $this->backupDir
      ];
    } catch (Exception $e) {
      error_log("AIBackupChecker::calculateStorageUsage error: " . $e->getMessage());
      return ['total_size' => 0, 'error' => $e->getMessage()];
    }
  }

  private function formatBytes($bytes)
  {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
  }
}
