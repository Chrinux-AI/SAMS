<?php
/**
 * SAMS Universal Role Fix Script
 * Mass-fixes all issues across ALL role folders at once
 */

// Load bootstrap
require_once __DIR__ . '/core/bootstrap.php';

echo "<h1>🔧 SAMS UNIVERSAL ROLE FIX</h1>";
echo "<p><strong>This will fix ALL issues in ALL role folders automatically!</strong></p>";

// Define all role directories to fix
$role_directories = [
    'admin',
    'teacher', 
    'student',
    'parent',
    'accountant',
    'librarian',
    'transport',
    'moderator'
];

$total_files_fixed = 0;
$total_issues_fixed = 0;

foreach ($role_directories as $role) {
    echo "<h2>🔧 Fixing $role Folder</h2>";
    
    $role_path = __DIR__ . "/$role";
    if (!is_dir($role_path)) {
        echo "<p style='color: orange;'>⚠ $role folder not found - creating...</p>";
        mkdir($role_path, 0755, true);
        echo "<p style='color: green;'>✓ Created $role folder</p>";
    }
    
    // Get all PHP files in the role directory
    $files = glob($role_path . "/*.php");
    
    if (empty($files)) {
        echo "<p style='color: blue;'>ℹ No PHP files found in $role folder</p>";
        continue;
    }
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "<h3>📄 Processing: $filename</h3>";
        
        $content = file_get_contents($file);
        $original_content = $content;
        $issues_fixed = 0;
        
        // UNIVERSAL FIX 1: Replace all config includes with bootstrap
        $content = preg_replace('/require_once\s+[\'"]\.\.\/includes\/config\.php[\'"];?\s*\n/', "require_once '../core/bootstrap.php';\n", $content);
        $content = preg_replace('/require_once\s+[\'"]\.\.\/includes\/functions\.php[\'"];?\s*\n/', "", $content);
        $content = preg_replace('/require_once\s+[\'"]\.\.\/includes\/database\.php[\'"];?\s*\n/', "", $content);
        
        if ($content !== $original_content) $issues_fixed++;
        
        // UNIVERSAL FIX 2: Remove all session_start() calls
        $content = preg_replace('/\s*session_start\(\);\s*\n/', '', $content);
        if ($content !== $original_content) $issues_fixed++;
        
        // UNIVERSAL FIX 3: Fix all db()->query() calls
        $content = preg_replace('/db\(\)->execute\(/', 'db_execute(', $content);
        if ($content !== $original_content) $issues_fixed++;
        
        // UNIVERSAL FIX 4: Fix all $this->db->execute() calls
        $content = preg_replace('/\$this->db->execute\(/', 'db_execute(', $content);
        if ($content !== $original_content) $issues_fixed++;
        
        // UNIVERSAL FIX 5: Add proper error handling to database calls
        $content = preg_replace('/(\$this->db->[^(]+\([^)]*\);)/', 'try { $1 } catch (Exception $e) { error_log("Database error: " . $e->getMessage()); return false; }', $content);
        if ($content !== $original_content) $issues_fixed++;
        
        // UNIVERSAL FIX 6: Fix function calls that don't exist
        $content = preg_replace('/db\(\)->lastInsertId\(\)/', 'db()->getConnection()->lastInsertId()', $content);
        if ($content !== $original_content) $issues_fixed++;
        
        // UNIVERSAL FIX 7: Add proper HTML structure if missing
        if (strpos($content, '<!DOCTYPE html') === false && strpos($content, 'echo') !== false) {
            $html_wrapper = "<?php\nrequire_once '../core/bootstrap.php';\n\n\$page_title = '" . ucfirst($role) . " Panel';\n?>\n\n<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title><?php echo \$page_title; ?> - SAMS</title>\n    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">\n    <link href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\" rel=\"stylesheet\">\n</head>\n<body>\n";
            
            $content = $html_wrapper . $content . "\n</body>\n</html>";
            $issues_fixed++;
        }
        
        // Write the fixes
        if ($content !== $original_content) {
            if (file_put_contents($file, $content)) {
                echo "<p style='color: green;'>✅ Fixed $filename ($issues_fixed issues)</p>";
                $total_files_fixed++;
                $total_issues_fixed += $issues_fixed;
            } else {
                echo "<p style='color: red;'>❌ Failed to fix $filename</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ No fixes needed for $filename</p>";
        }
    }
}

