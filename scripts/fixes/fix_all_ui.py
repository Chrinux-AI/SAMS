#!/usr/bin/env python3
"""
Fix UI Issues Across All SAMS Pages
- Replace cyber-theme.css with cyberpunk-ui.css
- Add PWA integration files
- Add cyber-bg, starfield, and cyber-grid to body
- Add proper meta tags and manifest
- Add PWA scripts at bottom
"""

import os
import re
from pathlib import Path

# Base directory
BASE_DIR = Path('/opt/lampp/htdocs/attendance')

# Files to process (all PHP files except includes and config)
EXCLUDE_PATTERNS = [
    'vendor/', 'includes/', 'config/', 'database/',
    'cache/', 'logs/', 'uploads/', 'backup/'
]

def should_process_file(file_path):
    """Check if file should be processed"""
    path_str = str(file_path)

    # Skip excluded directories
    for pattern in EXCLUDE_PATTERNS:
        if pattern in path_str:
            return False

    # Only process PHP files
    return file_path.suffix == '.php'

def fix_css_links(content):
    """Replace cyber-theme.css with cyberpunk-ui.css and add PWA styles"""
    # Replace cyber-theme.css
    content = re.sub(
        r'<link\s+rel="stylesheet"\s+href="\.\.\/assets\/css\/cyber-theme\.css">',
        '<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">\n    <link href="../assets/css/pwa-styles.css" rel="stylesheet">',
        content
    )

    return content

def add_pwa_meta_tags(content):
    """Add PWA meta tags if missing"""
    # Check if already has manifest link
    if 'rel="manifest"' in content:
        return content

    # Find <head> tag and add meta tags after it
    head_pattern = r'(<head>\s*\n)'

    pwa_meta = r'''\1    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
'''

    content = re.sub(head_pattern, pwa_meta, content, count=1)

    return content

def fix_body_tag(content):
    """Add cyber-bg class and elements to body tag"""
    # Pattern 1: <body> without class
    pattern1 = r'<body>\s*\n'
    replacement1 = '''<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

'''
    content = re.sub(pattern1, replacement1, content)

    # Pattern 2: <body class="..."> - add cyber-bg if missing
    def add_cyber_bg_class(match):
        existing_classes = match.group(1)
        if 'cyber-bg' not in existing_classes:
            if existing_classes:
                new_classes = f'{existing_classes} cyber-bg'
            else:
                new_classes = 'cyber-bg'
        else:
            new_classes = existing_classes

        return f'''<body class="{new_classes}">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

'''

    pattern2 = r'<body\s+class="([^"]*)"\s*>\s*\n'
    content = re.sub(pattern2, add_cyber_bg_class, content)

    return content

def add_pwa_scripts(content):
    """Add PWA scripts before closing body tag"""
    # Check if scripts already exist
    if 'pwa-manager.js' in content:
        return content

    # Find closing </body> tag and add scripts before it
    scripts = '''
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>'''

    content = re.sub(r'</body>', scripts, content)

    return content

def process_file(file_path):
    """Process a single PHP file"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        original_content = content

        # Apply fixes
        content = fix_css_links(content)
        content = add_pwa_meta_tags(content)
        content = fix_body_tag(content)
        content = add_pwa_scripts(content)

        # Only write if changed
        if content != original_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            return True

        return False

    except Exception as e:
        print(f"Error processing {file_path}: {e}")
        return False

def main():
    """Main function"""
    print("=" * 60)
    print("SAMS UI Fix Script")
    print("=" * 60)

    # Find all PHP files
    php_files = []
    for root, dirs, files in os.walk(BASE_DIR):
        # Remove excluded directories
        dirs[:] = [d for d in dirs if not any(pattern.rstrip('/') in d for pattern in EXCLUDE_PATTERNS)]

        for file in files:
            file_path = Path(root) / file
            if should_process_file(file_path):
                php_files.append(file_path)

    print(f"\nFound {len(php_files)} PHP files to process")
    print("\nProcessing files...\n")

    fixed_count = 0
    for file_path in php_files:
        relative_path = file_path.relative_to(BASE_DIR)
        if process_file(file_path):
            print(f"✓ Fixed: {relative_path}")
            fixed_count += 1
        else:
            print(f"○ Skipped: {relative_path}")

    print("\n" + "=" * 60)
    print(f"Complete! Fixed {fixed_count} files out of {len(php_files)}")
    print("=" * 60)

if __name__ == '__main__':
    main()
