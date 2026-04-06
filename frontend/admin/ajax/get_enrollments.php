<?php

/**
 * Get Class Enrollments (AJAX)
 */

session_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database.php';

if (!is_logged_in() || !has_role('admin')) {
    die('Access denied');
}

$class_id = (int)$_GET['class_id'];

// Get class info
$class = db()->fetch("SELECT * FROM classes WHERE id = ?", [$class_id]);
if (!$class) {
    die('<p class="alert alert-error">Class not found</p>');
}

// Get enrollments
$enrollments = db()->fetchAll("
    SELECT ce.*, s.admission_number AS student_id, u.first_name, u.last_name, u.email, c.grade_level
    FROM class_enrollments ce
    JOIN students s ON ce.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE ce.class_id = ?
    ORDER BY u.last_name, u.first_name
", [$class_id]);

?>
<div style="margin-bottom: 20px;">
    <h3><?php echo htmlspecialchars($class['name']); ?> (<?php echo htmlspecialchars($class['class_code']); ?>)</h3>
    <p>Total Enrolled: <strong><?php echo count($enrollments); ?></strong> students</p>
</div>

<?php if (empty($enrollments)): ?>
    <p class="alert alert-info">
        <i class="fas fa-info-circle"></i> No students enrolled in this class yet.
    </p>
<?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Grade</th>
                    <th>Email</th>
                    <th>Enrollment Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $enrollment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($enrollment['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></td>
                        <td><span class="badge badge-info">Grade <?php echo $enrollment['grade_level']; ?></span></td>
                        <td><?php echo htmlspecialchars($enrollment['email'] ?: '-'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                        <td>
                            <form method="POST" action="classes.php" style="display: inline;"
                                onsubmit="return confirm('Remove this student from the class?');">
                                <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                <button type="submit" name="unenroll_student" class="btn-icon btn-delete">
                                    <i class="fas fa-user-minus"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>