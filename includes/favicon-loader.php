<?php
/**
 * Dynamic favicon and icon loader
 * This script automatically adds favicon links to all pages
 */

$base_path = '/attendance';
$assets_path = $base_path . '/assets/images/icons';

echo '<link rel="icon" type="image/svg+xml" href="' . $assets_path . '/favicon.svg">' . "\n";
echo '<link rel="icon" type="image/svg+xml" sizes="32x32" href="' . $assets_path . '/icon-32x32.svg">' . "\n";
echo '<link rel="apple-touch-icon" href="' . $assets_path . '/icon-192x192.svg">' . "\n";
echo '<link rel="manifest" href="' . $base_path . '/manifest.json">' . "\n";
echo '<meta name="theme-color" content="#4F46E5">' . "\n";
echo '<meta name="msapplication-TileColor" content="#4F46E5">' . "\n";
?>
