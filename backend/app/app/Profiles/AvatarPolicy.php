<?php

/**
 * Avatar Policy — Validates avatar uploads against security rules.
 */
class AvatarPolicy
{
  private const MAX_SIZE = 2 * 1024 * 1024;  // 2MB
  private const MAX_DIMENSION = 4096;          // Max px in any direction
  private const MIN_DIMENSION = 50;            // Min px
  private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
  private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

  /**
   * Validate an uploaded file against all policies.
   * Returns ['valid' => bool, 'errors' => string[]]
   */
  public static function validate(array $file): array
  {
    $errors = [];

    // Check upload error
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
      $errors[] = self::getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
      return ['valid' => false, 'errors' => $errors];
    }

    // File size
    if ($file['size'] > self::MAX_SIZE) {
      $errors[] = 'File size exceeds 2MB limit.';
    }

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
      $errors[] = 'Invalid file extension. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS);
    }

    // MIME type check via fileinfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, self::ALLOWED_MIMES, true)) {
      $errors[] = 'Invalid file type detected. Allowed: JPEG, PNG, WebP.';
    }

    // Verify it's a real image
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
      $errors[] = 'File is not a valid image.';
    } else {
      // Dimension checks
      if ($imageInfo[0] > self::MAX_DIMENSION || $imageInfo[1] > self::MAX_DIMENSION) {
        $errors[] = 'Image is too large. Maximum ' . self::MAX_DIMENSION . 'px in any direction.';
      }
      if ($imageInfo[0] < self::MIN_DIMENSION || $imageInfo[1] < self::MIN_DIMENSION) {
        $errors[] = 'Image is too small. Minimum ' . self::MIN_DIMENSION . 'px.';
      }
    }

    // Check for PHP code embedded in image (polyglot attack)
    $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if ($content !== false && preg_match('/<\?php|<\?=/i', $content)) {
      $errors[] = 'File contains invalid content.';
    }

    return ['valid' => empty($errors), 'errors' => $errors];
  }

  /**
   * Translate PHP upload error code to human message.
   */
  private static function getUploadErrorMessage(int $code): string
  {
    return match ($code) {
      UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
      UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
      UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
      UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
      UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory missing.',
      UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
      UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
      default               => 'Unknown upload error.',
    };
  }
}
