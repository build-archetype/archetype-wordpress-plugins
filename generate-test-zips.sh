#!/bin/bash

set -e

DOWNLOADS_DIR="$HOME/Downloads"

echo "📦 Archetype Plugin Test Zip Generator"
echo "======================================"
echo ""

# Function to show help
show_help() {
    echo "Usage: $0 [plugin-name|all]"
    echo ""
    echo "Options:"
    echo "  all                    Generate zips for all plugins"
    echo "  wp-ant-media-stream-access    Generate zip for Ant Media plugin"
    echo "  wp-video-library       Generate zip for Video Library plugin"
    echo "  wp-rocket-chat-embed   Generate zip for Rocket Chat plugin"
    echo ""
    echo "Generated zips will be placed in: $DOWNLOADS_DIR"
    echo ""
    exit 1
}

# Function to copy zip to Downloads
copy_to_downloads() {
    local plugin=$1
    local zip_file=$(find build/ -name "${plugin}-v*.zip" -type f | head -1)
    
    if [ -f "$zip_file" ]; then
        cp "$zip_file" "$DOWNLOADS_DIR/"
        local filename=$(basename "$zip_file")
        echo "  ✅ Copied $filename to Downloads folder"
        echo "     �� Location: $DOWNLOADS_DIR/$filename"
        return 0
    else
        echo "  ❌ No zip file found for $plugin"
        return 1
    fi
}

# Parse command line arguments
PLUGIN_NAME=${1:-all}

if [ "$PLUGIN_NAME" = "help" ] || [ "$PLUGIN_NAME" = "--help" ] || [ "$PLUGIN_NAME" = "-h" ]; then
    show_help
fi

# Create Downloads directory if it doesn't exist
mkdir -p "$DOWNLOADS_DIR"

# Clear old build files
if [ -d "build" ]; then
    echo "🧹 Cleaning old build files..."
    rm -rf build/*.zip 2>/dev/null || true
fi

echo "🏗️  Generating plugin packages..."
echo ""

if [ "$PLUGIN_NAME" = "all" ]; then
    # Generate all plugins
    if ./scripts/build-all.sh | grep -E "(✅|❌|🎉)"; then
        echo ""
        echo "📁 Copying to Downloads folder..."
        
        # Copy all generated zips
        for plugin in wp-ant-media-stream-access wp-video-library wp-rocket-chat-embed; do
            copy_to_downloads "$plugin"
        done
    fi
else
    # Generate specific plugin
    if [ ! -d "plugins/$PLUGIN_NAME" ]; then
        echo "❌ Plugin '$PLUGIN_NAME' not found!"
        echo ""
        echo "Available plugins:"
        ls -1 plugins/
        echo ""
        show_help
    fi
    
    echo "🔨 Building $PLUGIN_NAME..."
    if ./scripts/package-plugin.sh "$PLUGIN_NAME"; then
        echo ""
        echo "📁 Copying to Downloads folder..."
        copy_to_downloads "$PLUGIN_NAME"
    fi
fi

echo ""
echo "🎉 Test zip generation complete!"
echo ""

# Show what's in Downloads folder
echo "📋 WordPress plugin zips in Downloads:"
ls -la "$DOWNLOADS_DIR"/*wordpress*.zip "$DOWNLOADS_DIR"/wp-*.zip 2>/dev/null | while read -r line; do
    echo "   $line"
done || echo "   (No WordPress plugin zips found)"

echo ""
echo "💡 Pro tip: You can now easily drag & drop these zips for testing!"
echo ""
