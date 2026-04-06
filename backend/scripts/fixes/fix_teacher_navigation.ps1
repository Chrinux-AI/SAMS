# Fix Teacher Pages Navigation Consistency

# This script will update all teacher pages to use unified sidebar navigation
# and ensure consistent theming

$teacherPages = @(
    "analytics.php",
    "assignments.php", 
    "attendance.php",
    "attendance_backup.php",
    "behavior-logs.php",
    "class-enrollment.php",
    "dashboard_backup.php",
    "emergency-alerts.php",
    "grades.php",
    "lms-sync.php",
    "materials.php",
    "meeting-hours.php",
    "my-classes_backup.php",
    "parent-comms.php",
    "reports.php",
    "resource-library.php",
    "resources.php",
    "settings.php",
    "students.php"
)

foreach ($page in $teacherPages) {
    $filePath = "c:/xampp/htdocs/attendance/teacher/$page"
    if (Test-Path $filePath) {
        Write-Host "Processing teacher/$page..."
        $content = Get-Content $filePath -Raw
        
        # Ensure sidebar-nav is included
        if ($content -match 'sidebar-nav\.php') {
            Write-Host "$page already has unified navigation"
        } else {
            # Replace any other navigation includes
            $content = $content -replace "include '../includes/[^;]*-nav\.php';", "include '../includes/sidebar-nav.php';"
            
            # Ensure professional CSS is used
            $content = $content -replace "cyberpunk-ui.css", "professional-ui.css"
            
            # Fix body classes if needed
            $content = $content -replace '<body class="[^"]*cyber[^"]*">', '<body>'
            $content = $content -replace '<div class="[^"]*cyber[^"]*">', '<div class="app-layout">'
            
            Set-Content $filePath $content -NoNewline
            Write-Host "Updated teacher/$page"
        }
    }
}

Write-Host "All teacher pages updated for consistent navigation!"
