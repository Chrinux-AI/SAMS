<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_post_csrf_if_present()) {
        $message = 'Security validation failed. Please refresh and try again.';
        $message_type = 'error';
    } elseif (isset($_POST['add_class'])) {
        $teacherColumn = table_has_column('classes', 'class_teacher_id') ? 'class_teacher_id' : 'teacher_id';
        $name = sanitize($_POST['name'] ?? '');
        $classCode = sanitize($_POST['class_code'] ?? '');
        $data = [
            'class_name' => $name,
            'name' => $name,
            'grade_level' => (int)($_POST['grade_level'] ?? 0),
            'academic_year' => sanitize($_POST['academic_year'] ?? ''),
            $teacherColumn => !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null,
            'room_number' => sanitize($_POST['room_number'] ?? ''),
            'section' => $classCode,
            'class_code' => $classCode,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $id = insert_flexible('classes', $data);
        if ($id) {
            log_activity($_SESSION['user_id'], 'create', 'classes', $id);
            $message = 'Class added successfully!';
            $message_type = 'success';
        } else {
            $message = 'Unable to add class. Please check class details.';
            $message_type = 'error';
        }
    } elseif (isset($_POST['edit_class'])) {
        $id = (int)$_POST['class_id'];
        $teacherColumn = table_has_column('classes', 'class_teacher_id') ? 'class_teacher_id' : 'teacher_id';
        $name = sanitize($_POST['name'] ?? '');
        $classCode = sanitize($_POST['class_code'] ?? '');
        $data = [
            'class_name' => $name,
            'name' => $name,
            'grade_level' => (int)($_POST['grade_level'] ?? 0),
            'academic_year' => sanitize($_POST['academic_year'] ?? ''),
            $teacherColumn => !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null,
            'room_number' => sanitize($_POST['room_number'] ?? ''),
            'section' => $classCode,
            'class_code' => $classCode,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        update_flexible('classes', $data, 'id = ?', [$id]);
        log_activity($_SESSION['user_id'], 'update', 'classes', $id);
        $message = 'Class updated successfully!';
        $message_type = 'success';
    } elseif (isset($_POST['delete_class'])) {
        $id = (int)$_POST['class_id'];
        db()->delete('classes', 'id = ?', [$id]);
        log_activity($_SESSION['user_id'], 'delete', 'classes', $id);
        $message = 'Class deleted successfully!';
        $message_type = 'success';
    }
}

$teacherColumn = table_has_column('classes', 'class_teacher_id') ? 'class_teacher_id' : 'teacher_id';
$classesSql = "
    SELECT c.*,
           c.class_name AS name,
           COALESCE(c.section, CONCAT('CLS-', c.id)) AS class_code,
           c.{$teacherColumn} AS teacher_id,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           u.email as teacher_email";
if (table_exists('class_enrollments')) {
    $classesSql .= ", COUNT(DISTINCT ce.student_id) as student_count";
} else {
    $classesSql .= ", 0 as student_count";
}
$classesSql .= "
    FROM classes c
    LEFT JOIN users u ON c.{$teacherColumn} = u.id";
if (table_exists('class_enrollments')) {
    $classesSql .= " LEFT JOIN class_enrollments ce ON c.id = ce.class_id";
}
$classesSql .= " GROUP BY c.id ORDER BY c.grade_level, c.class_name";
$classes = db()->fetchAll($classesSql);

$teachers = db()->fetchAll("SELECT id, first_name, last_name, email FROM users WHERE role = 'teacher' ORDER BY first_name, last_name");
$total_classes = count($classes);
$total_students_enrolled = array_sum(array_column($classes, 'student_count'));

$page_title = 'Classes Management';
$page_icon = 'door-open';
$full_name = $_SESSION['full_name'];
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta name="theme-color" content="#00BFFF">
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
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .class-card:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.3);
            transform: translateY(-5px);
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .class-title {
            font-size: 1.5rem;
            color: var(--cyber-cyan);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .class-code {
            color: rgba(0, 191, 255, 0.6);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .class-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
        }

        .detail-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .search-box {
            width: 100%;
            padding: 15px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--cyber-cyan);
            border-radius: 12px;
            color: var(--cyber-cyan);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            margin-bottom: 25px;
        }

        .level-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            color: var(--cyber-cyan);
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: rgba(0, 191, 255, 0.3);
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.4);
        }
    </style>
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
                    <button onclick="document.getElementById('addClassModal').style.display='flex'" class="cyber-btn primary" style="margin-right:15px;">
                        <i class="fas fa-plus-circle"></i> Add New Class
                    </button>
                    <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
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

                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_classes; ?></div>
                            <div class="stat-label">Total Classes</div>
                            <div class="stat-trend up"><i class="fas fa-arrow-up"></i><span>All Levels</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_students_enrolled; ?></div>
                            <div class="stat-label">Total Enrollments</div>
                            <div class="stat-trend up"><i class="fas fa-users"></i><span>Students</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($teachers); ?></div>
                            <div class="stat-label">Teachers</div>
                            <div class="stat-trend up"><i class="fas fa-check"></i><span>Available</span></div>
                        </div>
                    </div>
                </section>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-search"></i> <span>Search & Filter Classes</span></div>
                    </div>
                    <div class="card-body">
                        <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search by class name, code, teacher...">
                        <div class="level-filter">
                            <button class="filter-btn active" onclick="filterLevel('all')">All Levels</button>
                            <button class="filter-btn" onclick="filterLevel('100')">100 Level</button>
                            <button class="filter-btn" onclick="filterLevel('200')">200 Level</button>
                            <button class="filter-btn" onclick="filterLevel('300')">300 Level</button>
                            <button class="filter-btn" onclick="filterLevel('400')">400 Level</button>
                            <button class="filter-btn" onclick="filterLevel('500')">500 Level</button>
                        </div>
                    </div>
                </div>

                <div id="classesContainer">
                    <?php foreach ($classes as $class): ?>
                        <div class="class-card" data-level="<?php echo $class['grade_level']; ?>" data-search="<?php echo strtolower($class['name'] . ' ' . $class['class_code'] . ' ' . ($class['teacher_name'] ?? '')); ?>">
                            <div class="class-header">
                                <div>
                                    <div class="class-title"><?php echo htmlspecialchars($class['name']); ?></div>
                                    <div class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></div>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <button onclick="editClass(<?php echo $class['id']; ?>)" class="cyber-btn" style="padding:8px 15px;"><i class="fas fa-edit"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this class?');">
                                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                        <button type="submit" name="delete_class" class="cyber-btn danger" style="padding:8px 15px;"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="class-details">
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-layer-group"></i></div>
                                    <div>
                                        <div style="color:var(--text-muted);font-size:0.8rem;">Level</div>
                                        <div style="color:var(--cyber-cyan);font-weight:600;"><?php echo $class['grade_level']; ?> Level</div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                    <div>
                                        <div style="color:var(--text-muted);font-size:0.8rem;">Teacher</div>
                                        <div style="color:var(--cyber-cyan);font-weight:600;"><?php echo htmlspecialchars($class['teacher_name'] ?? 'Unassigned'); ?></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-users"></i></div>
                                    <div>
                                        <div style="color:var(--text-muted);font-size:0.8rem;">Students</div>
                                        <div style="color:var(--neon-green);font-weight:600;"><?php echo $class['student_count']; ?> Enrolled</div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-door-closed"></i></div>
                                    <div>
                                        <div style="color:var(--text-muted);font-size:0.8rem;">Room</div>
                                        <div style="color:var(--cyber-cyan);font-weight:600;"><?php echo htmlspecialchars($class['room_number'] ?? 'TBA'); ?></div>
                                    </div>
                                </div>
                                <div class="detail-item" style="grid-column:span 2;">
                                    <div class="detail-icon"><i class="fas fa-clock"></i></div>
                                    <div>
                                        <div style="color:var(--text-muted);font-size:0.8rem;">Schedule</div>
                                        <div style="color:var(--cyber-cyan);font-weight:600;">
                                            <?php
                                            if (!empty($class['schedule'])) {
                                                $schedule_data = json_decode($class['schedule'], true);
                                                echo htmlspecialchars($schedule_data['description'] ?? $class['schedule'] ?? 'Not set');
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Class Modal -->
    <div id="addClassModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> <span>Add New Class</span></div>
                <button onclick="document.getElementById('addClassModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" style="display:grid;gap:20px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS CODE *</label>
                            <input type="text" name="class_code" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">CLASS NAME *</label>
                            <input type="text" name="name" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">LEVEL *</label>
                            <select name="grade_level" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <option value="">Select Level</option>
                                <option value="100">100 Level</option>
                                <option value="200">200 Level</option>
                                <option value="300">300 Level</option>
                                <option value="400">400 Level</option>
                                <option value="500">500 Level</option>
                            </select>
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ACADEMIC YEAR *</label>
                            <input type="text" name="academic_year" required placeholder="2024/2025" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ASSIGN TEACHER</label>
                        <select name="teacher_id" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <option value="">Assign Later</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">ROOM NUMBER</label>
                            <input type="text" name="room_number" placeholder="e.g. Room 101" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">SCHEDULE</label>
                            <input type="text" name="schedule" placeholder="Mon-Fri 9:00-10:00" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('addClassModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="add_class" class="cyber-btn primary"><i class="fas fa-save"></i> Add Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('searchBox').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.class-card').forEach(card => {
                const text = card.getAttribute('data-search');
                card.style.display = text.includes(search) ? 'block' : 'none';
            });
        });

        function filterLevel(level) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            document.querySelectorAll('.class-card').forEach(card => {
                if (level === 'all' || card.getAttribute('data-level') === level) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = <?php echo json_encode($csrf_token); ?>;
            document.querySelectorAll('form').forEach((form) => {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = csrfToken;
                    form.appendChild(input);
                }
            });
        });
    </script>
</body>

</html>
