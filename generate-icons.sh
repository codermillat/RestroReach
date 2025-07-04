#!/bin/bash

# RestroReach PWA Icon Generator
# This script generates all required PWA icons from the base SVG

echo "🎨 Generating RestroReach PWA Icons..."

# Check if ImageMagick or similar tools are available
if command -v convert >/dev/null 2>&1; then
    echo "✅ ImageMagick found, generating PNG icons..."
    
    # Icon sizes required by the PWA manifest
    sizes=(16 32 72 96 128 144 152 180 192 384 512)
    
    for size in "${sizes[@]}"; do
        echo "Generating ${size}x${size} icon..."
        convert -background none -size "${size}x${size}" "assets/images/base-icon.svg" "assets/images/icon-${size}x${size}.png"
    done
    
    # Generate favicon
    convert -background none -size "32x32" "assets/images/base-icon.svg" "assets/images/favicon.ico"
    
    echo "✅ All PWA icons generated successfully!"
    
elif command -v rsvg-convert >/dev/null 2>&1; then
    echo "✅ rsvg-convert found, generating PNG icons..."
    
    sizes=(16 32 72 96 128 144 152 180 192 384 512)
    
    for size in "${sizes[@]}"; do
        echo "Generating ${size}x${size} icon..."
        rsvg-convert -w "$size" -h "$size" "assets/images/base-icon.svg" > "assets/images/icon-${size}x${size}.png"
    done
    
    echo "✅ All PWA icons generated successfully!"
    
else
    echo "⚠️  ImageMagick or rsvg-convert not found."
    echo "Please install one of the following:"
    echo "  macOS: brew install imagemagick"
    echo "  Ubuntu: sudo apt-get install imagemagick"
    echo "  Alternative: Use online SVG to PNG converter"
    echo ""
    echo "Manual steps:"
    echo "1. Open assets/images/base-icon.svg in any image editor"
    echo "2. Export as PNG in these sizes: 16, 32, 72, 96, 128, 144, 152, 180, 192, 384, 512"
    echo "3. Name them as: icon-16x16.png, icon-32x32.png, etc."
    
    # Create placeholder files for immediate functionality
    echo "Creating placeholder PNG files for immediate testing..."
    sizes=(16 32 72 96 128 144 152 180 192 384 512)
    
    for size in "${sizes[@]}"; do
        # Create a simple colored square as placeholder
        echo "Creating placeholder for ${size}x${size}..."
        # We'll create these as empty files that can be replaced later
        touch "assets/images/icon-${size}x${size}.png"
    done
    
    echo "✅ Placeholder files created. Replace with actual icons when ready."
fi

echo ""
echo "🎯 Icon Generation Complete!"
echo "Files created in: assets/images/"
echo "Next: Update manifest.json if needed"
