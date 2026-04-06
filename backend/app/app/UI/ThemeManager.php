<?php

/**
 * ThemeManager — Enterprise Theme Engine
 *
 * Manages per-user theme preferences with DB persistence and dynamic CSS injection.
 * Replaces the older ThemeService with a static API that works inside the bootstrap.
 *
 * Available themes:
 *   default, ocean, forest, sunset, midnight, rose, violet, corporate, ember,
 *   sapphire, dark, cyberpunk, cyberpunk-neon, cyberpunk-matrix, minimal-enterprise
 *
 * Usage:
 *   ThemeManager::load();              // auto-detect from session/DB
 *   ThemeManager::set($userId, 'cyberpunk');
 *   ThemeManager::get($userId);        // returns theme name string
 *   ThemeManager::allThemes();         // returns theme catalogue
 */
class ThemeManager
{
  /** All registered themes with metadata. */
  private static array $themes = [
    'default'             => ['label' => 'Default',            'group' => 'light',     'icon' => 'circle'],
    'ocean'               => ['label' => 'Ocean',              'group' => 'light',     'icon' => 'water'],
    'forest'              => ['label' => 'Forest',             'group' => 'light',     'icon' => 'tree'],
    'sunset'              => ['label' => 'Sunset',             'group' => 'light',     'icon' => 'sun'],
    'rose'                => ['label' => 'Rose',               'group' => 'light',     'icon' => 'heart'],
    'violet'              => ['label' => 'Violet',             'group' => 'light',     'icon' => 'palette'],
    'corporate'           => ['label' => 'Corporate',          'group' => 'light',     'icon' => 'building'],
    'ember'               => ['label' => 'Ember',              'group' => 'light',     'icon' => 'fire'],
    'sapphire'            => ['label' => 'Sapphire',           'group' => 'light',     'icon' => 'gem'],
    'midnight'            => ['label' => 'Midnight',           'group' => 'dark',      'icon' => 'moon'],
    'dark'                => ['label' => 'Dark Pro',           'group' => 'dark',      'icon' => 'adjust'],
    'cyberpunk'           => ['label' => 'Cyberpunk',          'group' => 'cyberpunk', 'icon' => 'bolt'],
    'cyberpunk-neon'      => ['label' => 'Cyberpunk Neon',     'group' => 'cyberpunk', 'icon' => 'lightbulb'],
    'cyberpunk-matrix'    => ['label' => 'Cyberpunk Matrix',   'group' => 'cyberpunk', 'icon' => 'code'],
    'minimal-enterprise'  => ['label' => 'Minimal Enterprise', 'group' => 'light',     'icon' => 'minus-circle'],
  ];

  /**
   * Load theme for the current session user.
   * Returns the theme name string (for data-theme attribute).
   */
  public static function load(?int $userId = null): string
  {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$userId) {
      return 'default';
    }

    // Check session cache first
    if (isset($_SESSION['_sams_theme'])) {
      return $_SESSION['_sams_theme'];
    }

    $theme = self::get($userId);
    $_SESSION['_sams_theme'] = $theme;
    return $theme;
  }

  /**
   * Get a user's saved theme from DB.
   */
  public static function get(int $userId): string
  {
    try {
      $row = db()->fetchOne(
        "SELECT theme FROM user_themes WHERE user_id = :uid",
        ['uid' => $userId]
      );
      if ($row && isset(self::$themes[$row['theme']])) {
        return $row['theme'];
      }
    } catch (\Throwable $e) {
      // Table may not exist yet
    }
    return 'default';
  }

  /**
   * Save a user's theme preference.
   */
  public static function set(int $userId, string $theme): bool
  {
    if (!isset(self::$themes[$theme])) {
      return false;
    }

    try {
      // Upsert
      $exists = db()->fetchOne(
        "SELECT id FROM user_themes WHERE user_id = :uid",
        ['uid' => $userId]
      );
      if ($exists) {
        db()->update('user_themes', [
          'theme'      => $theme,
          'updated_at' => date('Y-m-d H:i:s'),
        ], 'user_id = :uid', ['uid' => $userId]);
      } else {
        db()->insert('user_themes', [
          'user_id'    => $userId,
          'role'       => $_SESSION['role'] ?? 'user',
          'theme'      => $theme,
          'created_at' => date('Y-m-d H:i:s'),
          'updated_at' => date('Y-m-d H:i:s'),
        ]);
      }

      // Update session cache
      $_SESSION['_sams_theme'] = $theme;
      return true;
    } catch (\Throwable $e) {
      error_log("ThemeManager::set failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get all registered themes, grouped.
   */
  public static function allThemes(): array
  {
    return self::$themes;
  }

  /**
   * Get themes organized by group for the UI picker.
   */
  public static function grouped(): array
  {
    $groups = [];
    foreach (self::$themes as $key => $meta) {
      $groups[$meta['group']][$key] = $meta;
    }
    return $groups;
  }

  /**
   * Check if a theme belongs to the cyberpunk family.
   */
  public static function isCyberpunk(?string $theme = null): bool
  {
    $theme = $theme ?? ($_SESSION['_sams_theme'] ?? 'default');
    return str_starts_with($theme, 'cyberpunk');
  }

  /**
   * Get the theme group (light/dark/cyberpunk).
   */
  public static function getGroup(?string $theme = null): string
  {
    $theme = $theme ?? ($_SESSION['_sams_theme'] ?? 'default');
    return self::$themes[$theme]['group'] ?? 'light';
  }
}
