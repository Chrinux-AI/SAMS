#!/bin/bash

###############################################################################
# Nature Theme Conversion Script
# Converts all PHP files from Cyberpunk theme to Nature theme
# Version: 1.0
# Date: November 24, 2025
###############################################################################

echo "=================================================="
echo "  NATURE THEME CONVERSION SCRIPT"
echo "  Converting all pages to organic design"
echo "=================================================="
echo ""

# Color codes for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Conversion counters
converted=0
skipped=0
errors=0

# Function to convert a single PHP file
convert_file() {
    local file="$1"
    local backup="${file}.cyber-backup"

    # Skip if already converted or is a backup
    if grep -q "nature-theme.css" "$file" 2>/dev/null; then
        echo -e "${YELLOW}⊘${NC} Already converted: $file"
        ((skipped++))
        return
    fi

    if [[ "$file" == *".cyber-backup"* ]] || [[ "$file" == *"_backup.php"* ]]; then
        ((skipped++))
        return
    fi

    # Create backup
    cp "$file" "$backup"

    echo -e "${BLUE}⟳${NC} Converting: $file"

    # Perform conversions using sed
    sed -i \
        -e 's|Orbitron|Playfair Display|g' \
        -e "s|'Inter'|'Roboto'|g" \
        -e 's|"Inter"|"Roboto"|g' \
        -e 's|family=Inter:|family=Roboto:|g' \
        -e 's|family=Orbitron:|family=Playfair+Display:|g' \
        -e 's|family=Rajdhani:|family=Roboto:|g' \
        -e 's|Rajdhani|Roboto|g' \
        -e 's|cyberpunk-ui\.css|nature-theme.css" rel="stylesheet">\n    <link href="../assets/css/nature-components.css|g' \
        -e 's|assets/css/cyberpunk-ui\.css|assets/css/nature-theme.css" rel="stylesheet">\n    <link href="assets/css/nature-components.css|g' \
        -e 's|../assets/css/cyberpunk-ui\.css|../assets/css/nature-theme.css" rel="stylesheet">\n    <link href="../assets/css/nature-components.css|g' \
        -e 's|cyber-nav\.php|nature-nav.php|g' \
        -e 's|<div class="cyber-bg">|<!-- Nature Theme Background -->|g' \
        -e 's|<div class="starfield"></div>||g' \
        -e 's|</div>.*<!-- /?cyber-bg -->||g' \
        -e 's|<div class="cyber-grid"></div>||g' \
        -e 's|cyber-layout|nature-layout|g' \
        -e 's|cyber-sidebar|nature-sidebar|g' \
        -e 's|cyber-main|nature-main|g' \
        -e 's|cyber-header|nature-header|g' \
        -e 's|cyber-content|nature-content|g' \
        -e 's|holo-card|nature-card|g' \
        -e 's|stat-orb|stat-card|g' \
        -e 's|orb-grid|card-grid|g' \
        -e 's|orb-icon-wrapper|stat-icon|g' \
        -e 's|orb-content|stat-content|g' \
        -e 's|orb-value|stat-value|g' \
        -e 's|orb-label|stat-label|g' \
        -e 's|orb-trend|stat-trend|g' \
        -e 's|menu-item|sidebar-link|g' \
        -e 's|menu-icon|sidebar-icon|g' \
        -e 's|menu-label|sidebar-text|g' \
        -e 's|menu-badge|sidebar-badge|g' \
        -e 's|biometric-orb|quick-action-btn|g' \
        -e 's|Cyberpunk|Nature|g' \
        -e 's|CYBERPUNK|NATURE|g' \
        -e 's|cyberpunk|nature|g' \
        -e 's|Futuristic|Organic|g' \
        -e 's|holographic|natural|g' \
        -e 's|Holographic|Natural|g' \
        -e 's|Attendance AI|SAMS|g' \
        -e 's|slide-in|fade-in|g' \
        "$file"

    # Additional cleanup for specific patterns
    sed -i \
        -e '/<!-- Nature Theme Background -->/d' \
        -e 's|nature-theme.css" rel="stylesheet">.*<link href="../assets/css/nature-components.css|nature-theme.css">\n    <link rel="stylesheet" href="../assets/css/nature-components.css|g' \
        -e 's|nature-theme.css" rel="stylesheet">.*<link href="assets/css/nature-components.css|nature-theme.css">\n    <link rel="stylesheet" href="assets/css/nature-components.css|g' \
        "$file"

    # Check if conversion was successful
    if grep -q "nature-theme.css" "$file"; then
        echo -e "${GREEN}✓${NC} Converted successfully: $file"
        ((converted++))
    else
        echo -e "${RED}✗${NC} Conversion failed: $file"
        # Restore from backup
        mv "$backup" "$file"
        ((errors++))
    fi
}

