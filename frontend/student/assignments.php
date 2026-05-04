<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student('../login.php');

$message = '';
$message_type = '';

// Get student info
$student = db()->fetchOne("SELECT * FROM students WHERE user_id = ?", [$_SESSION['user_id']]);

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];

    // Check if already submitted
    $existing = db()->fetchOne("
        SELECT id FROM assignment_submissions
        WHERE assignment_id = ? AND student_id = ?
    ", [$assignment_id, $student['id']]);

    if ($existing) {
        $message = 'You have already submitted this assignment!';
        $message_type = 'error';
    } else {
        $data = [
            'assignment_id' => $assignment_id,
            'student_id' => $student['id'],
            'submitted_at' => date('Y-m-d H:i:s')
        ];

        if (!empty($_FILES['submission_file']['name'])) {
            $upload_dir = '../uploads/submissions/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_name = time() . '_' . $student['id'] . '_' . basename($_FILES['submission_file']['name']);
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $upload_dir . $file_name)) {
                $data['submission_url'] = 'uploads/submissions/' . $file_name;
            }
        }

        $id = db()->insert('assignment_submissions', $data);
        if ($id) {
            log_activity($_SESSION['user_id'], 'submit', 'assignment_submissions', $id);
            $message = 'Assignment submitted successfully!';
            $message_type = 'success';
        }
    }
}

// Get student's enrolled classes
$enrolled_class_ids = db()->fetchAll("
    SELECT class_id FROM class_enrollments WHERE student_id = ?
", [$student['id']]);
$class_ids = array_column($enrolled_class_ids, 'class_id');

// Get assignments for enrolled classes
$assignments = [];
if (!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $assignments = db()->fetchAll("
        SELECT a.*, c.class_name, c.grade,
               asub.id as submission_id, asub.submitted_at, asub.grade, asub.feedback, asub.graded_at
        FROM assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
        WHERE a.class_id IN ($placeholders)
        ORDER BY a.due_date ASC
    ", array_merge([$student['id']], $class_ids));
}

$page_title = 'My Assignments';
$page_icon = 'clipboard-list';
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

</head>

<body>
    <div class="starfield"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Student</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="app-layout">
                <?php if ($message): ?>
                    <div class="app-layout">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-tasks"></i> <span>All Assignments</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-clipboard-list" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No assignments available</div>
                                <div style="font-size:0.85rem;margin-top:10px;">Enroll in classes to see assignments</div>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;gap:20px;">
                                <?php
                                $now = new DateTime();
                                foreach ($assignments as $assignment):
                                    $due_date = new DateTime($assignment['due_date']);
                                    $is_overdue = $due_date < $now && !$assignment['submission_id'];
                                    $is_graded = $assignment['graded_at'] !== null;
                                ?>
                                    <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid <?php echo $is_overdue ? 'var(--cyber-pink)' : 'rgba(0,191,255,0.2)'; ?>;border-radius:12px;padding:20px;">
                                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;">
                                            <div style="flex:1;">
                                                <h3 style="color:var(--cyber-cyan);font-size:1.3rem;margin-bottom:5px;"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                                <div style="color:rgba(255,255,255,0.6);font-size:0.9rem;margin-bottom:10px;">
                                                    <?php echo htmlspecialchars($assignment['class_name']); ?> • Grade <?php echo $assignment['grade']; ?>
                                                </div>
                                            </div>
                                            <?php if ($assignment['submission_id']): ?>
                                                <?php if ($is_graded): ?>
                                                    <div style="text-align:center;padding:15px;background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));border:1px solid var(--cyber-cyan);border-radius:10px;">
                                                        <div style="font-size:2rem;font-weight:700;color:var(--cyber-pink);"><?php echo $assignment['grade']; ?></div>
                                                        <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);">/ <?php echo $assignment['max_points']; ?> pts</div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="padding:8px 15px;background:rgba(0,191,255,0.2);border:1px solid var(--cyber-cyan);border-radius:15px;font-weight:700;">
                                                        <i class="fas fa-check"></i> Submitted
                                                    </span>
                                                <?php endif; ?>
                                            <?php elseif ($is_overdue): ?>
                                                <span style="padding:8px 15px;background:rgba(255,20,147,0.2);border:1px solid var(--cyber-pink);border-radius:15px;font-weight:700;color:var(--cyber-pink);">
                                                    <i class="fas fa-exclamation-triangle"></i> Overdue
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($assignment['description'])): ?>
                                            <p style="color:rgba(255,255,255,0.7);margin-bottom:15px;"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                        <?php endif; ?>

                                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
                                            <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                <i class="fas fa-tag"></i> <?php echo ucfirst($assignment['assignment_type']); ?>
                                            </span>
                                            <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                <i class="fas fa-calendar"></i> Due: <?php echo $due_date->format('M d, Y h:i A'); ?>
                                            </span>
                                            <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                <i class="fas fa-trophy"></i> Max: <?php echo $assignment['max_points']; ?> pts
                                            </span>
                                        </div>

                                        <?php if ($assignment['submission_id']): ?>
                                            <div style="padding:12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:8px;">
                                                <div style="font-weight:600;color:var(--cyber-cyan);margin-bottom:5px;">
                                                    <i class="fas fa-check-circle"></i> Submitted on: <?php echo date('M d, Y h:i A', strtotime($assignment['submitted_at'])); ?>
                                                </div>
                                                <?php if ($is_graded && $assignment['feedback']): ?>
                                                    <div style="margin-top:10px;padding:10px;background:rgba(138,43,226,0.1);border-left:3px solid var(--cyber-pink);border-radius:5px;">
                                                        <strong>Teacher Feedback:</strong><br>
                                                        <?php echo htmlspecialchars($assignment['feedback']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="display:flex;gap:10px;">
                                                <?php if ($assignment['attachment_url']): ?>
                                                    <a href="../<?php echo htmlspecialchars($assignment['attachment_url']); ?>" target="_blank" class="cyber-btn">
                                                        <i class="fas fa-download"></i> Download Materials
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!$is_overdue): ?>
                                                    <button onclick="showSubmitForm(<?php echo $assignment['id']; ?>, '<?php echo addslashes($assignment['title']); ?>')" class="cyber-btn primary">
                                                        <i class="fas fa-upload"></i> Submit Assignment
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Submit Assignment Modal -->
    <div id="submitModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:500px;width:90%;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-upload"></i> <span id="submitModalTitle">Submit Assignment</span></div>
                <button onclick="document.getElementById('submitModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" style="display:grid;gap:15px;">
                    <input type="hidden" name="assignment_id" id="submitAssignmentId">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">UPLOAD YOUR WORK *</label>
                        <input type="file" name="submission_file" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);margin-top:5px;">Accepted formats: PDF, DOC, DOCX, images</div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('submitModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="submit_assignment" class="cyber-btn primary"><i class="fas fa-paper-plane"></i> Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showSubmitForm(assignmentId, assignmentTitle) {
            document.getElementById('submitAssignmentId').value = assignmentId;
            document.getElementById('submitModalTitle').textContent = 'Submit: ' + assignmentTitle;
            document.getElementById('submitModal').style.display = 'flex';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
