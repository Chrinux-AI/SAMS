<?php

/**
 * Input Sanitizer — Cleans and validates all user input.
 * Provides defense-in-depth against XSS, SQL injection, and malformed data.
 */
class InputSanitizer
{
  /**
   * Sanitize a single string value.
   */
  public static function sanitize(string $input): string
  {
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    // Trim whitespace
    $input = trim($input);
    // Encode HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $input;
  }

  /**
   * Recursively sanitize an array (e.g., $_POST, $_GET).
   */
  public static function sanitizeArray(array $data): array
  {
    $clean = [];
    foreach ($data as $key => $value) {
      $cleanKey = self::sanitize((string) $key);
      if (is_array($value)) {
        $clean[$cleanKey] = self::sanitizeArray($value);
      } elseif (is_string($value)) {
        $clean[$cleanKey] = self::sanitize($value);
      } else {
        $clean[$cleanKey] = $value;
      }
    }
    return $clean;
  }

  /**
   * Sanitize and validate an email address.
   */
  public static function email(string $input): string
  {
    $input = trim($input);
    $input = filter_var($input, FILTER_SANITIZE_EMAIL);
    return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : '';
  }

  /**
   * Sanitize an integer value.
   */
  public static function integer($input): int
  {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
  }

  /**
   * Sanitize a string to alphanumeric + underscores only.
   */
  public static function alphanumeric(string $input): string
  {
    return preg_replace('/[^a-zA-Z0-9_]/', '', $input);
  }

  /**
   * Strip all HTML tags (for plain text fields).
   */
  public static function plainText(string $input): string
  {
    return strip_tags(trim($input));
  }

  /**
   * Sanitize a URL.
   */
  public static function url(string $input): string
  {
    $input = filter_var(trim($input), FILTER_SANITIZE_URL);
    return filter_var($input, FILTER_VALIDATE_URL) ? $input : '';
  }

  /**
   * Sanitize a filename (no directory traversal).
   */
  public static function filename(string $input): string
  {
    // Remove any path components
    $input = basename($input);
    // Only allow safe characters
    return preg_replace('/[^a-zA-Z0-9_.\-]/', '', $input);
  }
}
