<?php

/**
 * ACI — Navigation Guardian
 * Validates all navigation links, detects broken routes, auto-creates stubs.
 */
class NavigationGuardian
{
  /**
   * Full navigation scan — checks every known page link.
   */
  public static function scan(): array
  {
    $results = ['scanned' => 0, 'broken' => [], 'repaired' => [], 'healthy' => 0];

    $rolePages = self::getExpectedPages();

    foreach ($rolePages as $role => $pages) {
      foreach ($pages as $page) {
        $results['scanned']++;
        $path = BASE_PATH . '/' . $role . '/' . $page;
        if (!is_file($path)) {
          $results['broken'][] = ['role' => $role, 'page' => $page, 'path' => $path];
        } else {
          $results['healthy']++;
        }
      }
    }

    return $results;
  }

  /**
   * Repair broken pages by creating safe stubs.
   */
  public static function repair(array $broken = null): array
  {
    if ($broken === null) {
      $scan = self::scan();
      $broken = $scan['broken'];
    }

    $repaired = [];
    foreach ($broken as $item) {
      $path = $item['path'];
      $role = $item['role'];
      $page = $item['page'];

      // Create safe stub
      $dir = dirname($path);
      if (!is_dir($dir)) @mkdir($dir, 0755, true);

      $title = ucwords(str_replace(['-', '_', '.php'], [' ', ' ', ''], $page));
      $stub = self::generateStub($role, $title);

      if (file_put_contents($path, $stub, LOCK_EX) !== false) {
        $repaired[] = "$role/$page";
        LearningMemory::recordSuccess('missing_page', "$role/$page", 'create_stub', "Created stub for $role/$page");
        try {
          ErrorCollector::log('aci_guardian', "Created stub: $role/$page", 'INFO');
        } catch (\Throwable $e) {
        }
      }
    }

    return ['repaired' => $repaired, 'count' => count($repaired)];
  }

  /**
   * Generate a safe page stub.
   */
  private static function generateStub(string $role, string $title): string
  {
    $safeTitle = htmlspecialchars($title);
    $configDepth = ($role === '.' || $role === '') ? '' : '../';

    return <<<PHP
<?php
/**
 * $safeTitle — Auto-generated stub
 * Created by ACI NavigationGuardian.
 */
require_once __DIR__ . '/{$configDepth}includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

\$page_title = '$safeTitle';
\$page_icon = 'fas fa-cog';
\$page_subtitle = 'Module initializing...';

ob_start();
?>
<div class="container-fluid py-4">
  <div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>$safeTitle</strong> — This module is being initialized. Content will be available shortly.
  </div>
</div>
<?php
\$page_content = ob_get_clean();
require_once BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
PHP;
  }

  /**
   * Get expected pages per role directory based on existing files.
   * Only checks pages referenced in navigation, not every file.
   */
  private static function getExpectedPages(): array
  {
    $pages = [];

    // Developer portal pages (from developer/index.php $modules array)
    $pages['developer'] = [
      'index.php',
      'system-health.php',
      'system-monitor.php',
      'devops-center.php',
      'intelligence-center.php',
      'ecosystem-center.php',
      'autofix-center.php',
      'healing-center.php',
      'os-center.php',
      'settings.php',
      'logs.php',
      'modules.php',
      'themes.php',
      'ai-center.php',
      'debug-overlay.php',
    ];

    // Admin core pages
    $pages['admin'] = [
      'dashboard.php',
      'attendance.php',
      'class-management.php',
      'approve-users.php',
      'announcements-system.php',
      'analytics.php',
    ];

    // Teacher core pages
    $pages['teacher'] = [
      'dashboard.php',
      'attendance.php',
      'my-classes.php',
      'students.php',
    ];

    // Student core pages
    $pages['student'] = [
      'dashboard.php',
      'attendance.php',
      'grades.php',
      'schedule.php',
    ];

    // Parent core pages
    $pages['parent'] = [
      'dashboard.php',
      'grades.php',
    ];

    return $pages;
  }
}
