<?php

/**
 * SAMS Theme API Controller
 * Handles theme management API endpoints
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../app/services/ThemeService.php';

class ThemeController
{
    private $themeService;

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function requireCsrfForPost(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($token)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'CSRF validation failed'
            ], 403);
        }
    }

    public function __construct()
    {
        $this->themeService = new ThemeService();
    }

    /**
     * Get user theme preference
     */
    public function getTheme()
    {
        $this->ensureSession();

        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'User not logged in'
            ], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $themeData = $this->themeService->getThemeData($userId);

        $this->sendJsonResponse([
            'success' => true,
            'theme_data' => $themeData
        ]);
    }

    /**
     * Set user theme preference
     */
    public function setTheme()
    {
        $this->ensureSession();
        $this->requireCsrfForPost();

        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'User not logged in'
            ], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $theme = $_POST['theme'] ?? 'light';
        $customColors = $_POST['custom_colors'] ?? [];

        // Validate theme
        $supportedThemes = ['light', 'dark'];
        if (!in_array($theme, $supportedThemes)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Invalid theme'
            ], 400);
            return;
        }

        try {
            $result = $this->themeService->handleThemeChange($userId, $theme, $customColors);

            if ($result['success']) {
                $this->sendJsonResponse($result);
            } else {
                $this->sendJsonResponse($result, 400);
            }
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error setting theme: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get theme statistics
     */
    public function getStatistics()
    {
        $this->ensureSession();

        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'owner'], true)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
            return;
        }

        $statistics = $this->themeService->getThemeStatistics();

        $this->sendJsonResponse([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Get color presets
     */
    public function getColorPresets()
    {
        $presets = $this->themeService->getColorPresets();

        $this->sendJsonResponse([
            'success' => true,
            'presets' => $presets
        ]);
    }

    /**
     * Apply color preset
     */
    public function applyColorPreset()
    {
        $this->ensureSession();
        $this->requireCsrfForPost();

        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'User not logged in'
            ], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $presetName = $_POST['preset'] ?? 'default';

        try {
            $this->themeService->applyColorPreset($userId, $presetName);

            $themeData = $this->themeService->getThemeData($userId);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Color preset applied successfully',
                'theme_data' => $themeData
            ]);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error applying preset: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reset theme
     */
    public function resetTheme()
    {
        $this->ensureSession();
        $this->requireCsrfForPost();

        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'User not logged in'
            ], 401);
            return;
        }

        $userId = $_SESSION['user_id'];

        try {
            $result = $this->themeService->resetTheme($userId);

            $this->sendJsonResponse($result);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error resetting theme: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export theme preferences
     */
    public function exportThemes()
    {
        $this->ensureSession();

        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'owner'], true)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
            return;
        }

        $userId = $_GET['user_id'] ?? null;
        $exportData = $this->themeService->exportThemePreferences($userId);

        $this->sendJsonResponse([
            'success' => true,
            'export_data' => $exportData
        ]);
    }

    /**
     * Import theme preferences
     */
    public function importThemes()
    {
        $this->ensureSession();
        $this->requireCsrfForPost();

        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'owner'], true)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
            return;
        }

        $preferences = json_decode(file_get_contents('php://input'), true);

        if (!$preferences || !is_array($preferences)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Invalid preferences data'
            ], 400);
            return;
        }

        try {
            $result = $this->themeService->importThemePreferences($preferences);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Theme preferences imported successfully',
                'result' => $result
            ]);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error importing preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get theme accessibility info
     */
    public function getAccessibility()
    {
        $this->ensureSession();

        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'User not logged in'
            ], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $accessibility = $this->themeService->getThemeAccessibility($userId);

        $this->sendJsonResponse([
            'success' => true,
            'accessibility' => $accessibility
        ]);
    }

    /**
     * Send JSON response
     */
    private function sendJsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// Route handler
if (isset($_GET['action'])) {
    $controller = new ThemeController();

    switch ($_GET['action']) {
        case 'get':
            $controller->getTheme();
            break;
        case 'set':
            $controller->setTheme();
            break;
        case 'statistics':
            $controller->getStatistics();
            break;
        case 'presets':
            $controller->getColorPresets();
            break;
        case 'apply-preset':
            $controller->applyColorPreset();
            break;
        case 'reset':
            $controller->resetTheme();
            break;
        case 'export':
            $controller->exportThemes();
            break;
        case 'import':
            $controller->importThemes();
            break;
        case 'accessibility':
            $controller->getAccessibility();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action'
            ]);
            break;
    }
} else {
    // Show available endpoints
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'SAMS Theme API',
        'endpoints' => [
            'GET /admin/api/theme?action=get' => 'Get user theme preference',
            'POST /admin/api/theme?action=set' => 'Set user theme preference',
            'GET /admin/api/theme?action=statistics' => 'Get theme statistics (admin only)',
            'GET /admin/api/theme?action=presets' => 'Get color presets',
            'POST /admin/api/theme?action=apply-preset' => 'Apply color preset',
            'POST /admin/api/theme?action=reset' => 'Reset theme to default',
            'GET /admin/api/theme?action=export' => 'Export theme preferences (admin only)',
            'POST /admin/api/theme?action=import' => 'Import theme preferences (admin only)',
            'GET /admin/api/theme?action=accessibility' => 'Get theme accessibility info'
        ]
    ]);
}
