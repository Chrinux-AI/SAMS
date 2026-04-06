<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher('../login.php');

$message = '';
$message_type = '';

// Get teacher info
$teacher = db()->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$_SESSION['user_id']]);

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $data = [
        'class_id' => (int)$_POST['class_id'],
        'title' => sanitize($_POST['title']),
        'description' => sanitize($_POST['description']),
        'assignment_type' => sanitize($_POST['assignment_type']),
        'due_date' => sanitize($_POST['due_date']),
        'max_points' => (int)$_POST['max_points'],
        'created_by' => $_SESSION['user_id']
    ];

    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = '../uploads/assignments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['attachment']['name']);
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $file_name)) {
            $data['attachment_url'] = 'uploads/assignments/' . $file_name;
        }
    }

    $id = db()->insert('assignments', $data);
    if ($id) {
        log_activity($_SESSION['user_id'], 'create', 'assignments', $id);
        $message = 'Assignment created successfully!';
        $message_type = 'success';
    }
}

// Handle submission grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $data = [
        'grade' => (int)$_POST['grade'],
        'feedback' => sanitize($_POST['feedback']),
        'graded_at' => date('Y-m-d H:i:s')
    ];
    db()->update('assignment_submissions', $data, 'id = ?', [$submission_id]);
    log_activity($_SESSION['user_id'], 'grade', 'assignment_submissions', $submission_id);
    $message = 'Submission graded successfully!';
    $message_type = 'success';
}

