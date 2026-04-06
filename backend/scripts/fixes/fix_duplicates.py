#!/usr/bin/env python3
"""
Remove duplicate starfield and cyber-grid divs
"""

import os
import re
from pathlib import Path

BASE_DIR = Path('/opt/lampp/htdocs/attendance')

def fix_duplicates(content):
    """Remove duplicate starfield and cyber-grid divs"""
    # Pattern to match multiple consecutive starfield/cyber-grid blocks
    pattern = r'(<div class="starfield"></div>\s*<div class="cyber-grid"></div>\s*\n)+'

    # Replace with single instance
    replacement = r'<div class="starfield"></div>\n    <div class="cyber-grid"></div>\n\n    '

    content = re.sub(pattern, replacement, content)

    return content

def process_file(file_path):
    """Process a single file"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        original_content = content
        content = fix_duplicates(content)

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
    print("Removing duplicate starfield/cyber-grid divs...")

    fixed_count = 0
    for root, dirs, files in os.walk(BASE_DIR):
        dirs[:] = [d for d in dirs if d not in ['vendor', 'cache', 'logs', 'uploads']]

        for file in files:
            if file.endswith('.php'):
                file_path = Path(root) / file
                if process_file(file_path):
                    print(f"✓ Fixed: {file_path.relative_to(BASE_DIR)}")
                    fixed_count += 1

    print(f"\nFixed {fixed_count} files")

if __name__ == '__main__':
    main()
