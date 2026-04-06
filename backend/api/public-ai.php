<?php

/**
 * Public AI Assistant API — No authentication required.
 * Uses 'guest' role scope from AIContextFilter.
 * Strict rate limiting to prevent abuse.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/ai-context-filter.php';
require_once __DIR__ . '/../includes/rate-limiter.php';

header('Content-Type: application/json');

function pub_json($payload, $statusCode = 200)
{
  http_response_code($statusCode);
  echo json_encode($payload);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  pub_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Strict IP-based rate limiting for public endpoint
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipLimit = rate_limiter()->check('public_ai_ip', $client_ip, 10, 60);
if (!$ipLimit['allowed']) {
  pub_json([
    'success' => false,
    'error' => 'Too many requests. Please wait a moment before trying again.',
    'retry_after' => $ipLimit['retry_after']
  ], 429);
}
rate_limiter()->record('public_ai_ip', $client_ip);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
  pub_json(['success' => false, 'error' => 'Invalid request'], 400);
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
  pub_json(['success' => false, 'error' => 'Message required'], 400);
}

if (mb_strlen($message, 'UTF-8') > 500) {
  pub_json(['success' => false, 'error' => 'Message too long. Keep it under 500 characters.'], 400);
}

// Run through AI context filter with 'guest' scope
$aiFilter = AIContextFilter::filterMessage('guest', $message);
if (!$aiFilter['allowed']) {
  pub_json([
    'success' => true,
    'response' => $aiFilter['response'],
    'filtered' => true,
    'timestamp' => date('Y-m-d H:i:s')
  ]);
}

// Guest knowledge base — answer from curated topics
$response = processPublicQuery($message);

pub_json([
  'success' => true,
  'response' => $response['answer'],
  'suggestions' => $response['suggestions'] ?? [],
  'timestamp' => date('Y-m-d H:i:s')
]);

/**
 * Process public visitor queries with curated knowledge base.
 */
