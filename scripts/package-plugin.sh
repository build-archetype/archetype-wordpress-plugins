#!/bin/bash

set -e

PLUGIN_NAME=$1
if [ -z "$PLUGIN_NAME" ]; then
    echo "Usage: $0 <plugin-name>"
    echo "Available plugins:"
    ls -1 plugins/
    exit 1
fi

PLUGIN_DIR="plugins/$PLUGIN_NAME"
if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Plugin directory not found: $PLUGIN_DIR"
    echo "Available plugins:"
    ls -1 plugins/
    exit 1
fi

echo "📦 Packaging plugin: $PLUGIN_NAME"

# Find the main plugin file
PLUGIN_FILE=""
if [ -f "$PLUGIN_DIR/$PLUGIN_NAME.php" ]; then
    PLUGIN_FILE="$PLUGIN_DIR/$PLUGIN_NAME.php"
elif [ -f "$PLUGIN_DIR/$(echo $PLUGIN_NAME | sed 's/wp-//').php" ]; then
    PLUGIN_FILE="$PLUGIN_DIR/$(echo $PLUGIN_NAME | sed 's/wp-//').php"
else
    # Find the first PHP file with plugin headers
    PLUGIN_FILE=$(find "$PLUGIN_DIR" -name "*.php" -exec grep -l "Plugin Name:" {} \; | head -1)
fi

if [ -z "$PLUGIN_FILE" ]; then
    echo "❌ Could not find main plugin file for $PLUGIN_NAME"
    exit 1
fi

echo "📄 Main plugin file: $PLUGIN_FILE"

# Get version from plugin file
VERSION=$(grep "Version:" "$PLUGIN_FILE" | sed 's/.*Version: *//' | sed 's/ *$//')
if [ -z "$VERSION" ]; then
    echo "❌ Could not extract version from $PLUGIN_FILE"
    exit 1
fi

echo "🏷️  Plugin version: $VERSION"

# Create build directory
BUILD_DIR="build"
mkdir -p "$BUILD_DIR"

# Create zip package excluding development files
ZIP_FILE="$BUILD_DIR/$PLUGIN_NAME-v$VERSION.zip"

echo "🗜️  Creating package: $ZIP_FILE"

cd "$PLUGIN_DIR"
zip -r "../../$ZIP_FILE" . \
    -x "*.git*" \
    -x "*.DS_Store" \
    -x "node_modules/*" \
    -x "src/*" \
    -x "*.log" \
    -x "package.json" \
    -x "package-lock.json" \
    -x "yarn.lock" \
    -x "webpack.config.js" \
    -x "*.md" \
    -x "tests/*" \
    -x ".github/*" \
    -x "*.zip"

cd ../..

# Verify the package
if [ -f "$ZIP_FILE" ]; then
    SIZE=$(du -h "$ZIP_FILE" | cut -f1)
    echo "✅ Package created successfully: $ZIP_FILE ($SIZE)"
    
    # List contents for verification
    echo "📋 Package contents:"
    unzip -l "$ZIP_FILE" | head -20
    
    if [ $(unzip -l "$ZIP_FILE" | wc -l) -gt 20 ]; then
        echo "... and $(( $(unzip -l "$ZIP_FILE" | wc -l) - 20 )) more files"
    fi
else
    echo "❌ Failed to create package"
    exit 1
fi

echo "🎉 Plugin $PLUGIN_NAME v$VERSION packaged successfully!"
