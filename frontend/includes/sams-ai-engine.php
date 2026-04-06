<?php

/**
 * SAMS AI Engine
 * Tenant-aware assistant with role navigation, learning guidance, and moderate security.
 */

class SAMS_AI_Engine
{
    private $userId;
    private $userRole;
    private $sessionId;
    private $ipAddress;
    private $userAgent;
    private $tenant;

    public function __construct($userId, $userRole, $sessionId = '', $ipAddress = '', $userAgent = '')
    {
        $this->userId = (int)$userId;
        $this->userRole = strtolower((string)$userRole);
        $this->sessionId = (string)$sessionId;
        $this->ipAddress = substr((string)$ipAddress, 0, 45);
        $this->userAgent = substr((string)$userAgent, 0, 255);
        $this->tenant = $this->resolveTenant();
    }

    public function getTenantContext()
    {
        return $this->tenant;
    }

    public function enforceRateLimit($message = '')
    {
        $now = time();
        $minuteWindow = $now - 60;
        $hourWindow = $now - 3600;

        if (!isset($_SESSION['ai_rate_limiter'])) {
            $_SESSION['ai_rate_limiter'] = [];
        }

        $bucketKey = 'u' . $this->userId;
        $bucket = $_SESSION['ai_rate_limiter'][$bucketKey] ?? [];
        $bucket = array_values(array_filter($bucket, function ($ts) use ($hourWindow) {
            return (int)$ts >= $hourWindow;
        }));

        $inMinute = 0;
        foreach ($bucket as $ts) {
            if ((int)$ts >= $minuteWindow) {
                $inMinute++;
            }
        }

        $maxPerMinute = 18;
        $maxPerHour = 180;

        if (count($bucket) >= $maxPerHour || $inMinute >= $maxPerMinute) {
            $retryAfter = 60;
            if ($inMinute >= $maxPerMinute && !empty($bucket)) {
                $recent = array_values(array_filter($bucket, function ($ts) use ($minuteWindow) {
                    return (int)$ts >= $minuteWindow;
                }));
                if (!empty($recent)) {
                    $oldestRecent = min($recent);
                    $retryAfter = max(1, 60 - ($now - (int)$oldestRecent));
                }
            }

            return [
                'allowed' => false,
                'retry_after' => $retryAfter,
                'remaining_minute' => 0,
                'remaining_hour' => max(0, $maxPerHour - count($bucket))
            ];
        }

        $bucket[] = $now;
        $_SESSION['ai_rate_limiter'][$bucketKey] = $bucket;

        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining_minute' => max(0, $maxPerMinute - ($inMinute + 1)),
            'remaining_hour' => max(0, $maxPerHour - count($bucket))
        ];
    }

    public function securityAssessment($message)
    {
        $text = strtolower((string)$message);
        $risk = 0;
        $issues = [];

        $patterns = [
            'script_tag' => '/<script\b/i',
            'sql_pattern' => '/\b(select\s+.+\s+from|union\s+select|drop\s+table|insert\s+into|delete\s+from)\b/i',
            'js_uri' => '/javascript\s*:/i',
            'event_handler' => '/on\w+\s*=/i',
            'path_traversal' => '/\.\.\//',
            'credential_harvest' => '/\b(password|otp|token|secret|api key|auth token)\b/i'
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $message)) {
                $issues[] = $name;
                $risk += ($name === 'credential_harvest') ? 2 : 3;
            }
        }

        if (strlen($message) > 800) {
            $issues[] = 'message_too_long';
            $risk += 2;
        }

        $riskLevel = 'low';
        if ($risk >= 6) {
            $riskLevel = 'high';
        } elseif ($risk >= 3) {
            $riskLevel = 'medium';
        }

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $risk,
            'issues' => $issues,
            'allow' => $riskLevel !== 'high'
        ];
    }

    public function processMessage($message, array $context = [])
    {
        $clean = $this->sanitizeMessage($message);
        $intent = $this->detectIntent($clean);

        $result = [
            'intent' => $intent,
            'message' => '',
            'suggestions' => [],
            'actions' => [],
            'learning_cards' => [],
            'tenant' => $this->tenant,
            'meta' => [
                'role' => $this->userRole,
                'tenant_id' => $this->tenant['id']
            ]
        ];

        switch ($intent) {
            case 'navigation':
                $result = array_merge($result, $this->handleNavigation($clean));
                break;
            case 'attendance':
                $result = array_merge($result, $this->handleAttendance());
                break;
            case 'schedule':
                $result = array_merge($result, $this->handleSchedule());
                break;
            case 'grades':
                $result = array_merge($result, $this->handleGrades());
                break;
            case 'learning':
                $result = array_merge($result, $this->handleLearning($clean));
                break;
            case 'help':
                $result = array_merge($result, $this->handleHelp());
                break;
            default:
                $result = array_merge($result, $this->handleGeneral());
                break;
        }

        $this->logConversation($clean, $result['message'], $intent, 'low');

        return $result;
    }

    private function sanitizeMessage($message)
    {
        $message = trim((string)$message);
        $message = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message);
        $message = preg_replace('/\s+/', ' ', $message);
        return mb_substr($message, 0, 1000, 'UTF-8');
    }

    private function detectIntent($message)
    {
        $m = strtolower($message);

        $map = [
            'navigation' => '/\b(go to|open|navigate|take me|where is|locate|page|panel|dashboard)\b/i',
            'attendance' => '/\b(attendance|present|absent|late|check ?in|check ?out)\b/i',
            'schedule' => '/\b(schedule|timetable|class time|period|calendar|meeting)\b/i',
            'grades' => '/\b(grade|result|score|marks|performance|gpa)\b/i',
            'learning' => '/\b(learn|study|teach|explain|topic|lesson|course|practice|quiz)\b/i',
            'help' => '/\b(help|support|how to|guide|stuck|problem)\b/i'
        ];

        foreach ($map as $intent => $pattern) {
            if (preg_match($pattern, $m)) {
                return $intent;
            }
        }

        return 'general';
    }

    private function handleNavigation($message)
    {
        $routes = $this->getRoleRoutes();
        $messageLower = strtolower($message);

        $best = null;
        $bestScore = 0;

        foreach ($routes as $route) {
            $score = 0;
            foreach ($route['keywords'] as $kw) {
                if (strpos($messageLower, $kw) !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $route;
            }
        }

        if ($best) {
            return [
                'message' => 'I found the best page for that request. Use the action below.',
                'suggestions' => [
                    'Open dashboard',
                    'Show attendance page',
                    'Find class schedule'
                ],
                'actions' => [
                    [
                        'type' => 'navigate',
                        'label' => 'Open ' . $best['label'],
                        'url' => $best['url']
                    ]
                ],
                'learning_cards' => []
            ];
        }

        $quickActions = [];
        foreach (array_slice($routes, 0, 4) as $route) {
            $quickActions[] = [
                'type' => 'navigate',
                'label' => $route['label'],
                'url' => $route['url']
            ];
        }

        return [
            'message' => 'Tell me where you want to go, and I will route you there quickly.',
            'suggestions' => ['Open dashboard', 'Go to settings', 'Take me to reports'],
            'actions' => $quickActions,
            'learning_cards' => []
        ];
    }

    private function handleAttendance()
    {
        if ($this->userRole === 'student') {
            $summary = $this->getStudentAttendanceSummary();
            return [
                'message' => "Your attendance is {$summary['percentage']}% ({$summary['present']}/{$summary['total']} records).",
                'suggestions' => ['Open attendance history', 'Show my schedule'],
                'actions' => [
                    ['type' => 'navigate', 'label' => 'Attendance Page', 'url' => '/attendance/student/attendance.php']
                ],
                'learning_cards' => []
            ];
        }

        if ($this->userRole === 'teacher') {
            return [
                'message' => 'You can mark and review class attendance from the attendance panel.',
                'suggestions' => ['Open attendance panel', 'Open class list'],
                'actions' => [
                    ['type' => 'navigate', 'label' => 'Teacher Attendance', 'url' => '/attendance/teacher/attendance.php']
                ],
                'learning_cards' => []
            ];
        }

        return [
            'message' => 'Attendance tools are available from your role dashboard.',
            'suggestions' => ['Open dashboard', 'Show attendance page'],
            'actions' => [
                ['type' => 'navigate', 'label' => 'Open Dashboard', 'url' => $this->getDashboardUrl()]
            ],
            'learning_cards' => []
        ];
    }

    private function handleSchedule()
    {
        return [
            'message' => 'I can direct you to the most relevant schedule page for your role.',
            'suggestions' => ['Open schedule', 'What is next class?', 'Show week calendar'],
            'actions' => [
                ['type' => 'navigate', 'label' => 'Open Schedule', 'url' => $this->getScheduleUrl()]
            ],
            'learning_cards' => []
        ];
    }

    private function handleGrades()
    {
        return [
            'message' => 'Grades and performance insights are available in your academic panel.',
            'suggestions' => ['Open grades', 'Show performance trend', 'How can I improve?'],
            'actions' => [
                ['type' => 'navigate', 'label' => 'Open Grades', 'url' => $this->getGradesUrl()]
            ],
            'learning_cards' => [
                [
                    'title' => 'Improve Scores Faster',
                    'description' => 'Focus weak topics first, then do short daily practice blocks.',
                    'action_label' => 'Create study plan',
                    'action_url' => '/attendance/student/lms-portal.php'
                ]
            ]
        ];
    }

    private function handleLearning($message)
    {
        $topic = $this->extractLearningTopic($message);
        $level = $this->detectLearningLevel($message);

        $cards = [
            [
                'title' => ucfirst($topic) . ' Foundations',
                'description' => 'Start with the core concepts and one guided example.',
                'action_label' => 'Open learning portal',
                'action_url' => '/attendance/student/lms-portal.php'
            ],
            [
                'title' => ucfirst($topic) . ' Practice',
                'description' => 'Do a short quiz and get instant feedback.',
                'action_label' => 'Open assignments',
                'action_url' => '/attendance/student/assignments.php'
            ]
        ];

        return [
            'message' => "I prepared a {$level}-level learning path for {$topic}. Start with the cards below.",
            'suggestions' => [
                "Explain {$topic}",
                "Give me {$topic} practice questions",
                'Track my learning progress'
            ],
            'actions' => [
                ['type' => 'navigate', 'label' => 'Open LMS', 'url' => '/attendance/student/lms-portal.php']
            ],
            'learning_cards' => $cards
        ];
    }

    private function handleHelp()
    {
        return [
            'message' => 'I can assist with navigation, attendance, schedules, grades, and learning guidance.',
            'suggestions' => [
                'Take me to dashboard',
                'Help me learn a topic',
                'Show my attendance summary'
            ],
            'actions' => [
                ['type' => 'navigate', 'label' => 'Open Dashboard', 'url' => $this->getDashboardUrl()]
            ],
            'learning_cards' => []
        ];
    }

    private function handleGeneral()
    {
        return [
            'message' => 'I am ready. Ask for learning help, navigation, attendance, schedule, or grades.',
            'suggestions' => [
                'Open dashboard',
                'Teach me a topic',
                'Show attendance'
            ],
            'actions' => [
                ['type' => 'navigate', 'label' => 'Go to Dashboard', 'url' => $this->getDashboardUrl()]
            ],
            'learning_cards' => []
        ];
    }

    private function extractLearningTopic($message)
    {
        $message = strtolower($message);
        $stopWords = ['learn', 'about', 'study', 'teach', 'explain', 'me', 'the', 'a', 'an', 'how', 'to'];
        $tokens = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/i', ' ', $message));
        $tokens = array_values(array_filter($tokens, function ($t) use ($stopWords) {
            return $t !== '' && !in_array($t, $stopWords, true);
        }));

        return !empty($tokens) ? $tokens[0] : 'your subject';
    }

    private function detectLearningLevel($message)
    {
        $m = strtolower($message);
        if (preg_match('/\b(advanced|expert|hard|difficult)\b/', $m)) {
            return 'advanced';
        }
        if (preg_match('/\b(intermediate|medium)\b/', $m)) {
            return 'intermediate';
        }
        return 'beginner';
    }

    private function getStudentAttendanceSummary()
    {
        try {
            $records = db()->fetchAll('SELECT status FROM attendance_records WHERE student_id = ?', [$this->userId]);
            $total = count($records);
            $present = 0;
            foreach ($records as $r) {
                if (($r['status'] ?? '') === 'present') {
                    $present++;
                }
            }
            $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;
            return ['present' => $present, 'total' => $total, 'percentage' => $pct];
        } catch (Throwable $e) {
            error_log('AI attendance summary error: ' . $e->getMessage());
            return ['present' => 0, 'total' => 0, 'percentage' => 0];
        }
    }

    private function getRoleRoutes()
    {
        $role = $this->userRole;
        $base = rtrim(function_exists('base_url') ? base_url('') : (defined('APP_URL') ? APP_URL : '/attendance'), '/');

        $common = [
            ['label' => 'Dashboard', 'url' => $this->getDashboardUrl(), 'keywords' => ['dashboard', 'home', 'overview']],
            ['label' => 'Messages', 'url' => $base . '/messages.php', 'keywords' => ['message', 'chat', 'inbox']],
            ['label' => 'Settings', 'url' => $base . '/' . $role . '/settings.php', 'keywords' => ['setting', 'profile', 'account']]
        ];

        if ($role === 'admin') {
            return array_merge($common, [
                ['label' => 'Students', 'url' => $base . '/admin/students.php', 'keywords' => ['student', 'learner']],
                ['label' => 'Teachers', 'url' => $base . '/admin/teachers.php', 'keywords' => ['teacher', 'staff']],
                ['label' => 'Reports', 'url' => $base . '/admin/reports.php', 'keywords' => ['report', 'analytics', 'insight']],
                ['label' => 'Attendance', 'url' => $base . '/admin/attendance.php', 'keywords' => ['attendance', 'checkin', 'check in']],
            ]);
        }

        if ($role === 'teacher') {
            return array_merge($common, [
                ['label' => 'Attendance', 'url' => $base . '/teacher/attendance.php', 'keywords' => ['attendance', 'mark']],
                ['label' => 'Schedule', 'url' => $base . '/teacher/schedule.php', 'keywords' => ['schedule', 'timetable', 'class']],
                ['label' => 'Reports', 'url' => $base . '/teacher/reports.php', 'keywords' => ['report', 'grade', 'result']],
            ]);
        }

        if ($role === 'parent') {
            return array_merge($common, [
                ['label' => 'Children', 'url' => $base . '/parent/children.php', 'keywords' => ['children', 'child', 'student']],
                ['label' => 'Attendance', 'url' => $base . '/parent/attendance.php', 'keywords' => ['attendance', 'present', 'absent']],
                ['label' => 'Fees', 'url' => $base . '/parent/fees.php', 'keywords' => ['fee', 'payment', 'pay']],
                ['label' => 'Grades', 'url' => $base . '/parent/grades.php', 'keywords' => ['grade', 'result', 'score']],
            ]);
        }

        return array_merge($common, [
            ['label' => 'Attendance', 'url' => $base . '/student/attendance.php', 'keywords' => ['attendance', 'present', 'absent']],
            ['label' => 'Schedule', 'url' => $base . '/student/schedule.php', 'keywords' => ['schedule', 'timetable', 'class']],
            ['label' => 'Grades', 'url' => $base . '/student/grades.php', 'keywords' => ['grade', 'result', 'score']],
            ['label' => 'Learning Portal', 'url' => $base . '/student/lms-portal.php', 'keywords' => ['learn', 'study', 'lms', 'portal']],
        ]);
    }

    private function getDashboardUrl()
    {
        switch ($this->userRole) {
            case 'admin':
                return function_exists('base_url') ? base_url('admin/dashboard.php') : '/attendance/frontend/admin/dashboard.php';
            case 'teacher':
                return function_exists('base_url') ? base_url('teacher/dashboard.php') : '/attendance/frontend/teacher/dashboard.php';
            case 'parent':
                return function_exists('base_url') ? base_url('parent/dashboard.php') : '/attendance/frontend/parent/dashboard.php';
            default:
                return function_exists('base_url') ? base_url('student/dashboard.php') : '/attendance/frontend/student/dashboard.php';
        }
    }

    private function getScheduleUrl()
    {
        switch ($this->userRole) {
            case 'teacher':
                return function_exists('base_url') ? base_url('teacher/schedule.php') : '/attendance/frontend/teacher/schedule.php';
            case 'parent':
                return function_exists('base_url') ? base_url('parent/my-meetings.php') : '/attendance/frontend/parent/my-meetings.php';
            default:
                return function_exists('base_url') ? base_url('student/schedule.php') : '/attendance/frontend/student/schedule.php';
        }
    }

    private function getGradesUrl()
    {
        switch ($this->userRole) {
            case 'parent':
                return function_exists('base_url') ? base_url('parent/grades.php') : '/attendance/frontend/parent/grades.php';
            case 'teacher':
                return function_exists('base_url') ? base_url('teacher/reports.php') : '/attendance/frontend/teacher/reports.php';
            default:
                return function_exists('base_url') ? base_url('student/grades.php') : '/attendance/frontend/student/grades.php';
        }
    }

    private function resolveTenant()
    {
        $default = [
            'id' => 1,
            'name' => 'Default School',
            'slug' => 'default-school'
        ];

        if (!empty($_SESSION['tenant_id'])) {
            $default['id'] = (int)$_SESSION['tenant_id'];
        }
        if (!empty($_SESSION['tenant_name'])) {
            $default['name'] = (string)$_SESSION['tenant_name'];
        }

        try {
            if ($this->tableExists('school_tenants') && $this->tableExists('tenant_users')) {
                $row = db()->fetchOne(
                    'SELECT t.id, t.name, t.slug
                     FROM tenant_users tu
                     JOIN school_tenants t ON t.id = tu.tenant_id
                     WHERE tu.user_id = ? AND tu.is_active = 1
                     ORDER BY tu.id DESC LIMIT 1',
                    [$this->userId]
                );

                if ($row) {
                    $_SESSION['tenant_id'] = (int)$row['id'];
                    $_SESSION['tenant_name'] = (string)$row['name'];
                    return [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'slug' => (string)($row['slug'] ?? 'default-school')
                    ];
                }
            }

            if ($this->columnExists('users', 'tenant_id')) {
                $user = db()->fetchOne('SELECT tenant_id FROM users WHERE id = ?', [$this->userId]);
                if ($user && !empty($user['tenant_id'])) {
                    $tenantId = (int)$user['tenant_id'];
                    $tenant = $default;
                    $tenant['id'] = $tenantId;
                    if ($this->tableExists('school_tenants')) {
                        $t = db()->fetchOne('SELECT id, name, slug FROM school_tenants WHERE id = ?', [$tenantId]);
                        if ($t) {
                            $tenant['name'] = (string)$t['name'];
                            $tenant['slug'] = (string)$t['slug'];
                        }
                    }
                    $_SESSION['tenant_id'] = $tenant['id'];
                    $_SESSION['tenant_name'] = $tenant['name'];
                    return $tenant;
                }
            }
        } catch (Throwable $e) {
            error_log('Tenant resolution error: ' . $e->getMessage());
        }

        return $default;
    }

    private function logConversation($message, $response, $intent, $riskLevel)
    {
        try {
            if (!$this->tableExists('ai_conversations')) {
                return;
            }

            db()->insert('ai_conversations', [
                'tenant_id' => (int)$this->tenant['id'],
                'user_id' => $this->userId,
                'session_id' => substr($this->sessionId, 0, 128),
                'message' => mb_substr((string)$message, 0, 2000, 'UTF-8'),
                'response' => mb_substr((string)$response, 0, 4000, 'UTF-8'),
                'intent' => substr((string)$intent, 0, 60),
                'risk_level' => substr((string)$riskLevel, 0, 20),
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log('AI log conversation error: ' . $e->getMessage());
        }
    }

    private function tableExists($table)
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($safe === '') {
            return false;
        }

        $row = db()->fetchOne("SHOW TABLES LIKE '{$safe}'");
        $cache[$table] = (bool)$row;
        return $cache[$table];
    }

    private function columnExists($table, $column)
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($safeTable === '' || $safeColumn === '') {
            return false;
        }

        $rows = db()->fetchAll("SHOW COLUMNS FROM {$safeTable}");
        $exists = false;
        foreach ($rows as $row) {
            if (($row['Field'] ?? '') === $safeColumn) {
                $exists = true;
                break;
            }
        }

        $cache[$key] = $exists;
        return $exists;
    }
}
