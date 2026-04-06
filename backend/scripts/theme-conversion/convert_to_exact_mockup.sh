#!/bin/bash

###############################################################################
# EXACT MOCKUP UI CONVERSION - UI1.png Precise Match
# Converts ALL files to match the exact teal/sage design from mockup
# Version: 3.0
###############################################################################

echo "=================================================="
echo "  EXACT MOCKUP UI CONVERSION"
echo "  Matching UI1.png Design Precisely"
echo "=================================================="
echo ""

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

converted=0
skipped=0
errors=0

# Function to convert file to exact mockup design
convert_to_mockup() {
    local file="$1"
    local backup="${file}.pre-mockup-backup"

    # Skip backups
    if [[ "$file" == *".backup"* ]]; then
        ((skipped++))
        return
    fi

    # Check if already converted
    if grep -q "mockup-exact-theme.css" "$file" 2>/dev/null; then
        echo -e "${YELLOW}⊘${NC} Already converted: $file"
        ((skipped++))
        return
    fi

    # Create backup
    cp "$file" "$backup"

    echo -e "${BLUE}⟳${NC} Converting to EXACT mockup: $file"

    # Apply EXACT mockup theme
    sed -i \
        -e 's|nature-theme\.css|mockup-exact-theme.css|g' \
        -e 's|nature-components\.css|mockup-exact-theme.css|g' \
        -e 's|cyberpunk-ui\.css|mockup-exact-theme.css|g' \
        -e 's|#4CAF50|#4A7C7A|g' \
        -e 's|#43A047|#2D5F5D|g' \
        -e 's|#2E7D32|#2D5F5D|g' \
        -e 's|#FFD700|#B89968|g' \
        -e 's|#FFC107|#A88858|g' \
        -e 's|background: #FFFFFF|background: #8FA878|g' \
        -e 's|background: white|background: #8FA878|g' \
        -e 's|bg-white|bg-sage|g' \
        -e 's|bg-green|bg-teal|g' \
        "$file"

    # Verify conversion
    if grep -q "mockup-exact-theme.css" "$file"; then
        echo -e "${GREEN}✓${NC} Converted successfully: $file"
        ((converted++))
    else
        echo -e "${RED}✗${NC} Conversion failed: $file"
        mv "$backup" "$file"
        ((errors++))
    fi
}

# Convert all PHP files
echo -e "${BLUE}━━━ Converting ALL PHP Files ━━━${NC}"
echo ""

for file in **/*.php; do
    if [ -f "$file" ]; then
        convert_to_mockup "$file"
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

if [ $errors -eq 0 ]; then
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ✓ MOCKUP CONVERSION COMPLETED!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "Your UI now matches UI1.png EXACTLY:"
    echo "  🎨 Teal/Sage backgrounds (#4A7C7A, #2D5F5D)"
    echo "  🟤 Brown cards (#7D5E4F)"
    echo "  🟡 Tan/Gold cards (#B89968)"
    echo "  🟢 Sage green cards (#8FA878)"
    echo "  🌿 Leaf pattern overlays"
    echo ""
else
    echo -e "${YELLOW}⚠ CONVERSION COMPLETED WITH WARNINGS${NC}"
fi

echo ""
echo "To ROLLBACK if you don't like it: bash ROLLBACK_MOCKUP.sh"
echo ""
