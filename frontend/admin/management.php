<?php

/**
 * Admin Management Dashboard
 * Unified interface for all admin workflows
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AdminWorkflow.php';

// Check admin access
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$workflow = new SAMS_AdminWorkflow();
$db = db();

// Get quick stats
$stats = [
    'teachers' => $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'teacher' AND status = 'active'")['cnt'] ?? 0,
    'students' => $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'student' AND status = 'active'")['cnt'] ?? 0,
    'parents' => $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'parent' AND status = 'active'")['cnt'] ?? 0,
    'classes' => $db->fetchOne("SELECT COUNT(*) as cnt FROM classes WHERE is_active = 1")['cnt'] ?? 0,
    'pending_activation' => $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE status = 'pending_activation'")['cnt'] ?? 0,
];

// Recent users (last 7 days)
$recent_users = $db->fetchAll("SELECT * FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 10");

// Get all classes for reference
$classes = $db->fetchAll("SELECT c.*, u.first_name, u.last_name FROM classes c LEFT JOIN users u ON c.class_teacher_id = u.id WHERE c.is_active = 1 ORDER BY c.class_name");

$active_tab = $_GET['tab'] ?? 'overview';
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_teacher':
            $result = $workflow->addTeacher([
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'employee_id' => $_POST['employee_id'] ?? null,
                'department' => $_POST['department'] ?? null,
                'phone' => $_POST['phone'] ?? null
            ]);
            if ($result['success']) {
                $message = "Teacher '{$_POST['full_name']}' added successfully. Activation email sent.";
                $message_type = 'success';
            } else {
                $message = $result['error'];
                $message_type = 'error';
            }
            break;

        case 'add_student':
            $result = $workflow->addStudent([
                'full_name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'admission_no' => $_POST['admission_no'] ?? null,
                'grade_level' => $_POST['grade_level'] ?? null,
                'parent_email' => $_POST['parent_email'] ?? null
            ]);
            if ($result['success']) {
                $message = "Student '{$_POST['full_name']}' added successfully.";
                $message_type = 'success';
            } else {
                $message = $result['error'];
                $message_type = 'error';
            }
            break;

        case 'create_class':
            $result = $workflow->createClass([
                'name' => $_POST['class_name'],
                'grade_level' => $_POST['grade_level'] ?? null,
                'teacher_id' => $_POST['teacher_id'] ?? null,
                'capacity' => $_POST['capacity'] ?? 30,
                'academic_year' => $_POST['academic_year'] ?? date('Y')
            ]);
            if ($result['success']) {
                $message = "Class '{$_POST['class_name']}' created successfully.";
                $message_type = 'success';
            } else {
                $message = $result['error'];
                $message_type = 'error';
            }
            break;
    }
}

$page_title = 'Management Dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f1f5f9;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .page-header h1 {
            font-size: 1.75rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-card.teachers .stat-icon {
            background: #dbeafe;
            color: #1e40af;
        }

        .stat-card.students .stat-icon {
            background: #dcfce7;
            color: #166534;
        }

        .stat-card.parents .stat-icon {
            background: #fce7f3;
            color: #9d174d;
        }

        .stat-card.classes .stat-icon {
            background: #fef3c7;
            color: #92400e;
        }

        .stat-card.pending .stat-icon {
            background: #fee2e2;
            color: #991b1b;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: white;
            padding: 0.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .tab {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tab:hover {
            background: #f1f5f9;
            color: #374151;
        }

        .tab.active {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card h2 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1.5rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            text-decoration: none;
            color: #374151;
            transition: all 0.2s;
        }

        .action-btn:hover {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.05);
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 2rem;
            color: #4f46e5;
        }

        .action-btn span {
            font-weight: 500;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }

        .recent-users {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            font-weight: 600;
            color: #374151;
            background: #f8fafc;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            color: #475569;
        }

        tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-flex;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-admin {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-teacher {
            background: #fce7f3;
            color: #9d174d;
        }

        .badge-student {
            background: #dcfce7;
            color: #166534;
        }

        .badge-parent {
            background: #fef3c7;
            color: #92400e;
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> Management Dashboard</h1>
            <p>Manage teachers, students, classes, and all school operations</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card teachers">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number"><?= $stats['teachers'] ?></div>
                <div class="stat-label">Teachers</div>
            </div>
            <div class="stat-card students">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number"><?= $stats['students'] ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card parents">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-number"><?= $stats['parents'] ?></div>
                <div class="stat-label">Parents</div>
            </div>
            <div class="stat-card classes">
                <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                <div class="stat-number"><?= $stats['classes'] ?></div>
                <div class="stat-label">Classes</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $stats['pending_activation'] ?></div>
                <div class="stat-label">Pending Activation</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=overview" class="tab <?= $active_tab === 'overview' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Overview
            </a>
            <a href="?tab=teachers" class="tab <?= $active_tab === 'teachers' ? 'active' : '' ?>">
                <i class="fas fa-chalkboard-teacher"></i> Add Teacher
            </a>
            <a href="?tab=students" class="tab <?= $active_tab === 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i> Add Student
            </a>
            <a href="?tab=classes" class="tab <?= $active_tab === 'classes' ? 'active' : '' ?>">
                <i class="fas fa-door-open"></i> Create Class
            </a>
            <a href="bulk-import.php" class="tab">
                <i class="fas fa-file-upload"></i> Bulk Import
            </a>
        </div>

        <?php if ($active_tab === 'overview'): ?>
            <!-- Quick Actions -->
            <div class="card">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions">
                    <a href="?tab=teachers" class="action-btn">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Add Teacher</span>
                    </a>
                    <a href="?tab=students" class="action-btn">
                        <i class="fas fa-user-graduate"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="?tab=classes" class="action-btn">
                        <i class="fas fa-door-open"></i>
                        <span>Create Class</span>
                    </a>
                    <a href="bulk-import.php" class="action-btn">
                        <i class="fas fa-file-upload"></i>
                        <span>Bulk Import</span>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <h2><i class="fas fa-history"></i> Recent Activity (Last 7 Days)</h2>
                <div class="recent-users">
                    <?php if ($recent_users): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $user['role'] ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $user['status'] ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #64748b; padding: 2rem;">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'teachers'): ?>
            <!-- Add Teacher Form -->
            <div class="card">
                <h2><i class="fas fa-chalkboard-teacher"></i> Add New Teacher</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_teacher">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" required placeholder="Enter teacher's full name">
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" required placeholder="Enter email address">
                        </div>
                        <div class="form-group">
                            <label>Employee ID</label>
                            <input type="text" name="employee_id" placeholder="Optional employee ID">
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" placeholder="e.g., Mathematics, Science">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" placeholder="Enter phone number">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Teacher
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'students'): ?>
            <!-- Add Student Form -->
            <div class="card">
                <h2><i class="fas fa-user-graduate"></i> Add New Student</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_student">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" required placeholder="Enter student's full name">
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" required placeholder="Enter student email">
                        </div>
                        <div class="form-group">
                            <label>Admission Number</label>
                            <input type="text" name="admission_no" placeholder="Optional admission number">
                        </div>
                        <div class="form-group">
                            <label>Grade Level</label>
                            <select name="grade_level">
                                <option value="">Select Grade</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>">Grade <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Parent Email</label>
                            <input type="email" name="parent_email" placeholder="For parent notifications">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'classes'): ?>
            <!-- Create Class Form -->
            <div class="card">
                <h2><i class="fas fa-door-open"></i> Create New Class</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_class">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Class Name *</label>
                            <input type="text" name="class_name" required placeholder="e.g., Mathematics 101">
                        </div>
                        <div class="form-group">
                            <label>Grade Level</label>
                            <select name="grade_level">
                                <option value="">Select Grade</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>">Grade <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assign Teacher</label>
                            <select name="teacher_id">
                                <option value="">Select Teacher</option>
                                <?php
                                $teachers = $db->fetchAll("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY first_name");
                                foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>">
                                        <?= htmlspecialchars(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Capacity</label>
                            <input type="number" name="capacity" value="30" min="1" max="100">
                        </div>
                        <div class="form-group">
                            <label>Academic Year</label>
                            <input type="text" name="academic_year" value="<?= date('Y') ?>" placeholder="e.g., 2024">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Class
                    </button>
                </form>
            </div>

            <!-- Existing Classes -->
            <div class="card">
                <h2><i class="fas fa-list"></i> Existing Classes</h2>
                <div class="recent-users">
                    <?php if ($classes): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Grade</th>
                                    <th>Teacher</th>
                                    <th>Capacity</th>
                                    <th>Academic Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($class['name']) ?></strong></td>
                                        <td>Grade <?= $class['grade_level'] ?? 'N/A' ?></td>
                                        <td><?= htmlspecialchars(($class['first_name'] ?? '') . ' ' . ($class['last_name'] ?? 'Not Assigned')) ?></td>
                                        <td><?= $class['capacity'] ?? 30 ?></td>
                                        <td><?= $class['academic_year'] ?? date('Y') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #64748b; padding: 2rem;">No classes created yet</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
