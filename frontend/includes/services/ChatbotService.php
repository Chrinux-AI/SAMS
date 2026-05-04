<?php
/**
 * Chatbot Foundation Service
 * Intent recognition, role-aware responses, and navigation assistance
 */

class SAMS_ChatbotService extends SAMS_BaseService {
    
    private $intents = [];
    private $userRole;
    private $conversationHistory = [];
    
    public function __construct($container) {
        parent::__construct($container);
        $this->initializeIntents();
        $this->userRole = $_SESSION['role'] ?? 'guest';
    }
    
    /**
     * Initialize intent patterns
     */
    private function initializeIntents() {
        $this->intents = [
            'navigation' => [
                'patterns' => [
                    'open {page}',
                    'go to {page}',
                    'show me {page}',
                    'take me to {page}',
                    'where is {page}',
                    'navigate to {page}'
                ],
                'pages' => [
                    'dashboard' => ['dashboard', 'home', 'main page'],
                    'attendance' => ['attendance', 'attendance page', 'mark attendance'],
                    'classes' => ['classes', 'class page', 'my classes'],
                    'students' => ['students', 'student list', 'all students'],
                    'teachers' => ['teachers', 'teacher list', 'all teachers'],
                    'profile' => ['profile', 'my profile', 'account settings'],
                    'reports' => ['reports', 'report page', 'analytics'],
                    'settings' => ['settings', 'configuration', 'preferences']
                ]
            ],
            'help' => [
                'patterns' => [
                    'how do I {action}',
                    'how to {action}',
                    'help me {action}',
                    'I need help with {action}',
                    'what is {topic}',
                    'explain {topic}'
                ],
                'actions' => [
                    'add student' => ['add student', 'create student', 'new student'],
                    'add teacher' => ['add teacher', 'create teacher', 'new teacher'],
                    'create class' => ['create class', 'add class', 'new class'],
                    'take attendance' => ['take attendance', 'mark attendance', 'record attendance'],
                    'generate report' => ['generate report', 'create report', 'export data']
                ]
            ],
            'information' => [
                'patterns' => [
                    'what are my {items}',
                    'show my {items}',
                    'list my {items}',
                    'today\'s {items}',
                    'current {items}'
                ],
                'items' => [
                    'classes' => ['classes', 'schedule', 'timetable'],
                    'students' => ['students', 'student count', 'enrolled students'],
                    'tasks' => ['tasks', 'to-do', 'pending work'],
                    'notifications' => ['notifications', 'alerts', 'messages']
                ]
            ],
            'greeting' => [
                'patterns' => [
                    'hello', 'hi', 'hey', 'good morning', 'good afternoon', 
                    'good evening', 'howdy', 'what\'s up'
                ]
            ],
            'goodbye' => [
                'patterns' => [
                    'bye', 'goodbye', 'see you', 'later', 'talk to you later',
                    'have a good day', 'cya'
                ]
            ],
            'thanks' => [
                'patterns' => [
                    'thanks', 'thank you', 'appreciate it', 'grateful', 'helpful'
                ]
            ]
        ];
    }
    
    /**
     * Process user message and generate response
     */
    public function processMessage($message) {
        $message = strtolower(trim($message));
        
        // Log conversation
        $this->logConversation($message, 'user');
        
        // Detect intent
        $intent = $this->detectIntent($message);
        
        // Generate response based on intent. If unknown, use our Local AI.
        if ($intent['type'] === 'unknown') {
            require_once __DIR__ . '/../../ai/local-inference.php';
            $localAI = new SentinelLocalAI();
            
            // Reconstruct recent conversation history for context
            $messages = [
                ['role' => 'system', 'content' => "You are the SAMS Public AI (School Attendance Management System Assistant). Be helpful, professional, and concise. Your school name is Academic Sentinel."]
            ];
            
            // Add history
            foreach ($this->conversationHistory as $hist) {
                $role = ($hist['sender'] === 'user') ? 'user' : 'assistant';
                $messages[] = ['role' => $role, 'content' => $hist['message']];
            }
            
            $aiResult = $localAI->generateResponse($messages);
            if ($aiResult['success']) {
                $response = rtrim(trim($aiResult['content']), '"');
                $response = ltrim($response, '"');
            } else {
                $response = $this->generateFallbackResponse();
            }
        } else {
            $response = $this->generateResponse($intent, $message);
        }
        
        // Log bot response
        $this->logConversation($response, 'bot');
        
        return [
            'intent' => $intent['type'],
            'confidence' => $intent['confidence'],
            'response' => $response,
            'suggested_actions' => $this->getSuggestedActions($intent),
            'context' => $this->getContext()
        ];
    }
    
