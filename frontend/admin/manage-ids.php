<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin();

$admin_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success_msg = '';
$error_msg = '';

// Handle ID assignment/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_id'])) {
    $user_id = intval($_POST['user_id']);
    $assigned_id = sanitize($_POST['assigned_id']);
    $role = sanitize($_POST['role']);

    try {
        if ($role === 'student') {
            // Check if ID already exists
            $existing = db()->fetchOne("SELECT user_id FROM students WHERE student_id = ? AND user_id != ?", [$assigned_id, $user_id]);
            if ($existing) {
                $error_msg = "Student ID already exists!";
            } else {
                db()->update('students', ['student_id' => $assigned_id], 'user_id = ?', [$user_id]);
                log_activity($admin_id, 'assign_student_id', 'students', $user_id, "Assigned Student ID: $assigned_id");
                $success_msg = "Student ID assigned successfully!";
            }
        } elseif ($role === 'teacher') {
            $existing = db()->fetchOne("SELECT user_id FROM teachers WHERE teacher_id = ? AND user_id != ?", [$assigned_id, $user_id]);
            if ($existing) {
                $error_msg = "Teacher ID already exists!";
            } else {
                db()->update('teachers', ['teacher_id' => $assigned_id], 'user_id = ?', [$user_id]);
                log_activity($admin_id, 'assign_teacher_id', 'teachers', $user_id, "Assigned Teacher ID: $assigned_id");
                $success_msg = "Teacher ID assigned successfully!";
            }
        }
    } catch (Exception $e) {
        $error_msg = "Failed to assign ID: " . $e->getMessage();
    }
}

// Get active tab
$active_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'students';

// Get students
$students = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.status, u.created_at,
           s.assigned_student_id as student_id, c.grade_level, s.is_active as student_status
    FROM users u
    JOIN students s ON u.id = s.user_id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
");

// Get teachers
$teachers = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.status, u.created_at,
           t.teacher_id, t.department, t.status as teacher_status,
           COUNT(DISTINCT c.id) as class_count
    FROM users u
    JOIN teachers t ON u.id = t.user_id
    LEFT JOIN classes c ON u.id = c.class_teacher_id
    WHERE u.role = 'teacher'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

// Get parents
$parents = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.status, u.created_at,
           COUNT(DISTINCT psl.student_id) as children_count
    FROM users u
    LEFT JOIN parent_student_links psl ON u.id = psl.parent_id
    WHERE u.role = 'parent'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

$page_title = 'Manage IDs';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


    <style>
        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(0, 191, 255, 0.2);
        }

        .tab-btn {
            padding: 15px 30px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #888;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
        }

        .tab-btn:hover {
            color: #00BFFF;
        }

        .tab-btn.active {
            color: #00BFFF;
            border-bottom-color: #00BFFF;
        }

        .id-input-inline {
            width: 150px;
            padding: 8px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 6px;
            color: #E0E0E0;
            font-family: 'Orbitron', monospace;
            font-size: 14px;
        }

        .id-input-inline:focus {
            outline: none;
            border-color: #00BFFF;
            background: rgba(0, 191, 255, 0.1);
        }

        .assign-btn {
            padding: 8px 15px;
            background: linear-gradient(135deg, #00FF7F, #00BFFF);
            border: none;
            border-radius: 6px;
            color: #000;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }

        .assign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 127, 0.3);
        }
    </style>
</head>

