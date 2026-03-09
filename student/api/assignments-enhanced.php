<?php

/**
 * Enhanced Assignments API - Student Panel
 * Handles submissions, feedback, file uploads, AI recommendations
 * Version: 2.1.0
 */

session_start();
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Auto-create tables
createTablesIfNotExist();

/**
 * Create required database tables
 */
function createTablesIfNotExist()
{
    global $pdo;

    // Assignments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        teacher_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        instructions TEXT,
        type ENUM('homework', 'project', 'quiz', 'exam', 'lab', 'presentation') DEFAULT 'homework',
        total_points INT DEFAULT 100,
        due_date DATETIME NOT NULL,
        allow_late TINYINT(1) DEFAULT 1,
        late_penalty_percent INT DEFAULT 10,
        max_attempts INT DEFAULT 1,
        requires_file TINYINT(1) DEFAULT 0,
        file_types VARCHAR(255) DEFAULT 'pdf,doc,docx,txt',
        max_file_size INT DEFAULT 10485760 COMMENT '10MB in bytes',
        rubric TEXT COMMENT 'JSON rubric data',
        status ENUM('draft', 'published', 'archived') DEFAULT 'published',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_class_due (class_id, due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Assignment submissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        student_id INT NOT NULL,
        attempt_number INT DEFAULT 1,
        submission_text TEXT,
        file_path VARCHAR(500),
        submitted_at DATETIME NOT NULL,
        is_late TINYINT(1) DEFAULT 0,
        points_earned DECIMAL(5,2) DEFAULT NULL,
        feedback TEXT,
        graded_at DATETIME NULL,
        graded_by INT NULL,
        status ENUM('submitted', 'graded', 'returned', 'resubmit') DEFAULT 'submitted',
        plagiarism_score DECIMAL(4,2) DEFAULT NULL,
        ai_feedback TEXT COMMENT 'Auto-generated feedback',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_attempt (assignment_id, student_id, attempt_number),
        INDEX idx_student_status (student_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Assignment materials table
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(50),
        file_size INT,
        description TEXT,
        uploaded_by INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Student assignment preferences
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_assignment_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        reminder_days INT DEFAULT 2,
        reminder_hours INT DEFAULT 24,
        email_reminders TINYINT(1) DEFAULT 1,
        push_reminders TINYINT(1) DEFAULT 1,
        show_grade_comparison TINYINT(1) DEFAULT 1,
        ai_suggestions TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Assignment bookmarks
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_bookmarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        assignment_id INT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        UNIQUE KEY unique_bookmark (student_id, assignment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Route actions
switch ($action) {
    case 'get_assignments':
        getAssignments();
        break;
    case 'get_assignment_details':
        getAssignmentDetails();
        break;
    case 'submit_assignment':
        submitAssignment();
        break;
    case 'get_submissions':
        getSubmissions();
        break;
    case 'get_submission_details':
        getSubmissionDetails();
        break;
    case 'download_material':
        downloadMaterial();
        break;
    case 'bookmark_assignment':
        bookmarkAssignment();
        break;
    case 'get_bookmarks':
        getBookmarks();
        break;
    case 'get_upcoming':
        getUpcomingAssignments();
        break;
    case 'get_statistics':
        getAssignmentStatistics();
        break;
    case 'request_extension':
        requestExtension();
        break;
    case 'save_preferences':
        savePreferences();
        break;
    case 'get_ai_suggestions':
        getAISuggestions();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Get assignments for enrolled classes
 */
function getAssignments()
{
    global $pdo, $student_id;

    $status = $_GET['status'] ?? 'all'; // all, pending, submitted, graded
    $class_id = $_GET['class_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);

    try {
        $sql = "
            SELECT a.*, c.name as class_name, c.class_code,
                   u.first_name as teacher_first, u.last_name as teacher_last,
                   asub.id as submission_id, asub.status as submission_status,
                   asub.points_earned, asub.submitted_at, asub.is_late,
                   (SELECT COUNT(*) FROM assignment_materials WHERE assignment_id = a.id) as material_count,
                   CASE
                       WHEN NOW() > a.due_date THEN 'overdue'
                       WHEN asub.id IS NOT NULL THEN 'submitted'
                       WHEN TIMESTAMPDIFF(HOUR, NOW(), a.due_date) <= 24 THEN 'due_soon'
                       ELSE 'pending'
                   END as computed_status
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            JOIN users u ON a.teacher_id = u.id
            LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
            WHERE ce.student_id = ? AND a.status = 'published'
        ";

        $params = [$student_id, $student_id];

        if ($class_id) {
            $sql .= " AND a.class_id = ?";
            $params[] = $class_id;
        }

        if ($status !== 'all') {
            switch ($status) {
                case 'pending':
                    $sql .= " AND asub.id IS NULL AND NOW() <= a.due_date";
                    break;
                case 'submitted':
                    $sql .= " AND asub.id IS NOT NULL AND asub.status = 'submitted'";
                    break;
                case 'graded':
                    $sql .= " AND asub.status = 'graded'";
                    break;
                case 'overdue':
                    $sql .= " AND asub.id IS NULL AND NOW() > a.due_date";
                    break;
            }
        }

        $sql .= " ORDER BY a.due_date ASC LIMIT ?";
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add time remaining
        foreach ($assignments as &$assignment) {
            $due = strtotime($assignment['due_date']);
            $now = time();
            $diff = $due - $now;

            if ($diff > 0) {
                $days = floor($diff / 86400);
                $hours = floor(($diff % 86400) / 3600);
                $assignment['time_remaining'] = $days > 0 ? "$days days" : "$hours hours";
                $assignment['is_overdue'] = false;
            } else {
                $assignment['time_remaining'] = 'Overdue';
                $assignment['is_overdue'] = true;
            }
        }

        echo json_encode(['success' => true, 'assignments' => $assignments, 'count' => count($assignments)]);
    } catch (Exception $e) {
        error_log("Get Assignments Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch assignments']);
    }
}

/**
 * Get assignment details with materials
 */
function getAssignmentDetails()
{
    global $pdo, $student_id;

    $assignment_id = $_GET['assignment_id'] ?? 0;

    try {
        // Get assignment
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name, c.class_code,
                   u.first_name as teacher_first, u.last_name as teacher_last,
                   u.email as teacher_email
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.teacher_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            echo json_encode(['success' => false, 'error' => 'Assignment not found']);
            return;
        }

        // Get materials
        $stmt = $pdo->prepare("
            SELECT * FROM assignment_materials WHERE assignment_id = ? ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$assignment_id]);
        $assignment['materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get student's submissions
        $stmt = $pdo->prepare("
            SELECT * FROM assignment_submissions
            WHERE assignment_id = ? AND student_id = ?
            ORDER BY attempt_number DESC
        ");
        $stmt->execute([$assignment_id, $student_id]);
        $assignment['submissions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if bookmarked
        $stmt = $pdo->prepare("SELECT id FROM assignment_bookmarks WHERE student_id = ? AND assignment_id = ?");
        $stmt->execute([$student_id, $assignment_id]);
        $assignment['is_bookmarked'] = $stmt->fetch() ? true : false;

        echo json_encode(['success' => true, 'assignment' => $assignment]);
    } catch (Exception $e) {
        error_log("Get Assignment Details Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch assignment details']);
    }
}

/**
 * Submit assignment
 */
function submitAssignment()
{
    global $pdo, $student_id;

    $assignment_id = $_POST['assignment_id'] ?? 0;
    $submission_text = trim($_POST['submission_text'] ?? '');

    try {
        // Get assignment details
        $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            echo json_encode(['success' => false, 'error' => 'Assignment not found']);
            return;
        }

        // Check if already submitted
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count FROM assignment_submissions
            WHERE assignment_id = ? AND student_id = ?
        ");
        $stmt->execute([$assignment_id, $student_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $attempt_number = $result['attempt_count'] + 1;

        if ($attempt_number > $assignment['max_attempts']) {
            echo json_encode(['success' => false, 'error' => 'Maximum attempts reached']);
            return;
        }

        // Check if late
        $is_late = time() > strtotime($assignment['due_date']);

        if ($is_late && !$assignment['allow_late']) {
            echo json_encode(['success' => false, 'error' => 'Late submissions not allowed']);
            return;
        }

        // Handle file upload
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/assignments/submissions/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = explode(',', $assignment['file_types']);

            if (!in_array($file_ext, $allowed_exts)) {
                echo json_encode(['success' => false, 'error' => 'File type not allowed. Allowed: ' . $assignment['file_types']]);
                return;
            }

            if ($_FILES['submission_file']['size'] > $assignment['max_file_size']) {
                $max_mb = $assignment['max_file_size'] / 1048576;
                echo json_encode(['success' => false, 'error' => "File too large. Max: {$max_mb}MB"]);
                return;
            }

            $filename = 'submission_' . $assignment_id . '_' . $student_id . '_' . $attempt_number . '_' . time() . '.' . $file_ext;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $filepath)) {
                $file_path = 'uploads/assignments/submissions/' . $filename;
            }
        }

        // Validate submission
        if (empty($submission_text) && !$file_path) {
            echo json_encode(['success' => false, 'error' => 'Submission text or file is required']);
            return;
        }

        if ($assignment['requires_file'] && !$file_path) {
            echo json_encode(['success' => false, 'error' => 'File submission is required for this assignment']);
            return;
        }

        // Generate AI feedback (simple placeholder)
        $ai_feedback = generateAIFeedback($submission_text);

        // Insert submission
        $stmt = $pdo->prepare("
            INSERT INTO assignment_submissions
            (assignment_id, student_id, attempt_number, submission_text, file_path, submitted_at, is_late, ai_feedback)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([
            $assignment_id,
            $student_id,
            $attempt_number,
            $submission_text,
            $file_path,
            $is_late ? 1 : 0,
            $ai_feedback
        ]);

        $submission_id = $pdo->lastInsertId();

        // Log activity
        log_activity(
            $student_id,
            'submit_assignment',
            'assignment_submissions',
            $submission_id,
            "Submitted assignment: {$assignment['title']}"
        );

        // Create notification for teacher
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, created_at)
            VALUES (?, 'assignment', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $assignment['teacher_id'],
            'New Assignment Submission',
            "Student submitted: {$assignment['title']}",
            "/teacher/assignments.php?id=$assignment_id"
        ]);

        echo json_encode([
            'success' => true,
            'message' => $is_late ? 'Late submission recorded' : 'Assignment submitted successfully',
            'submission_id' => $submission_id,
            'attempt_number' => $attempt_number,
            'is_late' => $is_late,
            'ai_feedback' => $ai_feedback
        ]);
    } catch (Exception $e) {
        error_log("Submit Assignment Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to submit assignment']);
    }
}

/**
 * Generate AI feedback (placeholder for actual AI integration)
 */
function generateAIFeedback($text)
{
    if (empty($text)) return null;

    $word_count = str_word_count($text);
    $feedback = [];

    if ($word_count < 50) {
        $feedback[] = "Your submission seems brief. Consider expanding your ideas.";
    } elseif ($word_count > 500) {
        $feedback[] = "Great detail! Make sure your key points are clear.";
    } else {
        $feedback[] = "Good length for your submission.";
    }

    // Check for common issues
    if (substr_count(strtolower($text), 'i think') > 3) {
        $feedback[] = "Tip: Try to use more assertive language instead of 'I think'.";
    }

    $feedback[] = "Remember to proofread before final submission.";

    return implode(' ', $feedback);
}

/**
 * Get student's submissions
 */
function getSubmissions()
{
    global $pdo, $student_id;

    $assignment_id = $_GET['assignment_id'] ?? null;

    try {
        $sql = "
            SELECT asub.*, a.title as assignment_title, a.total_points,
                   c.name as class_name,
                   u.first_name as grader_first, u.last_name as grader_last
            FROM assignment_submissions asub
            JOIN assignments a ON asub.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            LEFT JOIN users u ON asub.graded_by = u.id
            WHERE asub.student_id = ?
        ";

        $params = [$student_id];

        if ($assignment_id) {
            $sql .= " AND asub.assignment_id = ?";
            $params[] = $assignment_id;
        }

        $sql .= " ORDER BY asub.submitted_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'submissions' => $submissions]);
    } catch (Exception $e) {
        error_log("Get Submissions Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch submissions']);
    }
}

/**
 * Get submission details
 */
function getSubmissionDetails()
{
    global $pdo, $student_id;

    $submission_id = $_GET['submission_id'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            SELECT asub.*, a.title as assignment_title, a.total_points, a.rubric,
                   c.name as class_name,
                   u.first_name as grader_first, u.last_name as grader_last
            FROM assignment_submissions asub
            JOIN assignments a ON asub.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            LEFT JOIN users u ON asub.graded_by = u.id
            WHERE asub.id = ? AND asub.student_id = ?
        ");
        $stmt->execute([$submission_id, $student_id]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => 'Submission not found']);
            return;
        }

        echo json_encode(['success' => true, 'submission' => $submission]);
    } catch (Exception $e) {
        error_log("Get Submission Details Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch submission']);
    }
}

/**
 * Bookmark assignment
 */
function bookmarkAssignment()
{
    global $pdo, $student_id;

    $assignment_id = $_POST['assignment_id'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');
    $action_type = $_POST['action_type'] ?? 'add'; // add or remove

    try {
        if ($action_type === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM assignment_bookmarks WHERE student_id = ? AND assignment_id = ?");
            $stmt->execute([$student_id, $assignment_id]);
            echo json_encode(['success' => true, 'message' => 'Bookmark removed']);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO assignment_bookmarks (student_id, assignment_id, notes)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE notes = VALUES(notes)
            ");
            $stmt->execute([$student_id, $assignment_id, $notes]);
            echo json_encode(['success' => true, 'message' => 'Assignment bookmarked']);
        }
    } catch (Exception $e) {
        error_log("Bookmark Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to update bookmark']);
    }
}

/**
 * Get bookmarked assignments
 */
function getBookmarks()
{
    global $pdo, $student_id;

    try {
        $stmt = $pdo->prepare("
            SELECT ab.*, a.title, a.due_date, c.name as class_name
            FROM assignment_bookmarks ab
            JOIN assignments a ON ab.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE ab.student_id = ?
            ORDER BY a.due_date ASC
        ");
        $stmt->execute([$student_id]);
        $bookmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
    } catch (Exception $e) {
        error_log("Get Bookmarks Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch bookmarks']);
    }
}

/**
 * Get upcoming assignments
 */
function getUpcomingAssignments()
{
    global $pdo, $student_id;

    $days = (int)($_GET['days'] ?? 7);

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name, c.class_code,
                   asub.id as is_submitted
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
            WHERE ce.student_id = ?
            AND a.status = 'published'
            AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
            AND asub.id IS NULL
            ORDER BY a.due_date ASC
        ");
        $stmt->execute([$student_id, $student_id, $days]);
        $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'upcoming' => $upcoming, 'count' => count($upcoming)]);
    } catch (Exception $e) {
        error_log("Get Upcoming Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch upcoming assignments']);
    }
}

/**
 * Get assignment statistics
 */
function getAssignmentStatistics()
{
    global $pdo, $student_id;

    try {
        // Overall stats
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT a.id) as total_assignments,
                COUNT(DISTINCT asub.id) as submitted_count,
                COUNT(DISTINCT CASE WHEN asub.status = 'graded' THEN asub.id END) as graded_count,
                AVG(CASE WHEN asub.points_earned IS NOT NULL THEN (asub.points_earned / a.total_points) * 100 END) as avg_grade,
                SUM(CASE WHEN asub.is_late = 1 THEN 1 ELSE 0 END) as late_submissions
            FROM assignments a
            JOIN class_enrollments ce ON a.class_id = ce.class_id
            LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ce.student_id
            WHERE ce.student_id = ? AND a.status = 'published'
        ");
        $stmt->execute([$student_id]);
        $overall = $stmt->fetch(PDO::FETCH_ASSOC);

        // Class breakdown
        $stmt = $pdo->prepare("
            SELECT c.id, c.name as class_name,
                   COUNT(DISTINCT a.id) as total,
                   COUNT(DISTINCT asub.id) as submitted,
                   AVG(CASE WHEN asub.points_earned IS NOT NULL THEN (asub.points_earned / a.total_points) * 100 END) as avg_grade
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN assignments a ON c.id = a.class_id AND a.status = 'published'
            LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ce.student_id
            WHERE ce.student_id = ?
            GROUP BY c.id
            ORDER BY c.name
        ");
        $stmt->execute([$student_id]);
        $class_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'statistics' => [
                'overall' => $overall,
                'class_breakdown' => $class_breakdown
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get Statistics Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch statistics']);
    }
}

/**
 * Request extension (placeholder)
 */
function requestExtension()
{
    global $pdo, $student_id;

    $assignment_id = $_POST['assignment_id'] ?? 0;
    $reason = trim($_POST['reason'] ?? '');
    $requested_date = $_POST['requested_date'] ?? '';

    // This would create an extension request for teacher approval
    echo json_encode(['success' => true, 'message' => 'Extension request feature coming soon']);
}

/**
 * Save student preferences
 */
function savePreferences()
{
    global $pdo, $student_id;

    $reminder_days = (int)($_POST['reminder_days'] ?? 2);
    $reminder_hours = (int)($_POST['reminder_hours'] ?? 24);
    $email_reminders = (int)($_POST['email_reminders'] ?? 1);
    $push_reminders = (int)($_POST['push_reminders'] ?? 1);
    $show_grade_comparison = (int)($_POST['show_grade_comparison'] ?? 1);
    $ai_suggestions = (int)($_POST['ai_suggestions'] ?? 1);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO student_assignment_preferences
            (student_id, reminder_days, reminder_hours, email_reminders, push_reminders, show_grade_comparison, ai_suggestions)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                reminder_days = VALUES(reminder_days),
                reminder_hours = VALUES(reminder_hours),
                email_reminders = VALUES(email_reminders),
                push_reminders = VALUES(push_reminders),
                show_grade_comparison = VALUES(show_grade_comparison),
                ai_suggestions = VALUES(ai_suggestions)
        ");
        $stmt->execute([$student_id, $reminder_days, $reminder_hours, $email_reminders, $push_reminders, $show_grade_comparison, $ai_suggestions]);

        echo json_encode(['success' => true, 'message' => 'Preferences saved']);
    } catch (Exception $e) {
        error_log("Save Preferences Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to save preferences']);
    }
}

/**
 * Get AI suggestions (placeholder for actual AI)
 */
function getAISuggestions()
{
    global $pdo, $student_id;

    try {
        // Generate simple suggestions based on performance
        $stmt = $pdo->prepare("
            SELECT AVG((asub.points_earned / a.total_points) * 100) as avg_grade,
                   SUM(CASE WHEN asub.is_late = 1 THEN 1 ELSE 0 END) as late_count
            FROM assignment_submissions asub
            JOIN assignments a ON asub.assignment_id = a.id
            WHERE asub.student_id = ? AND asub.points_earned IS NOT NULL
        ");
        $stmt->execute([$student_id]);
        $performance = $stmt->fetch(PDO::FETCH_ASSOC);

        $suggestions = [];

        if ($performance['avg_grade'] < 70) {
            $suggestions[] = [
                'type' => 'improvement',
                'title' => 'Focus on Quality',
                'message' => 'Your average grade is below 70%. Consider spending more time on each assignment and reviewing the rubric carefully.',
                'priority' => 'high'
            ];
        }

        if ($performance['late_count'] > 3) {
            $suggestions[] = [
                'type' => 'time_management',
                'title' => 'Time Management',
                'message' => 'You have submitted several assignments late. Try setting reminders 2-3 days before the deadline.',
                'priority' => 'medium'
            ];
        }

        $suggestions[] = [
            'type' => 'study_tip',
            'title' => 'Use Available Resources',
            'message' => 'Check the materials provided by your teacher before starting each assignment.',
            'priority' => 'low'
        ];

        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    } catch (Exception $e) {
        error_log("Get AI Suggestions Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to generate suggestions']);
    }
}