    /**
     * Detect intent from message
     */
    private function detectIntent($message) {
        $bestMatch = [
            'type' => 'unknown',
            'confidence' => 0,
            'parameters' => []
        ];
        
        foreach ($this->intents as $intentType => $intentData) {
            foreach ($intentData['patterns'] as $pattern) {
                $score = $this->calculateMatchScore($message, $pattern);
                
                if ($score > $bestMatch['confidence']) {
                    $bestMatch = [
                        'type' => $intentType,
                        'confidence' => $score,
                        'parameters' => $this->extractParameters($message, $pattern, $intentData)
                    ];
                }
            }
        }
        
        // Normalize confidence
        $bestMatch['confidence'] = min(1.0, $bestMatch['confidence']);
        
        return $bestMatch;
    }
    
    /**
     * Calculate match score between message and pattern
     */
    private function calculateMatchScore($message, $pattern) {
        // Simple keyword matching
        $patternWords = explode(' ', strtolower($pattern));
        $messageWords = explode(' ', $message);
        
        $matches = 0;
        foreach ($patternWords as $word) {
            if (in_array($word, $messageWords)) {
                $matches++;
            }
        }
        
        // Check for exact phrase match
        if (strpos($message, $pattern) !== false) {
            return 1.0;
        }
        
        // Partial match scoring
        return $matches / max(count($patternWords), 1);
    }
    
    /**
     * Extract parameters from message based on pattern
     */
    private function extractParameters($message, $pattern, $intentData) {
        $params = [];
        
        // Extract {page}, {action}, {items}, {topic} from message
        if (isset($intentData['pages'])) {
            foreach ($intentData['pages'] as $page => $aliases) {
                foreach ($aliases as $alias) {
                    if (strpos($message, $alias) !== false) {
                        $params['page'] = $page;
                        break 2;
                    }
                }
            }
        }
        
        if (isset($intentData['actions'])) {
            foreach ($intentData['actions'] as $action => $aliases) {
                foreach ($aliases as $alias) {
                    if (strpos($message, $alias) !== false) {
                        $params['action'] = $action;
                        break 2;
                    }
                }
            }
        }
        
        if (isset($intentData['items'])) {
            foreach ($intentData['items'] as $item => $aliases) {
                foreach ($aliases as $alias) {
                    if (strpos($message, $alias) !== false) {
                        $params['item'] = $item;
                        break 2;
                    }
                }
            }
        }
        
        return $params;
    }
    
    /**
     * Generate response based on intent
     */
    private function generateResponse($intent, $originalMessage) {
        switch ($intent['type']) {
            case 'navigation':
                return $this->generateNavigationResponse($intent);
                
            case 'help':
                return $this->generateHelpResponse($intent);
                
            case 'information':
                return $this->generateInformationResponse($intent);
                
            case 'greeting':
                return $this->generateGreetingResponse();
                
            case 'goodbye':
                return "Goodbye! Have a great day!";
                
            case 'thanks':
                return "You're welcome! Let me know if you need anything else.";
                
            default:
                return $this->generateFallbackResponse();
        }
    }
    
