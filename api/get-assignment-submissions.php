<?php

/**
 * API: Get Assignment Submissions for Bulk Grading
 * Returns list of students and their submission status for an assignment
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$assignment_id = (int)($_GET['assignment_id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);

if (!$assignment_id || !$class_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get assignment details
$assignment = db()->fetchOne("
    SELECT a.*, c.teacher_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ? AND c.id = ?
", [$assignment_id, $class_id]);

if (!$assignment || $assignment['teacher_id'] != $_SESSION['assigned_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Assignment not found or access denied']);
    exit;
}

// Get all students in class with their submission status
$students = db()->fetchAll("
    SELECT
        s.id,
        CONCAT(u.first_name, ' ', u.last_name) as name,
        asub.id as submission_id,
        asub.grade as current_grade,
        asub.feedback,
        asub.submitted_at,
        asub.graded_at,
        CASE WHEN asub.id IS NOT NULL THEN 1 ELSE 0 END as submitted
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN class_enrollments ce ON s.id = ce.student_id
    LEFT JOIN assignment_submissions asub ON s.id = asub.student_id AND asub.assignment_id = ?
    WHERE ce.class_id = ?
    ORDER BY name
", [$assignment_id, $class_id]);

echo json_encode([
    'success' => true,
    'assignment_title' => $assignment['title'],
    'max_points' => $assignment['max_points'],
    'students' => $students
]);