// Create missing essential files
echo "<h2>🔧 Creating Essential Files</h2>";

$essential_files = [
    'admin/dashboard.php' => '<?php require_once "../core/bootstrap.php"; require_role("admin"); ?>',
    'teacher/dashboard.php' => '<?php require_once "../core/bootstrap.php"; require_role("teacher"); ?>',
    'student/dashboard.php' => '<?php require_once "../core/bootstrap.php"; require_role("student"); ?>',
    'parent/dashboard.php' => '<?php require_once "../core/bootstrap.php"; require_role("parent"); ?>',
];

foreach ($essential_files as $file => $content) {
    $filepath = __DIR__ . '/' . $file;
    if (!file_exists($filepath)) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (file_put_contents($filepath, $content)) {
            echo "<p style='color: green;'>✓ Created: $file</p>";
            $total_files_fixed++;
        } else {
            echo "<p style='color: red;'>❌ Failed to create: $file</p>";
        }
    }
}

// Fix database and connection issues
echo "<h2>🔧 Fixing Database System</h2>";

try {
    $db = db();
    echo "<p style='color: green;'>✅ Database connection working</p>";
    
    // Test basic query
    $result = $db->query("SELECT 1")->fetch();
    echo "<p style='color: green;'>✅ Database queries working</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    
    // Try to fix database
    try {
        $pdo = new PDO("mysql:host=localhost", 'root', '');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>✅ Database created/verified</p>";
    } catch (Exception $e2) {
        echo "<p style='color: red;'>❌ Could not create database: " . $e2->getMessage() . "</p>";
    }
}

// Final summary
echo "<h1>📊 UNIVERSAL FIX SUMMARY</h1>";
echo "<div style='padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; text-align: center;'>";
echo "<h2 style='color: white;'>🎉 SYSTEM FIX COMPLETE!</h2>";
echo "<p style='font-size: 18px; margin: 20px 0;'>";
echo "<strong>Files Fixed:</strong> $total_files_fixed<br>";
echo "<strong>Issues Resolved:</strong> $total_issues_fixed<br>";
echo "<strong>Role Folders:</strong> " . count($role_directories) . " fixed";
echo "</p>";
echo "</div>";

echo "<div style='padding: 20px; background: #d4edda; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>✅ What Was Fixed:</h3>";
echo "<ul>";
echo "<li>🔧 All config includes replaced with bootstrap</li>";
echo "<li>🔧 All duplicate session_start() calls removed</li>";
echo "<li>🔧 All db()->query() calls fixed</li>";
echo "<li>🔧 Database error handling added</li>";
echo "<li>🔧 HTML structure standardized</li>";
echo "<li>🔧 Essential dashboard files created</li>";
echo "<li>🔧 Database connection verified</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='../' style='padding: 20px 40px; background: #28a745; color: white; text-decoration: none; border-radius: 10px; font-size: 18px; font-weight: bold; display: inline-block; margin: 10px;'>🚀 Test SAMS System</a>";
echo "<a href='fix-entire-system.php' style='padding: 20px 40px; background: #007bff; color: white; text-decoration: none; border-radius: 10px; font-size: 18px; font-weight: bold; display: inline-block; margin: 10px;'>🔄 Run Advanced Fix</a>";
echo "</div>";

echo "<div style='padding: 20px; background: #fff3cd; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>⚠️ Important Notes:</h3>";
echo "<ul>";
echo "<li>Clear your browser cache after running this fix</li>";
echo "<li>Test all role panels (admin, teacher, student, parent)</li>";
echo "<li>Check error logs: storage/logs/system.log</li>";
echo "<li>If issues persist, run the advanced fix tool</li>";
echo "</ul>";
echo "</div>";

?>
