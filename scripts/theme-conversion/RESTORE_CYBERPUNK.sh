#!/bin/bash

###############################################################################
# RESTORE TO ORIGINAL CYBERPUNK THEME
# Converts all files back to the original cyberpunk-ui.css
###############################################################################

echo "=================================================="
echo "  RESTORING ORIGINAL CYBERPUNK THEME"
echo "  Converting all files back to cyberpunk-ui.css"
echo "=================================================="
echo ""

GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

converted=0

# Function to restore to cyberpunk
restore_to_cyberpunk() {
    local file="$1"

    echo -e "${BLUE}⟳${NC} Restoring: $file"

    # Convert fonts back to cyberpunk fonts
    sed -i \
        -e 's|Playfair Display|Orbitron|g' \
        -e "s|'Roboto'|'Inter'|g" \
        -e 's|"Roboto"|"Inter"|g' \
        -e 's|family=Roboto:|family=Inter:|g' \
        -e 's|family=Playfair+Display:|family=Orbitron:|g' \
        "$file"

    # Convert CSS files back to cyberpunk
    sed -i \
        -e 's|nature-theme\.css|cyberpunk-ui.css|g' \
        -e 's|nature-components\.css||g' \
        -e 's|mockup-exact-theme\.css|cyberpunk-ui.css|g' \
        "$file"

    # Convert layout classes back to cyberpunk
    sed -i \
        -e 's|nature-nav\.php|cyber-nav.php|g' \
        -e 's|nature-layout|cyber-layout|g' \
        -e 's|nature-sidebar|cyber-sidebar|g' \
        -e 's|nature-main|cyber-main|g' \
        -e 's|nature-header|cyber-header|g' \
        -e 's|nature-content|cyber-content|g' \
        -e 's|nature-card|holo-card|g' \
        -e 's|stat-card|stat-orb|g' \
        -e 's|card-grid|orb-grid|g' \
        -e 's|sidebar-link|menu-item|g' \
        -e 's|sidebar-icon|menu-icon|g' \
        -e 's|sidebar-text|menu-label|g' \
        -e 's|sidebar-badge|menu-badge|g' \
        -e 's|quick-action-btn|biometric-orb|g' \
        "$file"

    # Convert comments back to cyberpunk
    sed -i \
        -e 's|Nature Admin Dashboard|Cyberpunk Admin Dashboard|g' \
        -e 's|Advanced Organic UI|Advanced Futuristic UI|g' \
        -e 's|Nature UI Framework|Cyberpunk UI Framework|g' \
        -e 's|SAMS|Attendance AI|g' \
        -e 's|fade-in|slide-in|g' \
        "$file"

    # Add back cyberpunk background elements
    sed -i \
        -e 's|<!-- Nature Background -->|<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>|g' \
        "$file"

    echo -e "${GREEN}✓${NC} Restored: $file"
    ((converted++))
}

# Restore all PHP files
for file in **/*.php; do
    if [ -f "$file" ] && [[ "$file" != *"backup"* ]] && [[ "$file" != *"vendor"* ]]; then
        restore_to_cyberpunk "$file"
    fi
done

echo ""
echo "=================================================="
echo "  RESTORATION COMPLETE"
echo "=================================================="
echo -e "${GREEN}✓ Restored:${NC} $converted files to Cyberpunk theme"
echo ""
echo "Your original Cyberpunk UI has been restored!"
echo ""
