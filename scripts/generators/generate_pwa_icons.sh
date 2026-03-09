#!/bin/bash
# Generate PWA icons from a source image
# Usage: ./generate_pwa_icons.sh source_image.png

SOURCE_IMAGE="$1"

if [ -z "$SOURCE_IMAGE" ]; then
    echo "Usage: ./generate_pwa_icons.sh source_image.png"
    exit 1
fi

if [ ! -f "$SOURCE_IMAGE" ]; then
    echo "Error: Source image '$SOURCE_IMAGE' not found"
    exit 1
fi

# Create icons directory
ICONS_DIR="assets/images/icons"
mkdir -p "$ICONS_DIR"

# Icon sizes for PWA
SIZES=(72 96 128 144 152 192 384 512)

echo "Generating PWA icons..."

for SIZE in "${SIZES[@]}"; do
    OUTPUT="${ICONS_DIR}/icon-${SIZE}x${SIZE}.png"

    # Use ImageMagick to resize
    if command -v convert &> /dev/null; then
        convert "$SOURCE_IMAGE" -resize "${SIZE}x${SIZE}" "$OUTPUT"
        echo "✓ Generated ${SIZE}x${SIZE}"
    # Or use sips on macOS
    elif command -v sips &> /dev/null; then
        sips -z "$SIZE" "$SIZE" "$SOURCE_IMAGE" --out "$OUTPUT" > /dev/null 2>&1
        echo "✓ Generated ${SIZE}x${SIZE}"
    else
        echo "⚠ ImageMagick or sips required to generate icons"
        exit 1
    fi
done

# Generate badge icon (smaller, for notifications)
if command -v convert &> /dev/null; then
    convert "$SOURCE_IMAGE" -resize "72x72" "${ICONS_DIR}/badge-72x72.png"
    echo "✓ Generated badge icon"
fi

# Generate shortcut icons
SHORTCUT_ICONS=("checkin" "schedule" "messages")
for ICON in "${SHORTCUT_ICONS[@]}"; do
    convert "$SOURCE_IMAGE" -resize "96x96" "${ICONS_DIR}/${ICON}-96x96.png"
    echo "✓ Generated ${ICON} shortcut icon"
done

# Generate screenshots directory
SCREENSHOTS_DIR="assets/images/screenshots"
mkdir -p "$SCREENSHOTS_DIR"

echo "✓ Icon generation complete!"
echo ""
echo "Icons created in: $ICONS_DIR"
echo "Please add screenshots to: $SCREENSHOTS_DIR"
echo "  - dashboard-mobile.png (540x720)"
echo "  - dashboard-desktop.png (1280x720)"
