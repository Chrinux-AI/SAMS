<?php

/**
 * ResourceManager — Digital Asset Management
 *
 * Manages documents, uploads, profile pictures, notices, storage quotas.
 * Provides audit and cleanup capabilities for digital resources.
 */
class ResourceManager
{
  /**
   * Audit all managed resources.
   */
  public static function audit(): array
  {
    return [
      'storage'   => self::storageAudit(),
      'uploads'   => self::uploadsAudit(),
      'documents' => self::documentsAudit(),
      'timestamp' => date('c'),
    ];
  }

  /**
   * Audit storage directory.
   */
  private static function storageAudit(): array
  {
    $dir = BASE_PATH . '/storage';
    if (!is_dir($dir)) {
      return ['status' => 'missing', 'files' => 0, 'size' => 0];
    }

    $files = 0;
    $size  = 0;
    $iter  = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
    $flat  = new \RecursiveIteratorIterator($iter);
    foreach ($flat as $file) {
      if ($file->isFile()) {
        $files++;
        $size += $file->getSize();
      }
    }

    return [
      'status' => 'ok',
      'files'  => $files,
      'size'   => self::formatBytes($size),
      'bytes'  => $size,
    ];
  }

  /**
   * Audit uploads directory.
   */
  private static function uploadsAudit(): array
  {
    $dir = BASE_PATH . '/uploads';
    if (!is_dir($dir)) {
      return ['status' => 'missing', 'files' => 0, 'size' => 0];
    }

    $files = 0;
    $size  = 0;
    $byType = [];

    $iter = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
    $flat = new \RecursiveIteratorIterator($iter);
    foreach ($flat as $file) {
      if ($file->isFile()) {
        $files++;
        $size += $file->getSize();
        $ext = strtolower($file->getExtension());
        $byType[$ext] = ($byType[$ext] ?? 0) + 1;
      }
    }

    return [
      'status'  => 'ok',
      'files'   => $files,
      'size'    => self::formatBytes($size),
      'bytes'   => $size,
      'by_type' => $byType,
    ];
  }

  /**
   * Audit document records in database.
   */
  private static function documentsAudit(): array
  {
    try {
      if (!table_exists('documents')) {
        return ['status' => 'no_table', 'count' => 0];
      }
      $count = db()->count('documents', '1=1');
      return ['status' => 'ok', 'count' => $count];
    } catch (\Throwable $e) {
      return ['status' => 'error', 'count' => 0];
    }
  }

  /**
   * Handle file upload securely.
   */
  public static function upload(array $file, string $subdir = 'general', array $allowedExts = []): array
  {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'error' => 'Upload error: ' . ($file['error'] ?? 'unknown')];
    }

    // Validate file size
    $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 10485760;
    if ($file['size'] > $maxSize) {
      return ['success' => false, 'error' => 'File too large'];
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = !empty($allowedExts) ? $allowedExts : (defined('ALLOWED_EXTENSIONS') ? ALLOWED_EXTENSIONS : ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
    if (!in_array($ext, $allowed, true)) {
      return ['success' => false, 'error' => 'File type not allowed'];
    }

    // Validate MIME type matches extension
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = [
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'pdf' => 'application/pdf',
      'doc' => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (isset($allowedMimes[$ext]) && $allowedMimes[$ext] !== $mimeType) {
      return ['success' => false, 'error' => 'MIME type mismatch'];
    }

    // Build safe path
    $safeSubdir = preg_replace('/[^a-zA-Z0-9_\-]/', '', $subdir);
    $uploadDir  = BASE_PATH . '/uploads/' . $safeSubdir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $safeFilename = bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath     = $uploadDir . '/' . $safeFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
      return ['success' => false, 'error' => 'Move failed'];
    }

    EventBus::dispatch('resources', 'file_uploaded', [
      'path'  => $safeSubdir . '/' . $safeFilename,
      'size'  => $file['size'],
      'type'  => $ext,
    ]);

    return [
      'success'  => true,
      'filename' => $safeFilename,
      'path'     => $safeSubdir . '/' . $safeFilename,
      'size'     => $file['size'],
    ];
  }

  /**
   * Clean orphaned uploads (not referenced in DB).
   */
  public static function cleanOrphans(): array
  {
    // Placeholder — in a real system this would cross-reference DB
    return ['cleaned' => 0, 'status' => 'ok'];
  }

  /**
   * Get resource stats for dashboard.
   */
  public static function getStats(): array
  {
    $audit = self::audit();
    return [
      'storage_files'  => $audit['storage']['files'] ?? 0,
      'storage_size'   => $audit['storage']['size'] ?? '0 B',
      'uploads_files'  => $audit['uploads']['files'] ?? 0,
      'uploads_size'   => $audit['uploads']['size'] ?? '0 B',
      'documents'      => $audit['documents']['count'] ?? 0,
    ];
  }

  /**
   * Format bytes to human readable.
   */
  private static function formatBytes(int $bytes): string
  {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = (int) floor(log($bytes, 1024));
    $i     = min($i, count($units) - 1);
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
  }
}
