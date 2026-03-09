<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

// Handle bulk enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_enroll'])) {
    $class_id = (int)$_POST['class_id'];
    $student_ids = $_POST['student_ids'] ?? [];

    $enrolled_count = 0;
    $already_enrolled = 0;

    foreach ($student_ids as $student_id) {
        $student_id = (int)$student_id;

        // Check if already enrolled
        $existing = db()->fetchOne("
            SELECT id FROM class_enrollments
            WHERE class_id = ? AND student_id = ?
        ", [$class_id, $student_id]);

        if (!$existing) {
            db()->insert('class_enrollments', [
                'class_id' => $class_id,
                'student_id' => $student_id,
                'enrollment_date' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ]);
            $enrolled_count++;
            log_activity($_SESSION['user_id'], 'enroll', 'class_enrollments', $student_id);
        } else {
            $already_enrolled++;
        }
    }

    $message = "Successfully enrolled {$enrolled_count} student(s).";
    if ($already_enrolled > 0) {
        $message .= " {$already_enrolled} already enrolled.";
    }
    $message_type = 'success';
}

// Handle individual enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $class_id = (int)$_POST['class_id'];
    $student_id = (int)$_POST['student_id'];

    $existing = db()->fetchOne("
        SELECT id FROM class_enrollments
        WHERE class_id = ? AND student_id = ?
    ", [$class_id, $student_id]);

    if (!$existing) {
        db()->insert('class_enrollments', [
            'class_id' => $class_id,
            'student_id' => $student_id,
            'enrollment_date' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ]);
        log_activity($_SESSION['user_id'], 'enroll', 'class_enrollments', $student_id);
        $message = 'Student enrolled successfully!';
        $message_type = 'success';
    } else {
        $message = 'Student is already enrolled in this class!';
        $message_type = 'error';
    }
}

// Handle unenrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unenroll_student'])) {
    $enrollment_id = (int)$_POST['enrollment_id'];

    db()->delete('class_enrollments', 'id = ?', [$enrollment_id]);
    log_activity($_SESSION['user_id'], 'unenroll', 'class_enrollments', $enrollment_id);
    $message = 'Student unenrolled successfully!';
    $message_type = 'success';
}