# Main conversion process
echo "Starting conversion process..."
echo ""

# Convert admin panel files
echo -e "${BLUE}━━━ ADMIN PANEL ━━━${NC}"
for file in admin/*.php; do
    if [ -f "$file" ]; then
        convert_file "$file"
    fi
done

# Convert student panel files
echo ""
echo -e "${BLUE}━━━ STUDENT PANEL ━━━${NC}"
for file in student/*.php; do
    if [ -f "$file" ]; then
        convert_file "$file"
    fi
done

# Convert teacher panel files
echo ""
echo -e "${BLUE}━━━ TEACHER PANEL ━━━${NC}"
for file in teacher/*.php; do
    if [ -f "$file" ]; then
        convert_file "$file"
    fi
done

# Convert parent panel files
echo ""
echo -e "${BLUE}━━━ PARENT PANEL ━━━${NC}"
for file in parent/*.php; do
    if [ -f "$file" ]; then
        convert_file "$file"
    fi
done

# Convert root authentication files
echo ""
echo -e "${BLUE}━━━ ROOT PAGES ━━━${NC}"
for file in login.php register.php forgot-password.php reset-password.php verify-email.php index.php messages.php notices.php; do
    if [ -f "$file" ]; then
        convert_file "$file"
    fi
done

# Summary
echo ""
echo "=================================================="
echo "  CONVERSION SUMMARY"
echo "=================================================="
echo -e "${GREEN}✓ Converted:${NC} $converted files"
echo -e "${YELLOW}⊘ Skipped:${NC} $skipped files"
echo -e "${RED}✗ Errors:${NC} $errors files"
echo ""
echo "Backup files saved with .cyber-backup extension"
echo ""

# Create conversion log
cat > NATURE_CONVERSION_LOG.md << EOF
# Nature Theme Conversion Log
**Date**: $(date)
**Script Version**: 1.0

## Conversion Summary

- **Converted**: $converted files
- **Skipped**: $skipped files (already converted or backups)
- **Errors**: $errors files

## Changes Applied

### CSS & Fonts
- \`cyberpunk-ui.css\` → \`nature-theme.css\` + \`nature-components.css\`
- \`Orbitron\` font → \`Playfair Display\` (serif headings)
- \`Inter\` font → \`Roboto\` (body text)
- \`Rajdhani\` font → \`Roboto\`

### Navigation
- \`cyber-nav.php\` → \`nature-nav.php\`

### Layout Classes
- \`cyber-layout\` → \`nature-layout\`
- \`cyber-sidebar\` → \`nature-sidebar\`
- \`cyber-main\` → \`nature-main\`
- \`cyber-header\` → \`nature-header\`
- \`cyber-content\` → \`nature-content\`

### Components
- \`holo-card\` → \`nature-card\`
- \`stat-orb\` → \`stat-card\`
- \`orb-grid\` → \`card-grid\`
- \`menu-item\` → \`sidebar-link\`
- \`biometric-orb\` → \`quick-action-btn\`

### Background Elements
- Removed \`cyber-bg\`, \`starfield\`, and \`cyber-grid\` elements
- Added natural, organic background patterns

### Branding
- \`Attendance AI\` → \`SAMS\`
- \`Cyberpunk\`/\`Futuristic\` → \`Nature\`/\`Organic\`

## Backup Information

All original files backed up with \`.cyber-backup\` extension.
To restore a file:
\`\`\`bash
mv filename.php.cyber-backup filename.php
\`\`\`

## Next Steps

1. Review converted pages for visual consistency
2. Update custom inline styles if needed
3. Test all functionality
4. Remove backup files when satisfied

## Rollback (if needed)

\`\`\`bash
# Restore all from backups
for f in **/*.cyber-backup; do mv "\$f" "\${f%.cyber-backup}"; done
\`\`\`
EOF

echo -e "${GREEN}✓${NC} Conversion log saved to NATURE_CONVERSION_LOG.md"
echo ""

if [ $errors -eq 0 ]; then
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ✓ CONVERSION COMPLETED SUCCESSFULLY!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
else
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  ⚠ CONVERSION COMPLETED WITH WARNINGS${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
fi
