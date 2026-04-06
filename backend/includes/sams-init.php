<?php

/**
 * SAMS Initialization File
 * Loads all AI bot classes and core services for role-specific dashboards
 */

// Prevent double-loading
if (defined('SAMS_INIT_LOADED')) return;
define('SAMS_INIT_LOADED', true);

// Load base AI components
$includes_dir = __DIR__;
foreach (['sams-ai-engine.php', 'sams-ai-assistant.php', 'sams-ai-chatbot.php'] as $file) {
  $path = $includes_dir . '/' . $file;
  if (file_exists($path)) {
    require_once $path;
  }
}

// Load role-specific bot classes
require_once __DIR__ . '/sams-ai-bots.php';
