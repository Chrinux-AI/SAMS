# Update all pages to use the new favicon system

# This script will add favicon loader to pages that don't use sidebar-nav

$pagesToUpdate = @(
    "index.php",
    "login.php", 
    "register.php",
    "forgot-password.php",
    "reset-password.php",
    "verify-email.php",
    "setup-admin.php",
    "chat.php",
    "messages.php",
    "notices.php",
    "system-overview.php",
    "verify-system.php"
)

foreach ($page in $pagesToUpdate) {
    $filePath = "c:/xampp/htdocs/attendance/$page"
    if (Test-Path $filePath) {
        Write-Host "Processing $page..."
        $content = Get-Content $filePath -Raw
        
        # Check if page already has favicon loader
        if ($content -match 'favicon-loader\.php') {
            Write-Host "$page already has favicon loader"
            continue
        }
        
        # Add favicon loader in head section
        if ($content -match '<head>') {
            $content = $content -replace '<head>', '<head><?php include_once "includes/favicon-loader.php"; ?>'
            Set-Content $filePath $content -NoNewline
            Write-Host "Updated $page"
        } else {
            Write-Host "Could not find head section in $page"
        }
    }
}

Write-Host "All pages updated with new favicon system!"
