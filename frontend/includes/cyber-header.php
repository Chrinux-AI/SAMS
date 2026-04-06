<?php
/**
 * Cyber Header - Provides HTML document structure for pages using the bridge pattern
 * Outputs <!DOCTYPE html>, <head> with all required CSS/JS, and opens <body>
 * Included via ../includes/cyber-header.php from subdirectory pages
 */
$_base = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/favicon-loader.php'; ?>
    <script src="<?php echo $_base; ?>assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'SAMS'); ?> - <?php echo defined('APP_NAME') ? APP_NAME : 'SAMS'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="<?php echo $_base; ?>assets/css/cyberpunk-ui.css" rel="stylesheet">
    <script src="<?php echo $_base; ?>assets/js/main.js" defer></script>
</head>
<body>
