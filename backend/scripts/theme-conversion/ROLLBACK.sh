#!/bin/bash

###############################################################################
# ROLLBACK SCRIPT - Restore Original Cyberpunk Theme
# Use this to revert ALL changes if you don't like the new UI
###############################################################################

echo "=================================================="
echo "  UI ROLLBACK SYSTEM"
echo "  Restoring original theme..."
echo "=================================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

restored=0
errors=0

# Function to restore a file
restore_file() {
    local backup="$1"
    local original="${backup%.cyber-backup}"

    if [ -f "$backup" ]; then
        mv "$backup" "$original"
        echo -e "${GREEN}✓${NC} Restored: $original"
        ((restored++))
    else
        echo -e "${RED}✗${NC} Backup not found: $backup"
        ((errors++))
    fi
}

# Restore all backup files
echo "Searching for backup files..."
echo ""

for backup in **/*.cyber-backup; do
    if [ -f "$backup" ]; then
        restore_file "$backup"
    fi
done

echo ""
echo "=================================================="
echo "  ROLLBACK SUMMARY"
echo "=================================================="
echo -e "${GREEN}✓ Restored:${NC} $restored files"
echo -e "${RED}✗ Errors:${NC} $errors files"
echo ""

if [ $errors -eq 0 ]; then
    echo -e "${GREEN}✓ ROLLBACK COMPLETED SUCCESSFULLY!${NC}"
    echo "Your original Cyberpunk theme has been restored."
else
    echo -e "${YELLOW}⚠ ROLLBACK COMPLETED WITH WARNINGS${NC}"
fi

echo ""
echo "To use the nature theme again, run: bash convert_to_nature_theme.sh"
echo ""
