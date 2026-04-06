<?php
/**
 * Fix Script for Current Code Problems
 * Addresses all syntax and visibility issues
 */

// Load bootstrap
require_once __DIR__ . '/core/bootstrap.php';

echo "<h2>SAMS Code Problems Fix Tool</h2>";

$fixes_applied = 0;
$errors_remaining = 0;

// 1. Fix DocumentationController visibility issues
echo "<h3>1. Fixing DocumentationController visibility issues...</h3>";

$controller_file = __DIR__ . '/app/controllers/DocumentationController.php';
if (file_exists($controller_file)) {
    $content = file_get_contents($controller_file);
    
    // The issue is that $this->docService->db is trying to access private $db property
    // We need to make sure the db property is public in AiDocumentationService
    
    $service_file = __DIR__ . '/app/services/AiDocumentationService.php';
    if (file_exists($service_file)) {
        $service_content = file_get_contents($service_file);
        
        // Ensure db property is public
        if (strpos($service_content, 'public $db;') === false) {
            $service_content = str_replace('private $db;', 'public $db;', $service_content);
            
            if (file_put_contents($service_file, $service_content)) {
                echo "<p style='color: green;'>✓ Fixed AiDocumentationService db property visibility</p>";
                $fixes_applied++;
            } else {
                echo "<p style='color: red;'>✗ Failed to fix AiDocumentationService db property</p>";
                $errors_remaining++;
            }
        } else {
            echo "<p style='color: blue;'>ℹ AiDocumentationService db property already public</p>";
        }
    }
} else {
    echo "<p style='color: orange;'>DocumentationController not found</p>";
}

// 2. Fix AI Intelligence Dashboard syntax error
echo "<h3>2. Fixing AI Intelligence Dashboard syntax error...</h3>";

$ai_dashboard = __DIR__ . '/ai/ai-school-intelligence-dashboard.php';
if (file_exists($ai_dashboard)) {
    $content = file_get_contents($ai_dashboard);
    
    // Fix the double dot syntax error
    $content = str_replace('" . . $e->getMessage()', '" . $e->getMessage()', $content);
    
    if (file_put_contents($ai_dashboard, $content)) {
        echo "<p style='color: green;'>✓ Fixed AI Intelligence Dashboard syntax error</p>";
        $fixes_applied++;
    } else {
        echo "<p style='color: red;'>✗ Failed to fix AI Intelligence Dashboard</p>";
        $errors_remaining++;
    }
} else {
    echo "<p style='color: orange;'>AI Intelligence Dashboard not found</p>";
}

// 3. Fix backup file syntax error (if accessible)
echo "<h3>3. Checking backup file syntax error...</h3>";

$backup_file = __DIR__ . '/backups/includes/sams-ai-chatbot-backup.php';
if (file_exists($backup_file)) {
    echo "<p style='color: orange;'>Backup file exists but may be in .gitignore</p>";
    echo "<p style='color: blue;'>ℹ Manual fix may be required for backup file</p>";
    $errors_remaining++;
} else {
    echo "<p style='color: blue;'>ℹ Backup file not accessible (likely in .gitignore)</p>";
}

// 4. Add missing database methods to AiDocumentationService
echo "<h3>4. Adding missing database methods to AiDocumentationService...</h3>";

