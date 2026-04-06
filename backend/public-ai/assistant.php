<?php

/**
 * Public AI Assistant — Autonomous Educational Ecosystem
 * ResponseDepthLimiter=TRUE
 *
 * Capabilities: answer general questions, explain features, onboarding guidance.
 * Restrictions: no system architecture, no database details, no security mechanisms, no internal endpoints.
 */

require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . '/app/bootstrap.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/router.php';

// ---------- Response Depth Limiter ----------
class ResponseDepthLimiter
{
  private const BLOCKED_TOPICS = [
    'database',
    'sql',
    'query',
    'schema',
    'table structure',
    'api key',
    'encryption',
    'hmac',
    'aes',
    'cipher',
    'kernel',
    'bootstrap',
    'autoload',
    'class map',
    'cron job',
    'cron/',
    'devops',
    'autofix',
    'server config',
    'php.ini',
    'httpd.conf',
    'admin password',
    'session guard',
    'tenant token',
    'federation engine',
    'trust boundary',
    'consensus guard',
    '/developer',
    '/admin',
    'internal endpoint',
    'source code',
    'file path',
    'directory structure',
  ];

  private const MAX_RESPONSE_LENGTH = 800;

  public static function filter(string $question): ?string
  {
    $lower = strtolower($question);
    foreach (self::BLOCKED_TOPICS as $topic) {
      if (strpos($lower, $topic) !== false) {
        return 'I can help with general questions about using the school management system, '
          . 'features available, and onboarding guidance. '
          . 'For technical or security-related inquiries, please contact your system administrator.';
      }
    }
    return null;
  }

  public static function truncate(string $response): string
  {
    if (strlen($response) <= self::MAX_RESPONSE_LENGTH) {
      return $response;
    }
    $cut = substr($response, 0, self::MAX_RESPONSE_LENGTH);
    $lastPeriod = strrpos($cut, '.');
    if ($lastPeriod && $lastPeriod > self::MAX_RESPONSE_LENGTH * 0.5) {
      return substr($cut, 0, $lastPeriod + 1);
    }
    return $cut . '...';
  }
}

// ---------- Knowledge Base ----------
class PublicKnowledgeBase
{
  private static array $knowledge = [
    'attendance' => 'SAMS tracks student attendance in real-time. Teachers can mark attendance from their dashboard, and parents can view reports from their portal. Attendance data is used to generate insights and identify at-risk students.',
    'grades' => 'The grading module supports continuous assessment, exam scores, and cumulative GPAs. Teachers enter grades through their dashboard, and students/parents can view results through their respective portals.',
    'classes' => 'Classes and sections are managed by administrators. Students are enrolled into classes, and teachers are assigned subjects. Timetables are generated automatically based on available slots.',
    'communication' => 'SAMS includes a messaging system for announcements, direct messages between teachers and parents, and emergency alerts. Notifications are delivered via the platform and optionally by email.',
    'reports' => 'Various reports are available including attendance summaries, grade reports, student progress tracking, and institutional analytics. Reports can be viewed on-screen or exported.',
    'registration' => 'New user accounts are created by administrators or through self-registration (if enabled). Users verify their email and set a secure password. Roles are assigned based on the user type.',
    'login' => 'Log in with your registered email and password. If you forget your password, use the "Forgot Password" link on the login page. For security, sessions expire after 15 minutes of inactivity.',
    'roles' => 'SAMS supports multiple roles: Admin, Teacher, Student, Parent, Accountant, Librarian, Transport Manager, and Developer. Each role has specific permissions and a tailored dashboard.',
    'parent' => 'Parents can view their children\'s attendance, grades, and receive announcements. The parent portal provides a comprehensive overview of each child\'s academic progress.',
    'student' => 'Students can view their attendance, grades, timetable, and library records. The student portal provides a personalized academic dashboard.',
    'teacher' => 'Teachers can mark attendance, enter grades, manage class activities, view student profiles, and communicate with parents through the teacher dashboard.',
    'fees' => 'The accountant module manages fee collection, expense tracking, payroll, and financial reporting. Parents can view outstanding fees through their portal.',
    'library' => 'The library module tracks book inventory, issues, returns, and overdue books. Students and teachers can search the catalog and request books.',
    'transport' => 'The transport module manages bus routes, driver assignments, and student transport logistics. Parents can view their child\'s transport schedule.',
    'help' => 'For help using SAMS, you can: 1) Check the FAQ section, 2) Contact your school administrator, 3) Use this assistant for general questions, 4) Refer to the user guide provided by your institution.',
    'features' => 'SAMS includes: attendance tracking, grade management, timetabling, communication tools, financial management, library management, transport management, analytics, and AI-powered insights for institutional improvement.',
  ];

