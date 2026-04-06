"""
Fix duplicate HTML structure issues across all role pages.
Fixes:
1. Duplicate <meta charset> and <meta viewport> tags
2. Triplicate/quadruplicate starfield and cyber-grid divs
3. Broken nested cyber-bg divs
"""

import os
import re
import glob

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# All PHP files to process
patterns = [
    os.path.join(BASE_DIR, 'admin', '*.php'),
    os.path.join(BASE_DIR, 'student', '*.php'),
    os.path.join(BASE_DIR, 'teacher', '*.php'),
    os.path.join(BASE_DIR, 'parent', '*.php'),
    os.path.join(BASE_DIR, 'login.php'),
    os.path.join(BASE_DIR, 'register.php'),
    os.path.join(BASE_DIR, 'forgot-password.php'),
    os.path.join(BASE_DIR, 'reset-password.php'),
    os.path.join(BASE_DIR, 'index.php'),
    os.path.join(BASE_DIR, 'messages.php'),
    os.path.join(BASE_DIR, 'notices.php'),
    os.path.join(BASE_DIR, 'chat.php'),
    os.path.join(BASE_DIR, 'verify-email.php'),
    os.path.join(BASE_DIR, 'forum', '*.php'),
    os.path.join(BASE_DIR, 'general', '*.php'),
]

files = []
for p in patterns:
    files.extend(glob.glob(p))

fixed_count = 0
errors = []

for filepath in files:
    try:
        with open(filepath, 'r', encoding='utf-8', errors='replace') as f:
            content = f.read()

        original = content

        # Fix 1: Remove duplicate meta charset tags (keep only first)
        # Pattern: first pair of charset + viewport, then manifest/theme/apple-touch, then duplicate charset + viewport
        content = re.sub(
            r'(<meta\s+charset="UTF-8"\s*>)\s*\n\s*(<meta\s+name="viewport"\s+content="width=device-width,\s*initial-scale=1\.0"\s*>)\s*\n'
            r'(\s*<link\s+rel="manifest"[^>]*>\s*\n'
            r'\s*<meta\s+name="theme-color"[^>]*>\s*\n'
            r'\s*<link\s+rel="apple-touch-icon"[^>]*>\s*\n)'
            r'\s*<meta\s+charset="UTF-8"\s*>\s*\n'
            r'\s*<meta\s+name="viewport"\s+content="width=device-width,\s*initial-scale=1\.0"\s*>',
            r'\3    \1\n    \2',
            content,
            count=1
        )

        # Fix 2: Remove duplicate starfield/cyber-grid divs
        # Replace the bloated body opening with clean version
        # Pattern variations we need to handle:

        # Most common pattern (3-4 duplicates)
        content = re.sub(
            r'<body\s+class="cyber-bg">\s*\n'
            r'(\s*<div\s+class="starfield"><\/div>\s*\n'
            r'\s*<div\s+class="cyber-grid"><\/div>\s*\n)'
            r'(\s*\n?\s*<div\s+class="starfield"><\/div>\s*\n'
            r'\s*<div\s+class="cyber-grid"><\/div>\s*\n)+'
            r'(\s*\n?\s*(<div\s+class="cyber-bg">\s*\n?'
            r'\s*<div\s+class="starfield"><\/div>\s*\n?'
            r'\s*<\/div>\s*\n'
            r'\s*<div\s+class="cyber-grid"><\/div>\s*\n)?)',
            '<body class="cyber-bg">\n    <div class="starfield"></div>\n    <div class="cyber-grid"></div>\n\n',
            content,
            count=1
        )

        # Handle single-line variant: <div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
        content = re.sub(
            r'\s*<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>\s*',
            '\n',
            content
        )

        # Handle any remaining standalone duplicate starfield blocks after body
        # (catch stragglers that didn't match the main pattern)
        content = re.sub(
            r'(<body\s+class="cyber-bg">\s*\n\s*<div\s+class="starfield"><\/div>\s*\n\s*<div\s+class="cyber-grid"><\/div>\s*\n)\s*\n?\s*<div\s+class="starfield"><\/div>\s*\n\s*<div\s+class="cyber-grid"><\/div>',
            r'\1',
            content
        )

        # Remove orphan nested cyber-bg divs
        content = re.sub(
            r'\s*<div\s+class="cyber-bg">\s*\n?\s*<div\s+class="starfield"><\/div>\s*\n?\s*<\/div>\s*\n?\s*<div\s+class="cyber-grid"><\/div>\s*',
            '',
            content
        )

        # Clean up excessive blank lines (more than 2 in a row)
        content = re.sub(r'\n{4,}', '\n\n\n', content)

        if content != original:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            rel_path = os.path.relpath(filepath, BASE_DIR)
            print(f"  FIXED: {rel_path}")
            fixed_count += 1

    except Exception as e:
        errors.append(f"  ERROR: {os.path.relpath(filepath, BASE_DIR)} - {e}")

print(f"\n{'='*50}")
print(f"Fixed {fixed_count} files")
if errors:
    print(f"\nErrors ({len(errors)}):")
    for err in errors:
        print(err)
print(f"{'='*50}")