function processPublicQuery(string $message): array
{
  $msg = strtolower($message);

  // Get live stats for dynamic responses
  $stats = getPublicStats();

  // Knowledge base with pattern matching
  $knowledgeBase = [
    [
      'patterns' => ['what is sams', 'what is this', 'about sams', 'what does sams', 'tell me about'],
      'answer' => "SAMS (Smart Attendance Management System) is a comprehensive school management platform that handles attendance tracking, grade management, parent communication, financial management, and much more. It's designed for schools of all sizes with role-based dashboards for administrators, teachers, students, parents, and more.",
      'suggestions' => ['How do I register?', 'What features does SAMS offer?', 'Who can use this system?']
    ],
    [
      'patterns' => ['feature', 'what can', 'capabilities', 'what does it do', 'functionality'],
      'answer' => "SAMS offers a wide range of features:\n\n📊 **Attendance Tracking** — Biometric, QR code, and manual check-in\n📚 **Class Management** — Schedules, enrollment, and curricula\n📈 **Analytics** — Real-time dashboards with insights\n👨‍👩‍👧 **Parent Portal** — Track your child's progress\n💰 **Financial Management** — Fees, payments, and reporting\n🔔 **Notifications** — Instant alerts for attendance, grades, events\n💬 **Messaging** — Direct communication between all roles\n🤖 **AI Assistant** — Smart help across the platform",
      'suggestions' => ['How does attendance tracking work?', 'Tell me about the parent portal', 'How do I get started?']
    ],
    [
      'patterns' => ['register', 'sign up', 'create account', 'enroll', 'enrollment', 'join'],
      'answer' => "To get started with SAMS:\n\n1. Click the **Register** button on the homepage\n2. Fill in your details (name, email, role)\n3. Verify your email address\n4. Wait for admin approval (if required)\n5. Log in and set up your profile\n\nParents and students are typically enrolled by the school administrator. Contact your school if you need an invitation.",
      'suggestions' => ['What roles are available?', 'I forgot my password', 'How do I contact the school?']
    ],
    [
      'patterns' => ['login', 'sign in', 'log in', 'access', 'password', 'forgot'],
      'answer' => "To log in, visit the login page and enter your registered email and password. If you've forgotten your password, use the **Forgot Password** link on the login page to receive a reset email.\n\nFor security, accounts are locked after 5 failed attempts for 5 minutes.",
      'suggestions' => ['How do I register?', 'I need help with my account', 'How secure is the system?']
    ],
    [
      'patterns' => ['role', 'who can use', 'user type', 'account type', 'admin', 'teacher', 'student', 'parent'],
      'answer' => "SAMS supports multiple user roles:\n\n👤 **Admin** — Full system management\n👨‍🏫 **Teacher** — Attendance, grades, class management\n🎓 **Student** — View attendance, grades, assignments\n👪 **Parent** — Monitor child's progress\n💼 **Accountant** — Financial management\n📚 **Librarian** — Library resource management\n🚌 **Transport** — Bus route management\n💬 **Forum Moderator** — Community management\n👥 **General User** — Basic access",
      'suggestions' => ['How do I register?', 'What features are available?', 'Tell me about the admin dashboard']
    ],
    [
      'patterns' => ['attendance', 'check in', 'tracking', 'biometric', 'qr code'],
      'answer' => "SAMS attendance tracking supports multiple methods:\n\n📱 **QR Code Scan** — Quick mobile check-in\n🔒 **Biometric** — Fingerprint verification\n✍️ **Manual Entry** — Teacher marks attendance\n📊 **Real-Time Reports** — Instant attendance analytics\n\nParents receive notifications when their child checks in or is marked absent.",
      'suggestions' => ['How does the parent portal work?', 'What reports are available?', 'How secure is attendance data?']
    ],
    [
      'patterns' => ['secure', 'security', 'safe', 'privacy', 'data protection'],
      'answer' => "SAMS takes security seriously:\n\n🔐 **Encrypted Sessions** — All sessions are secured\n🛡️ **Role-Based Access** — Users only see what they're authorized to\n⏱️ **Auto Logout** — Sessions expire after inactivity\n🚫 **Brute Force Protection** — Account lockout after failed attempts\n📝 **Audit Logging** — All actions are tracked\n🔒 **CSRF Protection** — Forms are protected against cross-site attacks",
      'suggestions' => ['How do I report a security concern?', 'What roles are available?', 'How do I reset my password?']
    ],
    [
      'patterns' => ['contact', 'support', 'help', 'reach', 'email', 'phone'],
      'answer' => "For support:\n\n📧 Contact your school administrator directly\n💬 Use the messaging system within SAMS once logged in\n📋 Check the notices/updates page for announcements\n\nIf you're a school administrator looking to set up SAMS, please contact the system provider.",
      'suggestions' => ['How do I register?', 'I forgot my password', 'What is SAMS?']
    ],
    [
      'patterns' => ['price', 'cost', 'pricing', 'free', 'subscription', 'plan'],
      'answer' => "For pricing information, please contact the system administrator or visit the school's administration office. SAMS licensing and pricing varies by institution size and feature requirements.",
      'suggestions' => ['What features does SAMS offer?', 'How do I get started?', 'Contact support']
    ],
    [
      'patterns' => ['stats', 'statistics', 'how many', 'numbers'],
      'answer' => "Here are the current system statistics:\n\n🎓 **Active Students:** {$stats['students']}\n👨‍🏫 **Teachers:** {$stats['teachers']}\n📚 **Classes:** {$stats['classes']}\n📊 **Today's Attendance Rate:** {$stats['rate']}%",
      'suggestions' => ['What features are available?', 'How does attendance tracking work?', 'Tell me about SAMS']
    ],
  ];

  // Find best matching response
  $bestMatch = null;
  $bestScore = 0;

  foreach ($knowledgeBase as $entry) {
    $score = 0;
    foreach ($entry['patterns'] as $pattern) {
      if (strpos($msg, $pattern) !== false) {
        $score += strlen($pattern); // Longer matches score higher
      }
    }
    if ($score > $bestScore) {
      $bestScore = $score;
      $bestMatch = $entry;
    }
  }

  if ($bestMatch) {
    return $bestMatch;
  }

  // Default response for unrecognized queries
  return [
    'answer' => "Thanks for your interest! I can help you learn about SAMS — our school attendance and management system. Try asking about:\n\n• What SAMS does\n• Available features\n• How to register or log in\n• User roles\n• Security measures\n• Attendance tracking\n\nOr click one of the suggestions below!",
    'suggestions' => ['What is SAMS?', 'How do I register?', 'What features are available?']
  ];
}

/**
 * Get public-safe statistics.
 */
function getPublicStats(): array
{
  try {
    return [
      'students' => db()->fetchOne("SELECT COUNT(*) as cnt FROM students WHERE is_active = 1")['cnt'] ?? 0,
      'teachers' => db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'teacher' AND is_active = 1")['cnt'] ?? 0,
      'classes' => db()->fetchOne("SELECT COUNT(*) as cnt FROM classes WHERE is_active = 1")['cnt'] ?? 0,
      'rate' => (function () {
        $total = db()->fetchOne("SELECT COUNT(*) as cnt FROM attendance WHERE date = CURDATE()")['cnt'] ?? 0;
        if ($total === 0) return 0;
        $present = db()->fetchOne("SELECT COUNT(*) as cnt FROM attendance WHERE date = CURDATE() AND status = 'present'")['cnt'] ?? 0;
        return round(($present / $total) * 100);
      })()
    ];
  } catch (Exception $e) {
    return ['students' => 0, 'teachers' => 0, 'classes' => 0, 'rate' => 0];
  }
}
