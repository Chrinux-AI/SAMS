<?php

/**
 * LandingContentService — Centralized provider for landing page data.
 *
 * All statistics, features, and content for index.php flow through this service.
 * Ensures the landing page never displays stale or hardcoded data.
 */
class LandingContentService
{
  /**
   * Get live platform statistics for the landing page.
   *
   * @return array{students: int, teachers: int, classes: int, parents: int, attendance_today: int, attendance_rate: int}
   */
  public static function getStats(): array
  {
    try {
      $students = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM students WHERE is_active = 1")['cnt'] ?? 0);
      $teachers = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'teacher' AND is_active = 1")['cnt'] ?? 0);
      $classes  = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM classes WHERE is_active = 1")['cnt'] ?? 0);
      $parents  = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'parent' AND is_active = 1")['cnt'] ?? 0);
      $today    = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM attendance WHERE date = CURDATE() AND status = 'present'")['cnt'] ?? 0);
      $total    = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM attendance WHERE date = CURDATE()")['cnt'] ?? 0);
      $rate     = $total > 0 ? round(($today / $total) * 100) : 0;

      return [
        'students'         => $students,
        'teachers'         => $teachers,
        'classes'          => $classes,
        'parents'          => $parents,
        'attendance_today' => $today,
        'attendance_rate'  => $rate,
      ];
    } catch (\Throwable $e) {
      return [
        'students' => 0,
        'teachers' => 0,
        'classes' => 0,
        'parents' => 0,
        'attendance_today' => 0,
        'attendance_rate' => 0,
      ];
    }
  }

  /**
   * Get the feature list for the landing page.
   *
   * @return array Each item has icon, title, description
   */
  public static function getFeatures(): array
  {
    return [
      ['icon' => 'fingerprint',        'title' => 'Biometric Attendance',     'description' => 'Fast, accurate check-in via fingerprint or facial recognition.'],
      ['icon' => 'chart-line',          'title' => 'Real-Time Analytics',      'description' => 'Live dashboards with attendance trends, alerts, and insights.'],
      ['icon' => 'comments',            'title' => 'Communication Hub',        'description' => 'WhatsApp-style messaging between teachers, parents, and staff.'],
      ['icon' => 'user-shield',         'title' => 'Role-Based Access',        'description' => 'Secure dashboards for admin, teacher, student, parent, and staff.'],
      ['icon' => 'calendar-check',      'title' => 'Class Scheduling',         'description' => 'Relational timetables with day, time, and room assignments.'],
      ['icon' => 'bell',                'title' => 'Smart Notifications',      'description' => 'Automated alerts for absences, deadlines, and announcements.'],
      ['icon' => 'mobile-alt',          'title' => 'Mobile PWA',               'description' => 'Install on any device — works offline with background sync.'],
      ['icon' => 'file-invoice-dollar', 'title' => 'Financial Management',     'description' => 'Fee tracking, payroll, expenses, and budget reporting.'],
    ];
  }

  /**
   * Get platform branding info.
   */
  public static function getBranding(): array
  {
    return [
      'name'    => defined('APP_NAME') ? APP_NAME : 'SAMS',
      'tagline' => 'Next-Gen School Platform',
      'description' => 'A unified platform for real-time attendance tracking, analytics dashboards, seamless communication, and complete school operations.',
    ];
  }
}
