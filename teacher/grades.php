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

// Handle grade entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade'])) {
    $student_id = (int)$_POST['student_id'];
    $class_id = (int)$_POST['class_id'];
    $assignment_id = !empty($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : null;
    $points_earned = (float)$_POST['points_earned'];
    $max_points = (float)$_POST['max_points'];
    $percentage = ($points_earned / $max_points) * 100;

    // Calculate letter grade
    if ($percentage >= 90) $letter = 'A';
    elseif ($percentage >= 80) $letter = 'B';
    elseif ($percentage >= 70) $letter = 'C';
    elseif ($percentage >= 60) $letter = 'D';
    else $letter = 'F';

    $data = [
        'student_id' => $student_id,
        'class_id' => $class_id,
        'assignment_id' => $assignment_id,
        'points_earned' => $points_earned,
        'max_points' => $max_points,
        'percentage' => $percentage,
        'letter_grade' => $letter,
        'comments' => sanitize($_POST['comments']),
        'graded_by' => $_SESSION['user_id'],
        'grade_date' => date('Y-m-d')
    ];

    $id = db()->insert('grades', $data);
    if ($id) {
        log_activity($_SESSION['user_id'], 'create', 'grades', $id);
        $message = 'Grade added successfully!';
        $message_type = 'success';
    }
}

// Get teacher's classes
$classes = db()->fetchAll("
    SELECT * FROM classes WHERE teacher_id = ? ORDER BY class_name
", [$teacher['id']]);

// Get selected class students and grades
$selected_class_id = isset($_GET['class']) ? (int)$_GET['class'] : null;
$students = [];
$class_info = null;

if ($selected_class_id) {
    $class_info = db()->fetchOne("SELECT * FROM classes WHERE id = ? AND teacher_id = ?", [$selected_class_id, $teacher['id']]);

    if ($class_info) {
        // Get students enrolled in this class with their grades
        $students = db()->fetchAll("
            SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   (SELECT AVG(percentage) FROM grades WHERE student_id = s.id AND class_id = ?) as avg_percentage,
                   (SELECT COUNT(*) FROM grades WHERE student_id = s.id AND class_id = ?) as grade_count
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN class_enrollments ce ON s.id = ce.student_id
            WHERE ce.class_id = ?
            ORDER BY student_name
        ", [$selected_class_id, $selected_class_id, $selected_class_id]);
    }
}

// Get assignments for selected class
$assignments = [];
if ($selected_class_id) {
    $assignments = db()->fetchAll("
        SELECT * FROM assignments WHERE class_id = ? ORDER BY due_date DESC
    ", [$selected_class_id]);
}

$page_title = 'Grade Management';
$page_icon = 'graduation-cap';
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
    
</head>
<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

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
                            <div class="user-role">Teacher</div>
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

                <!-- Class Selection -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-school"></i> <span>Select Class</span></div>
                    </div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
                            <?php foreach ($classes as $class): ?>
                                <a href="?class=<?php echo $class['id']; ?>" class="cyber-btn <?php echo $selected_class_id === $class['id'] ? 'primary' : ''; ?>" style="display:block;padding:20px;text-align:center;">
                                    <div style="font-size:1.2rem;font-weight:700;margin-bottom:5px;"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                    <div style="font-size:0.85rem;opacity:0.8;">Grade <?php echo $class['grade']; ?> • Room <?php echo $class['room_number']; ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($class_info): ?>
                    <!-- Student Grades Table -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-users"></i>
                                <span><?php echo htmlspecialchars($class_info['class_name']); ?> - Student Grades</span>
                            </div>
                            <button onclick="document.getElementById('addGradeModal').style.display='flex'" class="cyber-btn primary">
                                <i class="fas fa-plus-circle"></i> Add Grade
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">No students enrolled in this class</div>
                            <?php else: ?>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr style="border-bottom:2px solid var(--cyber-cyan);">
                                                <th style="padding:12px;text-align:left;color:var(--cyber-cyan);font-weight:700;">STUDENT</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">STUDENT ID</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">GRADES RECORDED</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">AVERAGE</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $student): ?>
                                                <tr style="border-bottom:1px solid rgba(0,191,255,0.1);">
                                                    <td style="padding:12px;">
                                                        <div style="font-weight:600;color:var(--cyber-cyan);"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                    </td>
                                                    <td style="padding:12px;text-align:center;color:rgba(255,255,255,0.7);"><?php echo $student['student_id']; ?></td>
                                                    <td style="padding:12px;text-align:center;color:rgba(255,255,255,0.7);"><?php echo $student['grade_count']; ?></td>
                                                    <td style="padding:12px;text-align:center;">
                                                        <?php if ($student['avg_percentage']): ?>
                                                            <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-weight:700;">
                                                                <?php echo number_format($student['avg_percentage'], 1); ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="color:rgba(255,255,255,0.4);">No grades</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:12px;text-align:center;">
                                                        <button onclick="showGradeForm(<?php echo $student['id']; ?>, '<?php echo addslashes($student['student_name']); ?>')" class="cyber-btn primary" style="padding:6px 12px;">
                                                            <i class="fas fa-plus"></i> Add Grade
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Grade Modal -->
    <div id="addGradeModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> <span id="gradeModalTitle">Add Grade</span></div>
                <button onclick="document.getElementById('addGradeModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" style="display:grid;gap:15px;">
                    <input type="hidden" name="student_id" id="gradeStudentId">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ASSIGNMENT (OPTIONAL)</label>
                        <select name="assignment_id" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <option value="">General Grade (Not linked to assignment)</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option value="<?php echo $assignment['id']; ?>"><?php echo htmlspecialchars($assignment['title']); ?> (<?php echo $assignment['max_points']; ?> pts)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">POINTS EARNED *</label>
                            <input type="number" name="points_earned" step="0.01" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;font-size:1.2rem;">
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">MAX POINTS *</label>
                            <input type="number" name="max_points" step="0.01" required value="100" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;font-size:1.2rem;">
                        </div>
                    </div>

                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">COMMENTS</label>
                        <textarea name="comments" rows="3" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;"></textarea>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('addGradeModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="add_grade" class="cyber-btn primary"><i class="fas fa-save"></i> Add Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showGradeForm(studentId, studentName) {
            document.getElementById('gradeStudentId').value = studentId;
            document.getElementById('gradeModalTitle').textContent = 'Add Grade - ' + studentName;
            document.getElementById('addGradeModal').style.display = 'flex';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>