  public static function search(string $query): string
  {
    $lower = strtolower($query);
    $bestMatch = null;
    $bestScore = 0;

    foreach (self::$knowledge as $topic => $answer) {
      $score = 0;
      // Direct topic mention
      if (strpos($lower, $topic) !== false) {
        $score += 10;
      }
      // Word overlap
      $queryWords = array_unique(str_word_count($lower, 1));
      $answerWords = array_unique(str_word_count(strtolower($answer), 1));
      $overlap = count(array_intersect($queryWords, $answerWords));
      $score += $overlap;

      if ($score > $bestScore) {
        $bestScore = $score;
        $bestMatch = $answer;
      }
    }

    if ($bestScore >= 2 && $bestMatch) {
      return $bestMatch;
    }

    return 'I\'m not sure about that specific topic. I can help with questions about attendance, '
      . 'grades, classes, communication, reports, registration, login, user roles, fees, '
      . 'library, transport, and general SAMS features. What would you like to know?';
  }
}

// ---------- Handle API Request ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  $input = json_decode(file_get_contents('php://input'), true);
  $question = trim($input['question'] ?? '');

  if ($question === '') {
    echo json_encode(['success' => false, 'error' => 'Please provide a question.']);
    exit;
  }

  if (strlen($question) > 500) {
    echo json_encode(['success' => false, 'error' => 'Question is too long. Please keep it under 500 characters.']);
    exit;
  }

  // Depth limiter check
  $blocked = ResponseDepthLimiter::filter($question);
  if ($blocked) {
    echo json_encode(['success' => true, 'answer' => $blocked]);
    exit;
  }

  $answer = PublicKnowledgeBase::search($question);
  $answer = ResponseDepthLimiter::truncate($answer);

  echo json_encode(['success' => true, 'answer' => $answer]);
  exit;
}

