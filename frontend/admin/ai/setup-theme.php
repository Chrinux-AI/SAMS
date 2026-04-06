<?php
/**
 * SAMS Theme Setup Script
 * Creates necessary database tables for theme system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = db();

// Create user_theme_preferences table
$themeTable = "
CREATE TABLE IF NOT EXISTS user_theme_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    theme VARCHAR(20) NOT NULL DEFAULT 'system',
    custom_colors JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

echo "Creating theme preferences table...\n";

try {
    $db->createTable($themeTable);
    echo "✓ Theme preferences table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating theme preferences table: " . $e->getMessage() . "\n";
}

// Insert system default theme (user_id = 0)
echo "Setting system default theme...\n";

try {
    $existingSystem = $db->fetchOne("SELECT id FROM user_theme_preferences WHERE user_id = 0");
    
    if (!$existingSystem) {
        $db->insert('user_theme_preferences', [
            'user_id' => 0,
            'theme' => 'system',
            'custom_colors' => json_encode([])
        ]);
        echo "✓ System default theme set to 'system'\n";
    } else {
        echo "✓ System default theme already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Error setting system default theme: " . $e->getMessage() . "\n";
}

// Check if theme service exists
if (file_exists(__DIR__ . '/../app/services/ThemeService.php')) {
    echo "✓ Theme service file exists\n";
} else {
    echo "✗ Theme service file not found\n";
}

// Check if theme manager exists
if (file_exists(__DIR__ . '/../public/assets/js/theme-manager.js')) {
    echo "✓ Theme manager JavaScript exists\n";
} else {
    echo "✗ Theme manager JavaScript not found\n";
}

// Check if theme API exists
if (file_exists(__DIR__ . '/../admin/api/theme.php')) {
    echo "✓ Theme API endpoint exists\n";
} else {
    echo "✗ Theme API endpoint not found\n";
}

// Check if main layout has been updated
$mainLayoutPath = __DIR__ . '/../app/views/layouts/main.php';
if (file_exists($mainLayoutPath)) {
    $mainLayoutContent = file_get_contents($mainLayoutPath);
    
    if (strpos($mainLayoutContent, 'ThemeService') !== false) {
        echo "✓ Main layout has been updated with theme support\n";
    } else {
        echo "! Main layout may need theme integration\n";
    }
} else {
    echo "✗ Main layout file not found\n";
}

echo "\nTheme system setup completed!\n";
echo "Features available:\n";
echo "- Dark/Light/System theme modes\n";
echo "- Database and localStorage synchronization\n";
echo "- Custom color customization\n";
echo "- Instant theme switching\n";
echo "- Theme persistence across sessions\n";
echo "- Admin theme statistics\n";
echo "- Color presets\n";
echo "- WCAG accessibility support\n";
?>