// Get teacher's classes
$classes = db()->fetchAll("
    SELECT * FROM classes WHERE teacher_id = ? ORDER BY class_name
", [$teacher['id']]);

// Get all assignments for teacher's classes
$assignments = db()->fetchAll("
    SELECT a.*, c.class_name, c.grade,
           COUNT(DISTINCT asub.id) as total_submissions,
           COUNT(DISTINCT CASE WHEN asub.graded_at IS NOT NULL THEN asub.id END) as graded_count
    FROM assignments a
    LEFT JOIN classes c ON a.class_id = c.id
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id
    WHERE c.class_teacher_id = ?
    GROUP BY a.id
    ORDER BY a.due_date DESC
", [$teacher['id']]);

// Get selected assignment details if requested
$selected_assignment = null;
$submissions = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $assignment_id = (int)$_GET['view'];
    $selected_assignment = db()->fetchOne("
        SELECT a.*, c.class_name, c.grade
        FROM assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ", [$assignment_id]);

    if ($selected_assignment) {
        $submissions = db()->fetchAll("
            SELECT asub.*, s.admission_number AS student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name
            FROM assignment_submissions asub
            LEFT JOIN students s ON asub.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE asub.assignment_id = ?
            ORDER BY asub.submitted_at DESC
        ", [$assignment_id]);
    }
}

$page_title = 'Assignments';
$page_icon = 'tasks';
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
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">

</head>

<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

    <div class="cyber-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <button onclick="document.getElementById('addAssignmentModal').style.display='flex'" class="cyber-btn primary">
                        <i class="fas fa-plus-circle"></i> Create Assignment
                    </button>
                    <div class="user-card" style="padding:8px 15px;margin:0;margin-left:15px;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Teacher</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">
                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($selected_assignment): ?>
                    <!-- Submission Review View -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-file-alt"></i>
                                <span><?php echo htmlspecialchars($selected_assignment['title']); ?></span>
                            </div>
                            <a href="assignments.php" class="cyber-btn">
                                <i class="fas fa-arrow-left"></i> Back to All Assignments
                            </a>
                        </div>
                        <div class="card-body">
                            <div style="margin-bottom:20px;padding:15px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;">
                                <div><strong>Class:</strong> <?php echo htmlspecialchars($selected_assignment['class_name']); ?> (Grade <?php echo $selected_assignment['grade']; ?>)</div>
                                <div><strong>Due Date:</strong> <?php echo date('M d, Y h:i A', strtotime($selected_assignment['due_date'])); ?></div>
                                <div><strong>Max Points:</strong> <?php echo $selected_assignment['max_points']; ?></div>
                            </div>

                            <h3 style="color:var(--cyber-cyan);margin-bottom:15px;">Submissions (<?php echo count($submissions); ?>)</h3>
                            <?php if (empty($submissions)): ?>
                                <div style="text-align:center;padding:30px;color:rgba(255,255,255,0.4);">No submissions yet</div>
                            <?php else: ?>
                                <div style="display:grid;gap:15px;">
                                    <?php foreach ($submissions as $sub): ?>
                                        <div style="background:rgba(0,191,255,0.05);border:1px solid rgba(0,191,255,0.2);border-radius:10px;padding:15px;">
                                            <div style="display:flex;justify-content:space-between;align-items:start;">
                                                <div style="flex:1;">
                                                    <h4 style="color:var(--cyber-cyan);margin-bottom:5px;"><?php echo htmlspecialchars($sub['student_name']); ?></h4>
                                                    <div style="color:rgba(255,255,255,0.6);font-size:0.85rem;">
                                                        Submitted: <?php echo date('M d, Y h:i A', strtotime($sub['submitted_at'])); ?>
                                                    </div>
                                                    <?php if ($sub['submission_url']): ?>
                                                        <a href="../<?php echo htmlspecialchars($sub['submission_url']); ?>" target="_blank" class="cyber-btn" style="margin-top:10px;display:inline-block;">
                                                            <i class="fas fa-download"></i> Download Submission
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($sub['graded_at']): ?>
                                                        <div style="text-align:right;">
                                                            <div style="font-size:1.5rem;color:var(--cyber-pink);font-weight:700;">
                                                                <?php echo $sub['grade']; ?>/<?php echo $selected_assignment['max_points']; ?>
                                                            </div>
                                                            <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);">Graded</div>
                                                        </div>
                                                    <?php else: ?>
                                                        <button onclick="showGradeForm(<?php echo $sub['id']; ?>, '<?php echo addslashes($sub['student_name']); ?>', <?php echo $selected_assignment['max_points']; ?>)" class="cyber-btn primary">
                                                            <i class="fas fa-check"></i> Grade
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($sub['feedback']): ?>
                                                <div style="margin-top:10px;padding:10px;background:rgba(138,43,226,0.1);border-left:3px solid var(--cyber-pink);border-radius:5px;">
                                                    <strong>Feedback:</strong> <?php echo htmlspecialchars($sub['feedback']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Assignments List View -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-list"></i> <span>All Assignments</span></div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>
                                <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                    <i class="fas fa-tasks" style="font-size:3rem;margin-bottom:15px;"></i>
                                    <div>No assignments created yet</div>
                                </div>
                            <?php else: ?>
                                <div style="display:grid;gap:15px;">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid rgba(0,191,255,0.2);border-radius:10px;padding:20px;">
                                            <div style="display:flex;justify-content:space-between;align-items:start;">
                                                <div style="flex:1;">
                                                    <h3 style="color:var(--cyber-cyan);font-size:1.2rem;margin-bottom:8px;"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                                    <div style="color:rgba(255,255,255,0.7);margin-bottom:10px;"><?php echo htmlspecialchars(substr($assignment['description'], 0, 150)) . (strlen($assignment['description']) > 150 ? '...' : ''); ?></div>
                                                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                        <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-school"></i> <?php echo htmlspecialchars($assignment['class_name']); ?>
                                                        </span>
                                                        <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                        </span>
                                                        <span style="padding:5px 12px;background:rgba(138,43,226,0.1);border:1px solid var(--cyber-pink);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-paper-plane"></i> <?php echo $assignment['total_submissions']; ?> submissions
                                                        </span>
                                                        <span style="padding:5px 12px;background:rgba(138,43,226,0.1);border:1px solid var(--cyber-pink);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-check-circle"></i> <?php echo $assignment['graded_count']; ?> graded
                                                        </span>
                                                    </div>
                                                </div>
                                                <a href="?view=<?php echo $assignment['id']; ?>" class="cyber-btn primary">
                                                    <i class="fas fa-eye"></i> View Submissions
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Assignment Modal -->
    <div id="addAssignmentModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> <span>Create Assignment</span></div>
                <button onclick="document.getElementById('addAssignmentModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" style="display:grid;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS *</label>
                        <select name="class_id" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?> (Grade <?php echo $class['grade']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">TITLE *</label>
                        <input type="text" name="title" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">DESCRIPTION</label>
                        <textarea name="description" rows="3" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">TYPE *</label>
                            <select name="assignment_type" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <option value="homework">Homework</option>
                                <option value="quiz">Quiz</option>
                                <option value="exam">Exam</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">MAX POINTS *</label>
                            <input type="number" name="max_points" required value="100" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">DUE DATE *</label>
                        <input type="datetime-local" name="due_date" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ATTACHMENT</label>
                        <input type="file" name="attachment" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('addAssignmentModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="create_assignment" class="cyber-btn primary"><i class="fas fa-save"></i> Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Grade Submission Modal -->
    <div id="gradeModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:500px;width:90%;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-check-circle"></i> <span id="gradeModalTitle">Grade Submission</span></div>
                <button onclick="document.getElementById('gradeModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" style="display:grid;gap:15px;">
                    <input type="hidden" name="submission_id" id="gradeSubmissionId">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">GRADE * <span id="gradeRange"></span></label>
                        <input type="number" name="grade" id="gradeInput" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;font-size:1.2rem;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">FEEDBACK</label>
                        <textarea name="feedback" rows="4" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" onclick="document.getElementById('gradeModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="grade_submission" class="cyber-btn primary"><i class="fas fa-save"></i> Save Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showGradeForm(submissionId, studentName, maxPoints) {
            document.getElementById('gradeSubmissionId').value = submissionId;
            document.getElementById('gradeModalTitle').textContent = 'Grade Submission - ' + studentName;
            document.getElementById('gradeRange').textContent = '(0-' + maxPoints + ')';
            document.getElementById('gradeInput').max = maxPoints;
            document.getElementById('gradeModal').style.display = 'flex';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
