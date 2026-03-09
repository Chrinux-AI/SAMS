#!/bin/bash

# Advanced Attendance System - Cleanup Script
# Removes invalid, outdated, and non-functional files

echo "🧹 Starting System Cleanup..."
echo "=============================="

cd /opt/lampp/htdocs/attendance

# Remove backup files
echo "→ Removing backup files..."
rm -f admin/dashboard-backup.php
rm -f login-backup.php
rm -f admin/dashboard-modern.php

# Remove old/unused files
echo "→ Removing unused files..."
rm -f modern-index.php
rm -f test.php
rm -f quick-check.php

# Remove old navigation file
echo "→ Removing old navigation..."
rm -f includes/modern-nav.php
rm -f includes/page-header.php
rm -f includes/page-footer.php

# Remove old CSS files
echo "→ Cleaning old CSS..."
rm -f assets/css/advanced-ui.css
rm -f assets/css/dashboard-modern.css
rm -f assets/css/dashboard-widgets.css
rm -f assets/css/responsive-dashboard.css
rm -f assets/css/realtime-features.css

# Remove old JS files
echo "→ Cleaning old JavaScript..."
rm -f assets/js/dashboard-modern.js
rm -f assets/js/realtime-features.js

# Remove old docs
echo "→ Removing outdated documentation..."
rm -f MODERN-THEME-IMPLEMENTATION.md
rm -f MODERN-UI-DOCS.md
rm -f DASHBOARD-QUICK-START.md
rm -f ERROR-FIX-SUMMARY.md

echo ""
echo "✅ Cleanup Complete!"
echo "=============================="
echo "System is now clean and organized"
echo ""
echo "Active Files:"
echo "- login.php (Cyberpunk UI)"
echo "- admin/dashboard.php (Neural Dashboard)"
echo "- assets/css/cyberpunk-ui.css (Main stylesheet)"
echo "- includes/cyber-nav.php (Sidebar navigation)"
echo ""