// Get all classes
$classes = db()->fetchAll("
    SELECT c.*,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           COUNT(DISTINCT ce.student_id) as enrolled_count
    FROM classes c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    GROUP BY c.id
    ORDER BY c.class_name
");

// Get selected class details
$selected_class_id = isset($_GET['class']) ? (int)$_GET['class'] : null;
$selected_class = null;
$enrolled_students = [];
$available_students = [];

if ($selected_class_id) {
    $selected_class = db()->fetchOne("
        SELECT c.*,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM classes c
        LEFT JOIN teachers t ON c.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE c.id = ?
    ", [$selected_class_id]);

    if ($selected_class) {
        // Get enrolled students
        $enrolled_students = db()->fetchAll("
            SELECT s.*, ce.id as enrollment_id, ce.enrollment_date,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name
            FROM class_enrollments ce
            LEFT JOIN students s ON ce.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE ce.class_id = ?
            ORDER BY student_name
        ", [$selected_class_id]);

        // Get available students (not enrolled in this class)
        $available_students = db()->fetchAll("
            SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as student_name
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.grade_level = ?
            AND s.id NOT IN (
                SELECT student_id FROM class_enrollments WHERE class_id = ?
            )
            AND u.status = 'active'
            ORDER BY student_name
        ", [$selected_class['grade_level'], $selected_class_id]);
    }
}

$page_title = 'Class Enrollment Management';
$page_icon = 'user-plus';
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
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
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
                            <div class="user-role">Administrator</div>
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

                <!-- Class Selection -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-school"></i> <span>Select Class</span></div>
                    </div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;">
                            <?php foreach ($classes as $class): ?>
                                <a href="?class=<?php echo $class['id']; ?>" class="cyber-btn <?php echo $selected_class_id === $class['id'] ? 'primary' : ''; ?>" style="display:block;padding:20px;text-align:left;height:auto;">
                                    <div style="font-size:1.2rem;font-weight:700;margin-bottom:8px;"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                    <div style="font-size:0.85rem;opacity:0.8;margin-bottom:10px;">
                                        <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($class['teacher_name']); ?>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                                        <span><i class="fas fa-graduation-cap"></i> Grade <?php echo $class['grade']; ?></span>
                                        <span><i class="fas fa-users"></i> <?php echo $class['enrolled_count']; ?> students</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($selected_class): ?>
                    <!-- Enrolled Students -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-users"></i>
                                <span>Enrolled Students (<?php echo count($enrolled_students); ?>)</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($enrolled_students)): ?>
                                <div style="text-align:center;padding:30px;color:rgba(255,255,255,0.4);">No students enrolled yet</div>
                            <?php else: ?>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr style="border-bottom:2px solid var(--cyber-cyan);">
                                                <th style="padding:12px;text-align:left;color:var(--cyber-cyan);font-weight:700;">STUDENT</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">STUDENT ID</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">GRADE</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">ENROLLED</th>
                                                <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enrolled_students as $student): ?>
                                                <tr style="border-bottom:1px solid rgba(0,191,255,0.1);">
                                                    <td style="padding:12px;">
                                                        <div style="font-weight:600;color:var(--cyber-cyan);"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                    </td>
                                                    <td style="padding:12px;text-align:center;color:rgba(255,255,255,0.7);"><?php echo $student['student_id']; ?></td>
                                                    <td style="padding:12px;text-align:center;color:rgba(255,255,255,0.7);"><?php echo $student['grade']; ?></td>
                                                    <td style="padding:12px;text-align:center;color:rgba(255,255,255,0.7);"><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                                    <td style="padding:12px;text-align:center;">
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this student from the class?');">
                                                            <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                            <button type="submit" name="unenroll_student" class="cyber-btn danger" style="padding:6px 12px;">
                                                                <i class="fas fa-user-minus"></i> Remove
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Available Students -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-user-plus"></i>
                                <span>Available Students - Grade <?php echo $selected_class['grade']; ?> (<?php echo count($available_students); ?>)</span>
                            </div>
                            <?php if (!empty($available_students)): ?>
                                <button onclick="document.getElementById('bulkEnrollModal').style.display='flex'" class="cyber-btn primary">
                                    <i class="fas fa-users"></i> Bulk Enroll
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($available_students)): ?>
                                <div style="text-align:center;padding:30px;color:rgba(255,255,255,0.4);">
                                    <i class="fas fa-check-circle" style="font-size:2rem;margin-bottom:10px;"></i>
                                    <div>All Grade <?php echo $selected_class['grade']; ?> students are enrolled</div>
                                </div>
                            <?php else: ?>
                                <div style="display:grid;gap:15px;">
                                    <?php foreach ($available_students as $student): ?>
                                        <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid rgba(0,191,255,0.2);border-radius:12px;padding:15px;display:flex;justify-content:space-between;align-items:center;">
                                            <div>
                                                <div style="font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                <div style="font-size:0.85rem;color:rgba(255,255,255,0.6);">
                                                    <i class="fas fa-id-card"></i> <?php echo $student['student_id']; ?> •
                                                    <i class="fas fa-graduation-cap"></i> Grade <?php echo $student['grade']; ?>
                                                </div>
                                            </div>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" name="enroll_student" class="cyber-btn primary">
                                                    <i class="fas fa-plus"></i> Enroll
                                                </button>
                                            </form>
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

    <!-- Bulk Enrollment Modal -->
    <div id="bulkEnrollModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:80vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-users"></i> <span>Bulk Student Enrollment</span></div>
                <button onclick="document.getElementById('bulkEnrollModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                    <div style="margin-bottom:15px;">
                        <label style="color:var(--cyber-cyan);font-weight:600;margin-bottom:10px;display:block;">
                            <input type="checkbox" id="selectAll" onclick="toggleAll(this)"> SELECT ALL STUDENTS
                        </label>
                    </div>

                    <div style="max-height:400px;overflow-y:auto;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;">
                        <?php if (isset($available_students)): foreach ($available_students as $student): ?>
                                <label style="display:block;padding:10px;margin-bottom:5px;background:rgba(0,191,255,0.1);border-radius:8px;cursor:pointer;transition:all 0.3s;">
                                    <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox">
                                    <strong style="color:var(--cyber-cyan);margin-left:10px;"><?php echo htmlspecialchars($student['student_name']); ?></strong>
                                    <span style="color:rgba(255,255,255,0.6);margin-left:10px;">(<?php echo $student['student_id']; ?>)</span>
                                </label>
                        <?php endforeach;
                        endif; ?>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                        <button type="button" onclick="document.getElementById('bulkEnrollModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="bulk_enroll" class="cyber-btn primary">
                            <i class="fas fa-user-plus"></i> Enroll Selected
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>