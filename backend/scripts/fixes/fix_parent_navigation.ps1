# Fix Parent Pages Navigation Consistency

# This script will update all parent pages to use unified sidebar navigation
# and ensure consistent theming

$parentPages = @(
    "analytics.php",
    "attendance.php",
    "book-meeting.php",
    "children.php",
    "communication.php",
    "emergency-alerts.php",
    "events.php",
    "fees.php",
    "grades.php",
    "link-children.php",
    "lms-overview.php",
    "my-meetings.php",
    "reports.php",
    "settings.php"
)

foreach ($page in $parentPages) {
    $filePath = "c:/xampp/htdocs/attendance/parent/$page"
    if (Test-Path $filePath) {
        Write-Host "Processing parent/$page..."
        $content = Get-Content $filePath -Raw
        
        # Skip redirect files
        if ($content -match 'header.*Location') {
            Write-Host "$page is a redirect file, skipping"
            continue
        }
        
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
            Write-Host "Updated parent/$page"
        }
    }
}

Write-Host "All parent pages updated for consistent navigation!"
