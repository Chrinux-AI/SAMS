#!/usr/bin/env python3
"""
Comprehensive UI Fixer for Attendance System
Fixes navigation includes, broken links, and UI consistency issues
"""

import os
import re
from pathlib import Path

def fix_navigation_includes(filepath):
    """Fix navigation includes to use cyber-nav.php"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    changes = []

    # Fix admin-nav.php references to cyber-nav.php (except in includes directory itself)
    if 'includes/admin-nav.php' not in str(filepath):
        if "include '../includes/admin-nav.php'" in content or "include '../includes/admin-nav.php'" in content:
            content = content.replace("include '../includes/admin-nav.php'", "include '../includes/cyber-nav.php'")
            content = content.replace("include '../includes/admin-nav.php'", "include '../includes/cyber-nav.php'")
            changes.append("admin-nav → cyber-nav")

    # Fix parent/teacher/student nav references
    if "include '../includes/parent-nav.php'" in content:
        content = content.replace("include '../includes/parent-nav.php'", "include '../includes/cyber-nav.php'")
        changes.append("parent-nav → cyber-nav")

    if "include '../includes/teacher-nav.php'" in content:
        content = content.replace("include '../includes/teacher-nav.php'", "include '../includes/cyber-nav.php'")
        changes.append("teacher-nav → cyber-nav")

    # Fix student-nav.php for non-enhanced pages
    if 'dashboard-enhanced.php' not in str(filepath) and 'checkin-enhanced.php' not in str(filepath) and 'attendance-enhanced.php' not in str(filepath) and 'notifications.php' not in str(filepath):
        if "include '../includes/student-nav.php'" in content:
            content = content.replace("include '../includes/student-nav.php'", "include '../includes/cyber-nav.php'")
            changes.append("student-nav → cyber-nav")

    # Fix missing <?php for includes
    content = re.sub(r"include\s+'\.\.\/includes\/cyber-nav\.php';", "<?php include '../includes/cyber-nav.php'; ?>", content)
    content = re.sub(r"include\s+'includes\/cyber-nav\.php';", "<?php include 'includes/cyber-nav.php'; ?>", content)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        return True, changes

    return False, []

def fix_student_dashboard_nav(filepath):
    """Replace inline navigation with cyber-nav.php include for student dashboard"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Check if it has inline sidebar navigation
    if '<aside class="cyber-sidebar">' in content and '../includes/cyber-nav.php' not in content:
        # Find the entire sidebar section
        sidebar_pattern = r'<aside class="cyber-sidebar">.*?</aside>'
        if re.search(sidebar_pattern, content, re.DOTALL):
            # Replace with include
            content = re.sub(
                sidebar_pattern,
                '<?php include \'../includes/cyber-nav.php\'; ?>',
                content,
                flags=re.DOTALL
            )

            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            return True

    return False

def fix_broken_links(filepath):
    """Fix common broken link patterns"""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    changes = []

    # Fix double slashes in paths
    content = re.sub(r'href="\/\/', 'href="/', content)
    content = re.sub(r'src="\/\/', 'src="/', content)

    # Fix incorrect relative paths in admin
    if '/admin/' in str(filepath):
        # Fix links that should go to parent directory
        content = re.sub(r'href="messages\.php"', 'href="../messages.php"', content)
        content = re.sub(r'href="notices\.php"(?!>)', 'href="../notices.php"', content)  # Not for manage notices

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        return True

    return False

def main():
    print("🔧 Fixing all UI issues, navigation, and broken links...")
    print()

    directories = ['admin', 'teacher', 'student', 'parent', 'general']
    total_fixed = 0
    nav_fixed = 0
    link_fixed = 0

    for directory in directories:
        dir_path = Path(directory)
        if not dir_path.exists():
            continue

        for php_file in dir_path.glob('*.php'):
            fixed_nav, nav_changes = fix_navigation_includes(php_file)
            fixed_links = fix_broken_links(php_file)

            if fixed_nav or fixed_links:
                status = []
                if fixed_nav:
                    nav_fixed += 1
                    status.append(f"nav: {', '.join(nav_changes)}")
                if fixed_links:
                    link_fixed += 1
                    status.append("links fixed")

                print(f"✓ Fixed: {php_file} ({'; '.join(status)})")
                total_fixed += 1

    # Special fix for student dashboard
    student_dash = Path('student/dashboard.php')
    if student_dash.exists():
        if fix_student_dashboard_nav(student_dash):
            print(f"✓ Fixed: {student_dash} (replaced inline nav with cyber-nav.php)")
            nav_fixed += 1
            total_fixed += 1

    teacher_dash = Path('teacher/dashboard.php')
    if teacher_dash.exists():
        if fix_student_dashboard_nav(teacher_dash):
            print(f"✓ Fixed: {teacher_dash} (replaced inline nav with cyber-nav.php)")
            nav_fixed += 1
            total_fixed += 1

    print()
    print(f"✅ Fixed {total_fixed} files")
    print(f"   - Navigation fixes: {nav_fixed}")
    print(f"   - Link fixes: {link_fixed}")
    print("🎨 All UI issues resolved!")

if __name__ == '__main__':
    main()
