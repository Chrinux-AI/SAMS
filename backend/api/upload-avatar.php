<?php

/**
 * Avatar Upload API
 * Handles profile picture upload with validation, resize, and storage.
 * POST multipart/form-data with 'avatar' file field.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

function avatar_json($payload, $statusCode = 200)
{
  http_response_code($statusCode);
  echo json_encode($payload);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  avatar_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (!isset($_SESSION['user_id'])) {
  avatar_json(['success' => false, 'error' => 'Unauthorized'], 401);
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
  avatar_json(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.'], 403);
}

$userId = (int) $_SESSION['user_id'];

// ─── Handle avatar removal ─────────────────────────
if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
  $user = db()->fetchOne("SELECT profile_picture FROM users WHERE id = ?", [$userId]);
  if ($user && !empty($user['profile_picture'])) {
    $oldPath = __DIR__ . '/../' . $user['profile_picture'];
    if (file_exists($oldPath)) {
      unlink($oldPath);
    }
    db()->query("UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE id = ?", [$userId]);
  }
  avatar_json(['success' => true, 'message' => 'Profile picture removed', 'avatar_url' => '']);
}

// ─── Validate upload ────────────────────────────────
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
  $errors = [
    UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
    UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
    UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
  ];
  $code = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
  avatar_json(['success' => false, 'error' => $errors[$code] ?? 'Upload failed.'], 400);
}

$file = $_FILES['avatar'];

// Size limit: 2 MB
$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
  avatar_json(['success' => false, 'error' => 'File too large. Maximum size is 2 MB.'], 400);
}

// Validate MIME type via actual file content (not user-supplied type)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = [
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/webp' => 'webp',
];

if (!isset($allowedMimes[$mime])) {
  avatar_json(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, WEBP.'], 400);
}

$ext = $allowedMimes[$mime];

// Validate it's a real image
$imgInfo = @getimagesize($file['tmp_name']);
if ($imgInfo === false) {
  avatar_json(['success' => false, 'error' => 'File is not a valid image.'], 400);
}

// ─── Create storage directory ───────────────────────
$uploadDir = __DIR__ . '/../uploads/profiles/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

// Generate unique filename (non-guessable)
$filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $uploadDir . $filename;
$relPath = 'uploads/profiles/' . $filename;

// ─── Process and resize image ───────────────────────
$resized = resizeAvatar($file['tmp_name'], $destPath, $mime, 400);
if (!$resized) {
  // Fallback: just move the file
  if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    avatar_json(['success' => false, 'error' => 'Failed to save image.'], 500);
  }
}

// ─── Delete old avatar ──────────────────────────────
$user = db()->fetchOne("SELECT profile_picture FROM users WHERE id = ?", [$userId]);
if ($user && !empty($user['profile_picture'])) {
  $oldPath = __DIR__ . '/../' . $user['profile_picture'];
  if (file_exists($oldPath)) {
    unlink($oldPath);
  }
}

// ─── Update database ────────────────────────────────
db()->query("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?", [$relPath, $userId]);

avatar_json([
  'success' => true,
  'message' => 'Profile picture updated!',
  'avatar_url' => '/attendance/' . $relPath
]);

/**
 * Resize avatar image to a square with max dimensions.
 */
function resizeAvatar(string $src, string $dest, string $mime, int $maxDim = 400): bool
{
  if (!extension_loaded('gd')) {
    return false;
  }

  switch ($mime) {
    case 'image/jpeg':
      $img = @imagecreatefromjpeg($src);
      break;
    case 'image/png':
      $img = @imagecreatefrompng($src);
      break;
    case 'image/webp':
      $img = @imagecreatefromwebp($src);
      break;
    default:
      return false;
  }

  if (!$img) return false;

  $w = imagesx($img);
  $h = imagesy($img);

  // Crop to square from center
  $size = min($w, $h);
  $sx = (int)(($w - $size) / 2);
  $sy = (int)(($h - $size) / 2);

  // Target dimension
  $targetDim = min($size, $maxDim);

  $output = imagecreatetruecolor($targetDim, $targetDim);

  // Preserve transparency for PNG/WebP
  if ($mime === 'image/png' || $mime === 'image/webp') {
    imagealphablending($output, false);
    imagesavealpha($output, true);
  }

  imagecopyresampled($output, $img, 0, 0, $sx, $sy, $targetDim, $targetDim, $size, $size);

  $result = false;
  switch ($mime) {
    case 'image/jpeg':
      $result = imagejpeg($output, $dest, 85);
      break;
    case 'image/png':
      $result = imagepng($output, $dest, 6);
      break;
    case 'image/webp':
      $result = imagewebp($output, $dest, 85);
      break;
  }

  imagedestroy($img);
  imagedestroy($output);

  return $result;
}
