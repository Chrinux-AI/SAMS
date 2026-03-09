<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');

// Role check — admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php');
  exit;
}

$tenantId = $_SESSION['tenant_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

// Initialize chat history
if (!isset($_SESSION['chat_history_admin'])) {
  $_SESSION['chat_history_admin'] = [];
}

// Process chat message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['chat_history_admin'][] = ['role' => 'bot', 'message' => 'Security error. Please refresh the page.', 'time' => date('H:i')];
    header('Location: admin-bot.php');
    exit;
  }

  $message = trim($_POST['message'] ?? '');
  if ($message !== '') {
    $_SESSION['chat_history_admin'][] = ['role' => 'user', 'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), 'time' => date('H:i')];
    $response = processAdminCommand($message, $tenantId);
    $_SESSION['chat_history_admin'][] = ['role' => 'bot', 'message' => $response, 'time' => date('H:i')];
  }

  header('Location: admin-bot.php');
  exit;
}

function processAdminCommand($input, $tenantId)
{
  $input = strtolower(trim($input));

  try {
    // Show attendance anomalies
    if (strpos($input, 'attendance anomal') !== false || strpos($input, 'show attendance') !== false) {
      $results = db()->fetchAll(
        "SELECT a.teacher_id, a.class_id, a.date, COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count
                 FROM attendance a
                 JOIN users u ON a.teacher_id = u.id AND u.tenant_id = ?
                 GROUP BY a.teacher_id, a.class_id, a.date
                 HAVING (present_count / total) > 0.9 AND total >= 5
                 LIMIT 10",
        [$tenantId]
      );
      if (empty($results)) {
        return '✅ No attendance anomalies detected. All records look normal.';
      }
      $msg = '⚠️ <strong>Attendance Anomalies Detected:</strong><br>';
      foreach ($results as $r) {
        $ratio = round($r['present_count'] / $r['total'] * 100, 1);
        $msg .= "Teacher #{$r['teacher_id']} — Class #{$r['class_id']} on {$r['date']}: {$ratio}% present ({$r['present_count']}/{$r['total']})<br>";
      }
      return $msg;
    }

    // Generate grade report
    if (preg_match('/grade report.*grade\s*(\w+)/i', $input, $matches) || preg_match('/report.*grade\s*(\w+)/i', $input, $matches)) {
      $gradeLevel = $matches[1];
      $results = db()->fetchAll(
        "SELECT g.subject, COUNT(*) as total, ROUND(AVG(g.score), 1) as avg_score,
                        MIN(g.score) as min_score, MAX(g.score) as max_score
                 FROM grades g
                 JOIN users u ON g.student_id = u.id AND u.tenant_id = ?
                 JOIN classes c ON u.class_id = c.id
                 WHERE c.grade_level = ?
                 GROUP BY g.subject",
        [$tenantId, $gradeLevel]
      );
      if (empty($results)) {
        return "No grade data found for grade level <strong>" . htmlspecialchars($gradeLevel, ENT_QUOTES, 'UTF-8') . "</strong>.";
      }
      $msg = "📊 <strong>Grade Report — Grade {$gradeLevel}:</strong><br>";
      foreach ($results as $r) {
        $msg .= "<strong>{$r['subject']}:</strong> Avg {$r['avg_score']} | Min {$r['min_score']} | Max {$r['max_score']} ({$r['total']} records)<br>";
      }
      return $msg;
    }

    // Show inactive accounts
    if (strpos($input, 'inactive account') !== false || strpos($input, 'inactive user') !== false) {
      $results = db()->fetchAll(
        "SELECT id, full_name, email, role FROM users WHERE tenant_id = ? AND is_active = 0 LIMIT 20",
        [$tenantId]
      );
      if (empty($results)) {
        return '✅ No inactive accounts found.';
      }
      $msg = "👤 <strong>Inactive Accounts (" . count($results) . "):</strong><br>";
      foreach ($results as $r) {
        $msg .= "#{$r['id']} — " . htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') . " ({$r['role']}) — {$r['email']}<br>";
      }
      return $msg;
    }

    // Show system status
    if (strpos($input, 'system status') !== false || strpos($input, 'server status') !== false) {
      $tableCount = db()->fetchOne("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE()");
      $diskFree = disk_free_space('/');
      $diskFormatted = $diskFree !== false ? round($diskFree / 1073741824, 2) . ' GB' : 'N/A';
      return "🖥️ <strong>System Status:</strong><br>"
        . "PHP Version: " . PHP_VERSION . "<br>"
        . "Database Tables: " . ($tableCount['cnt'] ?? 'N/A') . "<br>"
        . "Disk Free: {$diskFormatted}<br>"
        . "Server Time: " . date('Y-m-d H:i:s');
    }

    // Show recent activity
    if (strpos($input, 'recent activity') !== false || strpos($input, 'audit log') !== false) {
      $results = db()->fetchAll(
        "SELECT action, user_id, ip_address, created_at FROM audit_logs
                 WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 10",
        [$tenantId]
      );
      if (empty($results)) {
        return 'No recent activity found.';
      }
      $msg = '📋 <strong>Recent Activity:</strong><br>';
      foreach ($results as $r) {
        $msg .= "[{$r['created_at']}] User #{$r['user_id']} — " . htmlspecialchars($r['action'], ENT_QUOTES, 'UTF-8') . " (IP: {$r['ip_address']})<br>";
      }
      return $msg;
    }

    // User counts
    if (preg_match('/how many (students|teachers|users)/', $input, $matches)) {
      $type = $matches[1];
      if ($type === 'users') {
        $result = db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?", [$tenantId]);
      } else {
        $role = rtrim($type, 's'); // students -> student
        $result = db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND role = ?", [$tenantId, $role]);
      }
      $count = $result['cnt'] ?? 0;
      return "📊 There are <strong>{$count}</strong> {$type} in the system.";
    }

    // Default help
    return '🤖 I can help with:<br>'
      . '• <strong>show attendance anomalies</strong> — detect suspicious attendance patterns<br>'
      . '• <strong>generate grade report grade X</strong> — grade distribution for a level<br>'
      . '• <strong>show inactive accounts</strong> — list disabled user accounts<br>'
      . '• <strong>show system status</strong> — PHP version, DB stats, disk space<br>'
      . '• <strong>show recent activity</strong> — last 10 audit log entries<br>'
      . '• <strong>how many students/teachers/users</strong> — user counts by role';
  } catch (Exception $e) {
    error_log("Admin bot error: " . $e->getMessage());
    return '❌ An error occurred while processing your request. Please try again.';
  }
}

$pageTitle = 'Admin Assistant';
$chatHistory = $_SESSION['chat_history_admin'];
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
        <!-- Bot welcome -->
        <div class="chat-bubble bot" style="display:flex;gap:12px;margin-bottom:16px;">
          <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:grid;place-items:center;flex-shrink:0;"><i class="fas fa-robot"></i></div>
          <div style="background:var(--bg-primary);padding:12px 16px;border-radius:0 14px 14px 14px;max-width:80%;">
            👋 Hello Admin! I'm your AI assistant. Ask me about attendance anomalies, grade reports, inactive accounts, system status, recent activity, or user counts.
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
