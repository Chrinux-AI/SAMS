<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
  header('Location: ../login.php');
  exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!isset($_SESSION['chat_history_teacher'])) {
  $_SESSION['chat_history_teacher'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['chat_history_teacher'][] = ['role' => 'bot', 'message' => 'Security error. Please refresh the page.', 'time' => date('H:i')];
    header('Location: teacher-bot.php');
    exit;
  }

  $message = trim($_POST['message'] ?? '');
  if ($message !== '') {
    $_SESSION['chat_history_teacher'][] = ['role' => 'user', 'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), 'time' => date('H:i')];
    $response = processTeacherCommand($message, $tenantId, $userId);
    $_SESSION['chat_history_teacher'][] = ['role' => 'bot', 'message' => $response, 'time' => date('H:i')];
  }

  header('Location: teacher-bot.php');
  exit;
}

function processTeacherCommand($input, $tenantId, $teacherId)
{
  $input = strtolower(trim($input));

  try {
    // Help with attendance
    if (strpos($input, 'help with attendance') !== false || strpos($input, 'mark attendance') !== false || $input === 'attendance') {
      return '📋 <strong>Attendance Guide:</strong><br>'
        . '1. Go to <a href="../teacher/attendance.php">Attendance Page</a><br>'
        . '2. Select your class and date<br>'
        . '3. Mark each student as Present, Absent, or Late<br>'
        . '4. Click Save to submit the attendance record<br><br>'
        . '💡 Tip: You can also view past attendance records from the same page.';
    }

    // Generate class report
    if (strpos($input, 'class report') !== false || strpos($input, 'generate report') !== false) {
      $results = db()->fetchAll(
        "SELECT c.id, c.class_name, c.grade_level,
                        COUNT(DISTINCT u.id) as student_count,
                        (SELECT COUNT(*) FROM attendance a2 WHERE a2.class_id = c.id AND a2.date >= NOW() - INTERVAL 30 DAY) as total_records,
                        (SELECT SUM(CASE WHEN a3.status = 'present' THEN 1 ELSE 0 END) FROM attendance a3 WHERE a3.class_id = c.id AND a3.date >= NOW() - INTERVAL 30 DAY) as present_count
                 FROM classes c
                 LEFT JOIN users u ON u.class_id = c.id AND u.role = 'student'
                 WHERE c.class_teacher_id = ? AND c.tenant_id = ?
                 GROUP BY c.id, c.class_name, c.grade_level",
        [$teacherId, $tenantId]
      );

      if (empty($results)) {
        return 'No classes assigned to you yet.';
      }

      $msg = '📊 <strong>Class Report (Last 30 Days):</strong><br>';
      foreach ($results as $r) {
        $avgAttendance = $r['total_records'] > 0 ? round(($r['present_count'] / $r['total_records']) * 100, 1) : 0;
        $msg .= "<strong>{$r['name']}</strong> (Grade {$r['grade_level']}): {$r['student_count']} students | Attendance: {$avgAttendance}%<br>";
      }
      return $msg;
    }

    // Show my classes
    if (strpos($input, 'my classes') !== false || strpos($input, 'show classes') !== false) {
      $results = db()->fetchAll(
        "SELECT c.id, c.class_name, c.grade_level, COUNT(u.id) as student_count
                 FROM classes c
                 LEFT JOIN users u ON u.class_id = c.id AND u.role = 'student'
                 WHERE c.class_teacher_id = ? AND c.tenant_id = ?
                 GROUP BY c.id, c.class_name, c.grade_level",
        [$teacherId, $tenantId]
      );

      if (empty($results)) {
        return 'You have no classes assigned currently.';
      }

      $msg = '📚 <strong>Your Classes:</strong><br>';
      foreach ($results as $r) {
        $msg .= "• <strong>{$r['name']}</strong> — Grade {$r['grade_level']} ({$r['student_count']} students)<br>";
      }
      return $msg;
    }

    // Class analytics
    if (strpos($input, 'class analytics') !== false || strpos($input, 'analytics') !== false) {
      $results = db()->fetchAll(
        "SELECT c.class_name,
                        COUNT(a.id) as total_records,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late
                 FROM classes c
                 LEFT JOIN attendance a ON a.class_id = c.id AND a.date >= NOW() - INTERVAL 30 DAY
                 WHERE c.class_teacher_id = ? AND c.tenant_id = ?
                 GROUP BY c.id, c.class_name",
        [$teacherId, $tenantId]
      );

      if (empty($results)) {
        return 'No analytics data available yet.';
      }

      $msg = '📈 <strong>Class Analytics (Last 30 Days):</strong><br>';
      foreach ($results as $r) {
        $rate = $r['total_records'] > 0 ? round(($r['present'] / $r['total_records']) * 100, 1) : 0;
        $msg .= "<strong>{$r['name']}:</strong> {$rate}% attendance | {$r['present']} present, {$r['absent']} absent, {$r['late']} late<br>";
      }
      return $msg;
    }

    // Default help
    return '🤖 I can help with:<br>'
      . '• <strong>help with attendance</strong> — how to mark attendance<br>'
      . '• <strong>generate class report</strong> — student count & attendance per class<br>'
      . '• <strong>show my classes</strong> — list your assigned classes<br>'
      . '• <strong>class analytics</strong> — attendance percentages per class';
  } catch (Exception $e) {
    error_log("Teacher bot error: " . $e->getMessage());
    return '❌ An error occurred while processing your request. Please try again.';
  }
}

$pageTitle = 'Teacher Assistant';
$chatHistory = $_SESSION['chat_history_teacher'];
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
            👋 Hello Teacher! I'm your AI assistant. Ask me about attendance, class reports, your classes, or class analytics.
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
