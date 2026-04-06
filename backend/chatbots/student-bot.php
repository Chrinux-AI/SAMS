<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
  header('Location: ../login.php');
  exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!isset($_SESSION['chat_history_student'])) {
  $_SESSION['chat_history_student'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['chat_history_student'][] = ['role' => 'bot', 'message' => 'Security error. Please refresh the page.', 'time' => date('H:i')];
    header('Location: student-bot.php');
    exit;
  }

  $message = trim($_POST['message'] ?? '');
  if ($message !== '') {
    $_SESSION['chat_history_student'][] = ['role' => 'user', 'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), 'time' => date('H:i')];
    $response = processStudentCommand($message, $tenantId, $userId);
    $_SESSION['chat_history_student'][] = ['role' => 'bot', 'message' => $response, 'time' => date('H:i')];
  }

  header('Location: student-bot.php');
  exit;
}

function processStudentCommand($input, $tenantId, $studentId)
{
  $input = strtolower(trim($input));

  try {
    // Homework reminders
    if (strpos($input, 'homework') !== false || strpos($input, 'assignment') !== false) {
      // Check if assignments table exists
      $tableCheck = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'assignments'"
      );
      if (!$tableCheck || (int)$tableCheck['cnt'] === 0) {
        return '📝 No assignments system configured yet. Check with your teacher for homework details.';
      }

      $results = db()->fetchAll(
        "SELECT title, description, due_date FROM assignments
                 WHERE class_id = (SELECT class_id FROM users WHERE id = ?)
                   AND due_date >= CURDATE()
                 ORDER BY due_date ASC
                 LIMIT 10",
        [$studentId]
      );

      if (empty($results)) {
        return '✅ No pending assignments! You\'re all caught up.';
      }

      $msg = '📝 <strong>Pending Assignments:</strong><br>';
      foreach ($results as $r) {
        $msg .= "• <strong>" . htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') . "</strong> — Due: {$r['due_date']}<br>";
        if (!empty($r['description'])) {
          $msg .= "&nbsp;&nbsp;" . htmlspecialchars(substr($r['description'], 0, 100), ENT_QUOTES, 'UTF-8') . "<br>";
        }
      }
      return $msg;
    }

    // Attendance summary
    if (strpos($input, 'attendance') !== false) {
      $result = db()->fetchOne(
        "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                 FROM attendance
                 WHERE user_id = ?",
        [$studentId]
      );

      if (!$result || (int)$result['total'] === 0) {
        return 'No attendance records found for you yet.';
      }

      $rate = round(($result['present'] / $result['total']) * 100, 1);
      return "📊 <strong>Your Attendance Summary:</strong><br>"
        . "Total Days: <strong>{$result['total']}</strong><br>"
        . "Present: <strong>{$result['present']}</strong><br>"
        . "Absent: <strong>{$result['absent']}</strong><br>"
        . "Late: <strong>{$result['late']}</strong><br>"
        . "Attendance Rate: <strong>{$rate}%</strong>";
    }

    // Grade summary
    if (strpos($input, 'grade') !== false || strpos($input, 'my grades') !== false) {
      $tableCheck = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'grades'"
      );
      if (!$tableCheck || (int)$tableCheck['cnt'] === 0) {
        return '📝 No grades system configured yet. Check with your teacher.';
      }

      $results = db()->fetchAll(
        "SELECT subject, score, grade, created_at FROM grades
                 WHERE student_id = ?
                 ORDER BY created_at DESC
                 LIMIT 15",
        [$studentId]
      );

      if (empty($results)) {
        return 'No grades recorded for you yet.';
      }

      $msg = '📊 <strong>Your Grades:</strong><br>';
      foreach ($results as $r) {
        $msg .= "• <strong>" . htmlspecialchars($r['subject'], ENT_QUOTES, 'UTF-8') . ":</strong> {$r['score']} ({$r['grade']})<br>";
      }
      return $msg;
    }

    // Show schedule
    if (strpos($input, 'schedule') !== false || strpos($input, 'timetable') !== false) {
      $classInfo = db()->fetchOne(
        "SELECT c.class_name, c.grade_level FROM users u
                 JOIN classes c ON u.class_id = c.id
                 WHERE u.id = ?",
        [$studentId]
      );

      if (!$classInfo) {
        return 'You are not assigned to a class yet. Contact your administrator.';
      }

      $tableCheck = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'timetable'"
      );
      if (!$tableCheck || (int)$tableCheck['cnt'] === 0) {
        return "📅 You are in <strong>" . htmlspecialchars($classInfo['name'], ENT_QUOTES, 'UTF-8') . "</strong> (Grade {$classInfo['grade_level']}). No timetable system configured yet.";
      }

      $results = db()->fetchAll(
        "SELECT day_of_week, subject, start_time, end_time FROM timetable
                 WHERE class_id = (SELECT class_id FROM users WHERE id = ?)
                 ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday'), start_time",
        [$studentId]
      );

      if (empty($results)) {
        return "📅 You are in <strong>" . htmlspecialchars($classInfo['name'], ENT_QUOTES, 'UTF-8') . "</strong>. No schedule entries found.";
      }

      $msg = "📅 <strong>Your Schedule ({$classInfo['name']}):</strong><br>";
      $currentDay = '';
      foreach ($results as $r) {
        if ($r['day_of_week'] !== $currentDay) {
          $currentDay = $r['day_of_week'];
          $msg .= "<br><strong>{$currentDay}:</strong><br>";
        }
        $msg .= "• {$r['start_time']} - {$r['end_time']}: " . htmlspecialchars($r['subject'], ENT_QUOTES, 'UTF-8') . "<br>";
      }
      return $msg;
    }

    // Default help
    return '🤖 I can help with:<br>'
      . '• <strong>homework reminders</strong> — check pending assignments<br>'
      . '• <strong>attendance summary</strong> — your attendance stats<br>'
      . '• <strong>my grades</strong> — view your grade summary<br>'
      . '• <strong>show schedule</strong> — your class timetable';
  } catch (Exception $e) {
    error_log("Student bot error: " . $e->getMessage());
    return '❌ An error occurred while processing your request. Please try again.';
  }
}