    /**
     * Generate navigation response
     */
    private function generateNavigationResponse($intent) {
        $page = $intent['parameters']['page'] ?? null;
        
        if (!$page) {
            return "I can help you navigate to different pages. Which page would you like to go to? (dashboard, classes, students, etc.)";
        }
        
        // Check if user has permission for this page
        if (!$this->canAccessPage($page)) {
            return "Sorry, you don't have access to the $page page based on your role ($this->userRole).";
        }
        
        $urls = [
            'dashboard' => $this->getRoleDashboard(),
            'attendance' => $this->getRolePath('attendance.php'),
            'classes' => $this->getRolePath('classes.php'),
            'students' => $this->getRolePath('students.php'),
            'teachers' => $this->getRolePath('teachers.php'),
            'profile' => $this->getRolePath('profile.php'),
            'reports' => $this->getRolePath('reports.php'),
            'settings' => $this->getRolePath('settings.php')
        ];
        
        $url = $urls[$page] ?? null;
        
        if ($url) {
            return "I'll take you to the $page page. [Click here to navigate]($url)";
        }
        
        return "I found the $page page, but I'm not sure of the exact URL. Please check the navigation menu.";
    }
    
    /**
     * Generate help response
     */
    private function generateHelpResponse($intent) {
        $action = $intent['parameters']['action'] ?? null;
        
        $helpContent = [
            'add student' => "To add a student:\n1. Go to Students page\n2. Click 'Add Student'\n3. Fill in the required information\n4. Save",
            'add teacher' => "To add a teacher:\n1. Go to Teachers page\n2. Click 'Add Teacher'\n3. Fill in the required information\n4. Save",
            'create class' => "To create a class:\n1. Go to Classes page\n2. Click 'Create Class'\n3. Enter class name, grade level\n4. Assign teacher\n5. Save",
            'take attendance' => "To take attendance:\n1. Go to Attendance page\n2. Select class and date\n3. Mark present/absent for each student\n4. Save attendance",
            'generate report' => "To generate a report:\n1. Go to Reports page\n2. Select report type\n3. Choose date range\n4. Click Generate"
        ];
        
        if ($action && isset($helpContent[$action])) {
            return $helpContent[$action];
        }
        
        return "I can help you with:\n- Adding students or teachers\n- Creating classes\n- Taking attendance\n- Generating reports\n\nWhat would you like help with?";
    }
    
    /**
     * Generate information response
     */
    private function generateInformationResponse($intent) {
        $item = $intent['parameters']['item'] ?? null;
        
        // Get real data based on user role
        $tenantId = $_SESSION['tenant_id'] ?? 1;
        
        switch ($item) {
            case 'classes':
                if ($this->userRole === 'teacher') {
                    $result = $this->db->query("SELECT COUNT(*) as count FROM classes WHERE teacher_id = " . ($_SESSION['user_id'] ?? 0));
                } else {
                    $result = $this->db->query("SELECT COUNT(*) as count FROM classes WHERE tenant_id = $tenantId");
                }
                $count = $result ? mysqli_fetch_assoc($result)['count'] : 0;
                return "You have $count classes today.";
                
            case 'students':
                $result = $this->db->query("SELECT COUNT(*) as count FROM students s JOIN users u ON s.user_id = u.id WHERE u.tenant_id = $tenantId AND u.status = 'active'");
                $count = $result ? mysqli_fetch_assoc($result)['count'] : 0;
                return "There are $count students in the system.";
                
            case 'tasks':
                return "You have no pending tasks at the moment.";
                
            case 'notifications':
                return "You have 3 unread notifications. Check the notification bell in the top right.";
                
            default:
                return "I can tell you about your classes, students, tasks, or notifications. What would you like to know?";
        }
    }
    