<body>



    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div>
                        <h1 class="page-title">Manage Role IDs</h1>
                        <p class="page-subtitle">Assign and manage IDs for all user roles</p>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <a href="?tab=students" class="tab-btn <?php echo $active_tab === 'students' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i> Students (<?php echo count($students); ?>)
                    </a>
                    <a href="?tab=teachers" class="tab-btn <?php echo $active_tab === 'teachers' ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers (<?php echo count($teachers); ?>)
                    </a>
                    <a href="?tab=parents" class="tab-btn <?php echo $active_tab === 'parents' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Parents (<?php echo count($parents); ?>)
                    </a>
                </div>

                <!-- Students Tab -->
                <?php if ($active_tab === 'students'): ?>
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-user-graduate"></i>
                                <span>Student IDs</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Student ID</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $student['student_id'] ? 'active' : 'inactive'; ?>" style="font-family: 'Orbitron', monospace;">
                                                    <?php echo $student['student_id'] ?: 'Not Assigned'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></td>
                                            <td><span class="status-badge <?php echo $student['status']; ?>"><?php echo ucfirst($student['status']); ?></span></td>
                                            <td>
                                                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                                                    <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                                                    <input type="hidden" name="role" value="student">
                                                    <input type="text" name="assigned_id" class="id-input-inline"
                                                        placeholder="STU20250001"
                                                        value="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>"
                                                        pattern="STU\d{8}"
                                                        maxlength="11"
                                                        required>
                                                    <button type="submit" name="assign_id" class="assign-btn">
                                                        <i class="fas fa-save"></i> <?php echo $student['student_id'] ? 'Update' : 'Assign'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Teachers Tab -->
                <?php if ($active_tab === 'teachers'): ?>
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Teacher IDs</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Teacher ID</th>
                                        <th>Department</th>
                                        <th>Classes</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $teacher['teacher_id'] ? 'active' : 'inactive'; ?>" style="font-family: 'Orbitron', monospace;">
                                                    <?php echo $teacher['teacher_id'] ?: 'Not Assigned'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo $teacher['class_count']; ?> classes</td>
                                            <td><span class="status-badge <?php echo $teacher['status']; ?>"><?php echo ucfirst($teacher['status']); ?></span></td>
                                            <td>
                                                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                                                    <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                                                    <input type="hidden" name="role" value="teacher">
                                                    <input type="text" name="assigned_id" class="id-input-inline"
                                                        placeholder="TCH20250001"
                                                        value="<?php echo htmlspecialchars($teacher['teacher_id'] ?? ''); ?>"
                                                        pattern="TCH\d{8}"
                                                        maxlength="11"
                                                        required>
                                                    <button type="submit" name="assign_id" class="assign-btn">
                                                        <i class="fas fa-save"></i> <?php echo $teacher['teacher_id'] ? 'Update' : 'Assign'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Parents Tab -->
                <?php if ($active_tab === 'parents'): ?>
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-users"></i>
                                <span>Parent Accounts</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Linked Children</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parents as $parent): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($parent['email']); ?></td>
                                            <td><?php echo htmlspecialchars($parent['username']); ?></td>
                                            <td><?php echo $parent['children_count']; ?> children</td>
                                            <td><span class="status-badge <?php echo $parent['status']; ?>"><?php echo ucfirst($parent['status']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($parent['created_at'])); ?></td>
                                            <td>
                                                <a href="link-parent-student.php?parent=<?php echo $parent['id']; ?>" class="cyber-btn btn-sm">
                                                    <i class="fas fa-link"></i> Link Children
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="stats-grid" style="margin-top: 30px;">
                    <div class="stat-orb">
                        <div class="stat-icon green">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value"><?php echo count($students); ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon cyan">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-label">Total Teachers</div>
                        <div class="stat-value"><?php echo count($teachers); ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon purple">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-label">Total Parents</div>
                        <div class="stat-value"><?php echo count($parents); ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon yellow">
                            <i class="fas fa-id-card-alt"></i>
                        </div>
                        <div class="stat-label">Assigned IDs</div>
                        <div class="stat-value">
                            <?php
                            $assigned = count(array_filter($students, fn($s) => !empty($s['student_id'])));
                            $assigned += count(array_filter($teachers, fn($t) => !empty($t['teacher_id'])));
                            echo $assigned;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-format ID inputs
        document.querySelectorAll('.id-input-inline').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                const role = e.target.form.querySelector('input[name="role"]').value;

                if (role === 'student' && value.length > 0 && !value.startsWith('STU')) {
                    value = 'STU' + value;
                } else if (role === 'teacher' && value.length > 0 && !value.startsWith('TCH')) {
                    value = 'TCH' + value;
                }

                e.target.value = value.substring(0, 11);
            });
        });
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
