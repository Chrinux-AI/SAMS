<?php

/**
 * Dynamic favicon and icon loader
 * This script automatically adds favicon links to all pages
 */

$base_path = '/attendance';
$assets_path = $base_path . '/assets/images/icons';
$shortcut_icon_light = $base_path . '/assets/images/icons/logo3.png';
$shortcut_icon_dark = $base_path . '/assets/images/icons/logo2.png';

echo '<link rel="icon" type="image/png" href="' . $shortcut_icon_light . '">' . "\n";
echo '<link rel="icon" media="(prefers-color-scheme: dark)" type="image/png" href="' . $shortcut_icon_dark . '">' . "\n";
echo '<link rel="shortcut icon" href="' . $shortcut_icon_light . '">' . "\n";
echo '<link rel="apple-touch-icon" href="' . $shortcut_icon_light . '">' . "\n";
echo '<link rel="manifest" href="' . $base_path . '/manifest.json">' . "\n";
echo '<meta name="theme-color" content="#4F46E5">' . "\n";
echo '<meta name="msapplication-TileColor" content="#4F46E5">' . "\n";
