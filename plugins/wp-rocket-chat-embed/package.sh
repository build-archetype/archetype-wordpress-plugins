#!/bin/bash

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" rocket-chat-embed.php | sed 's/.*Version: *//' | sed 's/ *$//')

# Split version into components
IFS='.' read -r -a VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR="${VERSION_PARTS[0]}"
MINOR="${VERSION_PARTS[1]}"
PATCH="${VERSION_PARTS[2]}"

# Increment patch version
NEW_PATCH=$((PATCH + 1))
NEW_VERSION="$MAJOR.$MINOR.$NEW_PATCH"

# Update version in plugin file
sed -i '' "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" rocket-chat-embed.php
sed -i '' "s/RCE_VERSION', '$CURRENT_VERSION'/RCE_VERSION', '$NEW_VERSION'/" rocket-chat-embed.php

# Create zip file in parent directory
cd ..
zip -r "rocket-chat-embed-v$NEW_VERSION.zip" rocket-chat-embed \
    -x "rocket-chat-embed/.git*" \
    -x "rocket-chat-embed/.DS_Store" \
    -x "rocket-chat-embed/package.sh" \
    -x "rocket-chat-embed/*.zip"

echo "Created rocket-chat-embed-v$NEW_VERSION.zip"
echo "Updated version from $CURRENT_VERSION to $NEW_VERSION" 