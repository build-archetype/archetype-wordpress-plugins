#!/bin/bash

set -e

echo "🏗️  Building all WordPress plugins..."

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

# Build each plugin
SUCCESS_COUNT=0
FAILED_PLUGINS=()

for plugin in $PLUGINS; do
    echo "🔨 Building $plugin..."
    
    if ./scripts/package-plugin.sh "$plugin"; then
        echo "✅ $plugin built successfully"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        echo "❌ $plugin build failed"
        FAILED_PLUGINS+=("$plugin")
    fi
    
    echo "----------------------------------------"
done

echo ""
echo "📊 Build Summary:"
echo "✅ Successful: $SUCCESS_COUNT"
echo "❌ Failed: ${#FAILED_PLUGINS[@]}"

if [ ${#FAILED_PLUGINS[@]} -gt 0 ]; then
    echo ""
    echo "Failed plugins:"
    for plugin in "${FAILED_PLUGINS[@]}"; do
        echo "  - $plugin"
    done
    exit 1
fi

echo ""
echo "🎉 All plugins built successfully!"

# List all generated packages
if [ -d "build" ]; then
    echo ""
    echo "📦 Generated packages:"
    ls -la build/*.zip 2>/dev/null || echo "No zip files found"
fi
