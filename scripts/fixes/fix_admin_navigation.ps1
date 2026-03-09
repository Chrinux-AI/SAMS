# Fix Admin Pages Navigation Consistency

# This script will update all admin pages to use unified sidebar navigation
# instead of cyber-nav for consistent theming across all role pages

$adminPages = @(
    "activity-monitor.php",
    "advanced-admin.php", 
    "analytics.php",
    "announcements-system.php",
    "announcements.php",
    "approve-users.php",
    "attendance.php",
    "attendance_new.php",
    "audit-logs.php",
    "backup-export.php",
    "biometric-scan.php",
    "class-enrollment.php",
    "classes.php",
    "classes_backup.php",
    "classes_old.php",
    "cloud-storage.php",
    "communication.php",
    "communication_old_backup.php",
    "emergency-alerts.php",
    "enhanced-analytics.php",
    "events.php",
    "facilities.php",
    "facilities_old_backup.php",
    "fee-management.php",
    "id-management.php",
    "lms-settings.php",
    "manage-ids.php",
    "messages.php",
    "mobile-api.php",
    "notices.php",
    "overview.php",
    "parents.php",
    "pwa-management.php",
    "realtime-sync.php",
    "registrations.php",
    "reports.php",
    "reports_old_backup.php",
    "reset-system.php",
    "security-logs.php",
    "settings.php",
    "settings_old_backup.php",
    "student-add.php",
    "student-edit.php",
    "student-view.php",
    "students-old-backup.php",
    "students.php",
    "system-health.php",
    "system-management.php",
    "system-monitor.php",
    "teachers.php",
    "teachers_old.php",
    "timetable.php",
    "timetable_old_backup.php",
    "unapproved-users.php",
    "users.php",
    "users_old_backup.php"
)

foreach ($page in $adminPages) {
    $filePath = "c:/xampp/htdocs/attendance/admin/$page"
    if (Test-Path $filePath) {
        Write-Host "Processing $page..."
        $content = Get-Content $filePath -Raw
        
        # Replace cyber-nav with sidebar-nav
        $content = $content -replace "include '../includes/cyber-nav.php';", "include '../includes/sidebar-nav.php';"
        
        # Replace cyberpunk CSS with professional CSS
        $content = $content -replace "cyberpunk-ui.css", "professional-ui.css"
        
        # Replace cyber body class with professional layout
        $content = $content -replace '<body class="cyber-bg">', '<body>'
        $content = $content -replace '<div class="starfield"></div>', ''
        $content = $content -replace '<div class="cyber-grid"></div>', ''
        $content = $content -replace '<div class="cyber-layout">', '<div class="app-layout">'
        
        # Replace cyber main with professional main
        $content = $content -replace '<main class="cyber-main">', '<main class="main-content">'
        
        Set-Content $filePath $content -NoNewline
        Write-Host "Updated $page"
    }
}

Write-Host "All admin pages updated for consistent navigation!"
