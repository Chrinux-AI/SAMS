<?php

/**
 * Class Management System
 * Create and manage classes for educational institutions
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Only admins can access this
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'principal'])) {
    header('Location: ../login.php');
    exit;
}

$class_manager = new Class_Manager();

// Get statistics
$total_classes = db()->fetchOne("SELECT COUNT(*) as count FROM classes")['count'] ?? 0;
$total_students = db()->fetchOne("SELECT COUNT(*) as count FROM students")['count'] ?? 0;
$total_teachers = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")['count'] ?? 0;
$recent_classes = db()->fetchAll("
    SELECT c.*, u.full_name as teacher_name
    FROM classes c
    LEFT JOIN users u ON c.teacher_id = u.id
    ORDER BY c.created_at DESC
    LIMIT 10
");

class Class_Manager
{
    public function createClass($class_data)
    {
        try {
            // Validate class data
            $validation = $this->validateClassData($class_data);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }

            // Create class record
            $class_record = [
                'class_name' => $class_data['class_name'],
                'class_code' => $class_data['class_code'],
                'grade_level' => $class_data['grade_level'],
                'subject' => $class_data['subject'] ?? null,
                'teacher_id' => $class_data['teacher_id'] ?? null,
                'room_number' => $class_data['room_number'] ?? null,
                'capacity' => $class_data['capacity'] ?? 30,
                'academic_year' => $class_data['academic_year'] ?? date('Y'),
                'semester' => $class_data['semester'] ?? '1',
                'description' => $class_data['description'] ?? null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_id']
            ];

            $class_id = db()->insert('classes', $class_record);

            // If students are provided, enroll them
            if (!empty($class_data['students'])) {
                $this->enrollStudents($class_id, $class_data['students']);
            }

            return [
                'success' => true,
                'class_id' => $class_id,
                'message' => 'Class created successfully'
            ];
        } catch (Exception $e) {
            error_log("Class Creation Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create class'];
        }
    }

    public function bulkCreateClasses($classes_data)
    {
        $results = [];
        $success_count = 0;
        $error_count = 0;

        foreach ($classes_data as $index => $class_data) {
            $result = $this->createClass($class_data);
            $results[$index] = $result;

            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        return [
            'total_processed' => count($classes_data),
            'success_count' => $success_count,
            'error_count' => $error_count,
            'results' => $results
        ];
    }

    private function validateClassData($class_data)
    {
        // Required fields
        $required_fields = ['class_name', 'class_code', 'grade_level'];
        foreach ($required_fields as $field) {
            if (empty($class_data[$field])) {
                return ['valid' => false, 'error' => "Missing required field: $field"];
            }
        }

        // Check for duplicate class code
        $existing = db()->fetchOne("SELECT id FROM classes WHERE class_code = ?", [$class_data['class_code']]);
        if ($existing) {
            return ['valid' => false, 'error' => 'Class code already exists'];
        }

        // Validate teacher if provided
        if (!empty($class_data['teacher_id'])) {
            $teacher = db()->fetchOne("SELECT id FROM users WHERE id = ? AND role = 'teacher'", [$class_data['teacher_id']]);
            if (!$teacher) {
                return ['valid' => false, 'error' => 'Invalid teacher selected'];
            }
        }

        return ['valid' => true];
    }

    private function enrollStudents($class_id, $students)
    {
        foreach ($students as $student_id) {
            // Check if student exists
            $student = db()->fetchOne("SELECT id FROM students WHERE id = ?", [$student_id]);
            if ($student) {
                db()->insert('class_enrollments', [
                    'class_id' => $class_id,
                    'student_id' => $student_id,
                    'enrollment_date' => date('Y-m-d H:i:s'),
                    'status' => 'active'
                ]);
            }
        }
    }
}

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $class_data = [
        'class_name' => $_POST['class_name'],
        'class_code' => $_POST['class_code'],
        'grade_level' => $_POST['grade_level'],
        'subject' => $_POST['subject'] ?? null,
        'teacher_id' => $_POST['teacher_id'] ?? null,
        'room_number' => $_POST['room_number'] ?? null,
        'capacity' => $_POST['capacity'] ?? 30,
        'academic_year' => $_POST['academic_year'] ?? date('Y'),
        'semester' => $_POST['semester'] ?? '1',
        'description' => $_POST['description'] ?? null,
        'students' => isset($_POST['students']) ? explode(',', $_POST['students']) : []
    ];

    $result = $class_manager->createClass($class_data);

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Handle bulk class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_classes'])) {
    $classes_data = json_decode($_POST['bulk_classes'], true) ?: [];
    $result = $class_manager->bulkCreateClasses($classes_data);

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Get available teachers
$teachers = db()->fetchAll("SELECT id, full_name, email FROM users WHERE role = 'teacher' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="../assets/images/icons/favicon.svg">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #10B981;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-weight: 500;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            color: #1F2937;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #10B981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary {
            background: #6B7280;
            color: white;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th {
            background: #F9FAFB;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #E5E7EB;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
        }

        .data-table tr:hover {
            background: #F9FAFB;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #E5E7EB;
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #10B981;
            border-bottom-color: #10B981;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">Class Management</div>
            <div class="page-subtitle">Create and manage educational classes</div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stat-value"><?php echo $total_classes; ?></div>
                <div class="stat-label">Total Classes</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-value"><?php echo $total_teachers; ?></div>
                <div class="stat-label">Total Teachers</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value">87%</div>
                <div class="stat-label">Class Capacity</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-grid">
            <!-- Class Creation -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Create New Class</h2>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab('single')">Single Class</button>
                    <button class="tab" onclick="showTab('bulk')">Bulk Classes</button>
                </div>

                <!-- Single Class Tab -->
                <div id="single-tab" class="tab-content active">
                    <form id="singleClassForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="class_name">
                                    <i class="fas fa-tag"></i>
                                    Class Name *
                                </label>
                                <input type="text" id="class_name" name="class_name" required
                                    placeholder="e.g., Mathematics 101">
                            </div>

                            <div class="form-group">
                                <label for="class_code">
                                    <i class="fas fa-hashtag"></i>
                                    Class Code *
                                </label>
                                <input type="text" id="class_code" name="class_code" required
                                    placeholder="e.g., MATH101">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="grade_level">
                                    <i class="fas fa-layer-group"></i>
                                    Grade Level *
                                </label>
                                <select id="grade_level" name="grade_level" required>
                                    <option value="">Select Grade</option>
                                    <option value="K">Kindergarten</option>
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                    <option value="10">Grade 10</option>
                                    <option value="11">Grade 11</option>
                                    <option value="12">Grade 12</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subject">
                                    <i class="fas fa-book"></i>
                                    Subject
                                </label>
                                <input type="text" id="subject" name="subject"
                                    placeholder="e.g., Mathematics">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="teacher_id">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    Teacher
                                </label>
                                <select id="teacher_id" name="teacher_id">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="room_number">
                                    <i class="fas fa-door-open"></i>
                                    Room Number
                                </label>
                                <input type="text" id="room_number" name="room_number"
                                    placeholder="e.g., Room 201">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="capacity">
                                    <i class="fas fa-users"></i>
                                    Capacity
                                </label>
                                <input type="number" id="capacity" name="capacity"
                                    value="30" min="1" max="100">
                            </div>

                            <div class="form-group">
                                <label for="academic_year">
                                    <i class="fas fa-calendar"></i>
                                    Academic Year
                                </label>
                                <input type="number" id="academic_year" name="academic_year"
                                    value="<?php echo date('Y'); ?>" min="2020" max="2030">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">
                                <i class="fas fa-align-left"></i>
                                Description
                            </label>
                            <textarea id="description" name="description"
                                placeholder="Class description and objectives..."></textarea>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="create_class" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Create Class
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearSingleForm()">
                                <i class="fas fa-times"></i>
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Bulk Classes Tab -->
                <div id="bulk-tab" class="tab-content">
                    <form id="bulkClassForm">
                        <div class="form-group">
                            <label for="bulk_classes">
                                <i class="fas fa-list"></i>
                                Bulk Classes Data (JSON)
                            </label>
                            <textarea id="bulk_classes" name="bulk_classes"
                                placeholder='Paste multiple classes data in JSON format...
[
  {
    "class_name": "Mathematics 101",
    "class_code": "MATH101",
    "grade_level": "9",
    "subject": "Mathematics",
    "teacher_id": 1,
    "room_number": "Room 201",
    "capacity": 30
  },
  {
    "class_name": "English 101",
    "class_code": "ENG101",
    "grade_level": "9",
    "subject": "English",
    "teacher_id": 2,
    "room_number": "Room 202",
    "capacity": 25
  }
]'></textarea>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="bulk_classes" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Create Classes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearBulkForm()">
                                <i class="fas fa-times"></i>
                                Clear
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Classes -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Recent Classes</h2>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Code</th>
                            <th>Grade</th>
                            <th>Teacher</th>
                            <th>Capacity</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_classes as $class): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                    <?php if ($class['subject']): ?>
                                        <br><small><?php echo htmlspecialchars($class['subject']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: #E5E7EB; padding: 4px 8px; border-radius: 4px; font-family: monospace;">
                                        <?php echo htmlspecialchars($class['class_code']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($class['grade_level']); ?></td>
                                <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Unassigned'); ?></td>
                                <td><?php echo $class['capacity']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($class['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        function clearSingleForm() {
            document.getElementById('singleClassForm').reset();
        }

        function clearBulkForm() {
            document.getElementById('bulkClassForm').reset();
        }

        // Single class form submission
        document.getElementById('singleClassForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;

            // Show loading state
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitButton.disabled = true;

            try {
                const response = await fetch('class-management.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Class created successfully!', 'success');
                    clearSingleForm();
                    location.reload();
                } else {
                    showNotification(result.error || 'Failed to create class', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            } finally {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }
        });

        // Bulk class form submission
        document.getElementById('bulkClassForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;

            // Show loading state
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitButton.disabled = true;

            try {
                const response = await fetch('class-management.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success_count > 0) {
                    showNotification(`${result.success_count} classes created successfully!`, 'success');
                    clearBulkForm();
                    location.reload();
                } else {
                    showNotification('Failed to create classes', 'error');
                }
            } catch (error) {
                showNotification('Network error occurred', 'error');
            } finally {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }
        });

        function showNotification(message, type) {
            // Simple notification system
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                padding: 16px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                ${type === 'success' ? 'background: #10B981;' : 'background: #EF4444;'}
            `;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    </script>
</body>

</html>
