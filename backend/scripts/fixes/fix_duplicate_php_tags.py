#!/usr/bin/env python3
"""
Fix Duplicate PHP Tags
Removes duplicate <?php <?php patterns from all PHP files
"""

import os
import re
from pathlib import Path

def fix_duplicate_php_tags(filepath):
    """Remove duplicate PHP tags"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # Fix patterns like <?php <?php <?php include ... ?> ?> ?>
    content = re.sub(r'<\?php\s+<\?php\s+<\?php\s+(.*?)\s+\?>\s+\?>\s+\?>', r'<?php \1 ?>', content)

    # Fix patterns like <?php <?php include ... ?> ?>
    content = re.sub(r'<\?php\s+<\?php\s+(.*?)\s+\?>\s+\?>', r'<?php \1 ?>', content)

    # Fix patterns where there are just duplicate opening tags
    content = re.sub(r'<\?php\s+<\?php', r'<?php', content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        return True

    return False

def main():
    print("🔧 Fixing duplicate PHP tags...")
    print()

    directories = ['admin', 'teacher', 'student', 'parent', 'general', 'includes']
    total_fixed = 0

    for directory in directories:
        dir_path = Path(directory)
        if not dir_path.exists():
            continue

        for php_file in dir_path.glob('*.php'):
            if fix_duplicate_php_tags(php_file):
                print(f"✓ Fixed: {php_file}")
                total_fixed += 1

    print()
    print(f"✅ Fixed {total_fixed} files with duplicate PHP tags")
    print("🎨 All PHP syntax cleaned!")

if __name__ == '__main__':
    main()
