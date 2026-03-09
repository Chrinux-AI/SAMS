#!/usr/bin/env python3
"""
Fix remaining pages with UI issues:
1. Forum pages - Add full PWA integration
2. Emergency-alerts pages - Remove duplicate meta tags, fix duplicates
"""

import re
import os

# Define pages to fix
FORUM_PAGES = [
    '/opt/lampp/htdocs/attendance/forum/index.php',
    '/opt/lampp/htdocs/attendance/forum/category.php',
    '/opt/lampp/htdocs/attendance/forum/thread.php',
    '/opt/lampp/htdocs/attendance/forum/create-thread.php'
]

EMERGENCY_PAGES = [
    '/opt/lampp/htdocs/attendance/student/emergency-alerts.php',
    '/opt/lampp/htdocs/attendance/teacher/emergency-alerts.php',
    '/opt/lampp/htdocs/attendance/parent/emergency-alerts.php',
    '/opt/lampp/htdocs/attendance/admin/emergency-alerts.php'
]

def fix_forum_page(filepath):
    """Add PWA integration to forum pages"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # Check if already has PWA integration
    if 'manifest.json' in content:
        print(f"  ✓ {os.path.basename(filepath)} already has PWA integration")
        return False

    # Add PWA meta tags after opening <head> tag
    head_pattern = r'(<head>\s*)'
    pwa_meta = r'''\1
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
'''

    if re.search(r'<head>', content):
        content = re.sub(head_pattern, pwa_meta, content, count=1)

    # Fix body tag to include cyber-bg class
    body_patterns = [
        (r'<body\s*>', '<body class="cyber-bg">'),
        (r'<body\s+class="([^"]*)">', lambda m: f'<body class="{m.group(1)} cyber-bg">' if 'cyber-bg' not in m.group(1) else m.group(0))
    ]

    for pattern, replacement in body_patterns:
        content = re.sub(pattern, replacement, content)

    # Add starfield and cyber-grid after opening body tag
    if '<div class="starfield"></div>' not in content:
        body_open = r'(<body[^>]*>\s*)'
        bg_elements = r'''\1
    <div class="starfield"></div>
    <div class="cyber-grid"></div>
'''
        content = re.sub(body_open, bg_elements, content, count=1)

    # Add PWA scripts before closing </body> tag if not present
    if 'pwa-manager.js' not in content:
        pwa_scripts = '''
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>'''
        content = re.sub(r'</body>', pwa_scripts, content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        return True
    return False

def fix_emergency_alerts_page(filepath):
    """Remove duplicate meta tags and fix background elements"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    changes = []

    # Remove duplicate meta tags (keep first occurrence)
    # Pattern to find duplicate meta charset
    meta_charset_count = len(re.findall(r'<meta\s+charset=', content))
    if meta_charset_count > 1:
        # Remove all but first
        first_found = False
        def replace_charset(match):
            nonlocal first_found
            if not first_found:
                first_found = True
                return match.group(0)
            return ''
        content = re.sub(r'\s*<meta\s+charset="UTF-8">\s*', replace_charset, content)
        changes.append("Removed duplicate charset meta tag")

    # Remove duplicate viewport meta tags
    viewport_count = len(re.findall(r'<meta\s+name="viewport"', content))
    if viewport_count > 1:
        first_found = False
        def replace_viewport(match):
            nonlocal first_found
            if not first_found:
                first_found = True
                return match.group(0)
            return ''
        content = re.sub(r'\s*<meta\s+name="viewport"[^>]+>\s*', replace_viewport, content)
        changes.append("Removed duplicate viewport meta tag")

    # Fix duplicate starfield/cyber-grid elements
    # Remove consecutive duplicates
    starfield_pattern = r'(\s*<div class="starfield"></div>\s*<div class="cyber-grid"></div>\s*)(\1)+'
    if re.search(starfield_pattern, content):
        content = re.sub(starfield_pattern, r'\1', content)
        changes.append("Removed duplicate starfield/cyber-grid divs")

    # Fix title branding - change "Attendance AI" to "SAMS"
    if 'Attendance AI</title>' in content:
        content = content.replace('Attendance AI</title>', 'SAMS</title>')
        changes.append("Fixed branding to SAMS")

    # Ensure cyber-bg class on body
    if '<body>' in content and '<body class="cyber-bg">' not in content:
        content = content.replace('<body>', '<body class="cyber-bg">')
        changes.append("Added cyber-bg class to body")

    # Ensure PWA scripts are present
    if 'pwa-manager.js' not in content:
        if '</body>' in content:
            pwa_scripts = '''
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>'''
            content = re.sub(r'</body>', pwa_scripts, content)
            changes.append("Added PWA scripts")

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"  ✓ {os.path.basename(filepath)}: {', '.join(changes)}")
        return True
    else:
        print(f"  ✓ {os.path.basename(filepath)}: No changes needed")
        return False

def main():
    print("=" * 60)
    print("FIXING REMAINING PAGES")
    print("=" * 60)

    # Fix forum pages
    print("\n1. FORUM PAGES - Adding PWA Integration")
    print("-" * 60)
    forum_fixed = 0
    for page in FORUM_PAGES:
        if os.path.exists(page):
            print(f"Processing: {os.path.basename(page)}")
            if fix_forum_page(page):
                forum_fixed += 1
                print(f"  ✓ Fixed!")
        else:
            print(f"  ⚠ File not found: {page}")

    print(f"\nForum pages fixed: {forum_fixed}/{len(FORUM_PAGES)}")

    # Fix emergency alerts pages
    print("\n2. EMERGENCY ALERTS PAGES - Removing Duplicates")
    print("-" * 60)
    emergency_fixed = 0
    for page in EMERGENCY_PAGES:
        if os.path.exists(page):
            print(f"Processing: {os.path.basename(page)}")
            if fix_emergency_alerts_page(page):
                emergency_fixed += 1
        else:
            print(f"  ⚠ File not found: {page}")

    print(f"\nEmergency alerts pages processed: {emergency_fixed}/{len(EMERGENCY_PAGES)}")

    # Summary
    print("\n" + "=" * 60)
    print("SUMMARY")
    print("=" * 60)
    print(f"Forum pages fixed: {forum_fixed}")
    print(f"Emergency alerts pages processed: {emergency_fixed}")
    print(f"Total pages updated: {forum_fixed + emergency_fixed}")
    print("\n✅ All remaining pages have been fixed!")

if __name__ == '__main__':
    main()
