#!/usr/bin/env python3
"""
Fix forum pages by replacing broken header/footer includes with proper HTML structure
"""

import re

def fix_forum_page(filepath, page_title_var='$page_title'):
    """Fix a single forum page"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Replace header includes
    header_replacement = f'''?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <title><?php echo {page_title_var}; ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
    <link href="../assets/css/pwa-styles.css" rel="stylesheet">'''

    # Pattern to match the broken includes
    header_pattern = r"include\s+['\"]\.\.\/includes\/cyber-header\.php['\"];\s*\n\s*include\s+['\"]\.\.\/includes\/cyber-nav\.php['\"];\s*\n\s*\?>"

    if re.search(header_pattern, content):
        content = re.sub(header_pattern, header_replacement, content)

    # Add body structure after </style>
    style_close = r'(</style>)\s*\n\s*(<\?php)'
    body_structure = r'''\1
</head>
<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

<?php include '../includes/student-nav.php'; ?>

\2'''

    content = re.sub(style_close, body_structure, content)

    # Replace footer include with closing tags and PWA scripts
    footer_pattern = r"(\?>\s*\n\s*)include\s+['\"]\.\.\/includes\/cyber-footer\.php['\"];?\s*\n\s*\?>"
    footer_replacement = r'''\1?>

<?php include '../includes/sams-bot.php'; ?>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
</body>
</html>'''

    content = re.sub(footer_pattern, footer_replacement, content)

    # Also try alternative footer pattern (with <?php tag)
    footer_pattern2 = r"<\?php\s+include\s+['\"]\.\.\/includes\/cyber-footer\.php['\"];\s*\?>"
    footer_replacement2 = '''<?php include '../includes/sams-bot.php'; ?>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
</body>
</html>'''

    content = re.sub(footer_pattern2, footer_replacement2, content)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

    return True

# Fix all forum pages
forum_pages = [
    '/opt/lampp/htdocs/attendance/forum/category.php',
    '/opt/lampp/htdocs/attendance/forum/thread.php',
    '/opt/lampp/htdocs/attendance/forum/create-thread.php'
]

print("Fixing remaining forum pages...")
for page in forum_pages:
    print(f"  Processing {page.split('/')[-1]}...")
    try:
        fix_forum_page(page)
        print(f"    ✓ Fixed!")
    except Exception as e:
        print(f"    ✗ Error: {e}")

print("\n✅ All forum pages fixed!")
