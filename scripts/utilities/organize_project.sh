#!/bin/bash

# SAMS Project Organization Script
# This script helps maintain the organized project structure

echo "🗂️  SAMS Project Organization Script"
echo "=================================="

# Define project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "Project root: $PROJECT_ROOT"

# Function to create directories if they don't exist
ensure_dir() {
    if [ ! -d "$1" ]; then
        mkdir -p "$1"
        echo "✅ Created directory: $1"
    fi
}

# Function to move files with confirmation
move_file() {
    local src="$1"
    local dest="$2"
    
    if [ -f "$src" ]; then
        if [ ! -f "$dest" ]; then
            mv "$src" "$dest"
            echo "📁 Moved: $src → $dest"
        else
            echo "⚠️  Destination exists: $dest"
        fi
    fi
}

# Ensure proper directory structure
echo "📁 Ensuring directory structure..."
ensure_dir "docs/guides"
ensure_dir "docs/implementation"
ensure_dir "docs/themes"
ensure_dir "docs/api"
ensure_dir "scripts"
ensure_dir "logs"
ensure_dir "storage"
ensure_dir "cache"

# Move any remaining documentation files
echo "📚 Organizing documentation files..."

# Move any remaining .md files to appropriate locations
for file in *.md; do
    if [ -f "$file" ] && [ "$file" != "README.md" ]; then
        case "$file" in
            *GUIDE*|*TUTORIAL*|*MANUAL*)
                move_file "$file" "docs/guides/$file"
                ;;
            *IMPLEMENTATION*|*COMPLETE*|*STATUS*|*REPORT*)
                move_file "$file" "docs/implementation/$file"
                ;;
            *THEME*|*UI*|*CYBERPUNK*|*NATURE*)
                move_file "$file" "docs/themes/$file"
                ;;
            *API*|*ENDPOINT*)
                move_file "$file" "docs/api/$file"
                ;;
        esac
    fi
done

# Move any remaining script files
echo "🔧 Organizing script files..."
for file in *.sh *.py *.ps1; do
    if [ -f "$file" ]; then
        move_file "$file" "scripts/$file"
    fi
done

# Clean up temporary files
echo "🧹 Cleaning up temporary files..."
rm -f _tmp_*.txt
rm -f *.tmp
rm -f *.temp

# Set proper permissions
echo "🔒 Setting proper permissions..."
chmod 755 scripts/*.sh 2>/dev/null || true
chmod 644 scripts/*.py 2>/dev/null || true
chmod 755 scripts/*.ps1 2>/dev/null || true
chmod 755 scripts/*.php 2>/dev/null || true

# Create .gitignore for logs and cache
if [ ! -f "logs/.gitignore" ]; then
    echo "*.log" > logs/.gitignore
    echo "✅ Created logs/.gitignore"
fi

if [ ! -f "cache/.gitignore" ]; then
    echo "*" > cache/.gitignore
    echo "!.gitignore" >> cache/.gitignore
    echo "✅ Created cache/.gitignore"
fi

# Create storage directory structure
ensure_dir "storage/avatars"
ensure_dir "storage/documents"
ensure_dir "storage/exports"
ensure_dir "storage/backups"

echo ""
echo "✅ Project organization complete!"
echo ""
echo "📊 Summary:"
echo "  - Documentation organized in docs/"
echo "  - Scripts organized in scripts/"
echo "  - Temporary files cleaned"
echo "  - Permissions set"
echo "  - Storage structure created"
echo ""
echo "📖 Check docs/INDEX.md for complete documentation guide"