$pageTitle = 'Student Assistant';
$chatHistory = $_SESSION['chat_history_student'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — SAMS</title>
  <link rel="stylesheet" href="../assets/css/professional-ui.css">
  <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <?php if (file_exists(__DIR__ . '/../includes/favicon-loader.php')) include __DIR__ . '/../includes/favicon-loader.php'; ?>
</head>

<body>
  <?php if (file_exists(__DIR__ . '/../includes/sidebar-nav.php')) include __DIR__ . '/../includes/sidebar-nav.php'; ?>

  <main class="main-content" style="padding:20px;">
    <h1 style="margin-bottom:20px;"><i class="fas fa-robot"></i> <?= htmlspecialchars($pageTitle) ?></h1>

    <div class="chat-container" style="display:flex;flex-direction:column;height:calc(100vh - 200px);max-width:900px;margin:0 auto;">
      <div class="chat-messages" id="chatMessages" style="flex:1;overflow-y:auto;padding:20px;background:var(--bg-white);border:1px solid var(--border-color);border-radius:14px 14px 0 0;">
        <div class="chat-bubble bot" style="display:flex;gap:12px;margin-bottom:16px;">
          <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:grid;place-items:center;flex-shrink:0;"><i class="fas fa-robot"></i></div>
          <div style="background:var(--bg-primary);padding:12px 16px;border-radius:0 14px 14px 14px;max-width:80%;">
            👋 Hi there! I'm your Student Assistant. Ask me about homework, attendance, grades, or your schedule.
          </div>
        </div>

        <?php foreach ($chatHistory as $msg): ?>
          <?php if ($msg['role'] === 'user'): ?>
            <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
              <div style="background:var(--primary);color:#fff;padding:12px 16px;border-radius:14px 0 14px 14px;max-width:80%;">
                <?= $msg['message'] ?>
              </div>
            </div>
          <?php else: ?>
            <div class="chat-bubble bot" style="display:flex;gap:12px;margin-bottom:16px;">
              <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:grid;place-items:center;flex-shrink:0;"><i class="fas fa-robot"></i></div>
              <div style="background:var(--bg-primary);padding:12px 16px;border-radius:0 14px 14px 14px;max-width:80%;">
                <?= $msg['message'] ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <form method="post" style="display:flex;gap:8px;padding:12px;background:var(--bg-white);border:1px solid var(--border-color);border-top:none;border-radius:0 0 14px 14px;">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="chat">
        <input type="text" name="message" placeholder="Type a message..." autocomplete="off" style="flex:1;padding:10px 16px;border:1px solid var(--border-color);border-radius:10px;" required>
        <button type="submit" style="padding:10px 20px;border-radius:10px;background:var(--primary);color:#fff;border:none;cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
      </form>
    </div>
  </main>

  <script src="../assets/js/theme-loader.js"></script>
  <script src="../assets/js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var chatMessages = document.getElementById('chatMessages');
      if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
    });
  </script>
</body>

</html>
