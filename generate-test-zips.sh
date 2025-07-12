#!/bin/bash

set -e

DOWNLOADS_DIR="$HOME/Downloads"

echo "📦 Archetype Plugin Test Zip Generator with Version Incrementing"
echo "================================================================="
echo ""

# Function to show help
show_help() {
    echo "Usage: $0 [plugin-name|all] [--no-increment]"
    echo ""
    echo "Options:"
    echo "  all                    Generate zips for all plugins (increments versions)"
    echo "  wp-ant-media-stream-access    Generate zip for Ant Media plugin"
    echo "  wp-video-library       Generate zip for Video Library plugin"  
    echo "  wp-rocket-chat-embed   Generate zip for Rocket Chat plugin"
    echo "  --no-increment         Don't increment versions (use current)"
    echo ""
    echo "Generated zips will be placed in: $DOWNLOADS_DIR"
    echo ""
    exit 1
}

# Function to increment version in a plugin file
increment_version() {
    local plugin_file=$1
    local plugin_name=$2
    
    if [ ! -f "$plugin_file" ]; then
        echo "  ❌ Plugin file not found: $plugin_file"
        return 1
    fi
    
    # Extract current version from header
    local current_version=$(grep "Version:" "$plugin_file" | head -1 | sed 's/.*Version: *\([0-9.]*\).*/\1/')
    
    if [ -z "$current_version" ]; then
        echo "  ❌ Could not find version in $plugin_file"
        return 1
    fi
    
    # Increment patch version (x.y.z -> x.y.z+1)
    local new_version=$(echo "$current_version" | awk -F. '{$NF = $NF + 1; print}' OFS='.')
    
    echo "  📈 $plugin_name: $current_version → $new_version"
    
    # Update version in header
    sed -i '' "s/Version: $current_version/Version: $new_version/" "$plugin_file"
    
    # Update version constant (look for define with plugin-specific prefix)
    case "$plugin_name" in
        "wp-ant-media-stream-access")
            sed -i '' "s/define('AMSA_VERSION', '$current_version')/define('AMSA_VERSION', '$new_version')/" "$plugin_file"
            sed -i '' "s/if (!defined('AMSA_VERSION')) define('AMSA_VERSION', '$current_version')/if (!defined('AMSA_VERSION')) define('AMSA_VERSION', '$new_version')/" "$plugin_file"
            ;;
        "wp-rocket-chat-embed")
            sed -i '' "s/define('RCE_VERSION', '$current_version')/define('RCE_VERSION', '$new_version')/" "$plugin_file"
            sed -i '' "s/if (!defined('RCE_VERSION')) define('RCE_VERSION', '$current_version')/if (!defined('RCE_VERSION')) define('RCE_VERSION', '$new_version')/" "$plugin_file"
            ;;
        "wp-video-library")
            sed -i '' "s/define('WPVL_VERSION', '$current_version')/define('WPVL_VERSION', '$new_version')/" "$plugin_file"
            sed -i '' "s/if (!defined('WPVL_VERSION')) define('WPVL_VERSION', '$current_version')/if (!defined('WPVL_VERSION')) define('WPVL_VERSION', '$new_version')/" "$plugin_file"
            ;;
    esac
    
    echo "$new_version"
}

# Function to copy zip to Downloads
copy_to_downloads() {
    local plugin=$1
    local zip_file=$(find build/ -name "${plugin}-v*.zip" -type f | head -1)
    
    if [ -f "$zip_file" ]; then
        cp "$zip_file" "$DOWNLOADS_DIR/"
        local filename=$(basename "$zip_file")
        echo "  ✅ Copied $filename to Downloads folder"
        echo "     📍 Location: $DOWNLOADS_DIR/$filename"
        return 0
    else
        echo "  ❌ No zip file found for $plugin"
        return 1
    fi
}

# Function to get plugin main file
get_plugin_main_file() {
    local plugin=$1
    case "$plugin" in
        "wp-ant-media-stream-access")
            echo "plugins/$plugin/ant-media-stream-access.php"
            ;;
        "wp-rocket-chat-embed")
            echo "plugins/$plugin/rocket-chat-embed.php"
            ;;
        "wp-video-library")
            echo "plugins/$plugin/video-library.php"
            ;;
        *)
            echo "plugins/$plugin/$plugin.php"
            ;;
    esac
}

# Parse command line arguments
PLUGIN_NAME=${1:-all}
INCREMENT_VERSION=true

if [ "$2" = "--no-increment" ] || [ "$1" = "--no-increment" ]; then
    INCREMENT_VERSION=false
    if [ "$1" = "--no-increment" ]; then
        PLUGIN_NAME="all"
    fi
fi

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

# Increment versions if requested
if [ "$INCREMENT_VERSION" = true ]; then
    echo "🔢 Incrementing plugin versions..."
    echo ""
    
    if [ "$PLUGIN_NAME" = "all" ]; then
        # Increment all plugins
        for plugin in wp-ant-media-stream-access wp-video-library wp-rocket-chat-embed; do
            plugin_file=$(get_plugin_main_file "$plugin")
            increment_version "$plugin_file" "$plugin"
        done
    else
        # Increment specific plugin
        if [ ! -d "plugins/$PLUGIN_NAME" ]; then
            echo "❌ Plugin '$PLUGIN_NAME' not found!"
            echo ""
            echo "Available plugins:"
            ls -1 plugins/
            echo ""
            show_help
        fi
        
        plugin_file=$(get_plugin_main_file "$PLUGIN_NAME")
        increment_version "$plugin_file" "$PLUGIN_NAME"
    fi
    
    echo ""
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

if [ "$INCREMENT_VERSION" = true ]; then
    echo ""
    echo "⚠️  NOTE: Plugin versions have been incremented."
    echo "   Use 'git checkout .' to revert if needed."
fi

echo ""

# Show what's in Downloads folder
echo "📋 WordPress plugin zips in Downloads:"
ls -la "$DOWNLOADS_DIR"/*wordpress*.zip "$DOWNLOADS_DIR"/wp-*.zip 2>/dev/null | while read -r line; do
    echo "   $line"
done || echo "   (No WordPress plugin zips found)"

echo ""
echo "💡 Pro tip: You can now easily drag & drop these zips for testing!"
echo "   Use './generate-test-zips.sh --no-increment' to build without version bumps"
echo ""
