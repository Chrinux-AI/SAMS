<?php

/**
 * API: Export Student Transcripts
 * Generates CSV/PDF export of student grades
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$class_id = (int)($_GET['class_id'] ?? 0);
$format = $_GET['format'] ?? 'csv';

if (!$class_id) {
    die('Class ID required');
}

// Verify teacher owns this class
$class = db()->fetchOne("SELECT * FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $_SESSION['assigned_id']]);

if (!$class) {
    die('Class not found or access denied');
}

// Get student grades
$students = db()->fetchAll("
    SELECT
        s.id,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        u.email,
        AVG((asub.grade / a.max_points) * 100) as assignment_avg,
        AVG((mg.grade_value / mg.max_points) * 100) as manual_grade_avg,
        COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.id END) as present_count,
        COUNT(DISTINCT ar.id) as total_attendance
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN class_enrollments ce ON s.id = ce.student_id
    LEFT JOIN assignment_submissions asub ON s.id = asub.student_id
        AND asub.assignment_id IN (SELECT id FROM assignments WHERE class_id = ?)
        AND asub.grade IS NOT NULL
    LEFT JOIN assignments a ON asub.assignment_id = a.id
    LEFT JOIN manual_grades mg ON s.id = mg.student_id AND mg.class_id = ?
    LEFT JOIN attendance_records ar ON s.id = ar.student_id
        AND ar.class_id = ?
        AND ar.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    WHERE ce.class_id = ?
    GROUP BY s.id
    ORDER BY student_name
", [$class_id, $class_id, $class_id, $class_id]);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transcripts_' . $class['class_name'] . '_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, [
        'Student Name',
        'Email',
        'Assignment Average (%)',
        'Manual Grades Average (%)',
        'Overall Grade (%)',
        'Letter Grade',
        'Attendance Rate (%)',
        'Days Present',
        'Total Days'
    ]);

    // Data rows
    foreach ($students as $student) {
        $assignment_avg = $student['assignment_avg'] ?? 0;
        $manual_avg = $student['manual_grade_avg'] ?? 0;
        $overall = ($assignment_avg && $manual_avg) ? ($assignment_avg * 0.7 + $manual_avg * 0.3) : ($assignment_avg ?: $manual_avg);

        // Letter grade
        $letter = 'N/A';
        if ($overall >= 90) $letter = 'A';
        elseif ($overall >= 80) $letter = 'B';
        elseif ($overall >= 70) $letter = 'C';
        elseif ($overall >= 60) $letter = 'D';
        elseif ($overall > 0) $letter = 'F';

        $attendance_rate = $student['total_attendance'] > 0
            ? round(($student['present_count'] / $student['total_attendance']) * 100, 1)
            : 0;

        fputcsv($output, [
            $student['student_name'],
            $student['email'],
            round($assignment_avg, 2),
            round($manual_avg, 2),
            round($overall, 2),
            $letter,
            $attendance_rate,
            $student['present_count'],
            $student['total_attendance']
        ]);
    }

    fclose($output);
    exit;
}

// For other formats, return JSON for now
header('Content-Type: application/json');
echo json_encode(['students' => $students]);
