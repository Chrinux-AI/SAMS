<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Allow students and teachers to access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'teacher', 'admin'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

$message = '';
$message_type = '';

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_class'])) {
    $class_id = (int)$_POST['class_id'];

    if ($user_role === 'student') {
        $student = db()->fetchOne("SELECT id FROM students WHERE user_id = ?", [$user_id]);
        if ($student) {
            // Check if already enrolled
            $existing = db()->fetchOne("
                SELECT id FROM class_enrollments
                WHERE class_id = ? AND student_id = ?
            ", [$class_id, $student['id']]);

            if ($existing) {
                $message = 'You are already enrolled in this class';
                $message_type = 'error';
            } else {
                // Enroll student
                db()->insert('class_enrollments', [
                    'class_id' => $class_id,
                    'student_id' => $student['id'],
                    'enrollment_date' => date('Y-m-d'),
                    'status' => 'active'
                ]);
                $message = 'Successfully enrolled in class!';
                $message_type = 'success';
            }
        }
    }
}

// Handle unenrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unenroll_class'])) {
    $class_id = (int)$_POST['class_id'];

    if ($user_role === 'student') {
        $student = db()->fetchOne("SELECT id FROM students WHERE user_id = ?", [$user_id]);
        if ($student) {
            db()->delete('class_enrollments', 'class_id = ? AND student_id = ?', [$class_id, $student['id']]);
            $message = 'Successfully unenrolled from class';
            $message_type = 'success';
        }
    }
}

