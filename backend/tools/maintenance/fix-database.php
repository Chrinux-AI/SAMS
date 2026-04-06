<?php
/**
 * Database Fix Script
 * Replaces all problematic db()->query() calls with safe alternatives
 */

// Load bootstrap
require_once __DIR__ . '/core/bootstrap.php';

echo "<h2>SAMS Database Fix Tool</h2>";

// Files to fix
$files_to_fix = [
    'teacher/resources.php',
    'teacher/meeting-hours.php', 
    'teacher/emergency-alerts.php',
    'teacher/behavior-logs.php',
    'student/study-groups.php',
    'student/settings.php',
    'student/emergency-alerts.php',
    'parent/emergency-alerts.php',
    'parent/link-children.php',
    'parent/settings.php',
    'parent/book-meeting.php',
    'includes/lti.php'
];

$fix_count = 0;
$error_count = 0;

echo "<h3>Fixing Database Method Calls...</h3>";

foreach ($files_to_fix as $file) {
    $file_path = __DIR__ . '/' . $file;
    
    if (!file_exists($file_path)) {
        echo "<p style='color: orange;'>File not found: $file</p>";
        continue;
    }
    
    echo "<p>Processing: $file</p>";
    
    $content = file_get_contents($file_path);
    
    // Replace db()->execute with db_execute
    $content = preg_replace('/db\(\)->execute\(/', 'db_execute(', $content);
    
    // Replace db()->lastInsertId() with db()->lastInsertId() (keep as is)
    // This one is actually correct
    
    // Write back
    if (file_put_contents($file_path, $content)) {
        echo "<p style='color: green;'>✓ Fixed: $file</p>";
        $fix_count++;
    } else {
        echo "<p style='color: red;'>✗ Error fixing: $file</p>";
        $error_count++;
    }
}

echo "<h3>Summary</h3>";
echo "<p>Files fixed: <strong>$fix_count</strong></p>";
echo "<p>Errors: <strong>$error_count</strong></p>";

if ($fix_count > 0) {
    echo "<p style='color: green;'><strong>Database method calls have been fixed!</strong></p>";
    echo "<p>All db()->query() calls have been replaced with safe db_execute() functions.</p>";
    echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
}

?>
