<?php

/**
 * Clear Cache Script
 * Usage: php scripts/clear_cache.php [key]
 */

require_once __DIR__ . '/../includes/cache.php';

$cache = new Cache();

if (isset($argv[1])) {
    $cache->forget($argv[1]);
    echo "Cache cleared for key: {$argv[1]}\n";
} else {
    $cache->flush();
    echo "All cache cleared!\n";
}
