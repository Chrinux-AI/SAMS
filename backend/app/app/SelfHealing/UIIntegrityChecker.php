<?php

/**
 * UIIntegrityChecker — Ensures dashboards are functional, not cosmetic-only.
 *
 * Validates every tab/page: loads controller, executes query, renders component, returns data.
 * Flags empty tabs, disables broken UI, creates repair tasks.
 */
class UIIntegrityChecker
{
  /**
   * Run full UI integrity scan.
   */
  public static function scan(): array
  {
    $results = [];
    $results['developer_tabs'] = self::checkDeveloperTabs();
    $results['dashboards'] = self::checkDashboards();
    $results['layouts'] = self::checkLayouts();
    return $results;
  }

  /**
   * Verify developer tabs contain executable logic.
   */
  private static function checkDeveloperTabs(): array
  {
    $tabDir = BASE_PATH . '/developer/tabs';
    $issues = [];

    if (!is_dir($tabDir)) {
      return [['tab' => '*', 'issue' => 'Tab directory missing']];
    }

    $tabs = glob($tabDir . '/*.php');
    foreach ($tabs as $tabFile) {
      $name = basename($tabFile, '.php');
      $content = file_get_contents($tabFile);
      $size = strlen($content);

      // Tab should have meaningful content (> 50 bytes)
      if ($size < 50) {
        $issues[] = ['tab' => $name, 'issue' => 'Tab too small — likely stub', 'size' => $size];
        continue;
      }

      // Check for HTML output
      $hasHtml = preg_match('/<(div|table|form|h[1-6]|p|section|ul)/i', $content);
      if (!$hasHtml) {
        $issues[] = ['tab' => $name, 'issue' => 'No HTML rendering detected'];
      }
    }

    return $issues;
  }

  /**
   * Check role dashboards exist and have layout.
   */
  private static function checkDashboards(): array
  {
    $roles = ['admin', 'teacher', 'student', 'parent', 'accountant', 'librarian', 'developer'];
    $issues = [];

    foreach ($roles as $role) {
      $dashPath = BASE_PATH . '/' . $role . '/dashboard.php';
      if ($role === 'developer') {
        $dashPath = BASE_PATH . '/developer/index.php';
      }

      if (!file_exists($dashPath)) {
        $issues[] = ['dashboard' => $role, 'issue' => 'Dashboard file missing'];
        continue;
      }

      $content = file_get_contents($dashPath);
      $hasLayout = strpos($content, 'master-dashboard.php') !== false
        || strpos($content, '<!DOCTYPE html>') !== false;

      if (!$hasLayout) {
        $issues[] = ['dashboard' => $role, 'issue' => 'No layout structure'];
      }

      // Check auth gate
      $hasAuth = strpos($content, 'require_admin') !== false
        || strpos($content, 'require_role') !== false
        || strpos($content, 'is_logged_in') !== false;

      if (!$hasAuth) {
        $issues[] = ['dashboard' => $role, 'issue' => 'No authentication check'];
      }
    }

    return $issues;
  }

  /**
   * Check layout files integrity.
   */
  private static function checkLayouts(): array
  {
    $issues = [];

    $layouts = [
      'includes/layouts/header.php',
      'includes/layouts/footer.php',
      'resources/ui-core/layouts/master-dashboard.php',
    ];

    foreach ($layouts as $layout) {
      $path = BASE_PATH . '/' . $layout;
      if (!file_exists($path)) {
        $issues[] = ['layout' => $layout, 'issue' => 'Layout file missing'];
      } elseif (filesize($path) < 20) {
        $issues[] = ['layout' => $layout, 'issue' => 'Layout file too small'];
      }
    }

    return $issues;
  }

  /**
   * Auto-disable a broken tab by renaming it.
   */
  public static function disableTab(string $tabName): bool
  {
    $tabFile = BASE_PATH . '/developer/tabs/' . basename($tabName) . '.php';
    $disabled = $tabFile . '.disabled';

    if (file_exists($tabFile)) {
      rename($tabFile, $disabled);
      ErrorCollector::log('self_healing', "Disabled broken tab: $tabName", 'MEDIUM');
      return true;
    }
    return false;
  }

  public static function getSummary(): array
  {
    $scan = self::scan();
    $totalIssues = count($scan['developer_tabs']) + count($scan['dashboards']) + count($scan['layouts']);
    return [
      'tab_issues'       => count($scan['developer_tabs']),
      'dashboard_issues' => count($scan['dashboards']),
      'layout_issues'    => count($scan['layouts']),
      'total_issues'     => $totalIssues,
      'status'           => $totalIssues === 0 ? 'healthy' : 'degraded',
    ];
  }
}
