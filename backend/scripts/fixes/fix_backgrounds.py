#!/usr/bin/env python3
"""
Fix Cyberpunk UI Backgrounds
Adds cyber-bg and cyber-grid elements to all pages using cyber-layout
"""

import os
import re
from pathlib import Path

# Background elements template
BACKGROUND_TEMPLATE = """<body>
    <div class="cyber-bg">
        <div class="starfield"></div>
    </div>
    <div class="cyber-grid"></div>

    <div class="cyber-layout">"""

# Pattern to match the malformed body structure
PATTERNS = [
    (r'</head>\s*<body>\s*</div>\s*<div class="cyber-layout">', '</head>\n' + BACKGROUND_TEMPLATE),
    (r'</head>\s*<body>\s+</div>\s+<div class="cyber-layout">', '</head>\n' + BACKGROUND_TEMPLATE),
]

def fix_file(filepath):
    """Fix a single PHP file"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Check if file needs cyber-layout but doesn't have cyber-bg
    if 'cyber-layout' in content and 'cyber-bg' not in content:
        original_content = content

        # Try each pattern
        for pattern, replacement in PATTERNS:
            content = re.sub(pattern, replacement, content, flags=re.MULTILINE)

        # If content changed, write it back
        if content != original_content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            return True

    return False

def main():
    print("🔧 Fixing Cyberpunk UI backgrounds across all pages...")

    directories = ['admin', 'student', 'teacher', 'parent']
    count = 0

    for directory in directories:
        dir_path = Path(directory)
        if not dir_path.exists():
            continue

        for php_file in dir_path.glob('*.php'):
            if fix_file(php_file):
                count += 1
                print(f"✓ Fixed: {php_file}")

    print(f"\n✅ Fixed {count} files with cyberpunk backgrounds")
    print("🎨 All pages now have proper starfield and grid effects!")

if __name__ == '__main__':
    main()