// Get enrolled classes
$enrolled_classes = [];
if ($user_role === 'student') {
    $student = db()->fetchOne("SELECT id FROM students WHERE user_id = ?", [$user_id]);
    if ($student) {
        $enrolled_classes = db()->fetchAll("
            SELECT c.*, ce.enrollment_date, ce.status as enrollment_status,
                   CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                   COUNT(DISTINCT ce2.student_id) as class_size
            FROM class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            LEFT JOIN users u ON c.class_teacher_id = u.id
            LEFT JOIN class_enrollments ce2 ON c.id = ce2.class_id
            WHERE ce.student_id = ? AND ce.status = 'active'
            GROUP BY c.id
            ORDER BY c.class_name
        ", [$student['id']]);
    }
} elseif ($user_role === 'teacher') {
    $enrolled_classes = db()->fetchAll("
        SELECT c.*,
               COUNT(DISTINCT ce.student_id) as class_size
        FROM classes c
        LEFT JOIN class_enrollments ce ON c.id = ce.class_id
        WHERE c.class_teacher_id = ?
        GROUP BY c.id
        ORDER BY c.class_name
    ", [$user_id]);
}

// Get available classes for enrollment (students only)
$available_classes = [];
if ($user_role === 'student') {
    $student = db()->fetchOne("SELECT id, grade FROM students WHERE user_id = ?", [$user_id]);
    if ($student) {
        $available_classes = db()->fetchAll("
            SELECT c.*,
                   CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
                   COUNT(DISTINCT ce.student_id) as class_size,
                   (SELECT COUNT(*) FROM class_enrollments
                    WHERE class_id = c.id AND student_id = ?) as is_enrolled
            FROM classes c
            LEFT JOIN users u ON c.class_teacher_id = u.id
            LEFT JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE c.grade_level = ?
            GROUP BY c.id
            HAVING is_enrolled = 0
            ORDER BY c.class_name
        ", [$student['id'], $student['grade']]);
    }
}

$page_title = $user_role === 'student' ? 'Class Registration' : 'My Classes';
$page_icon = 'clipboard-list';
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

    <style>
        .class-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .class-card:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.2);
            transform: translateY(-3px);
        }

        .class-name {
            font-size: 1.3rem;
            color: var(--cyber-cyan);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .class-code {
            color: rgba(0, 191, 255, 0.6);
            font-family: monospace;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .class-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 191, 255, 0.2);
        }

        .detail-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid var(--cyber-cyan);
            border-radius: 15px;
            font-size: 0.85rem;
        }
    </style>
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
                            <div class="user-role"><?php echo ucfirst($user_role); ?></div>
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

                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($enrolled_classes); ?></div>
                            <div class="stat-label"><?php echo $user_role === 'teacher' ? 'Teaching' : 'Enrolled'; ?></div>
                            <div class="stat-trend up"><i class="fas fa-book"></i><span>Classes</span></div>
                        </div>
                    </div>
                    <?php if ($user_role === 'student'): ?>
                        <div class="stat-orb">
                            <div class="stat-icon green"><i class="fas fa-plus-circle"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo count($available_classes); ?></div>
                                <div class="stat-label">Available</div>
                                <div class="stat-trend up"><i class="fas fa-clipboard-list"></i><span>To Enroll</span></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Enrolled Classes -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-bookmark"></i>
                            <span><?php echo $user_role === 'teacher' ? 'Classes You Teach' : 'Your Enrolled Classes'; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($enrolled_classes)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No enrolled classes yet</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($enrolled_classes as $class): ?>
                                <div class="class-card">
                                    <div style="display:flex;justify-content:space-between;align-items:start;">
                                        <div style="flex:1;">
                                            <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
                                            <div class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></div>
                                            <div style="margin-top:10px;">
                                                <?php if (!empty($class['teacher_name'])): ?>
                                                    <span class="detail-badge">
                                                        <i class="fas fa-chalkboard-teacher"></i>
                                                        <?php echo htmlspecialchars($class['teacher_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="detail-badge">
                                                    <i class="fas fa-users"></i>
                                                    <?php echo $class['class_size']; ?> Students
                                                </span>
                                                <span class="detail-badge">
                                                    <i class="fas fa-layer-group"></i>
                                                    Level <?php echo $class['grade_level']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if ($user_role === 'student'): ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to unenroll from this class?');">
                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                <button type="submit" name="unenroll_class" class="cyber-btn danger">
                                                    <i class="fas fa-times"></i> Unenroll
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($class['schedule'])): ?>
                                        <div class="class-details">
                                            <div>
                                                <div style="color:rgba(255,255,255,0.5);font-size:0.8rem;margin-bottom:5px;">Schedule</div>
                                                <div style="color:var(--cyber-cyan);">
                                                    <?php
                                                    $schedule_data = json_decode($class['schedule'], true);
                                                    echo htmlspecialchars($schedule_data['description'] ?? 'Not set');
                                                    ?>
                                                </div>
                                            </div>
                                            <?php if (!empty($class['room_number'])): ?>
                                                <div>
                                                    <div style="color:rgba(255,255,255,0.5);font-size:0.8rem;margin-bottom:5px;">Room</div>
                                                    <div style="color:var(--cyber-cyan);"><?php echo htmlspecialchars($class['room_number']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Available Classes (Students Only) -->
                <?php if ($user_role === 'student' && !empty($available_classes)): ?>
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-plus-circle"></i>
                                <span>Available Classes for Your Grade</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php foreach ($available_classes as $class): ?>
                                <div class="class-card">
                                    <div style="display:flex;justify-content:space-between;align-items:start;">
                                        <div style="flex:1;">
                                            <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
                                            <div class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></div>
                                            <div style="margin-top:10px;">
                                                <?php if (!empty($class['teacher_name'])): ?>
                                                    <span class="detail-badge">
                                                        <i class="fas fa-chalkboard-teacher"></i>
                                                        <?php echo htmlspecialchars($class['teacher_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="detail-badge">
                                                    <i class="fas fa-users"></i>
                                                    <?php echo $class['class_size']; ?> Students
                                                </span>
                                                <span class="detail-badge">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo htmlspecialchars($class['academic_year'] ?? 'N/A'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                            <button type="submit" name="enroll_class" class="cyber-btn primary">
                                                <i class="fas fa-plus"></i> Enroll Now
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