// ---------- Public UI ----------
$page_title = 'SAMS Assistant';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> — SAMS</title>
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #f0f4f8;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .assistant-header {
      width: 100%;
      background: linear-gradient(135deg, #1a73e8, #0d47a1);
      color: #fff;
      padding: 20px 0;
      text-align: center;
    }

    .assistant-header h1 {
      font-size: 1.5rem;
      font-weight: 600;
    }

    .assistant-header p {
      font-size: 0.9rem;
      opacity: 0.85;
      margin-top: 4px;
    }

    .chat-container {
      width: 100%;
      max-width: 640px;
      flex: 1;
      display: flex;
      flex-direction: column;
      padding: 16px;
    }

    .messages {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 12px;
      padding-bottom: 16px;
    }

    .msg {
      max-width: 85%;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 0.93rem;
      line-height: 1.5;
      word-wrap: break-word;
    }

    .msg.bot {
      align-self: flex-start;
      background: #fff;
      border: 1px solid #e0e4e8;
      border-bottom-left-radius: 4px;
    }

    .msg.user {
      align-self: flex-end;
      background: #1a73e8;
      color: #fff;
      border-bottom-right-radius: 4px;
    }

    .input-area {
      display: flex;
      gap: 8px;
      padding-top: 8px;
      border-top: 1px solid #e0e4e8;
    }

    .input-area input {
      flex: 1;
      padding: 12px 16px;
      border: 1px solid #d0d4d8;
      border-radius: 24px;
      font-size: 0.93rem;
      outline: none;
    }

    .input-area input:focus {
      border-color: #1a73e8;
    }

    .input-area button {
      padding: 0 20px;
      background: #1a73e8;
      color: #fff;
      border: none;
      border-radius: 24px;
      font-size: 0.93rem;
      cursor: pointer;
    }

    .input-area button:hover {
      background: #1565c0;
    }

    .input-area button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .suggestions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 12px;
    }

    .suggestions button {
      padding: 6px 14px;
      background: #e8f0fe;
      border: 1px solid #c2d7f5;
      border-radius: 16px;
      font-size: 0.82rem;
      cursor: pointer;
      color: #1a73e8;
    }

    .suggestions button:hover {
      background: #d2e3fc;
    }

    .typing {
      display: none;
      align-self: flex-start;
      padding: 8px 16px;
      background: #fff;
      border: 1px solid #e0e4e8;
      border-radius: 12px;
      font-size: 0.85rem;
      color: #888;
    }

    .footer-link {
      padding: 16px;
      text-align: center;
      font-size: 0.82rem;
      color: #888;
    }

    .footer-link a {
      color: #1a73e8;
      text-decoration: none;
    }
  </style>
</head>

<body>

  <div class="assistant-header">
    <h1>SAMS Assistant</h1>
    <p>Ask me anything about the School Attendance Management System</p>
  </div>

  <div class="chat-container">
    <div class="messages" id="messages">
      <div class="msg bot">Hello! I'm the SAMS Assistant. I can help you with questions about attendance, grades, classes, communication, and more. What would you like to know?</div>

      <div class="suggestions" id="suggestions">
        <button onclick="askSuggestion(this)">How does attendance work?</button>
        <button onclick="askSuggestion(this)">What roles are available?</button>
        <button onclick="askSuggestion(this)">How do I log in?</button>
        <button onclick="askSuggestion(this)">What features does SAMS have?</button>
        <button onclick="askSuggestion(this)">How can I get help?</button>
      </div>
    </div>

    <div class="typing" id="typing">Thinking...</div>

    <div class="input-area">
      <input type="text" id="questionInput" placeholder="Type your question..." maxlength="500"
        onkeydown="if(event.key==='Enter')sendQuestion()">
      <button id="sendBtn" onclick="sendQuestion()">Send</button>
    </div>
  </div>

  <div class="footer-link">
    <a href="<?= htmlspecialchars(rtrim(APP_URL ?? '/attendance', '/')) ?>/login.php">Back to SAMS Login</a>
  </div>

  <script>
    const messagesEl = document.getElementById('messages');
    const inputEl = document.getElementById('questionInput');
    const sendBtn = document.getElementById('sendBtn');
    const typingEl = document.getElementById('typing');

    function addMessage(text, role) {
      const div = document.createElement('div');
      div.className = 'msg ' + role;
      div.textContent = text;
      messagesEl.appendChild(div);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function askSuggestion(btn) {
      inputEl.value = btn.textContent;
      const suggestionsEl = document.getElementById('suggestions');
      if (suggestionsEl) suggestionsEl.remove();
      sendQuestion();
    }

    async function sendQuestion() {
      const q = inputEl.value.trim();
      if (!q) return;

      addMessage(q, 'user');
      inputEl.value = '';
      sendBtn.disabled = true;
      typingEl.style.display = 'block';

      try {
        const res = await fetch(window.location.pathname, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            question: q
          })
        });
        const data = await res.json();
        addMessage(data.answer || data.error || 'Sorry, something went wrong.', 'bot');
      } catch {
        addMessage('Unable to connect. Please try again later.', 'bot');
      } finally {
        sendBtn.disabled = false;
        typingEl.style.display = 'none';
        inputEl.focus();
      }
    }
  </script>

</body>

</html>
