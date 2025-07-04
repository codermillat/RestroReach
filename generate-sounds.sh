#!/bin/bash

# RestroReach Notification Sound Generator
# This script generates notification sound files for the plugin

echo "🔊 Generating RestroReach Notification Sounds..."

# Create sounds directory
mkdir -p assets/sounds

# Check if ffmpeg is available for generating actual audio files
if command -v ffmpeg >/dev/null 2>&1; then
    echo "✅ ffmpeg found, generating audio files..."
    
    # Generate a simple notification beep (440Hz for 0.3 seconds)
    ffmpeg -f lavfi -i "sine=frequency=440:duration=0.3" -ar 44100 -ac 1 assets/sounds/notification.wav -y
    
    # Generate a success sound (higher pitch, shorter)
    ffmpeg -f lavfi -i "sine=frequency=800:duration=0.2" -ar 44100 -ac 1 assets/sounds/success.wav -y
    
    # Generate an urgent notification (lower pitch, longer)
    ffmpeg -f lavfi -i "sine=frequency=300:duration=0.5" -ar 44100 -ac 1 assets/sounds/urgent.wav -y
    
    # Generate order ready sound (pleasant ding)
    ffmpeg -f lavfi -i "sine=frequency=523:duration=0.15,sine=frequency=659:duration=0.15" -filter_complex "[0][1]concat=n=2:v=0:a=1" assets/sounds/order-ready.wav -y
    
    # Convert to MP3 for better web compatibility
    ffmpeg -i assets/sounds/notification.wav assets/sounds/notification.mp3 -y
    ffmpeg -i assets/sounds/success.wav assets/sounds/success.mp3 -y
    ffmpeg -i assets/sounds/urgent.wav assets/sounds/urgent.mp3 -y
    ffmpeg -i assets/sounds/order-ready.wav assets/sounds/order-ready.mp3 -y
    
    echo "✅ All notification sounds generated successfully!"
    
else
    echo "⚠️  ffmpeg not found for generating audio files."
    echo "Please install ffmpeg:"
    echo "  macOS: brew install ffmpeg"
    echo "  Ubuntu: sudo apt-get install ffmpeg"
    echo ""
    echo "Alternative: Use online audio generators or download free sounds from:"
    echo "  - freesound.org"
    echo "  - zapsplat.com"
    echo "  - mixkit.co"
    echo ""
    echo "Required files:"
    echo "  - notification.mp3 (general notification)"
    echo "  - success.mp3 (action successful)"
    echo "  - urgent.mp3 (urgent alert)"
    echo "  - order-ready.mp3 (order status change)"
    
    # Create placeholder files
    echo "Creating placeholder audio files..."
    mkdir -p assets/sounds
    touch assets/sounds/notification.mp3
    touch assets/sounds/success.mp3
    touch assets/sounds/urgent.mp3
    touch assets/sounds/order-ready.mp3
    
    echo "✅ Placeholder audio files created."
fi

echo ""
echo "🎯 Sound Generation Complete!"
echo "Files created in: assets/sounds/"
echo "Next: Update notification system to use these sounds"
