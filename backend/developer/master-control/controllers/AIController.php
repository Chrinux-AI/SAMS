<?php

/**
 * MCC — AI Control Center Controller
 * Manages all AI modules: Admin AI, Role AI, Public AI, training, model status.
 */
class AIController
{
  public static function getStatus(): array
  {
    $modules = [];

    // Check each AI service
    $aiServices = [
      'AdminAI'   => 'AdminAIService',
      'TeacherAI' => 'TeacherAIService',
      'StudentAI' => 'StudentAIService',
      'CoreAI'    => 'CoreAIService',
      'AIRouter'  => 'AIRouter',
    ];

    foreach ($aiServices as $label => $class) {
      $modules[] = [
        'name'   => $label,
        'class'  => $class,
        'active' => class_exists($class),
        'status' => class_exists($class) ? 'online' : 'unavailable',
      ];
    }

    // Public AI chatbots
    $chatbots = ['student-bot.php', 'teacher-bot.php'];
    foreach ($chatbots as $bot) {
      $exists = is_file(BASE_PATH . '/chatbots/' . $bot);
      $modules[] = [
        'name'   => str_replace('.php', '', $bot),
        'class'  => $bot,
        'active' => $exists,
        'status' => $exists ? 'online' : 'missing',
      ];
    }

    // AI cache status
    $cacheFiles = glob(BASE_PATH . '/cache/ai_*.json') ?: [];
    $cacheSize = 0;
    foreach ($cacheFiles as $f) $cacheSize += filesize($f);

    // Training data
    $trainingFiles = glob(BASE_PATH . '/data/training/*.json') ?: [];

    return [
      'modules'        => $modules,
      'total_modules'   => count($modules),
      'active_count'    => count(array_filter($modules, fn($m) => $m['active'])),
      'cache_files'     => count($cacheFiles),
      'cache_size_kb'   => round($cacheSize / 1024, 1),
      'training_sets'   => count($trainingFiles),
      'ai_health'       => count(array_filter($modules, fn($m) => $m['active'])) === count($modules) ? 100 : round((count(array_filter($modules, fn($m) => $m['active'])) / max(1, count($modules))) * 100),
    ];
  }

  public static function clearAICache(): array
  {
    $cleared = 0;
    foreach (glob(BASE_PATH . '/cache/ai_*.json') ?: [] as $f) {
      @unlink($f);
      $cleared++;
    }
    try {
      AuditLogger::log('clear_ai_cache', 'ai', "Cleared $cleared AI cache files", $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }
    return ['cleared' => $cleared];
  }
}
