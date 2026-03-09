<?php

/**
 * Advanced Admin Features
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mass_email'])) {
        $recipient_type = $_POST['recipient_type'];
        $subject = sanitize($_POST['subject']);
        $email_message = sanitize($_POST['message']);

        $recipients = [];
        switch ($recipient_type) {
            case 'all_users':
                $recipients = db()->fetchAll("SELECT email, first_name, last_name FROM users WHERE status = 'active'");
                break;
            case 'students':
                $recipients = db()->fetchAll("SELECT email, first_name, last_name FROM users WHERE role = 'student' AND status = 'active'");
                break;
            case 'teachers':
                $recipients = db()->fetchAll("SELECT email, first_name, last_name FROM users WHERE role = 'teacher' AND status = 'active'");
                break;
            case 'parents':
                $recipients = db()->fetchAll("SELECT email, first_name, last_name FROM users WHERE role = 'parent' AND status = 'active'");
                break;
        }

        $sent_count = 0;
        foreach ($recipients as $recipient) {
            $personalized_message = str_replace('{name}', $recipient['first_name'] . ' ' . $recipient['last_name'], $email_message);
            if (send_email($recipient['email'], $subject, $personalized_message)) {
                $sent_count++;
            }
        }

        log_activity($_SESSION['user_id'], 'mass_email', 'system', 0, "Sent to $sent_count recipients");
        $message = "Mass email sent successfully to $sent_count recipients!";
        $message_type = 'success';
    } elseif (isset($_POST['generate_reports'])) {
        $report_type = $_POST['report_type'];
        $date_from = $_POST['date_from'];
        $date_to = $_POST['date_to'];

        // Generate comprehensive report
        $filename = "report_{$report_type}_" . date('Y-m-d_H-i-s') . '.csv';
        $filepath = "../reports/$filename";

        if (!is_dir('../reports')) {
            mkdir('../reports', 0777, true);
        }

        $file = fopen($filepath, 'w');

        switch ($report_type) {
            case 'attendance':
                fputcsv($file, ['Date', 'Student ID', 'Student Name', 'Class', 'Status', 'Check-in Time']);
                $data = db()->fetchAll("
                    SELECT DATE(ar.check_in_time) as date, s.admission_number as student_id,
                           CONCAT(u.first_name, ' ', u.last_name) as name,
                           c.name as class_name, ar.status, ar.check_in_time
                    FROM attendance_records ar
                    JOIN students s ON ar.student_id = s.id
                    LEFT JOIN users u ON s.user_id = u.id
                    JOIN classes c ON ar.class_id = c.id
                    WHERE DATE(ar.check_in_time) BETWEEN ? AND ?
                    ORDER BY ar.check_in_time DESC
                ", [$date_from, $date_to]);
                break;

            case 'users':
                fputcsv($file, ['ID', 'Username', 'Name', 'Email', 'Role', 'Status', 'Created Date', 'Last Login']);
                $data = db()->fetchAll("
                    SELECT id, username, CONCAT(first_name, ' ', last_name) as name,
                           email, role, status, created_at, last_login
                    FROM users
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    ORDER BY created_at DESC
                ", [$date_from, $date_to]);
                break;

            case 'performance':
                fputcsv($file, ['Student ID', 'Student Name', 'Total Classes', 'Attended', 'Attendance Rate']);
                $data = db()->fetchAll("
                    SELECT s.admission_number as student_id, CONCAT(u.first_name, ' ', u.last_name) as name,
                           COUNT(DISTINCT c.id) as total_classes,
                           COUNT(ar.id) as attended,
                           ROUND((COUNT(ar.id) * 100.0 / COUNT(DISTINCT c.id)), 2) as rate
                    FROM students s
                    LEFT JOIN users u ON s.user_id = u.id
                    JOIN class_enrollments ce ON s.id = ce.student_id
                    JOIN classes c ON ce.class_id = c.id
                    LEFT JOIN attendance_records ar ON s.id = ar.student_id AND c.id = ar.class_id
                           AND DATE(ar.check_in_time) BETWEEN ? AND ?
                    WHERE u.status = 'active'
                    GROUP BY s.id, s.admission_number, u.first_name, u.last_name
                    ORDER BY rate DESC
                ", [$date_from, $date_to]);
                break;
        }

        foreach ($data as $row) {
            fputcsv($file, array_values($row));
        }
        fclose($file);

        log_activity($_SESSION['user_id'], 'generate_report', 'system', 0, $report_type);
        $message = "Report generated successfully: <a href='../reports/$filename' target='_blank'>Download $filename</a>";
        $message_type = 'success';
    } elseif (isset($_POST['auto_assign_ids'])) {
        try {
            db()->beginTransaction();

            // Get students without IDs
            $students = db()->fetchAll("SELECT id FROM students WHERE assigned_student_id IS NULL OR assigned_student_id = ''");
            $count = 0;

            foreach ($students as $student) {
                $next_id_result = db()->fetchOne("SELECT MAX(CAST(SUBSTRING(assigned_student_id, 4) AS UNSIGNED)) + 1 as next_id FROM students WHERE assigned_student_id LIKE 'STU%'");
                $next_id = $next_id_result['next_id'] ?? 1;
                $new_id = 'STU' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

                db()->update('students', ['assigned_student_id' => $new_id], 'id = ?', [$student['id']]);
                $count++;
            }

            // Get teachers without IDs
            $teachers = db()->fetchAll("SELECT id FROM teachers WHERE employee_id IS NULL OR employee_id = ''");

            foreach ($teachers as $teacher) {
                $next_id_result = db()->fetchOne("SELECT MAX(CAST(SUBSTRING(employee_id, 4) AS UNSIGNED)) + 1 as next_id FROM teachers WHERE employee_id LIKE 'TCH%'");
                $next_id = $next_id_result['next_id'] ?? 1;
                $new_id = 'TCH' . str_pad($next_id, 6, '0', STR_PAD_LEFT);

                db()->update('teachers', ['employee_id' => $new_id], 'id = ?', [$teacher['id']]);
                $count++;
            }

            db()->commit();
            log_activity($_SESSION['user_id'], 'auto_assign_ids', 'system', $count);
            $message = "Automatically assigned IDs to $count users!";
            $message_type = 'success';
        } catch (Exception $e) {
            db()->rollback();
            $message = 'ID assignment failed: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get statistics for dashboard
$stats = [
    'pending_registrations' => db()->count('users', 'status = ?', ['pending']),
    'total_emails_sent' => db()->count('activity_logs', 'action = ?', ['mass_email']),
    'reports_generated' => count(glob('../reports/*.csv')),
    'system_health' => round((db()->count('users', 'status = ?', ['active']) / max(1, db()->count('users'))) * 100)
];

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
    <title>Advanced Admin - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <style>
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin: 25px 0;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #3b82f6;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .feature-title {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .form-grid {
            display: grid;
            gap: 15px;
        }

        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .stat-orb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            opacity: 0.9;
        }

        .ai-suggestions {
            background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
        }

        .suggestion-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <div class="cyber-main">

    <div class="container">
            <div class="cyber-header" style="padding: 24px 32px; border-bottom: 1px solid var(--border-color);">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:44px;height:44px;background:var(--primary-50);color:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div>
                        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary);">Advanced Admin Features</h1>
                        <p style="color:var(--text-secondary);font-size:0.85rem;">Powerful tools for system management</p>
                    </div>
                </div>
            </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Admin Statistics -->
        <div class="admin-stats">
            <div class="stat-orb">
                <div class="stat-number"><?php echo $stats['pending_registrations']; ?></div>
                <div class="stat-label">Pending Registrations</div>
            </div>
            <div class="stat-orb" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="stat-number"><?php echo $stats['total_emails_sent']; ?></div>
                <div class="stat-label">Mass Emails Sent</div>
            </div>
            <div class="stat-orb" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="stat-number"><?php echo $stats['reports_generated']; ?></div>
                <div class="stat-label">Reports Generated</div>
            </div>
            <div class="stat-orb" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="stat-number"><?php echo $stats['system_health']; ?>%</div>
                <div class="stat-label">System Health</div>
            </div>
        </div>

        <!-- AI Suggestions -->
        <div class="ai-suggestions">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-robot"></i> AI-Powered Suggestions</h3>
            <div class="suggestion-item">
                <i class="fas fa-lightbulb"></i>
                <div>
                    <strong>Optimize Performance:</strong> Consider implementing automated ID assignment for new registrations.
                </div>
            </div>
            <div class="suggestion-item">
                <i class="fas fa-chart-line"></i>
                <div>
                    <strong>Analytics Insight:</strong> Generate weekly performance reports to track attendance trends.
                </div>
            </div>
            <div class="suggestion-item">
                <i class="fas fa-envelope"></i>
                <div>
                    <strong>Communication:</strong> Send personalized welcome emails to improve user engagement.
                </div>
            </div>
        </div>

        <!-- Advanced Features Grid -->
        <div class="feature-grid">
            <!-- Mass Email System -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div class="feature-title">Mass Email System</div>
                </div>

                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label for="recipient_type">Recipients</label>
                        <select name="recipient_type" id="recipient_type" required>
                            <option value="all_users">All Active Users</option>
                            <option value="students">All Students</option>
                            <option value="teachers">All Teachers</option>
                            <option value="parents">All Parents</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">Email Subject</label>
                        <input type="text" name="subject" id="subject" required
                            placeholder="Important Announcement">
                    </div>

                    <div class="form-group">
                        <label for="message">Message (Use {name} for personalization)</label>
                        <textarea name="message" id="message" rows="4" required
                            placeholder="Dear {name}, We are pleased to inform you..."></textarea>
                    </div>

                    <button type="submit" name="mass_email" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Mass Email
                    </button>
                </form>
            </div>

            <!-- Advanced Reports -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="feature-title">Advanced Reports</div>
                </div>

                <form method="POST" class="form-grid">
                    <div class="form-group">
                        <label for="report_type">Report Type</label>
                        <select name="report_type" id="report_type" required>
                            <option value="attendance">Attendance Report</option>
                            <option value="users">User Activity Report</option>
                            <option value="performance">Performance Analysis</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" name="date_from" id="date_from" required
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" name="date_to" id="date_to" required
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <button type="submit" name="generate_reports" class="btn btn-success">
                        <i class="fas fa-file-download"></i> Generate Report
                    </button>
                </form>
            </div>

            <!-- Auto ID Assignment -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-magic"></i>
                    </div>
                    <div class="feature-title">Auto ID Assignment</div>
                </div>

                <p style="color: #64748b; margin-bottom: 20px;">
                    Automatically assign Student/Employee IDs to users who don't have them yet.
                </p>

                <form method="POST">
                    <button type="submit" name="auto_assign_ids" class="btn btn-warning"
                        onclick="return confirm('Assign IDs to all users without them?')">
                        <i class="fas fa-wand-magic-sparkles"></i> Auto-Assign IDs
                    </button>
                </form>
            </div>

            <!-- Quick Actions -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-lightning-bolt"></i>
                    </div>
                    <div class="feature-title">Quick Actions</div>
                </div>

                <div style="display: grid; gap: 10px;">
                    <a href="registrations.php" class="btn btn-info">
                        <i class="fas fa-user-check"></i> Pending Registrations
                        <?php if ($stats['pending_registrations'] > 0): ?>
                            <span class="badge badge-warning"><?php echo $stats['pending_registrations']; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="analytics.php" class="btn btn-primary">
                        <i class="fas fa-brain"></i> AI Analytics Dashboard
                    </a>

                    <a href="system-management.php" class="btn btn-danger">
                        <i class="fas fa-tools"></i> System Management
                    </a>

                    <a href="../reports/" class="btn btn-success" target="_blank">
                        <i class="fas fa-folder-open"></i> View All Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
        </div>
    </div>
</body>

</html>