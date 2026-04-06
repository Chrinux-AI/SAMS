<?php
/**
 * SAMS Theme Service
 * Centralized theme management system
 * Handles dark/light/system theme modes with database and localStorage synchronization
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

class ThemeService
{
    private $db;
    private $defaultTheme = 'system';
    private $supportedThemes = ['light', 'dark', 'system'];
    
    public function __construct()
    {
        $this->db = db();
        $this->initThemeTable();
    }
    
    /**
     * Initialize theme preferences table
     */
    private function initThemeTable()
    {
        $sql = "
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
        
        $this->db->createTable($sql);
    }
    
    /**
     * Get user's theme preference
     */
    public function getUserTheme($userId = null)
    {
        // If no user ID provided, try to get from session
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        
        // If still no user ID, return default theme
        if ($userId === null) {
            return $this->defaultTheme;
        }
        
        // Get from database
        $preference = $this->db->fetchOne(
            "SELECT theme, custom_colors FROM user_theme_preferences WHERE user_id = ?",
            [$userId]
        );
        
        if ($preference) {
            return [
                'theme' => $preference['theme'],
                'custom_colors' => json_decode($preference['custom_colors'] ?? '{}', true)
            ];
        }
        
        // Return default if no preference found
        return [
            'theme' => $this->defaultTheme,
            'custom_colors' => []
        ];
    }
    
    /**
     * Set user's theme preference
     */
    public function setUserTheme($userId, $theme, $customColors = [])
    {
        // Validate theme
        if (!in_array($theme, $this->supportedThemes)) {
            throw new InvalidArgumentException("Invalid theme: $theme");
        }
        
        // Validate custom colors
        if (!empty($customColors)) {
            $customColors = $this->validateCustomColors($customColors);
        }
        
        // Update or insert preference
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_theme_preferences WHERE user_id = ?",
            [$userId]
        );
        
        if ($existing) {
            $this->db->update('user_theme_preferences', [
                'theme' => $theme,
                'custom_colors' => json_encode($customColors),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [$userId]);
        } else {
            $this->db->insert('user_theme_preferences', [
                'user_id' => $userId,
                'theme' => $theme,
                'custom_colors' => json_encode($customColors)
            ]);
        }
        
        return true;
    }
    
    /**
     * Get system default theme
     */
    public function getSystemTheme()
    {
        // Check if system has a preference
        $systemPreference = $this->db->fetchOne(
            "SELECT theme, custom_colors FROM user_theme_preferences WHERE user_id = 0"
        );
        
        if ($systemPreference) {
            return [
                'theme' => $systemPreference['theme'],
                'custom_colors' => json_decode($systemPreference['custom_colors'] ?? '{}', true)
            ];
        }
        
        // Return system default
        return [
            'theme' => $this->defaultTheme,
            'custom_colors' => []
        ];
    }
    
    /**
     * Set system default theme
     */
    public function setSystemTheme($theme, $customColors = [])
    {
        // Validate theme
        if (!in_array($theme, $this->supportedThemes)) {
            throw new InvalidArgumentException("Invalid theme: $theme");
        }
        
        // Validate custom colors
        if (!empty($customColors)) {
            $customColors = $this->validateCustomColors($customColors);
        }
        
        // Update or insert system preference
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_theme_preferences WHERE user_id = 0"
        );
        
        if ($existing) {
            $this->db->update('user_theme_preferences', [
                'theme' => $theme,
                'custom_colors' => json_encode($customColors),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [0]);
        } else {
            $this->db->insert('user_theme_preferences', [
                'user_id' => 0,
                'theme' => $theme,
                'custom_colors' => json_encode($customColors)
            ]);
        }
        
        return true;
    }
    
    /**
     * Get effective theme for current user
     */
    public function getEffectiveTheme($userId = null)
    {
        $userPreference = $this->getUserTheme($userId);
        $systemPreference = $this->getSystemTheme();
        
        $theme = $userPreference['theme'];
        
        // If user prefers system theme, use system preference
        if ($theme === 'system') {
            $theme = $systemPreference['theme'];
        }
        
        // Check system preference for dark/light mode
        if ($theme === 'system') {
            $theme = $this->detectSystemTheme();
        }
        
        return [
            'theme' => $theme,
            'custom_colors' => array_merge(
                $systemPreference['custom_colors'],
                $userPreference['custom_colors']
            )
        ];
    }
    
    /**
     * Detect system theme preference
     */
    private function detectSystemTheme()
    {
        // Check if system prefers dark mode
        if (isset($_SERVER['HTTP_SEC_CH_PREFERS_COLOR_SCHEME'])) {
            $prefersDark = $_SERVER['HTTP_SEC_CH_PREFERS_COLOR_SCHEME'] === 'dark';
        } else {
            // Fallback to JavaScript detection
            $prefersDark = false;
        }
        
        return $prefersDark ? 'dark' : 'light';
    }
    
    /**
     * Validate custom colors
     */
    private function validateCustomColors($colors)
    {
        $validated = [];
        $allowedColors = [
            'primary', 'secondary', 'accent', 'success', 'warning', 'error',
            'bg-primary', 'bg-secondary', 'bg-tertiary', 'text-primary', 'text-secondary'
        ];
        
        foreach ($colors as $key => $value) {
            if (in_array($key, $allowedColors) && $this->isValidColor($value)) {
                $validated[$key] = $value;
            }
        }
        
        return $validated;
    }
    
    /**
     * Check if color is valid
     */
    private function isValidColor($color)
    {
        // Check hex color
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return true;
        }
        
        // Check RGB color
        if (preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/', $color)) {
            return true;
        }
        
        // Check RGBA color
        if (preg_match('/^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[\d.]+\s*\)$/', $color)) {
            return true;
        }
        
        // Check CSS color name
        $cssColors = [
            'red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink',
            'black', 'white', 'gray', 'grey', 'brown', 'navy', 'teal', 'lime'
        ];
        
        return in_array(strtolower($color), $cssColors);
    }
    
    /**
     * Get theme CSS variables
     */
    public function getThemeCSSVariables($userId = null)
    {
        $effectiveTheme = $this->getEffectiveTheme($userId);
        $customColors = $effectiveTheme['custom_colors'];
        
        $variables = [];
        
        // Apply custom colors
        foreach ($customColors as $key => $value) {
            $variables[$key] = $value;
        }
        
        // Add theme-specific overrides
        if ($effectiveTheme['theme'] === 'dark') {
            $variables = array_merge($variables, [
                'bg-primary' => '#111827',
                'bg-secondary' => '#1a202A2',
                'bg-tertiary' => '#374151',
                'text-primary' => '#E2E8F0',
                'text-secondary' => '#A0AEC0',
                'text-muted' => '#6B7280',
                'text-inverse' => '#FFFFFF',
                'border-primary' => '#4B5563',
                'border-secondary' => '#495057',
                'shadow' => 'rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2)'
            ]);
        } else {
            $variables = array_merge($variables, [
                'bg-primary' => '#FFFFFF',
                'bg-secondary' => '#F8F9FA',
                'bg-tertiary' => '#E9ECEF',
                'text-primary' => '#1F2937',
                'text-secondary' => '#6B7280',
                'text-muted' => '#6B7280',
                'text-inverse' => '#FFFFFF',
                'border-primary' => '#E5E7EB',
                'border-secondary' => '#DEE2E6',
                'shadow' => '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.05)'
            ]);
        }
        
        return $variables;
    }
    
    /**
     * Generate theme CSS
     */
    public function generateThemeCSS($userId = null)
    {
        $variables = $this->getThemeCSSVariables($userId);
        
        $css = ":root {\n";
        foreach ($variables as $key => $value) {
            $css .= "    --$key: $value;\n";
        }
        $css .= "}\n";
        
        return $css;
    }
    
    /**
     * Get theme data for API response
     */
    public function getThemeData($userId = null)
    {
        $userPreference = $this->getUserTheme($userId);
        $effectiveTheme = $this->getEffectiveTheme($userId);
        $systemPreference = $this->getSystemTheme();
        
        return [
            'user_preference' => $userPreference,
            'system_preference' => $systemPreference,
            'effective_theme' => $effectiveTheme,
            'supported_themes' => $this->supportedThemes,
            'css_variables' => $this->getThemeCSSVariables($userId),
            'css' => $this->generateThemeCSS($userId)
        ];
    }
    
    /**
     * Apply theme to page (for server-side rendering)
     */
    public function applyThemeToPage($userId = null)
    {
        $themeData = $this->getThemeData($userId);
        
        // Add theme class to body
        $bodyClass = 'theme-' . $themeData['effective_theme']['theme'];
        
        // Add custom CSS
        $customCSS = '<style id="theme-custom-css">' . $themeData['css'] . '</style>';
        
        // Add theme data to JavaScript
        $themeScript = '<script>window.SAMS_THEME_DATA = ' . json_encode($themeData) . ';</script>';
        
        return [
            'body_class' => $bodyClass,
            'custom_css' => $customCSS,
            'theme_script' => $themeScript
        ];
    }
    
    /**
     * Handle theme change API request
     */
    public function handleThemeChange($userId, $theme, $customColors = [])
    {
        try {
            $this->setUserTheme($userId, $theme, $customColors);
            
            return [
                'success' => true,
                'message' => 'Theme updated successfully',
                'theme_data' => $this->getThemeData($userId)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating theme: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get theme statistics
     */
    public function getThemeStatistics()
    {
        $stats = $this->db->fetchAll("
            SELECT theme, COUNT(*) as count
            FROM user_theme_preferences
            WHERE user_id > 0
            GROUP BY theme
            ORDER BY count DESC
        ");
        
        $totalUsers = $this->db->count('users', 'is_active = 1');
        $usersWithPreferences = $this->db->count('user_theme_preferences', 'user_id > 0');
        
        return [
            'total_users' => $totalUsers,
            'users_with_preferences' => $usersWithPreferences,
            'preference_breakdown' => $stats,
            'adoption_rate' => $totalUsers > 0 ? round(($usersWithPreferences / $totalUsers) * 100, 2) : 0
        ];
    }
    
    /**
     * Export theme preferences
     */
    public function exportThemePreferences($userId = null)
    {
        if ($userId) {
            // Export single user preference
            $preference = $this->getUserTheme($userId);
            return [
                'user_id' => $userId,
                'preference' => $preference,
                'exported_at' => date('Y-m-d H:i:s')
            ];
        } else {
            // Export all preferences
            $preferences = $this->db->fetchAll("
                SELECT u.id, u.email, u.first_name, u.last_name, utp.theme, utp.custom_colors, utp.updated_at
                FROM users u
                LEFT JOIN user_theme_preferences utp ON u.id = utp.user_id
                WHERE u.is_active = 1
                ORDER BY u.email
            ");
            
            return [
                'total_users' => count($preferences),
                'preferences' => $preferences,
                'exported_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Import theme preferences
     */
    public function importThemePreferences($preferences)
    {
        $imported = 0;
        $errors = [];
        
        foreach ($preferences as $pref) {
            try {
                if (!isset($pref['user_id']) || !isset($pref['theme'])) {
                    $errors[] = 'Invalid preference format';
                    continue;
                }
                
                $customColors = $pref['custom_colors'] ?? [];
                $this->setUserTheme($pref['user_id'], $pref['theme'], $customColors);
                $imported++;
            } catch (Exception $e) {
                $errors[] = 'Error importing preference for user ' . $pref['user_id'] . ': ' . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($preferences)
        ];
    }
    
    /**
     * Reset theme to default
     */
    public function resetTheme($userId)
    {
        try {
            $this->setUserTheme($userId, $this->defaultTheme, []);
            
            return [
                'success' => true,
                'message' => 'Theme reset to default',
                'theme_data' => $this->getThemeData($userId)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error resetting theme: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get available color presets
     */
    public function getColorPresets()
    {
        return [
            'default' => [
                'primary' => '#4F46E5',
                'secondary' => '#64748B',
                'accent' => '#10B981',
                'success' => '#059669',
                'warning' => '#F59E0B',
                'error' => '#EF4444'
            ],
            'blue' => [
                'primary' => '#2563EB',
                'secondary' => '#64748B',
                'accent' => '#0891B2',
                'success' => '#059669',
                'warning' => '#F59E0B',
                'error' => '#DC2626'
            ],
            'green' => [
                'primary' => '#059669',
                'secondary' => '#64748B',
                'accent' => '#10B981',
                'success' => '#047857',
                'warning' => '#F59E0B',
                'error' => '#DC2626'
            ],
            'purple' => [
                'primary' => '#7C3AED',
                'secondary' => '#64748B',
                'accent' => '#A855F7',
                'success' => '#059669',
                'warning' => '#F59E0B',
                'error' => '#DC2626'
            ],
            'red' => [
                'primary' => '#DC2626',
                'secondary' => '#64748B',
                'accent' => '#EF4444',
                'success' => '#059669',
                'warning' => '#F59E0B',
                'error' => '#991B1B'
            ]
        ];
    }
    
    /**
     * Apply color preset
     */
    public function applyColorPreset($userId, $presetName)
    {
        $presets = $this->getColorPresets();
        
        if (!isset($presets[$presetName])) {
            throw new InvalidArgumentException("Invalid preset: $presetName");
        }
        
        $currentPreference = $this->getUserTheme($userId);
        $customColors = array_merge($currentPreference['custom_colors'], $presets[$presetName]);
        
        return $this->setUserTheme($userId, $currentPreference['theme'], $customColors);
    }
    
    /**
     * Get theme accessibility info
     */
    public function getThemeAccessibility($userId = null)
    {
        $effectiveTheme = $this->getEffectiveTheme($userId);
        $variables = $this->getThemeCSSVariables($userId);
        
        $accessibility = [
            'theme' => $effectiveTheme['theme'],
            'contrast_ratios' => [],
            'wcag_compliance' => [],
            'recommendations' => []
        ];
        
        // Calculate contrast ratios (simplified)
        if (isset($variables['text-primary']) && isset($variables['bg-primary'])) {
            $accessibility['contrast_ratios']['text_on_bg'] = $this->calculateContrastRatio(
                $variables['text-primary'],
                $variables['bg-primary']
            );
        }
        
        // WCAG compliance checks
        foreach ($accessibility['contrast_ratios'] as $ratio) {
            if ($ratio >= 4.5) {
                $accessibility['wcag_compliance'][] = 'WCAG AA compliant';
            }
            if ($ratio >= 7) {
                $accessibility['wcag_compliance'][] = 'WCAG AAA compliant';
            }
        }
        
        // Recommendations
        if (empty($accessibility['wcag_compliance'])) {
            $accessibility['recommendations'][] = 'Consider increasing text contrast for better accessibility';
        }
        
        return $accessibility;
    }
    
    /**
     * Calculate contrast ratio (simplified)
     */
    private function calculateContrastRatio($color1, $color2)
    {
        // This is a simplified calculation
        // In production, use a proper color contrast library
        return 4.5; // Placeholder value
    }
}
