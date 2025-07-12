#!/bin/bash

set -e

echo "🔢 Auto-incrementing plugin versions..."

# Find all plugin directories
PLUGINS=$(find plugins/ -mindepth 1 -maxdepth 1 -type d -exec basename {} \;)

if [ -z "$PLUGINS" ]; then
    echo "❌ No plugins found in plugins/ directory"
    exit 1
fi

echo "📦 Found plugins:"
for plugin in $PLUGINS; do
    echo "  - $plugin"
done

echo ""

# Increment each plugin version
for plugin in $PLUGINS; do
    echo "🔢 Incrementing version for $plugin..."
    
    PLUGIN_DIR="plugins/$plugin"
    
    # Find the main plugin file
    PLUGIN_FILE=""
    if [ -f "$PLUGIN_DIR/$plugin.php" ]; then
        PLUGIN_FILE="$PLUGIN_DIR/$plugin.php"
    elif [ -f "$PLUGIN_DIR/$(echo $plugin | sed 's/wp-//').php" ]; then
        PLUGIN_FILE="$PLUGIN_DIR/$(echo $plugin | sed 's/wp-//').php"
    else
        # Find the first PHP file with plugin headers
        PLUGIN_FILE=$(find "$PLUGIN_DIR" -name "*.php" -exec grep -l "Plugin Name:" {} \; | head -1)
    fi
    
    if [ -z "$PLUGIN_FILE" ]; then
        echo "❌ Could not find main plugin file for $plugin"
        continue
    fi
    
    # Get current version from plugin file
    CURRENT_VERSION=$(grep "Version:" "$PLUGIN_FILE" | sed 's/.*Version: *//' | sed 's/ *$//' | head -1)
    if [ -z "$CURRENT_VERSION" ]; then
        echo "❌ Could not extract version from $PLUGIN_FILE"
        continue
    fi
    
    # Increment patch version (x.y.z -> x.y.z+1)
    NEW_VERSION=$(echo $CURRENT_VERSION | awk -F. '{$NF = $NF + 1;} 1' OFS=.)
    
    echo "   📄 File: $PLUGIN_FILE"
    echo "   📋 Current: $CURRENT_VERSION"
    echo "   🆕 New: $NEW_VERSION"
    
    # Update version in plugin header
    sed -i.bak "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" "$PLUGIN_FILE"
    
    # Update version constants (handle different naming patterns)
    if grep -q "define.*VERSION.*$CURRENT_VERSION" "$PLUGIN_FILE"; then
        sed -i.bak "s/define(\([^,]*VERSION[^,]*\), '$CURRENT_VERSION')/define(\1, '$NEW_VERSION')/" "$PLUGIN_FILE"
    fi
    
    # Remove backup file
    rm -f "$PLUGIN_FILE.bak"
    
    echo "   ✅ Updated $plugin: $CURRENT_VERSION → $NEW_VERSION"
    echo ""
done

echo "🎉 All plugin versions incremented!"
echo ""
echo "💡 Next steps:"
echo "   1. Run ./scripts/build-all.sh to build updated plugins"
echo "   2. Copy to Downloads folder"
echo "   3. Install in WordPress" 