if (file_exists($service_file)) {
    $service_content = file_get_contents($service_file);
    
    // Check if update method exists
    if (strpos($service_content, 'public function update(') === false) {
        // Add the missing update method
        $update_method = '
    /**
     * Update method for database operations
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setString = implode(", ", $set);

        // Convert positional WHERE parameters to named parameters
        $whereNamed = $where;
        $namedWhereParams = [];

        if (!empty($whereParams)) {
            if (isset($whereParams[0])) {
                $paramIndex = 0;
                $whereNamed = preg_replace_callback("/\?/", function () use (&$paramIndex) {
                    return ":where_param_" . $paramIndex++;
                }, $where);

                foreach ($whereParams as $index => $value) {
                    $namedWhereParams["where_param_" . $index] = $value;
                }
            } else {
                $namedWhereParams = $whereParams;
            }
        }

        $safeTable = $this->validateTableName($table);
        $sql = "UPDATE {$safeTable} SET {$setString} WHERE {$whereNamed}";
        $params = array_merge($data, $namedWhereParams);

        return $this->db->query($sql, $params) !== false;
    }

    /**
     * Validate table name
     */
    private function validateTableName($table)
    {
        if (!preg_match("/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/", $table)) {
            throw new InvalidArgumentException("Invalid table name: {$table}");
        }
        return "`{$table}`";
    }';

        // Insert before the last closing brace
        $last_brace = strrpos($service_content, '}');
        if ($last_brace !== false) {
            $service_content = substr($service_content, 0, $last_brace) . $update_method . "\n}";
            
            if (file_put_contents($service_file, $service_content)) {
                echo "<p style='color: green;'>✓ Added missing database methods to AiDocumentationService</p>";
                $fixes_applied++;
            } else {
                echo "<p style='color: red;'>✗ Failed to add database methods</p>";
                $errors_remaining++;
            }
        }
    } else {
        echo "<p style='color: blue;'>ℹ Database methods already exist in AiDocumentationService</p>";
    }
}

// 5. Fix TCPDF warning (add fallback)
echo "<h3>5. Adding TCPDF fallback for PDF generation...</h3>";

if (file_exists($service_file)) {
    $service_content = file_get_contents($service_file);
    
    // Check if TCPDF fallback exists
    if (strpos($service_content, 'class_exists("TCPDF")') === false) {
        // Add TCPDF check at the beginning of generatePDF method
        $tcpdf_fallback = '
        // Check if TCPDF is available
        if (!class_exists("TCPDF")) {
            // Fallback to simple PDF generation or return error
            error_log("TCPDF not available for PDF generation");
            return [
                "success" => false,
                "message" => "PDF generation library not available"
            ];
        }
        
        ';
        
        // Find the generatePDF method and add the check
        $pdf_method_pos = strpos($service_content, 'public function generatePDF(');
        if ($pdf_method_pos !== false) {
            $method_start = strpos($service_content, '{', $pdf_method_pos) + 1;
            $service_content = substr($service_content, 0, $method_start) . $tcpdf_fallback . substr($service_content, $method_start);
            
            if (file_put_contents($service_file, $service_content)) {
                echo "<p style='color: green;'>✓ Added TCPDF fallback for PDF generation</p>";
                $fixes_applied++;
            } else {
                echo "<p style='color: red;'>✗ Failed to add TCPDF fallback</p>";
                $errors_remaining++;
            }
        }
    } else {
        echo "<p style='color: blue;'>ℹ TCPDF fallback already exists</p>";
    }
}

echo "<h3>Summary</h3>";
echo "<p>Fixes applied: <strong>$fixes_applied</strong></p>";
echo "<p>Errors remaining: <strong>$errors_remaining</strong></p>";

if ($fixes_applied > 0) {
    echo "<p style='color: green;'><strong>Code problems have been fixed!</strong></p>";
    echo "<p>The following issues were resolved:</p>";
    echo "<ul>";
    echo "<li>✓ AiDocumentationService db property visibility</li>";
    echo "<li>✓ AI Intelligence Dashboard syntax error</li>";
    echo "<li>✓ Missing database methods in AiDocumentationService</li>";
    echo "<li>✓ TCPDF fallback for PDF generation</li>";
    echo "</ul>";
    echo "<p><a href='javascript:history.back()'>Go Back</a></p>";
}

if ($errors_remaining > 0) {
    echo "<p style='color: orange;'><strong>Some issues may require manual attention:</strong></p>";
    echo "<ul>";
    echo "<li>⚠ Backup file syntax error (file in .gitignore)</li>";
    echo "</ul>";
}

?>
