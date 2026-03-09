<?php

/**
 * ID Management Page (Admin Only)
 * Manage and view all system IDs
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';

// Get all IDs from the system
$students = db()->fetchAll("
    SELECT s.admission_number as student_id, u.first_name, u.last_name, c.grade_level, u.status, u.email, u.phone
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY s.admission_number ASC
") ?: [];

$teachers = db()->fetchAll("
    SELECT employee_id, first_name, last_name, department, status, email, phone
    FROM teachers
    ORDER BY employee_id ASC
") ?: [];

$classes = db()->fetchAll("
    SELECT class_code, name, grade_level, academic_year, room_number
    FROM classes
    ORDER BY class_code ASC
") ?: [];

$users = db()->fetchAll("
    SELECT id, username, email, role, status, CONCAT(first_name, ' ', last_name) as full_name
    FROM users
    ORDER BY id ASC
") ?: [];

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
    <title>ID Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <style>
        .id-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .id-section h3 {
            color: #667eea;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .id-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .id-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .id-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
        }

        .id-value {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            font-family: 'Courier New', monospace;
            margin-bottom: 8px;
        }

        .id-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .id-details {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
        }

        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .copy-btn:hover {
            background: #5568d3;
        }

        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            display: none;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
        }

        .stat-box h2 {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .stat-box p {
            font-size: 16px;
            opacity: 0.9;
        }

        .search-box {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: #667eea;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }

        .badge-active {
            background: #d1fae5;
            color: #059669;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-pending {
            background: #fef3c7;
            color: #d97706;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <div class="cyber-main">

<div class="copy-notification" id="copyNotification">
        <i class="fas fa-check-circle"></i>
        <span id="copyText">ID copied to clipboard!</span>
    </div>

    <div class="container">
            <div class="cyber-header" style="padding: 24px 32px; border-bottom: 1px solid var(--border-color);">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:44px;height:44px;background:var(--primary-50);color:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div>
                        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary);">ID Management</h1>
                        <p style="color:var(--text-secondary);font-size:0.85rem;">Student ID card management</p>
                    </div>
                </div>
            </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <h2><?php echo count($students); ?></h2>
                <p><i class="fas fa-user-graduate"></i> Student IDs</p>
            </div>
            <div class="stat-box" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <h2><?php echo count($teachers); ?></h2>
                <p><i class="fas fa-chalkboard-teacher"></i> Teacher IDs</p>
            </div>
            <div class="stat-box" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h2><?php echo count($classes); ?></h2>
                <p><i class="fas fa-book"></i> Class Codes</p>
            </div>
            <div class="stat-box" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <h2><?php echo count($users); ?></h2>
                <p><i class="fas fa-users"></i> User IDs</p>
            </div>
        </div>

        <!-- Student IDs -->
        <div class="id-section">
            <h3><i class="fas fa-user-graduate"></i> Student IDs</h3>
            <input type="text" class="search-box" id="searchStudents" placeholder="🔍 Search student IDs, names, or emails...">
            <div class="id-grid" id="studentGrid">
                <?php foreach ($students as $student): ?>
                    <div class="id-card" onclick="copyToClipboard('<?php echo htmlspecialchars($student['student_id']); ?>', 'Student ID')">
                        <button class="copy-btn" onclick="event.stopPropagation(); copyToClipboard('<?php echo htmlspecialchars($student['student_id']); ?>', 'Student ID')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <div class="id-value"><?php echo htmlspecialchars($student['student_id']); ?></div>
                        <div class="id-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                        <div class="id-details">
                            <div><i class="fas fa-graduation-cap"></i> Grade <?php echo $student['grade_level']; ?></div>
                            <?php if ($student['email']): ?>
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></div>
                            <?php endif; ?>
                            <span class="badge badge-<?php echo $student['status']; ?>"><?php echo ucfirst($student['status']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Teacher IDs -->
        <div class="id-section">
            <h3><i class="fas fa-chalkboard-teacher"></i> Teacher IDs (Employee IDs)</h3>
            <input type="text" class="search-box" id="searchTeachers" placeholder="🔍 Search teacher IDs, names, or departments...">
            <div class="id-grid" id="teacherGrid">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="id-card" onclick="copyToClipboard('<?php echo htmlspecialchars($teacher['employee_id']); ?>', 'Teacher ID')">
                        <button class="copy-btn" onclick="event.stopPropagation(); copyToClipboard('<?php echo htmlspecialchars($teacher['employee_id']); ?>', 'Teacher ID')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <div class="id-value"><?php echo htmlspecialchars($teacher['employee_id']); ?></div>
                        <div class="id-name"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                        <div class="id-details">
                            <?php if ($teacher['department']): ?>
                                <div><i class="fas fa-building"></i> <?php echo htmlspecialchars($teacher['department']); ?></div>
                            <?php endif; ?>
                            <?php if ($teacher['email']): ?>
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></div>
                            <?php endif; ?>
                            <span class="badge badge-<?php echo $teacher['status']; ?>"><?php echo ucfirst($teacher['status']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Class Codes -->
        <div class="id-section">
            <h3><i class="fas fa-book"></i> Class Codes</h3>
            <input type="text" class="search-box" id="searchClasses" placeholder="🔍 Search class codes or names...">
            <div class="id-grid" id="classGrid">
                <?php foreach ($classes as $class): ?>
                    <div class="id-card" onclick="copyToClipboard('<?php echo htmlspecialchars($class['class_code']); ?>', 'Class Code')">
                        <button class="copy-btn" onclick="event.stopPropagation(); copyToClipboard('<?php echo htmlspecialchars($class['class_code']); ?>', 'Class Code')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <div class="id-value"><?php echo htmlspecialchars($class['class_code']); ?></div>
                        <div class="id-name"><?php echo htmlspecialchars($class['name']); ?></div>
                        <div class="id-details">
                            <?php if ($class['grade_level']): ?>
                                <div><i class="fas fa-graduation-cap"></i> Grade <?php echo $class['grade_level']; ?></div>
                            <?php endif; ?>
                            <?php if ($class['academic_year']): ?>
                                <div><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($class['academic_year']); ?></div>
                            <?php endif; ?>
                            <?php if ($class['room_number']): ?>
                                <div><i class="fas fa-door-open"></i> Room <?php echo htmlspecialchars($class['room_number']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- User IDs -->
        <div class="id-section">
            <h3><i class="fas fa-users"></i> System User IDs</h3>
            <input type="text" class="search-box" id="searchUsers" placeholder="🔍 Search user IDs, usernames, or emails...">
            <div class="id-grid" id="userGrid">
                <?php foreach ($users as $user): ?>
                    <div class="id-card" onclick="copyToClipboard('<?php echo $user['id']; ?>', 'User ID')">
                        <button class="copy-btn" onclick="event.stopPropagation(); copyToClipboard('<?php echo $user['id']; ?>', 'User ID')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <div class="id-value">ID: <?php echo $user['id']; ?></div>
                        <div class="id-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="id-details">
                            <div><i class="fas fa-user"></i> @<?php echo htmlspecialchars($user['username']); ?></div>
                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                            <div>
                                <span class="badge badge-<?php
                                                            echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'primary' : ($user['role'] === 'student' ? 'info' : 'success'));
                                                            ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                                <span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        function copyToClipboard(text, type) {
            // Create temporary input
            const temp = document.createElement('input');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);

            // Show notification
            const notification = document.getElementById('copyNotification');
            const copyText = document.getElementById('copyText');
            copyText.textContent = type + ' "' + text + '" copied to clipboard!';
            notification.style.display = 'flex';

            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Search functionality
        function setupSearch(inputId, gridId) {
            const searchInput = document.getElementById(inputId);
            const grid = document.getElementById(gridId);
            const cards = grid.getElementsByClassName('id-card');

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();

                Array.from(cards).forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Initialize search for all sections
        setupSearch('searchStudents', 'studentGrid');
        setupSearch('searchTeachers', 'teacherGrid');
        setupSearch('searchClasses', 'classGrid');
        setupSearch('searchUsers', 'userGrid');
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
        </div>
    </div>
</body>

</html>