    /**
     * Generate greeting response
     */
    private function generateGreetingResponse() {
        $hour = date('H');
        
        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 17) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }
        
        $name = $_SESSION['user_name'] ?? '';
        $role = ucfirst(str_replace('_', ' ', $this->userRole));
        
        return "$greeting$name! I'm your SAMS assistant. I can help you navigate, answer questions, or provide information about your school. What can I do for you?";
    }
    
    /**
     * Generate fallback response for unknown intents
     */
    private function generateFallbackResponse() {
        $fallbacks = [
            "I'm not sure I understood that. I can help you with navigation, answering questions, or providing information. What would you like to do?",
            "I didn't catch that. Try asking me to 'open dashboard', 'how do I add a student', or 'what are my classes'?",
            "Hmm, I'm not sure what you mean. I can help navigate pages, explain features, or show your information. What do you need?"
        ];
        
        return $fallbacks[array_rand($fallbacks)];
    }
    
    /**
     * Check if user can access page
     */
    private function canAccessPage($page) {
        $rolePermissions = [
            'admin' => ['dashboard', 'attendance', 'classes', 'students', 'teachers', 'profile', 'reports', 'settings'],
            'teacher' => ['dashboard', 'attendance', 'classes', 'students', 'profile'],
            'student' => ['dashboard', 'attendance', 'classes', 'profile'],
            'parent' => ['dashboard', 'attendance', 'profile']
        ];
        
        $allowed = $rolePermissions[$this->userRole] ?? [];
        return in_array($page, $allowed);
    }
    
    /**
     * Get suggested actions based on intent
     */
    private function getSuggestedActions($intent) {
        $suggestions = [];
        
        switch ($intent['type']) {
            case 'navigation':
                $suggestions = ['Go to Dashboard', 'View Classes', 'Check Attendance'];
                break;
            case 'help':
                $suggestions = ['Add Student', 'Create Class', 'Take Attendance'];
                break;
            case 'information':
                $suggestions = ['View Reports', 'Check Notifications', 'My Profile'];
                break;
            default:
                $suggestions = ['Help', 'Dashboard', 'My Classes'];
        }
        
        return $suggestions;
    }
    
    /**
     * Get current conversation context
     */
    private function getContext() {
        return [
            'role' => $this->userRole,
            'last_intent' => end($this->conversationHistory)['intent'] ?? null,
            'history_count' => count($this->conversationHistory)
        ];
    }
    
    /**
     * Log conversation for training
     */
    private function logConversation($message, $sender) {
        $this->conversationHistory[] = [
            'sender' => $sender,
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Keep only last 20 messages
        if (count($this->conversationHistory) > 20) {
            array_shift($this->conversationHistory);
        }
        
        // Log to database for future training
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId && $sender === 'user') {
            $escapedMessage = mysqli_real_escape_string($this->db, substr($message, 0, 500));
            $intent = $this->detectIntent($message);
            $intentType = mysqli_real_escape_string($this->db, $intent['type']);
            
            $this->db->query("INSERT INTO chatbot_logs (user_id, message, detected_intent, created_at) 
                            VALUES ($userId, '$escapedMessage', '$intentType', NOW())");
        }
    }
    
    /**
     * Get role-specific dashboard URL
     */
    private function getRoleDashboard() {
        $dashboards = [
            'admin' => 'admin/index.php',
            'super_admin' => 'admin/index.php',
            'teacher' => 'teacher/index.php',
            'student' => 'student/index.php',
            'parent' => 'parent/index.php'
        ];
        
        return $dashboards[$this->userRole] ?? 'index.php';
    }
    
    /**
     * Get role-specific path for a page
     */
    private function getRolePath($page) {
        $base = '';
        
        switch ($this->userRole) {
            case 'admin':
            case 'super_admin':
                $base = 'admin/';
                break;
            case 'teacher':
                $base = 'teacher/';
                break;
            case 'student':
                $base = 'student/';
                break;
            case 'parent':
                $base = 'parent/';
                break;
        }
        
        return $base . $page;
    }
}
