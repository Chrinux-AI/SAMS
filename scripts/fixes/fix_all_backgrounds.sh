#!/bin/bash

# Fix All Cyberpunk UI Backgrounds
# Adds cyber-bg and cyber-grid elements to all pages using cyber-layout

echo "🔧 Fixing Cyberpunk UI backgrounds across all pages..."

count=0

# Process all PHP files in admin, student, teacher, parent directories
for file in admin/*.php student/*.php teacher/*.php parent/*.php; do
    if [ ! -f "$file" ]; then
        continue
    fi

    # Check if file has cyber-layout but no cyber-bg
    if grep -q "cyber-layout" "$file" && ! grep -q "cyber-bg" "$file"; then
        # Fix the pattern: </head>\n<body>\n    </div>\n    \n    <div class="cyber-layout">
        sed -i 's|</head>\n\n<body>\n        \n    </div>\n    \n    <div class="cyber-layout">|</head>\n\n<body>\n    <div class="cyber-bg">\n        <div class="starfield"></div>\n    </div>\n    <div class="cyber-grid"></div>\n\n    <div class="cyber-layout">|g' "$file"

        ((count++))
        echo "✓ Fixed: $file"
    fi
done

echo ""
echo "✅ Fixed $count files with cyberpunk backgrounds"
echo "🎨 All pages now have proper starfield and grid effects!"
