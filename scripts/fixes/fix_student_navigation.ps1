# Fix Student Pages Navigation Consistency

# This script will update all student pages to use unified sidebar navigation
# and ensure consistent theming

$studentPages = @(
    "analytics.php",
    "assignments.php", 
    "attendance-enhanced.php",
    "attendance.php",
    "checkin-enhanced.php",
    "checkin.php",
    "checkin_old_backup.php",
    "class-registration.php",
    "communication.php",
    "dashboard-enhanced.php",
    "emergency-alerts.php",
    "events.php",
    "grades.php",
    "id-card.php",
    "lms-portal.php",
    "notifications.php",
    "profile.php",
    "reports.php",
    "schedule.php",
    "settings.php",
    "study-groups.php"
)

foreach ($page in $studentPages) {
    $filePath = "c:/xampp/htdocs/attendance/student/$page"
    if (Test-Path $filePath) {
        Write-Host "Processing student/$page..."
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
            Write-Host "Updated student/$page"
        }
    }
}

Write-Host "All student pages updated for consistent navigation!"
