#!/bin/bash

###############################################################################
# ROLLBACK MOCKUP CONVERSION
# Restores files before the exact mockup UI was applied
###############################################################################

echo "=================================================="
echo "  MOCKUP UI ROLLBACK"
echo "  Restoring previous state..."
echo "=================================================="
echo ""

GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

restored=0

# Restore all pre-mockup backups
for backup in **/*.pre-mockup-backup; do
    if [ -f "$backup" ]; then
        original="${backup%.pre-mockup-backup}"
        mv "$backup" "$original"
        echo -e "${GREEN}✓${NC} Restored: $original"
        ((restored++))
    fi
done

echo ""
echo "=================================================="
echo "  ROLLBACK COMPLETE"
echo "=================================================="
echo -e "${GREEN}✓ Restored:${NC} $restored files"
echo ""
echo "Your UI has been reverted to the previous state."
echo ""
