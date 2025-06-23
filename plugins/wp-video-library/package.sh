#!/bin/bash

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" video-library.php | sed 's/.*Version: *//' | sed 's/ *$//')

# Split version into components
IFS='.' read -r -a VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR="${VERSION_PARTS[0]}"
MINOR="${VERSION_PARTS[1]}"
PATCH="${VERSION_PARTS[2]}"

# Increment patch version
NEW_PATCH=$((PATCH + 1))
NEW_VERSION="$MAJOR.$MINOR.$NEW_PATCH"

# Update version in plugin file
sed -i '' "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" video-library.php
sed -i '' "s/VL_VERSION', '$CURRENT_VERSION'/VL_VERSION', '$NEW_VERSION'/" video-library.php

# Create zip file in parent directory
cd ..
zip -r "wp-video-library-v$NEW_VERSION.zip" wp-video-library \
    -x "wp-video-library/.git*" \
    -x "wp-video-library/.DS_Store" \
    -x "wp-video-library/package.sh" \
    -x "wp-video-library/*.zip" \
    -x "wp-video-library/.wordpress-org/*"

echo "Created wp-video-library-v$NEW_VERSION.zip"
echo "Updated version from $CURRENT_VERSION to $NEW_VERSION"
