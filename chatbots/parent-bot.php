<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
  header('Location: ../login.php');
  exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!isset($_SESSION['chat_history_parent'])) {
  $_SESSION['chat_history_parent'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['chat_history_parent'][] = ['role' => 'bot', 'message' => 'Security error. Please refresh the page.', 'time' => date('H:i')];
    header('Location: parent-bot.php');
    exit;
  }

  $message = trim($_POST['message'] ?? '');
  if ($message !== '') {
    $_SESSION['chat_history_parent'][] = ['role' => 'user', 'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), 'time' => date('H:i')];
    $response = processParentCommand($message, $tenantId, $userId);
    $_SESSION['chat_history_parent'][] = ['role' => 'bot', 'message' => $response, 'time' => date('H:i')];
  }

  header('Location: parent-bot.php');
  exit;
}

function getLinkedChild($parentId)
{
  try {
    // Try parent_student_link table first, fallback to users.parent_id
    $tableCheck = db()->fetchOne(
      "SELECT COUNT(*) as cnt FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'parent_student_link'"
    );

    if ($tableCheck && (int)$tableCheck['cnt'] > 0) {
      return db()->fetchOne(
        "SELECT u.id, u.full_name, u.class_id FROM parent_student_link psl
                 JOIN users u ON psl.student_id = u.id
                 WHERE psl.parent_id = ?
                 LIMIT 1",
        [$parentId]
      );
    }

    // Fallback: check users table for parent_id column
    return db()->fetchOne(
      "SELECT id, full_name, class_id FROM users WHERE parent_id = ? AND role = 'student' LIMIT 1",
      [$parentId]
    );
  } catch (Exception $e) {
    return null;
  }
}

function processParentCommand($input, $tenantId, $parentId)
{
  $input = strtolower(trim($input));

  try {
    $child = getLinkedChild($parentId);
    if (!$child) {
      return '⚠️ No child account is linked to your profile. Please contact the school administrator to link your child\'s account.';
    }

    $childName = htmlspecialchars($child['full_name'], ENT_QUOTES, 'UTF-8');
    $childId = $child['id'];

    // Child performance / child summary
    if (strpos($input, 'child performance') !== false || strpos($input, 'child summary') !== false || strpos($input, 'performance') !== false) {
      $attendance = db()->fetchOne(
        "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                 FROM attendance
                 WHERE user_id = ?",
        [$childId]
      );

      $attendanceRate = 0;
      if ($attendance && (int)$attendance['total'] > 0) {
        $attendanceRate = round(($attendance['present'] / $attendance['total']) * 100, 1);
      }

      $msg = "📊 <strong>Performance Summary — {$childName}:</strong><br><br>";
      $msg .= "<strong>Attendance:</strong><br>";
      $msg .= "Total Days: {$attendance['total']} | Present: {$attendance['present']} | Absent: {$attendance['absent']} | Late: {$attendance['late']}<br>";
      $msg .= "Attendance Rate: <strong>{$attendanceRate}%</strong><br><br>";

      // Grades if available
      $tableCheck = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'grades'"
      );
      if ($tableCheck && (int)$tableCheck['cnt'] > 0) {
        $grades = db()->fetchAll(
          "SELECT subject, score, grade FROM grades WHERE student_id = ? ORDER BY created_at DESC LIMIT 10",
          [$childId]
        );
        if (!empty($grades)) {
          $msg .= "<strong>Recent Grades:</strong><br>";
          foreach ($grades as $g) {
            $msg .= "• " . htmlspecialchars($g['subject'], ENT_QUOTES, 'UTF-8') . ": {$g['score']} ({$g['grade']})<br>";
          }
        }
      }

      return $msg;
    }

    // Attendance alerts
    if (strpos($input, 'attendance alert') !== false || strpos($input, 'absent') !== false) {
      $results = db()->fetchAll(
        "SELECT date, status FROM attendance
                 WHERE user_id = ? AND status != 'present'
                 ORDER BY date DESC
                 LIMIT 10",
        [$childId]
      );

      if (empty($results)) {
        return "✅ Great news! <strong>{$childName}</strong> has no recent absences.";
      }

      $msg = "⚠️ <strong>Attendance Alerts for {$childName}:</strong><br>";
      foreach ($results as $r) {
        $icon = $r['status'] === 'absent' ? '🔴' : '🟡';
        $msg .= "{$icon} {$r['date']} — " . ucfirst($r['status']) . "<br>";
      }
      return $msg;
    }

    // Show child grades
    if (strpos($input, 'child grade') !== false || strpos($input, 'grade') !== false) {
      $tableCheck = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'grades'"
      );
      if (!$tableCheck || (int)$tableCheck['cnt'] === 0) {
        return '📝 No grades system configured yet. Check with the school administration.';
      }

      $results = db()->fetchAll(
        "SELECT subject, score, grade, created_at FROM grades
                 WHERE student_id = ?
                 ORDER BY created_at DESC
                 LIMIT 15",
        [$childId]
      );

      if (empty($results)) {
        return "No grades recorded for <strong>{$childName}</strong> yet.";
      }

      $msg = "📊 <strong>Grades for {$childName}:</strong><br>";
      foreach ($results as $r) {
        $msg .= "• <strong>" . htmlspecialchars($r['subject'], ENT_QUOTES, 'UTF-8') . ":</strong> {$r['score']} ({$r['grade']})<br>";
      }
      return $msg;
    }

    // Default help
    return '🤖 I can help with:<br>'
      . '• <strong>child performance</strong> — attendance & grade summary for your child<br>'
      . '• <strong>attendance alerts</strong> — recent absences for your child<br>'
      . '• <strong>show child grades</strong> — your child\'s grade details';
  } catch (Exception $e) {
    error_log("Parent bot error: " . $e->getMessage());
    return '❌ An error occurred while processing your request. Please try again.';
  }
}

$pageTitle = 'Parent Assistant';
$chatHistory = $_SESSION['chat_history_parent'];
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
            👋 Hello! I'm your Parent Assistant. Ask me about your child's performance, attendance alerts, or grades.
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
