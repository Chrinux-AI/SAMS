<?php

/**
 * Enhanced Grades API - Student Panel
 * Handles grade analytics, comparisons, trends, AI recommendations
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

    // Grade categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS grade_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        weight DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage weight',
        drop_lowest INT DEFAULT 0,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        INDEX idx_class (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Student grades table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        category_id INT NULL,
        assignment_id INT NULL,
        grade_type ENUM('assignment', 'quiz', 'exam', 'participation', 'project', 'other') DEFAULT 'assignment',
        points_earned DECIMAL(6,2) NOT NULL,
        points_possible DECIMAL(6,2) NOT NULL,
        percentage DECIMAL(5,2) GENERATED ALWAYS AS ((points_earned / points_possible) * 100) STORED,
        letter_grade VARCHAR(2) NULL,
        feedback TEXT,
        graded_by INT NULL,
        graded_at DATETIME NULL,
        is_extra_credit TINYINT(1) DEFAULT 0,
        is_dropped TINYINT(1) DEFAULT 0,
        weight DECIMAL(5,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES grade_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
        FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_student_class (student_id, class_id),
        INDEX idx_category (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Student grade goals
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_grade_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        target_grade DECIMAL(5,2) NOT NULL,
        target_letter VARCHAR(2),
        deadline DATE,
        notes TEXT,
        achieved TINYINT(1) DEFAULT 0,
        achieved_at DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        UNIQUE KEY unique_goal (student_id, class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Grade trends tracking
    $pdo->exec("CREATE TABLE IF NOT EXISTS grade_trends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        average_grade DECIMAL(5,2),
        trend ENUM('improving', 'declining', 'stable') DEFAULT 'stable',
        assignments_completed INT DEFAULT 0,
        assignments_graded INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        INDEX idx_student_period (student_id, period_end)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Route actions
switch ($action) {
    case 'get_overview':
        getGradeOverview();
        break;
    case 'get_class_grades':
        getClassGrades();
        break;
    case 'get_grade_details':
        getGradeDetails();
        break;
    case 'get_analytics':
        getGradeAnalytics();
        break;
    case 'get_trends':
        getGradeTrends();
        break;
    case 'get_comparison':
        getGradeComparison();
        break;
    case 'set_goal':
        setGradeGoal();
        break;
    case 'get_goals':
        getGradeGoals();
        break;
    case 'get_recommendations':
        getGradeRecommendations();
        break;
    case 'export_transcript':
        exportTranscript();
        break;
    case 'get_gpa':
        calculateGPA();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Get grade overview for all classes
 */
