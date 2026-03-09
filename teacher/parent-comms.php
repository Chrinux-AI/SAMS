<?php

/**
 * Parent Communication Portal
 * Send progress reports, schedule meetings, track responses, automated notifications
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher('../login.php');

$teacher_id = $_SESSION['assigned_id'];
$full_name = $_SESSION['full_name'];
$message = '';
$message_type = '';

// Handle sending progress report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_report'])) {
    $student_id = (int)$_POST['student_id'];
    $subject = sanitize($_POST['subject']);
    $message_body = sanitize($_POST['message_body']);
    $include_attendance = isset($_POST['include_attendance']);
    $include_grades = isset($_POST['include_grades']);

    // Get student and parent info
    $student = db()->fetchOne("
        SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as student_name,
               p.id as parent_id, pu.id as parent_user_id,
               CONCAT(pu.first_name, ' ', pu.last_name) as parent_name,
               pu.email as parent_email
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN parent_student ps ON s.id = ps.student_id
        LEFT JOIN parents p ON ps.parent_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        WHERE s.id = ?
    ", [$student_id]);

    if ($student && $student['parent_user_id']) {
        $report_content = $message_body . "\n\n";

        // Add attendance data if requested
        if ($include_attendance) {
            $attendance_stats = db()->fetchOne("
                SELECT
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
                FROM attendance_records
                WHERE student_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ", [$student_id]);

            $attendance_rate = $attendance_stats['total_days'] > 0
                ? round((($attendance_stats['present'] + $attendance_stats['late']) / $attendance_stats['total_days']) * 100, 1)
                : 0;

            $report_content .= "=== ATTENDANCE SUMMARY (Last 30 Days) ===\n";
            $report_content .= "Attendance Rate: {$attendance_rate}%\n";
            $report_content .= "Present: {$attendance_stats['present']} days\n";
            $report_content .= "Late: {$attendance_stats['late']} days\n";
            $report_content .= "Absent: {$attendance_stats['absent']} days\n\n";
        }

        // Add grades data if requested
        if ($include_grades) {
            $grades = db()->fetchAll("
                SELECT a.title, asub.grade, a.max_points
                FROM assignment_submissions asub
                JOIN assignments a ON asub.assignment_id = a.id
                WHERE asub.student_id = ? AND asub.grade IS NOT NULL
                ORDER BY asub.submitted_at DESC
                LIMIT 5
            ", [$student_id]);

            if (!empty($grades)) {
                $report_content .= "=== RECENT GRADES ===\n";
                foreach ($grades as $grade) {
                    $percentage = round(($grade['grade'] / $grade['max_points']) * 100, 1);
                    $report_content .= "{$grade['title']}: {$grade['grade']}/{$grade['max_points']} ({$percentage}%)\n";
                }
                $report_content .= "\n";
            }
        }

        // Send message
        $message_id = db()->insert('messages', [
            'sender_id' => $_SESSION['user_id'],
            'subject' => $subject,
            'message' => $report_content,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        db()->insert('message_recipients', [
            'message_id' => $message_id,
            'recipient_id' => $student['parent_user_id'],
            'is_read' => 0
        ]);

        // Send email notification
        send_email(
            $student['parent_email'],
            $subject,
            $report_content,
            "From: " . APP_NAME . " <noreply@attendance.com>"
        );

        log_activity($_SESSION['user_id'], 'send_report', 'messages', $message_id, "Sent progress report for {$student['student_name']}");

        $message = 'Progress report sent successfully to ' . $student['parent_name'] . '!';
        $message_type = 'success';
    } else {
        $message = 'Parent information not found for this student!';
        $message_type = 'error';
    }
}

// Handle meeting request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_meeting'])) {
    $parent_id = (int)$_POST['parent_id'];
    $student_id = (int)$_POST['student_id'];
    $meeting_date = sanitize($_POST['meeting_date']);
    $meeting_time = sanitize($_POST['meeting_time']);
    $purpose = sanitize($_POST['purpose']);

    $parent = db()->fetchOne("
        SELECT p.*, u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
        FROM parents p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ", [$parent_id]);

    if ($parent) {
        $meeting_id = db()->insert('parent_meetings', [
            'teacher_id' => $teacher_id,
            'parent_id' => $parent_id,
            'student_id' => $student_id,
            'meeting_date' => $meeting_date,
            'meeting_time' => $meeting_time,
            'purpose' => $purpose,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Send notification
        $subject = "Meeting Request from Teacher";
        $body = "Dear {$parent['name']},\n\n";
        $body .= "Your child's teacher has requested a meeting.\n\n";
        $body .= "Proposed Date: {$meeting_date}\n";
        $body .= "Proposed Time: {$meeting_time}\n";
        $body .= "Purpose: {$purpose}\n\n";
        $body .= "Please log in to respond to this meeting request.\n\n";
        $body .= "Best regards,\n{$full_name}";

        $msg_id = db()->insert('messages', [
            'sender_id' => $_SESSION['user_id'],
            'subject' => $subject,
            'message' => $body,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        db()->insert('message_recipients', [
            'message_id' => $msg_id,
            'recipient_id' => $parent['user_id'],
            'is_read' => 0
        ]);

        send_email($parent['email'], $subject, $body);

        $message = 'Meeting request sent successfully!';
        $message_type = 'success';
    }
}

// Get teacher's students with parent information
$students = db()->fetchAll("
    SELECT DISTINCT s.*, CONCAT(su.first_name, ' ', su.last_name) as student_name,
           p.id as parent_id, CONCAT(pu.first_name, ' ', pu.last_name) as parent_name,
           pu.email as parent_email, pu.id as parent_user_id
    FROM students s
    JOIN users su ON s.user_id = su.id
    JOIN class_enrollments ce ON s.id = ce.student_id
    JOIN classes c ON ce.class_id = c.id
    LEFT JOIN parent_student ps ON s.id = ps.student_id
    LEFT JOIN parents p ON ps.parent_id = p.id
    LEFT JOIN users pu ON p.user_id = pu.id
    WHERE c.teacher_id = ?
    ORDER BY student_name
", [$teacher_id]);

// Get recent communications
$recent_communications = db()->fetchAll("
    SELECT m.*, mr.is_read, mr.read_at,
           CONCAT(u.first_name, ' ', u.last_name) as recipient_name,
           s.admission_number AS student_id
    FROM messages m
    JOIN message_recipients mr ON m.id = mr.message_id
    JOIN users u ON mr.recipient_id = u.id
    LEFT JOIN parents p ON u.id = p.user_id
    LEFT JOIN parent_student ps ON p.id = ps.parent_id
    LEFT JOIN students s ON ps.student_id = s.id
    WHERE m.sender_id = ? AND u.role = 'parent'
    ORDER BY m.created_at DESC
    LIMIT 10
", [$_SESSION['user_id']]);

// Get upcoming meetings
$upcoming_meetings = db()->fetchAll("
    SELECT pm.*, CONCAT(pu.first_name, ' ', pu.last_name) as parent_name,
           CONCAT(su.first_name, ' ', su.last_name) as student_name
    FROM parent_meetings pm
    JOIN parents p ON pm.parent_id = p.id
    JOIN users pu ON p.user_id = pu.id
    JOIN students s ON pm.student_id = s.id
    JOIN users su ON s.user_id = su.id
    WHERE pm.teacher_id = ? AND pm.meeting_date >= CURDATE()
    ORDER BY pm.meeting_date, pm.meeting_time
", [$teacher_id]);

$page_title = 'Parent Communication';
$page_icon = 'users';
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
        .communication-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
            border: 1px solid var(--glass-border);
            border-left-width: 4px;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .communication-card.read {
            border-left-color: rgba(100, 100, 100, 0.5);
            opacity: 0.7;
        }

        .communication-card.unread {
            border-left-color: var(--neon-green);
        }

        .communication-card:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
        }

        .student-card {
            background: rgba(0, 191, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .student-card:hover {
            border-color: var(--cyber-cyan);
            transform: translateX(5px);
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

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:25px;margin-bottom:30px;">
                    <!-- Students & Parents List -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-users"></i> Students & Parents</h3>
                        <div style="max-height:500px;overflow-y:auto;">
                            <?php foreach ($students as $student): ?>
                                <div class="student-card">
                                    <div>
                                        <div style="font-weight:700;color:var(--text-primary);margin-bottom:5px;">
                                            <?php echo htmlspecialchars($student['student_name']); ?>
                                        </div>
                                        <div style="font-size:0.85rem;color:var(--text-muted);">
                                            <?php if ($student['parent_name']): ?>
                                                <i class="fas fa-user-friends"></i> Parent: <?php echo htmlspecialchars($student['parent_name']); ?>
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle"></i> No parent linked
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($student['parent_id']): ?>
                                        <div style="display:flex;gap:8px;">
                                            <button onclick="openReportModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['student_name']); ?>')" class="cyber-btn" title="Send Report">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                            <button onclick="openMeetingModal(<?php echo $student['parent_id']; ?>, <?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['parent_name']); ?>')" class="cyber-btn primary" title="Request Meeting">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Upcoming Meetings -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-calendar-check"></i> Upcoming Meetings</h3>
                        <?php if (empty($upcoming_meetings)): ?>
                            <div style="text-align:center;padding:30px;color:var(--text-muted);">
                                <i class="fas fa-calendar" style="font-size:2.5rem;margin-bottom:10px;opacity:0.3;"></i>
                                <div>No upcoming meetings scheduled</div>
                            </div>
                        <?php else: ?>
                            <div style="max-height:500px;overflow-y:auto;">
                                <?php foreach ($upcoming_meetings as $meeting): ?>
                                    <div style="background:rgba(0,191,255,0.05);border:1px solid var(--glass-border);border-radius:12px;padding:15px;margin-bottom:12px;">
                                        <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                                            <strong style="color:var(--text-primary);"><?php echo htmlspecialchars($meeting['parent_name']); ?></strong>
                                            <span class="cyber-badge <?php
                                                                        echo $meeting['status'] === 'confirmed' ? 'success' : ($meeting['status'] === 'pending' ? 'warning' : 'danger');
                                                                        ?>">
                                                <?php echo ucfirst($meeting['status']); ?>
                                            </span>
                                        </div>
                                        <div style="color:var(--text-muted);font-size:0.9rem;margin-bottom:5px;">
                                            <i class="fas fa-user-graduate"></i> Student: <?php echo htmlspecialchars($meeting['student_name']); ?>
                                        </div>
                                        <div style="color:var(--text-muted);font-size:0.9rem;margin-bottom:5px;">
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?> at <?php echo $meeting['meeting_time']; ?>
                                        </div>
                                        <div style="color:var(--text-muted);font-size:0.9rem;">
                                            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($meeting['purpose']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Communications -->
                <div class="holo-card">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-comments"></i> Recent Communications</h3>
                    <?php if (empty($recent_communications)): ?>
                        <div style="text-align:center;padding:40px;color:var(--text-muted);">
                            <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px;opacity:0.3;"></i>
                            <div>No recent communications</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_communications as $comm): ?>
                            <div class="communication-card <?php echo $comm['is_read'] ? 'read' : 'unread'; ?>">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                                    <div>
                                        <strong style="color:var(--text-primary);font-size:1.1rem;"><?php echo htmlspecialchars($comm['subject']); ?></strong>
                                        <div style="color:var(--text-muted);font-size:0.85rem;margin-top:5px;">
                                            To: <?php echo htmlspecialchars($comm['recipient_name']); ?>
                                            <?php if ($comm['student_id']): ?>
                                                • Re: Student ID <?php echo $comm['student_id']; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="color:var(--cyber-cyan);font-size:0.85rem;">
                                            <?php echo format_datetime($comm['created_at']); ?>
                                        </div>
                                        <?php if ($comm['is_read']): ?>
                                            <div style="color:var(--neon-green);font-size:0.75rem;margin-top:3px;">
                                                <i class="fas fa-check-double"></i> Read <?php echo format_datetime($comm['read_at']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="color:var(--golden-pulse);font-size:0.75rem;margin-top:3px;">
                                                <i class="fas fa-clock"></i> Pending
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="color:var(--text-muted);font-size:0.9rem;white-space:pre-line;max-height:100px;overflow:hidden;">
                                    <?php echo htmlspecialchars(substr($comm['message'], 0, 200)) . (strlen($comm['message']) > 200 ? '...' : ''); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Send Report Modal -->
    <div id="reportModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
                <h3><i class="fas fa-file-alt"></i> Send Progress Report</h3>
                <button onclick="document.getElementById('reportModal').style.display='none'" class="cyber-btn danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="student_id" id="report_student_id">
                <div id="report_student_name" style="color:var(--cyber-cyan);margin-bottom:20px;font-size:1.1rem;"></div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="cyber-label">Subject</label>
                    <input type="text" name="subject" class="cyber-input" required placeholder="e.g., Monthly Progress Report">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="cyber-label">Message</label>
                    <textarea name="message_body" class="cyber-input" rows="6" required placeholder="Enter your message to the parent..."></textarea>
                </div>

                <div style="background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:12px;padding:15px;margin-bottom:20px;">
                    <div style="margin-bottom:10px;font-weight:600;color:var(--cyber-cyan);">Include in Report:</div>
                    <label style="display:block;margin-bottom:8px;cursor:pointer;">
                        <input type="checkbox" name="include_attendance" checked> Attendance Summary (Last 30 Days)
                    </label>
                    <label style="display:block;cursor:pointer;">
                        <input type="checkbox" name="include_grades" checked> Recent Grades
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="document.getElementById('reportModal').style.display='none'" class="cyber-btn">Cancel</button>
                    <button type="submit" name="send_report" class="cyber-btn primary">
                        <i class="fas fa-paper-plane"></i> Send Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Meeting Request Modal -->
    <div id="meetingModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;">
            <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
                <h3><i class="fas fa-calendar-plus"></i> Request Meeting</h3>
                <button onclick="document.getElementById('meetingModal').style.display='none'" class="cyber-btn danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="parent_id" id="meeting_parent_id">
                <input type="hidden" name="student_id" id="meeting_student_id">
                <div id="meeting_parent_name" style="color:var(--cyber-cyan);margin-bottom:20px;font-size:1.1rem;"></div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
                    <div class="form-group">
                        <label class="cyber-label">Proposed Date</label>
                        <input type="date" name="meeting_date" class="cyber-input" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="cyber-label">Proposed Time</label>
                        <input type="time" name="meeting_time" class="cyber-input" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="cyber-label">Purpose of Meeting</label>
                    <textarea name="purpose" class="cyber-input" rows="4" required placeholder="Briefly describe what you'd like to discuss..."></textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="document.getElementById('meetingModal').style.display='none'" class="cyber-btn">Cancel</button>
                    <button type="submit" name="request_meeting" class="cyber-btn primary">
                        <i class="fas fa-calendar-check"></i> Send Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReportModal(studentId, studentName) {
            document.getElementById('report_student_id').value = studentId;
            document.getElementById('report_student_name').textContent = 'Student: ' + studentName;
            document.getElementById('reportModal').style.display = 'flex';
        }

        function openMeetingModal(parentId, studentId, parentName) {
            document.getElementById('meeting_parent_id').value = parentId;
            document.getElementById('meeting_student_id').value = studentId;
            document.getElementById('meeting_parent_name').textContent = 'Parent: ' + parentName;
            document.getElementById('meetingModal').style.display = 'flex';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>