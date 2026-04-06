<?php
/**
 * SAMS AI Admin Controller
 * Handles AI-powered automation for admin tasks
 * Provides endpoints for Google Form processing and account creation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../app/services/AiExtractionService.php';

class AiAdminController
{
    private $aiService;
    private $logger;
    
    public function __construct()
    {
        $this->aiService = new AiExtractionService();
        $this->logger = new Logger('ai_admin_controller');
        
        // Verify admin access
        $this->requireAdminAccess();
    }
    
    /**
     * Process Google Form submissions endpoint
     * POST /admin/ai/process-submissions
     */
    public function processSubmissions()
    {
        try {
            $this->logger->info('Processing Google Form submissions', [
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
            ]);
            
            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Method not allowed. Use POST.'
                ], 405);
                return;
            }
            
            // Get JSON data
            $jsonData = file_get_contents('php://input');
            
            if (empty($jsonData)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'No data received. Please provide JSON data.'
                ], 400);
                return;
            }
            
            // Process the submission
            $result = $this->aiService->processGoogleFormSubmission($jsonData);
            
            if ($result['success']) {
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => $result['message'],
                    'job_id' => $result['job_id'],
                    'entities_found' => $result['entities_found'],
                    'check_status_url' => BASE_URL . '/admin/ai/job-status?job_id=' . $result['job_id']
                ], 200);
            } else {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error processing submissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get job status endpoint
     * GET /admin/ai/job-status?job_id={job_id}
     */
    public function getJobStatus()
    {
        try {
            $jobId = $_GET['job_id'] ?? null;
            
            if (empty($jobId)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Job ID is required'
                ], 400);
                return;
            }
            
            $result = $this->aiService->getJobStatus($jobId);
            
            if ($result['success']) {
                $this->sendJsonResponse([
                    'success' => true,
                    'job' => $result['job'],
                    'progress_percentage' => $result['progress_percentage']
                ], 200);
            } else {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => $result['message']
                ], 404);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error getting job status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify OTP endpoint
     * GET /admin/ai/verify-otp?token={token}&email={email}
     */
    public function verifyOTP()
    {
        try {
            $token = $_GET['token'] ?? null;
            $email = $_GET['email'] ?? null;
            
            if (empty($token) || empty($email)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Token and email are required'
                ], 400);
                return;
            }
            
            $result = $this->aiService->verifyOTP($token, $email);
            
            if ($result['success']) {
                // Redirect to password setup page
                $this->redirectToPasswordSetup($result['user_id'], $result['entity_type']);
            } else {
                // Show error page
                $this->showVerificationError($result['message']);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error verifying OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->showVerificationError('Verification failed. Please try again or contact support.');
        }
    }
    
    /**
     * Set password endpoint (after OTP verification)
     * POST /admin/ai/set-password
     */
    public function setPassword()
    {
        try {
            $userId = $_POST['user_id'] ?? null;
            $password = $_POST['password'] ?? null;
            $confirmPassword = $_POST['confirm_password'] ?? null;
            
            if (empty($userId) || empty($password) || empty($confirmPassword)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'All fields are required'
                ], 400);
                return;
            }
            
            if ($password !== $confirmPassword) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Passwords do not match'
                ], 400);
                return;
            }
            
            if (strlen($password) < 8) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Password must be at least 8 characters long'
                ], 400);
                return;
            }
            
            // Update user password
            $db = db();
            $db->update('users', [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'password_set_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$userId]);
            
            // Log password set
            $this->logger->info('Password set for user', ['user_id' => $userId]);
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Password set successfully. You can now login.',
                'login_url' => BASE_URL . '/login.php'
            ], 200);
            
        } catch (Exception $e) {
            $this->logger->error('Error setting password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get queue statistics endpoint
     * GET /admin/ai/queue-stats
     */
    public function getQueueStatistics()
    {
        try {
            $result = $this->aiService->getQueueStatistics();
            
            if ($result['success']) {
                $this->sendJsonResponse([
                    'success' => true,
                    'statistics' => $result['statistics']
                ], 200);
            } else {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error getting queue statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process pending jobs endpoint
     * POST /admin/ai/process-jobs
     */
    public function processPendingJobs()
    {
        try {
            $this->logger->info('Processing pending jobs');
            
            // Get pending jobs
            $db = db();
            $pendingJobs = $db->fetchAll(
                "SELECT id FROM jobs WHERE queue_name = 'ai_extraction' AND status = 'pending' ORDER BY created_at ASC LIMIT 10"
            );
            
            $processed = 0;
            $results = [];
            
            foreach ($pendingJobs as $job) {
                try {
                    $jobResult = $this->aiService->processExtractionJob($job['id']);
                    $results[] = [
                        'job_id' => $job['id'],
                        'success' => true,
                        'results' => $jobResult
                    ];
                    $processed++;
                } catch (Exception $e) {
                    $results[] = [
                        'job_id' => $job['id'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => "Processed $processed jobs",
                'processed' => $processed,
                'results' => $results
            ], 200);
            
        } catch (Exception $e) {
            $this->logger->error('Error processing pending jobs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * AI dashboard endpoint
     * GET /admin/ai/dashboard
     */
    public function dashboard()
    {
        try {
            // Get queue statistics
            $queueStats = $this->aiService->getQueueStatistics();
            
            // Get recent jobs
            $db = db();
            $recentJobs = $db->fetchAll(
                "SELECT id, status, created_at, updated_at, progress, total FROM jobs 
                 WHERE queue_name = 'ai_extraction' 
                 ORDER BY created_at DESC 
                 LIMIT 10"
            );
            
            // Get processing statistics
            $processingStats = $this->getProcessingStatistics();
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => [
                    'queue_statistics' => $queueStats['statistics'] ?? [],
                    'recent_jobs' => $recentJobs,
                    'processing_statistics' => $processingStats
                ]
            ], 200);
            
        } catch (Exception $e) {
            $this->logger->error('Error loading dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get processing statistics
     */
    private function getProcessingStatistics()
    {
        $db = db();
        
        // Jobs processed today
        $todayStats = $db->fetchOne(
            "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM jobs WHERE queue_name = 'ai_extraction' AND DATE(created_at) = CURDATE()"
        );
        
        // Jobs processed this week
        $weekStats = $db->fetchOne(
            "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM jobs WHERE queue_name = 'ai_extraction' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        );
        
        // Average processing time
        $avgProcessingTime = $db->fetchOne(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_time
             FROM jobs WHERE queue_name = 'ai_extraction' AND status = 'completed' AND completed_at IS NOT NULL"
        );
        
        return [
            'today' => $todayStats,
            'week' => $weekStats,
            'average_processing_time' => round($avgProcessingTime['avg_time'] ?? 0, 2)
        ];
    }
    
    /**
     * Require admin access
     */
    private function requireAdminAccess()
    {
        session_start();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
            exit;
        }
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
    
    /**
     * Redirect to password setup page
     */
    private function redirectToPasswordSetup($userId, $entityType)
    {
        $url = BASE_URL . "/admin/ai/password-setup?user_id=" . $userId . "&type=" . $entityType;
        header("Location: " . $url);
        exit;
    }
    
    /**
     * Show verification error page
     */
    private function showVerificationError($message)
    {
        // Simple error page
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Verification Failed - SAMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error { color: #dc3545; }
        .container { max-width: 600px; margin: 0 auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1 class='error'>Verification Failed</h1>
        <p>" . htmlspecialchars($message) . "</p>
        <p>Please check your email for the correct verification link or contact support.</p>
        <a href='" . BASE_URL . "/login.php' class='btn'>Go to Login</a>
    </div>
</body>
</html>";
        exit;
    }
}

// Route handler
if (isset($_GET['action'])) {
    $controller = new AiAdminController();
    
    switch ($_GET['action']) {
        case 'process-submissions':
            $controller->processSubmissions();
            break;
        case 'job-status':
            $controller->getJobStatus();
            break;
        case 'verify-otp':
            $controller->verifyOTP();
            break;
        case 'set-password':
            $controller->setPassword();
            break;
        case 'queue-stats':
            $controller->getQueueStatistics();
            break;
        case 'process-jobs':
            $controller->processPendingJobs();
            break;
        case 'dashboard':
            $controller->dashboard();
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
        'message' => 'SAMS AI Admin API',
        'endpoints' => [
            'POST /admin/ai/process-submissions' => 'Process Google Form submissions',
            'GET /admin/ai/job-status?job_id={id}' => 'Get job processing status',
            'GET /admin/ai/verify-otp?token={token}&email={email}' => 'Verify OTP token',
            'POST /admin/ai/set-password' => 'Set account password',
            'GET /admin/ai/queue-stats' => 'Get queue statistics',
            'POST /admin/ai/process-jobs' => 'Process pending jobs',
            'GET /admin/ai/dashboard' => 'AI dashboard data'
        ]
    ]);
}
?>
