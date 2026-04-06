<?php

/**
 * Student - Study Group Details
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$group_id = (int)($_GET['id'] ?? 0);

if ($group_id <= 0) {
  $_SESSION['error_message'] = 'Invalid study group selected.';
  header('Location: study-groups.php');
  exit;
}

$student = db()->fetchOne("SELECT id FROM students WHERE user_id = ?", [$user_id]);
$student_id = (int)($student['id'] ?? 0);
if ($student_id <= 0) {
  $_SESSION['error_message'] = 'Student profile not found.';
  header('Location: study-groups.php');
  exit;
}

$group = db()->fetchOne("SELECT sg.*, c.class_name, c.class_code, u.first_name, u.last_name
    FROM study_groups sg
    JOIN classes c ON c.id = sg.class_id
    JOIN users u ON u.id = sg.creator_id
    WHERE sg.id = ?", [$group_id]);

if (!$group) {
  $_SESSION['error_message'] = 'Study group not found.';
  header('Location: study-groups.php');
  exit;
}

$membership = db()->fetchOne("SELECT status FROM study_group_members WHERE group_id = ? AND student_id = ?", [$group_id, $student_id]);
if (!$membership) {
  $_SESSION['error_message'] = 'You do not have access to this study group.';
  header('Location: study-groups.php');
  exit;
}

$members = db()->fetchAll("SELECT u.first_name, u.last_name, u.email, sgm.status, sgm.joined_at
    FROM study_group_members sgm
    JOIN students s ON s.id = sgm.student_id
    JOIN users u ON u.id = s.user_id
    WHERE sgm.group_id = ?
    ORDER BY FIELD(sgm.status, 'accepted', 'pending', 'rejected'), u.last_name, u.first_name", [$group_id]);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
  <div class="content-header">
    <h1><i class="fas fa-users"></i> <?php echo htmlspecialchars($group['name']); ?></h1>
    <p class="subtitle"><?php echo htmlspecialchars($group['class_name'] . ' (' . $group['class_code'] . ')'); ?></p>
    <a href="study-groups.php" class="cyber-btn-outline"><i class="fas fa-arrow-left"></i> Back to Groups</a>
  </div>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']);
                                                unset($_SESSION['error_message']); ?>
    </div>
  <?php endif; ?>

  <div class="holo-card" style="margin-bottom: 24px;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-info-circle"></i> Group Details</div>
    </div>
    <div class="card-body">
      <p><strong>Creator:</strong> <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?></p>
      <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($group['status'])); ?></p>
      <p><strong>Your Membership:</strong> <?php echo htmlspecialchars(ucfirst($membership['status'])); ?></p>
      <p><strong>Max Members:</strong> <?php echo (int)($group['max_members'] ?? 0); ?></p>
      <?php if (!empty($group['meeting_schedule'])): ?>
        <p><strong>Meeting Schedule:</strong> <?php echo htmlspecialchars($group['meeting_schedule']); ?></p>
      <?php endif; ?>
      <?php if (!empty($group['description'])): ?>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($group['description']); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="holo-card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-user-friends"></i> Members (<?php echo count($members); ?>)</div>
    </div>
    <div class="card-body">
      <?php if (empty($members)): ?>
        <div class="empty-state">
          <i class="fas fa-users" style="font-size: 3rem; opacity: 0.25;"></i>
          <p>No members yet.</p>
        </div>
      <?php else: ?>
        <table class="holo-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Status</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
              <tr>
                <td><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></td>
                <td><?php echo htmlspecialchars($m['email']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($m['status'])); ?></td>
                <td><?php echo !empty($m['joined_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($m['joined_at']))) : '-'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/cyber-footer.php'; ?>