function getGradeOverview()
{
    global $pdo, $student_id;

    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name as class_name, c.class_code, c.credits,
                   u.first_name as teacher_first, u.last_name as teacher_last,
                   AVG(sg.percentage) as current_grade,
                   COUNT(sg.id) as graded_items,
                   SUM(CASE WHEN sg.percentage >= 90 THEN 1 ELSE 0 END) as a_count,
                   SUM(CASE WHEN sg.percentage >= 80 AND sg.percentage < 90 THEN 1 ELSE 0 END) as b_count,
                   SUM(CASE WHEN sg.percentage < 70 THEN 1 ELSE 0 END) as below_c_count,
                   MAX(sg.graded_at) as last_graded
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN users u ON c.teacher_id = u.id
            LEFT JOIN student_grades sg ON c.id = sg.class_id AND sg.student_id = ce.student_id AND sg.is_dropped = 0
            WHERE ce.student_id = ?
            GROUP BY c.id
            ORDER BY c.name
        ");
        $stmt->execute([$student_id]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add letter grades and GPA points
        foreach ($classes as &$class) {
            $class['letter_grade'] = percentageToLetter($class['current_grade']);
            $class['gpa_points'] = letterToGPA($class['letter_grade']);
        }

        echo json_encode(['success' => true, 'classes' => $classes]);
    } catch (Exception $e) {
        error_log("Get Grade Overview Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch grade overview']);
    }
}

/**
 * Get detailed grades for a specific class
 */
function getClassGrades()
{
    global $pdo, $student_id;

    $class_id = $_GET['class_id'] ?? 0;

    try {
        // Get class info
        $stmt = $pdo->prepare("
            SELECT c.*, u.first_name as teacher_first, u.last_name as teacher_last
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get grade categories
        $stmt = $pdo->prepare("SELECT * FROM grade_categories WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all grades
        $stmt = $pdo->prepare("
            SELECT sg.*, gc.name as category_name, a.title as assignment_title,
                   u.first_name as grader_first, u.last_name as grader_last
            FROM student_grades sg
            LEFT JOIN grade_categories gc ON sg.category_id = gc.id
            LEFT JOIN assignments a ON sg.assignment_id = a.id
            LEFT JOIN users u ON sg.graded_by = u.id
            WHERE sg.student_id = ? AND sg.class_id = ?
            ORDER BY sg.graded_at DESC
        ");
        $stmt->execute([$student_id, $class_id]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate weighted average
        $total_weight = 0;
        $weighted_sum = 0;

        foreach ($grades as $grade) {
            if (!$grade['is_dropped']) {
                $weight = $grade['weight'] ?? 1.0;
                $weighted_sum += $grade['percentage'] * $weight;
                $total_weight += $weight;
            }
        }

        $weighted_average = $total_weight > 0 ? $weighted_sum / $total_weight : 0;

        echo json_encode([
            'success' => true,
            'class' => $class,
            'categories' => $categories,
            'grades' => $grades,
            'weighted_average' => round($weighted_average, 2),
            'letter_grade' => percentageToLetter($weighted_average)
        ]);
    } catch (Exception $e) {
        error_log("Get Class Grades Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch class grades']);
    }
}

/**
 * Get detailed grade information
 */
function getGradeDetails()
{
    global $pdo, $student_id;

    $grade_id = $_GET['grade_id'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            SELECT sg.*, c.name as class_name, gc.name as category_name,
                   a.title as assignment_title, a.description as assignment_description,
                   u.first_name as grader_first, u.last_name as grader_last
            FROM student_grades sg
            JOIN classes c ON sg.class_id = c.id
            LEFT JOIN grade_categories gc ON sg.category_id = gc.id
            LEFT JOIN assignments a ON sg.assignment_id = a.id
            LEFT JOIN users u ON sg.graded_by = u.id
            WHERE sg.id = ? AND sg.student_id = ?
        ");
        $stmt->execute([$grade_id, $student_id]);
        $grade = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$grade) {
            echo json_encode(['success' => false, 'error' => 'Grade not found']);
            return;
        }

        echo json_encode(['success' => true, 'grade' => $grade]);
    } catch (Exception $e) {
        error_log("Get Grade Details Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch grade details']);
    }
}

/**
 * Get grade analytics
 */
function getGradeAnalytics()
{
    global $pdo, $student_id;

    $period = $_GET['period'] ?? 'semester'; // week, month, semester, year

    try {
        $date_filter = match ($period) {
            'week' => "DATE(sg.graded_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "DATE(sg.graded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'semester' => "DATE(sg.graded_at) >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)",
            'year' => "DATE(sg.graded_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)",
            default => "DATE(sg.graded_at) >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)"
        };

        // Overall statistics
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_grades,
                AVG(percentage) as average_grade,
                MAX(percentage) as highest_grade,
                MIN(percentage) as lowest_grade,
                STDDEV(percentage) as std_deviation,
                SUM(CASE WHEN percentage >= 90 THEN 1 ELSE 0 END) as a_count,
                SUM(CASE WHEN percentage >= 80 AND percentage < 90 THEN 1 ELSE 0 END) as b_count,
                SUM(CASE WHEN percentage >= 70 AND percentage < 80 THEN 1 ELSE 0 END) as c_count,
                SUM(CASE WHEN percentage >= 60 AND percentage < 70 THEN 1 ELSE 0 END) as d_count,
                SUM(CASE WHEN percentage < 60 THEN 1 ELSE 0 END) as f_count
            FROM student_grades sg
            WHERE sg.student_id = ? AND $date_filter AND sg.is_dropped = 0
        ");
        $stmt->execute([$student_id]);
        $overall = $stmt->fetch(PDO::FETCH_ASSOC);

        // Grade distribution by type
        $stmt = $pdo->prepare("
            SELECT grade_type, COUNT(*) as count, AVG(percentage) as avg_grade
            FROM student_grades
            WHERE student_id = ? AND $date_filter AND is_dropped = 0
            GROUP BY grade_type
        ");
        $stmt->execute([$student_id]);
        $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Trend over time
        $stmt = $pdo->prepare("
            SELECT DATE(graded_at) as date, AVG(percentage) as avg_grade, COUNT(*) as count
            FROM student_grades
            WHERE student_id = ? AND $date_filter AND is_dropped = 0
            GROUP BY DATE(graded_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$student_id]);
        $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Class-wise breakdown
        $stmt = $pdo->prepare("
            SELECT c.name as class_name, c.class_code,
                   COUNT(sg.id) as grade_count,
                   AVG(sg.percentage) as avg_grade,
                   MAX(sg.percentage) as highest,
                   MIN(sg.percentage) as lowest
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN student_grades sg ON c.id = sg.class_id AND sg.student_id = ce.student_id
                AND $date_filter AND sg.is_dropped = 0
            WHERE ce.student_id = ?
            GROUP BY c.id
            ORDER BY avg_grade DESC
        ");
        $stmt->execute([$student_id]);
        $by_class = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'analytics' => [
                'overall' => $overall,
                'by_type' => $by_type,
                'trend' => $trend,
                'by_class' => $by_class,
                'period' => $period
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get Grade Analytics Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch analytics']);
    }
}

/**
 * Get grade trends
 */
function getGradeTrends()
{
    global $pdo, $student_id;

    $class_id = $_GET['class_id'] ?? null;

    try {
        $sql = "
            SELECT * FROM grade_trends
            WHERE student_id = ?
        ";

        $params = [$student_id];

        if ($class_id) {
            $sql .= " AND class_id = ?";
            $params[] = $class_id;
        }

        $sql .= " ORDER BY period_end DESC LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'trends' => $trends]);
    } catch (Exception $e) {
        error_log("Get Grade Trends Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch trends']);
    }
}

/**
 * Get grade comparison (student vs class average)
 */
function getGradeComparison()
{
    global $pdo, $student_id;

    $class_id = $_GET['class_id'] ?? 0;

    try {
        // Student's grades
        $stmt = $pdo->prepare("
            SELECT AVG(percentage) as student_avg
            FROM student_grades
            WHERE student_id = ? AND class_id = ? AND is_dropped = 0
        ");
        $stmt->execute([$student_id, $class_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Class average (anonymized)
        $stmt = $pdo->prepare("
            SELECT AVG(percentage) as class_avg,
                   MAX(percentage) as class_high,
                   MIN(percentage) as class_low,
                   STDDEV(percentage) as class_stddev,
                   COUNT(DISTINCT student_id) as student_count
            FROM student_grades
            WHERE class_id = ? AND is_dropped = 0
        ");
        $stmt->execute([$class_id]);
        $class_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Percentile calculation
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT student_id) as below_count
            FROM student_grades sg1
            WHERE sg1.class_id = ?
            AND (SELECT AVG(percentage) FROM student_grades sg2 WHERE sg2.student_id = sg1.student_id AND sg2.class_id = ?)
                < (SELECT AVG(percentage) FROM student_grades sg3 WHERE sg3.student_id = ? AND sg3.class_id = ?)
        ");
        $stmt->execute([$class_id, $class_id, $student_id, $class_id]);
        $rank = $stmt->fetch(PDO::FETCH_ASSOC);

        $percentile = $class_stats['student_count'] > 0
            ? round(($rank['below_count'] / $class_stats['student_count']) * 100, 1)
            : 0;

        echo json_encode([
            'success' => true,
            'comparison' => [
                'student_average' => round($student['student_avg'], 2),
                'class_average' => round($class_stats['class_avg'], 2),
                'class_high' => round($class_stats['class_high'], 2),
                'class_low' => round($class_stats['class_low'], 2),
                'percentile' => $percentile,
                'student_count' => $class_stats['student_count']
            ]
        ]);
    } catch (Exception $e) {
        error_log("Get Grade Comparison Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch comparison']);
    }
}

/**
 * Set grade goal
 */
function setGradeGoal()
{
    global $pdo, $student_id;

    $class_id = $_POST['class_id'] ?? 0;
    $target_grade = $_POST['target_grade'] ?? 0;
    $target_letter = $_POST['target_letter'] ?? null;
    $deadline = $_POST['deadline'] ?? null;
    $notes = trim($_POST['notes'] ?? '');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO student_grade_goals (student_id, class_id, target_grade, target_letter, deadline, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                target_grade = VALUES(target_grade),
                target_letter = VALUES(target_letter),
                deadline = VALUES(deadline),
                notes = VALUES(notes),
                achieved = 0
        ");
        $stmt->execute([$student_id, $class_id, $target_grade, $target_letter, $deadline, $notes]);

        echo json_encode(['success' => true, 'message' => 'Grade goal set successfully']);
    } catch (Exception $e) {
        error_log("Set Grade Goal Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to set grade goal']);
    }
}

/**
 * Get grade goals
 */
function getGradeGoals()
{
    global $pdo, $student_id;

    try {
        $stmt = $pdo->prepare("
            SELECT gg.*, c.name as class_name,
                   (SELECT AVG(percentage) FROM student_grades WHERE student_id = gg.student_id AND class_id = gg.class_id AND is_dropped = 0) as current_grade
            FROM student_grade_goals gg
            JOIN classes c ON gg.class_id = c.id
            WHERE gg.student_id = ?
            ORDER BY gg.deadline ASC
        ");
        $stmt->execute([$student_id]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if goals achieved
        foreach ($goals as &$goal) {
            if (!$goal['achieved'] && $goal['current_grade'] >= $goal['target_grade']) {
                // Update goal as achieved
                $stmt = $pdo->prepare("UPDATE student_grade_goals SET achieved = 1, achieved_at = CURDATE() WHERE id = ?");
                $stmt->execute([$goal['id']]);
                $goal['achieved'] = 1;
                $goal['achieved_at'] = date('Y-m-d');
            }
        }

        echo json_encode(['success' => true, 'goals' => $goals]);
    } catch (Exception $e) {
        error_log("Get Grade Goals Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch grade goals']);
    }
}

/**
 * Get AI-powered grade recommendations
 */
function getGradeRecommendations()
{
    global $pdo, $student_id;

    try {
        $recommendations = [];

        // Check for classes below 70%
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, AVG(sg.percentage) as avg_grade
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN student_grades sg ON c.id = sg.class_id AND sg.student_id = ce.student_id AND sg.is_dropped = 0
            WHERE ce.student_id = ?
            GROUP BY c.id
            HAVING avg_grade < 70
        ");
        $stmt->execute([$student_id]);
        $struggling = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($struggling as $class) {
            $recommendations[] = [
                'type' => 'alert',
                'priority' => 'high',
                'title' => "Focus on {$class['name']}",
                'message' => "Your current average is " . round($class['avg_grade'], 1) . "%. Consider scheduling extra study time or meeting with your teacher.",
                'action' => 'View Class Grades',
                'action_link' => "/student/grades.php?class_id={$class['id']}"
            ];
        }

        // Check for improving trends
        $stmt = $pdo->prepare("
            SELECT class_id, trend FROM grade_trends
            WHERE student_id = ? AND trend = 'improving'
            ORDER BY period_end DESC LIMIT 1
        ");
        $stmt->execute([$student_id]);
        if ($stmt->fetch()) {
            $recommendations[] = [
                'type' => 'positive',
                'priority' => 'low',
                'title' => 'Keep Up the Good Work!',
                'message' => 'Your grades are showing an improving trend. Continue your current study habits.',
                'action' => null
            ];
        }

        // Suggest setting goals
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as goal_count FROM student_grade_goals WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $goal_result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($goal_result['goal_count'] == 0) {
            $recommendations[] = [
                'type' => 'suggestion',
                'priority' => 'medium',
                'title' => 'Set Grade Goals',
                'message' => 'Setting specific grade goals can help you stay motivated and focused.',
                'action' => 'Set Goals',
                'action_link' => '/student/grades.php?tab=goals'
            ];
        }

        echo json_encode(['success' => true, 'recommendations' => $recommendations]);
    } catch (Exception $e) {
        error_log("Get Recommendations Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to generate recommendations']);
    }
}

/**
 * Calculate GPA
 */
function calculateGPA()
{
    global $pdo, $student_id;

    try {
        $stmt = $pdo->prepare("
            SELECT c.credits, AVG(sg.percentage) as class_average
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN student_grades sg ON c.id = sg.class_id AND sg.student_id = ce.student_id AND sg.is_dropped = 0
            WHERE ce.student_id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$student_id]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_points = 0;
        $total_credits = 0;

        foreach ($classes as $class) {
            if ($class['class_average'] !== null) {
                $letter = percentageToLetter($class['class_average']);
                $gpa_points = letterToGPA($letter);
                $credits = $class['credits'] ?? 3;

                $total_points += $gpa_points * $credits;
                $total_credits += $credits;
            }
        }

        $gpa = $total_credits > 0 ? $total_points / $total_credits : 0;

        echo json_encode([
            'success' => true,
            'gpa' => round($gpa, 2),
            'total_credits' => $total_credits,
            'class_count' => count($classes)
        ]);
    } catch (Exception $e) {
        error_log("Calculate GPA Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to calculate GPA']);
    }
}

/**
 * Export transcript (placeholder)
 */
function exportTranscript()
{
    // Would generate PDF transcript
    echo json_encode(['success' => true, 'message' => 'Transcript export feature coming soon']);
}

/**
 * Convert percentage to letter grade
 */
function percentageToLetter($percentage)
{
    if ($percentage === null) return 'N/A';
    if ($percentage >= 93) return 'A';
    if ($percentage >= 90) return 'A-';
    if ($percentage >= 87) return 'B+';
    if ($percentage >= 83) return 'B';
    if ($percentage >= 80) return 'B-';
    if ($percentage >= 77) return 'C+';
    if ($percentage >= 73) return 'C';
    if ($percentage >= 70) return 'C-';
    if ($percentage >= 67) return 'D+';
    if ($percentage >= 63) return 'D';
    if ($percentage >= 60) return 'D-';
    return 'F';
}

/**
 * Convert letter grade to GPA points
 */
function letterToGPA($letter)
{
    $gpa_map = [
        'A' => 4.0,
        'A-' => 3.7,
        'B+' => 3.3,
        'B' => 3.0,
        'B-' => 2.7,
        'C+' => 2.3,
        'C' => 2.0,
        'C-' => 1.7,
        'D+' => 1.3,
        'D' => 1.0,
        'D-' => 0.7,
        'F' => 0.0
    ];
    return $gpa_map[$letter] ?? 0.0;
}
