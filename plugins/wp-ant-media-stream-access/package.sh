#!/bin/bash

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" wp-ant-media-stream-access.php | sed 's/.*Version: *//' | sed 's/ *$//')

# Split version into components
IFS='.' read -r -a VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR="${VERSION_PARTS[0]}"
MINOR="${VERSION_PARTS[1]}"
PATCH="${VERSION_PARTS[2]}"

# Increment patch version
NEW_PATCH=$((PATCH + 1))
NEW_VERSION="$MAJOR.$MINOR.$NEW_PATCH"

# Update version in plugin file
sed -i '' "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" wp-ant-media-stream-access.php
sed -i '' "s/AMSA_VERSION', '$CURRENT_VERSION'/AMSA_VERSION', '$NEW_VERSION'/" wp-ant-media-stream-access.php

# Create zip file in parent directory
cd ..
zip -r "wp-ant-media-stream-access-v$NEW_VERSION.zip" wp-ant-media-stream-access \
    -x "wp-ant-media-stream-access/.git*" \
    -x "wp-ant-media-stream-access/.DS_Store" \
    -x "wp-ant-media-stream-access/package.sh" \
    -x "wp-ant-media-stream-access/*.zip"

echo "Created wp-ant-media-stream-access-v$NEW_VERSION.zip"
echo "Updated version from $CURRENT_VERSION to $NEW_VERSION" 