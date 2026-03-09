#!/bin/bash
echo "=== COMPREHENSIVE 404 CHECK ==="
echo ""

# Extract all .php links from cyber-nav.php
grep -oP "'\K[^']+\.php(?=')" includes/cyber-nav.php | sort -u | while read link; do
    # Clean up ../
    clean_link="${link#../}"
    
    found=false
    
    # Check root
    if [[ -f "$clean_link" ]]; then
        echo "✓ ROOT: $clean_link"
        found=true
    fi
    
    # Check in role folders
    for role in admin teacher student parent general; do
        if [[ -f "$role/$clean_link" ]]; then
            echo "✓ $role/$clean_link"
            found=true
            break
        fi
    done
    
    if [[ "$found" == "false" ]]; then
        echo "✗ MISSING: $link"
    fi
done
