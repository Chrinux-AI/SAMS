<?php

/**
 * MediaHandler — Chat Attachment Handling
 *
 * Secure file upload and retrieval for chat attachments.
 * Validates file types, sizes, and stores with safe filenames.
 */
class MediaHandler
{
  private static string $uploadDir = '';

  private static function init(): void
  {
    if (!self::$uploadDir) {
      self::$uploadDir = BASE_PATH . '/uploads/chat';
    }
  }

  /**
   * Upload a chat attachment.
   */
  public static function upload(array $file, int $senderId, int $conversationId): array
  {
    self::init();

    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'error' => 'Upload error'];
    }

    // Max 5MB for chat attachments
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
      return ['success' => false, 'error' => 'File too large (max 5MB)'];
    }

    // Allowed types for chat
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
      return ['success' => false, 'error' => 'File type not allowed'];
    }

    // Validate MIME type
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $safeMimes = [
      'image/jpeg',
      'image/png',
      'image/gif',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'text/plain',
    ];
    if (!in_array($mimeType, $safeMimes, true)) {
      return ['success' => false, 'error' => 'Invalid MIME type'];
    }

    // Create directory
    if (!is_dir(self::$uploadDir)) {
      mkdir(self::$uploadDir, 0755, true);
    }

    // Generate safe filename
    $safeName = bin2hex(random_bytes(12)) . '.' . $ext;
    $destPath = self::$uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
      return ['success' => false, 'error' => 'File move failed'];
    }

    // Determine message type
    $messageType = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file';

    $attachmentUrl = 'uploads/chat/' . $safeName;

    EventBus::dispatch('chat', 'media_uploaded', [
      'sender_id'       => $senderId,
      'conversation_id' => $conversationId,
      'type'            => $messageType,
      'size'            => $file['size'],
    ]);

    return [
      'success'        => true,
      'filename'       => $safeName,
      'attachment_url'  => $attachmentUrl,
      'message_type'   => $messageType,
      'original_name'  => basename($file['name']),
      'size'           => $file['size'],
      'mime'           => $mimeType,
    ];
  }

  /**
   * Get attachment info.
   */
  public static function getInfo(string $attachmentUrl): array
  {
    self::init();
    $fullPath = BASE_PATH . '/' . $attachmentUrl;
    if (!is_file($fullPath)) {
      return ['exists' => false];
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    return [
      'exists' => true,
      'size'   => filesize($fullPath),
      'type'   => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file',
      'ext'    => $ext,
    ];
  }

  /**
   * Delete an attachment.
   */
  public static function delete(string $attachmentUrl): bool
  {
    $fullPath = BASE_PATH . '/' . $attachmentUrl;
    // Ensure path is within uploads/chat
    $realPath = realpath($fullPath);
    $realBase = realpath(BASE_PATH . '/uploads/chat');
    if ($realPath && $realBase && strpos($realPath, $realBase) === 0 && is_file($realPath)) {
      return unlink($realPath);
    }
    return false;
  }

  /**
   * Get storage stats.
   */
  public static function getStats(): array
  {
    self::init();
    if (!is_dir(self::$uploadDir)) {
      return ['files' => 0, 'size' => 0];
    }

    $files = 0;
    $size  = 0;
    foreach (new \DirectoryIterator(self::$uploadDir) as $f) {
      if ($f->isFile()) {
        $files++;
        $size += $f->getSize();
      }
    }

    return ['files' => $files, 'size' => $size];
  }
}
