#!/usr/bin/env python3
"""
Fix Duplicate Layout Structures
Removes duplicate cyber-layout divs and navigation includes
"""

import os
import re
from pathlib import Path

def fix_duplicate_structures(filepath):
    """Fix duplicate layout and navigation structures"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    fixes = []

    # Fix duplicate cyber-layout sections
    # Pattern: <div class="cyber-layout">.*?<?php include.*?cyber-nav.php.*?>.*?<div class="cyber-layout">
    pattern = r'(<div class="cyber-layout">)\s*<?php include[^>]*cyber-nav\.php[^>]*?>\s*.*?<div class="cyber-layout">'
    if re.search(pattern, content, re.DOTALL):
        content = re.sub(
            pattern,
            r'\1\n        <?php include \'../includes/cyber-nav.php\'; ?>\n\n        <!-- Main Content -->',
            content,
            count=1,
            flags=re.DOTALL
        )
        fixes.append("removed duplicate cyber-layout")

    # Fix duplicate navigation includes
    if content.count("include '../includes/cyber-nav.php'") > 1 or content.count('include "../includes/cyber-nav.php"') > 1:
        # Keep only the first occurrence
        first_found = False
        lines = content.split('\n')
        new_lines = []
        for line in lines:
            if "include '../includes/cyber-nav.php'" in line or 'include "../includes/cyber-nav.php"' in line or "include '../includes/cyber-nav.php'" in line:
                if not first_found:
                    new_lines.append(line)
                    first_found = True
                    continue
            else:
                new_lines.append(line)
        content = '\n'.join(new_lines)
        if not any("duplicate cyber-layout" in f for f in fixes):
            fixes.append("removed duplicate nav include")

    # Fix empty divs before cyber-layout
    content = re.sub(r'</div>\s*</div>\s*<div class="cyber-layout">', r'<div class="cyber-layout">', content)

    # Fix malformed body sections with empty divs
    content = re.sub(r'<body>\s*<div class="cyber-bg">.*?</div>\s*<div class="cyber-grid"></div>\s*</div>\s*<div',
                     '<body>\n    <div class="cyber-bg">\n        <div class="starfield"></div>\n    </div>\n    <div class="cyber-grid"></div>\n\n    <div',
                     content, flags=re.DOTALL)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        return True, fixes

    return False, []

def main():
    print("🔧 Fixing duplicate layout structures...")
    print()

    directories = ['admin', 'teacher', 'student', 'parent']
    total_fixed = 0

    for directory in directories:
        dir_path = Path(directory)
        if not dir_path.exists():
            continue

        for php_file in dir_path.glob('*.php'):
            fixed, changes = fix_duplicate_structures(php_file)
            if fixed:
                change_str = ", ".join(changes) if changes else "structural fixes"
                print(f"✓ Fixed: {php_file} ({change_str})")
                total_fixed += 1

    print()
    print(f"✅ Fixed {total_fixed} files with duplicate structures")
    print("🎨 All layout structures cleaned!")

if __name__ == '__main__':
    main()
