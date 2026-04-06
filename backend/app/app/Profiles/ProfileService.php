<?php

/**
 * Profile Service — Manages user profiles, avatars, and preferences.
 *
 * Upload flow: Upload → Validate → Resize → Compress → Store → Broadcast Update
 * Storage: uploads/profiles/{role}/{userId}.jpg
 */
class ProfileService
{
  private const AVATAR_MAX_SIZE = 2 * 1024 * 1024; // 2MB
  private const AVATAR_DIMENSION = 400;             // 400x400px
  private const AVATAR_QUALITY = 85;                // JPEG quality
  private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

  /**
   * Get a user's full profile data.
   */
  public static function getProfile(int $userId): ?array
  {
    try {
      $user = db()->fetchOne(
        "SELECT id, full_name, email, role, profile_picture,
                        phone, address, date_of_birth, bio,
                        status, created_at, last_login
                 FROM users WHERE id = :id",
        ['id' => $userId]
      );

      if (!$user) {
        return null;
      }

      $user['avatar_url'] = self::getAvatarUrl($userId, $user['profile_picture'] ?? null);
      $user['initials'] = self::getInitials($user['full_name'] ?? '');

      return $user;
    } catch (\Throwable $e) {
      error_log("ProfileService::getProfile failed: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Update profile fields (non-avatar).
   */
  public static function updateProfile(int $userId, array $data): bool
  {
    // Whitelist of updateable fields
    $allowed = ['full_name', 'phone', 'address', 'date_of_birth', 'bio'];
    $clean = [];
    foreach ($allowed as $field) {
      if (array_key_exists($field, $data)) {
        $clean[$field] = $data[$field];
      }
    }

    if (empty($clean)) {
      return false;
    }

    try {
      $result = db()->update('users', $clean, 'id = :id', ['id' => $userId]);

      if ($result && class_exists('EventDispatcher')) {
        EventDispatcher::dispatch('ProfileChanged', [
          'user_id' => $userId,
          'changes' => implode(', ', array_keys($clean)),
        ]);
      }

      return $result;
    } catch (\Throwable $e) {
      error_log("ProfileService::updateProfile failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Process and store an avatar upload.
   *
   * @param int   $userId  User ID
   * @param array $file    $_FILES['avatar'] entry
   * @return array ['success' => bool, 'message' => string, 'avatar_url' => string]
   */
  public static function uploadAvatar(int $userId, array $file): array
  {
    // 1. Validate file
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'message' => 'No file uploaded or upload error.'];
    }

    if ($file['size'] > self::AVATAR_MAX_SIZE) {
      return ['success' => false, 'message' => 'File too large. Maximum 2MB.'];
    }

    // 2. Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, self::ALLOWED_MIMES, true)) {
      return ['success' => false, 'message' => 'Invalid file type. Allowed: JPEG, PNG, WebP.'];
    }

    // 3. Validate it's a real image
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
      return ['success' => false, 'message' => 'File is not a valid image.'];
    }

    // 4. Get user's role for storage path
    $user = db()->fetchOne("SELECT role FROM users WHERE id = :id", ['id' => $userId]);
    $role = $user['role'] ?? 'general';

    // 5. Create storage directory
    $storageDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . "/uploads/profiles/{$role}";
    if (!is_dir($storageDir)) {
      mkdir($storageDir, 0755, true);

      // Security: prevent PHP execution in upload dir
      file_put_contents($storageDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>");
    }

    // 6. Resize and crop to square
    $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
    $filename = "avatar_{$userId}_" . bin2hex(random_bytes(4)) . ".{$ext}";
    $destPath = $storageDir . '/' . $filename;

    if (!self::resizeImage($file['tmp_name'], $destPath, $mime)) {
      return ['success' => false, 'message' => 'Failed to process image.'];
    }

    // 7. Delete old avatar
    self::deleteOldAvatar($userId);

    // 8. Update database
    $relativePath = "uploads/profiles/{$role}/{$filename}";
    db()->update('users', ['profile_picture' => $relativePath], 'id = :id', ['id' => $userId]);

    // 9. Dispatch event
    if (class_exists('EventDispatcher')) {
      EventDispatcher::dispatch('AvatarUpdated', ['user_id' => $userId, 'path' => $relativePath]);
    }

    $avatarUrl = (defined('APP_URL') ? APP_URL : '/attendance') . '/' . $relativePath;
    return ['success' => true, 'message' => 'Profile picture updated!', 'avatar_url' => $avatarUrl];
  }

  /**
   * Remove a user's avatar.
   */
  public static function removeAvatar(int $userId): bool
  {
    try {
      self::deleteOldAvatar($userId);
      return db()->update('users', ['profile_picture' => null], 'id = :id', ['id' => $userId]);
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Get avatar URL with fallback.
   */
  public static function getAvatarUrl(int $userId, ?string $profilePicture = null): string
  {
    if ($profilePicture) {
      return (defined('APP_URL') ? APP_URL : '/attendance') . '/' . $profilePicture;
    }
    return '';  // Empty = use initials fallback in UI
  }

  /**
   * Get initials from a name.
   */
  public static function getInitials(string $name): string
  {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
      return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
  }

  /**
   * Resize image to a square crop.
   */
  private static function resizeImage(string $source, string $dest, string $mime): bool
  {
    $dim = self::AVATAR_DIMENSION;

    switch ($mime) {
      case 'image/jpeg':
        $src = imagecreatefromjpeg($source);
        break;
      case 'image/png':
        $src = imagecreatefrompng($source);
        break;
      case 'image/webp':
        $src = imagecreatefromwebp($source);
        break;
      default:
        return false;
    }

    if (!$src) {
      return false;
    }

    $width = imagesx($src);
    $height = imagesy($src);
    $cropSize = min($width, $height);
    $cropX = (int) (($width - $cropSize) / 2);
    $cropY = (int) (($height - $cropSize) / 2);

    $dst = imagecreatetruecolor($dim, $dim);

    // Preserve transparency for PNG/WebP
    if ($mime !== 'image/jpeg') {
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
      $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
      imagefilledrectangle($dst, 0, 0, $dim, $dim, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $dim, $dim, $cropSize, $cropSize);

    switch ($mime) {
      case 'image/jpeg':
        $result = imagejpeg($dst, $dest, self::AVATAR_QUALITY);
        break;
      case 'image/png':
        $result = imagepng($dst, $dest, 6);
        break;
      case 'image/webp':
        $result = imagewebp($dst, $dest, self::AVATAR_QUALITY);
        break;
      default:
        $result = false;
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $result;
  }

  /**
   * Delete old avatar file from disk.
   */
  private static function deleteOldAvatar(int $userId): void
  {
    try {
      $user = db()->fetchOne("SELECT profile_picture FROM users WHERE id = :id", ['id' => $userId]);
      if (!empty($user['profile_picture'])) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $oldFile = $basePath . '/' . $user['profile_picture'];
        if (is_file($oldFile)) {
          unlink($oldFile);
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
  }
}
