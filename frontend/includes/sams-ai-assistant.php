<?php
/**
 * SAMS AI Assistant - Advanced Learning & Navigation System
 * Smart, helpful, and accessible AI for educational environment
 */

class SAMS_AI_Assistant {
    private $user_id;
    private $user_role;
    private $context;
    private $learning_data;
    
    public function __construct($user_id, $user_role) {
        $this->user_id = $user_id;
        $this->user_role = $user_role;
        $this->context = $this->buildUserContext();
        $this->learning_data = $this->loadLearningData();
    }
    
    /**
     * Build user context for personalized responses
     */
    private function buildUserContext() {
        try {
            $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$this->user_id]);
            
            $context = [
                'role' => $this->user_role,
                'name' => $user['full_name'] ?? 'User',
                'preferences' => $this->getUserPreferences(),
                'recent_activity' => $this->getRecentActivity(),
                'permissions' => $this->getUserPermissions(),
                'learning_style' => $this->analyzeLearningStyle()
            ];
            
            return $context;
        } catch (Exception $e) {
            return ['role' => $this->user_role, 'name' => 'User'];
        }
    }
    
    /**
     * Process user query and provide intelligent response
     */
    public function processQuery($query, $page_context = []) {
        // Security: Input sanitization
        $query = $this->sanitizeInput($query);
        
        // Analyze intent
        $intent = $this->analyzeIntent($query);
        
        // Generate response based on intent and context
        $response = $this->generateResponse($query, $intent, $page_context);
        
        // Learn from interaction
        $this->learnFromInteraction($query, $response);
        
        return $response;
    }
    
    /**
     * Analyze user intent from query
     */
    private function analyzeIntent($query) {
        $patterns = [
            'navigation' => '/(navigate|go to|find|show me|where is|how to get to)/i',
            'learning' => '/(learn|study|explain|teach me|help me understand|what is)/i',
            'attendance' => '/(attendance|check in|mark attendance|present|absent|late)/i',
            'schedule' => '/(schedule|timetable|classes|when is|what time)/i',
            'grades' => '/(grades|marks|scores|results|performance)/i',
            'help' => '/(help|assist|support|problem|issue|trouble)/i',
            'search' => '/(search|find|look for|show me)/i',
            'communication' => '/(message|chat|contact|talk to|communicate)/i'
        ];
        
        foreach ($patterns as $intent => $pattern) {
            if (preg_match($pattern, $query)) {
                return $intent;
            }
        }
        
        return 'general';
    }
    
    /**
     * Generate intelligent response
     */
    private function generateResponse($query, $intent, $page_context) {
        switch ($intent) {
            case 'navigation':
                return $this->handleNavigation($query);
            case 'learning':
                return $this->handleLearning($query);
            case 'attendance':
                return $this->handleAttendance($query);
            case 'schedule':
                return $this->handleSchedule($query);
            case 'grades':
                return $this->handleGrades($query);
            case 'help':
                return $this->handleHelp($query, $page_context);
            case 'search':
                return $this->handleSearch($query);
            case 'communication':
                return $this->handleCommunication($query);
            default:
                return $this->handleGeneral($query);
        }
    }
    
    /**
     * Handle navigation requests
     */
    private function handleNavigation($query) {
        $pages = $this->getAccessiblePages();
        
        // Extract page name from query
        foreach ($pages as $page => $info) {
            if (stripos($query, $page) !== false) {
                return [
                    'type' => 'navigation',
                    'message' => "I can help you navigate to {$info['title']}.",
                    'action' => 'navigate',
                    'target' => $info['url'],
                    'suggestions' => ["Take me to {$info['title']}", "Show me {$info['title']}"]
                ];
            }
        }
        
        return [
            'type' => 'navigation_help',
            'message' => "I can help you navigate to different pages. What are you looking for?",
            'options' => array_slice($pages, 0, 5),
            'suggestions' => array_keys(array_slice($pages, 0, 3))
        ];
    }
    
    /**
     * Handle learning requests
     */
    private function handleLearning($query) {
        $learning_resources = $this->getLearningResources();
        
        // Extract topic from query
        $topic = $this->extractTopic($query);
        
        if ($topic) {
            $resources = $this->findLearningResources($topic);
            
            return [
                'type' => 'learning',
                'message' => "I found some learning resources about {$topic} for you.",
                'resources' => $resources,
                'suggestions' => ["Tell me more about {$topic}", "Show me exercises for {$topic}"]
            ];
        }
        
        return [
            'type' => 'learning_help',
            'message' => "I can help you learn about various topics. What would you like to learn about?",
            'subjects' => array_keys($learning_resources),
            'suggestions' => array_keys(array_slice($learning_resources, 0, 3))
        ];
    }
    
    /**
     * Handle attendance-related queries
     */
    private function handleAttendance($query) {
        $today = date('Y-m-d');
        
        if ($this->user_role === 'student') {
            $attendance = $this->getStudentAttendance($this->user_id, $today);
            
            return [
                'type' => 'attendance',
                'message' => "Your attendance status for today: " . ($attendance['status'] ?? 'Not marked yet'),
                'data' => $attendance,
                'suggestions' => ["Check in now", "View attendance history"]
            ];
        } elseif ($this->user_role === 'teacher') {
            $classes = $this->getTeacherClasses($this->user_id);
            
            return [
                'type' => 'attendance_management',
                'message' => "You can manage attendance for your classes.",
                'classes' => $classes,
                'suggestions' => ["Mark attendance", "View attendance reports"]
            ];
        }
        
        return [
            'type' => 'attendance_info',
            'message' => "I can help you with attendance-related tasks.",
            'suggestions' => ["View attendance policy", "Check attendance history"]
        ];
    }
    
    /**
     * Handle schedule queries
     */
    private function handleSchedule($query) {
        if ($this->user_role === 'student') {
            $schedule = $this->getStudentSchedule($this->user_id);
        } elseif ($this->user_role === 'teacher') {
            $schedule = $this->getTeacherSchedule($this->user_id);
        } else {
            $schedule = $this->getGeneralSchedule();
        }
        
        $today_schedule = $this->getTodaySchedule($schedule);
        
        return [
            'type' => 'schedule',
            'message' => "Here's your schedule for today:",
            'schedule' => $today_schedule,
            'suggestions' => ["Show weekly schedule", "View class details"]
        ];
    }
    
    /**
     * Handle help requests with contextual assistance
     */
    private function handleHelp($query, $page_context) {
        $page_help = $this->getPageHelp($page_context);
        
        if (!empty($page_help)) {
            return [
                'type' => 'contextual_help',
                'message' => "I can help you with this page. " . $page_help['description'],
                'tips' => $page_help['tips'],
                'shortcuts' => $page_help['shortcuts'],
                'suggestions' => ["Show me tutorial", "Explain this feature"]
            ];
        }
        
        return [
            'type' => 'general_help',
            'message' => "I'm here to help! What do you need assistance with?",
            'help_topics' => $this->getHelpTopics(),
            'suggestions' => ["Navigation help", "Using features", "Account settings"]
        ];
    }
    
    /**
     * Handle search queries
     */
    private function handleSearch($query) {
        $search_term = $this->extractSearchTerm($query);
        $results = $this->performSearch($search_term);
        
        return [
            'type' => 'search',
            'message' => "I found " . count($results) . " results for '{$search_term}':",
            'results' => $results,
            'suggestions' => ["Refine search", "Search in different category"]
        ];
    }
    
    /**
     * Handle communication requests
     */
    private function handleCommunication($query) {
        $contacts = $this->getAvailableContacts();
        
        return [
            'type' => 'communication',
            'message' => "I can help you communicate with others.",
            'contacts' => $contacts,
            'suggestions' => ["Send message", "View conversations", "Contact support"]
        ];
    }
    
    /**
     * Handle general queries
     */
    private function handleGeneral($query) {
        // Check for common patterns
        if (stripos($query, 'who are you') !== false) {
            return [
                'type' => 'introduction',
                'message' => "I'm SAMS AI Assistant, your intelligent learning companion. I can help you navigate, learn, manage attendance, and much more!",
                'capabilities' => $this->getCapabilities(),
                'suggestions' => ["What can you do?", "Show me features"]
            ];
        }
        
        if (stripos($query, 'thank') !== false) {
            return [
                'type' => 'acknowledgment',
                'message' => "You're welcome! I'm always here to help you succeed.",
                'suggestions' => ["Ask another question", "Get help with something"]
            ];
        }
        
        return [
            'type' => 'general_response',
            'message' => "I understand you're asking about: " . substr($query, 0, 50) . "...",
            'suggestions' => ["Be more specific", "Show help topics", "Navigate to page"]
        ];
    }
    
    /**
     * Get accessible pages based on user role
     */
    private function getAccessiblePages() {
        $pages = [
            'dashboard' => ['title' => 'Dashboard', 'url' => $this->getRolePath() . '/dashboard.php'],
            'attendance' => ['title' => 'Attendance', 'url' => $this->getRolePath() . '/attendance.php'],
            'schedule' => ['title' => 'Schedule', 'url' => $this->getRolePath() . '/schedule.php'],
            'grades' => ['title' => 'Grades', 'url' => $this->getRolePath() . '/grades.php'],
            'messages' => ['title' => 'Messages', 'url' => '../messages.php'],
            'profile' => ['title' => 'Profile', 'url' => $this->getRolePath() . '/profile.php'],
            'settings' => ['title' => 'Settings', 'url' => $this->getRolePath() . '/settings.php']
        ];
        
        // Role-specific pages
        if ($this->user_role === 'admin') {
            $pages['students'] = ['title' => 'Students', 'url' => '../admin/students.php'];
            $pages['teachers'] = ['title' => 'Teachers', 'url' => '../admin/teachers.php'];
            $pages['classes'] = ['title' => 'Classes', 'url' => '../admin/classes.php'];
            $pages['reports'] = ['title' => 'Reports', 'url' => '../admin/reports.php'];
        } elseif ($this->user_role === 'teacher') {
            $pages['my classes'] = ['title' => 'My Classes', 'url' => '../teacher/my-classes.php'];
            $pages['students'] = ['title' => 'Students', 'url' => '../teacher/students.php'];
            $pages['assignments'] = ['title' => 'Assignments', 'url' => '../teacher/assignments.php'];
        } elseif ($this->user_role === 'student') {
            $pages['check in'] = ['title' => 'Check In', 'url' => '../student/checkin.php'];
            $pages['assignments'] = ['title' => 'Assignments', 'url' => '../student/assignments.php'];
        } elseif ($this->user_role === 'parent') {
            $pages['children'] = ['title' => 'Children', 'url' => '../parent/children.php'];
            $pages['link children'] = ['title' => 'Link Children', 'url' => '../parent/link-children.php'];
        }
        
        return $pages;
    }
    
    /**
     * Get role-specific path
     */
    private function getRolePath() {
        return '../' . $this->user_role;
    }
    
    /**
     * Security: Sanitize user input
     */
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Learn from user interactions
     */
    private function learnFromInteraction($query, $response) {
        // Store interaction for improving future responses
        $this->saveLearningData([
            'user_id' => $this->user_id,
            'query' => $query,
            'response_type' => $response['type'],
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $this->context
        ]);
    }
    
    /**
     * Get user preferences
     */
    private function getUserPreferences() {
        // Get user preferences from database or defaults
        return [
            'language' => 'en',
            'notification_level' => 'normal',
            'ui_theme' => 'default',
            'learning_pace' => 'moderate'
        ];
    }
    
    /**
     * Get recent activity
     */
    private function getRecentActivity() {
        // Get user's recent activity for context
        return [];
    }
    
    /**
     * Get user permissions
     */
    private function getUserPermissions() {
        // Get user's permissions based on role
        return [];
    }
    
    /**
     * Analyze learning style
     */
    private function analyzeLearningStyle() {
        // Analyze user's learning patterns
        return 'visual';
    }
    
    /**
     * Get AI capabilities
     */
    private function getCapabilities() {
        return [
            'Navigation assistance',
            'Learning support',
            'Schedule management',
            'Attendance tracking',
            'Communication help',
            'Search functionality',
            'Contextual help'
        ];
    }
    
    // Additional helper methods would be implemented here
    private function loadLearningData() { return []; }
    private function extractTopic($query) { return ''; }
    private function getLearningResources() { return []; }
    private function findLearningResources($topic) { return []; }
    private function getStudentAttendance($user_id, $date) { return []; }
    private function getTeacherClasses($user_id) { return []; }
    private function getStudentSchedule($user_id) { return []; }
    private function getTeacherSchedule($user_id) { return []; }
    private function getGeneralSchedule() { return []; }
    private function getTodaySchedule($schedule) { return []; }
    private function getPageHelp($context) { return []; }
    private function getHelpTopics() { return []; }
    private function extractSearchTerm($query) { return ''; }
    private function performSearch($term) { return []; }
    private function getAvailableContacts() { return []; }
    private function saveLearningData($data) { return; }
}
?>
