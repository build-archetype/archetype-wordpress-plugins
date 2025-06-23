# Solution 2: Consolidated Repository with GitHub Actions

If you prefer to have all plugins in a single repository with automated publishing, here's how to set it up:

## 🏗️ Repository Structure

```
archetype-wordpress-plugins/
├── .github/
│   └── workflows/
│       ├── deploy-plugins.yml
│       ├── test-plugins.yml
│       └── release.yml
├── plugins/
│   ├── wp-ant-media-stream-access/
│   ├── wp-video-library/
│   └── wp-rocket-chat-embed/
├── scripts/
│   ├── build-all.sh
│   ├── deploy-plugin.sh
│   └── package-plugin.sh
├── tests/
├── docker-compose.yml
├── dev-setup.sh
├── dev-helpers.sh
└── README.md
```

## 📋 Setup Instructions

### 1. Create Repository on GitHub

```bash
# In the build archetype org
gh repo create build-archetype/archetype-wordpress-plugins --public
```

### 2. GitHub Actions Workflow

Create `.github/workflows/deploy-plugins.yml`:

```yaml
name: Deploy WordPress Plugins

on:
  push:
    tags:
      - 'v*'
  workflow_dispatch:
    inputs:
      plugin:
        description: 'Plugin to deploy'
        required: true
        type: choice
        options:
        - wp-ant-media-stream-access
        - wp-video-library
        - wp-rocket-chat-embed
        - all

jobs:
  detect-changes:
    runs-on: ubuntu-latest
    outputs:
      plugins: ${{ steps.changes.outputs.plugins }}
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      
      - name: Detect changed plugins
        id: changes
        run: |
          if [ "${{ github.event.inputs.plugin }}" = "all" ] || [ "${{ github.event_name }}" = "push" ]; then
            echo "plugins=[\"wp-ant-media-stream-access\",\"wp-video-library\",\"wp-rocket-chat-embed\"]" >> $GITHUB_OUTPUT
          elif [ -n "${{ github.event.inputs.plugin }}" ]; then
            echo "plugins=[\"${{ github.event.inputs.plugin }}\"]" >> $GITHUB_OUTPUT
          else
            # Detect changes since last tag
            LAST_TAG=$(git describe --tags --abbrev=0 HEAD^ 2>/dev/null || echo "")
            if [ -z "$LAST_TAG" ]; then
              CHANGED_PLUGINS=$(find plugins/ -mindepth 1 -maxdepth 1 -type d -exec basename {} \;)
            else
              CHANGED_PLUGINS=$(git diff --name-only $LAST_TAG..HEAD | grep '^plugins/' | cut -d'/' -f2 | sort -u)
            fi
            PLUGINS_JSON=$(echo "$CHANGED_PLUGINS" | jq -R -s -c 'split("\n")[:-1]')
            echo "plugins=$PLUGINS_JSON" >> $GITHUB_OUTPUT
          fi

  deploy:
    needs: detect-changes
    if: ${{ needs.detect-changes.outputs.plugins != '[]' }}
    runs-on: ubuntu-latest
    strategy:
      matrix:
        plugin: ${{ fromJSON(needs.detect-changes.outputs.plugins) }}
    
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '18'

      - name: Get plugin version
        id: version
        run: |
          VERSION=$(grep "Version:" plugins/${{ matrix.plugin }}/${{ matrix.plugin }}.php | sed 's/.*Version: *//' | sed 's/ *$//')
          echo "version=$VERSION" >> $GITHUB_OUTPUT

      - name: Build plugin
        run: |
          cd plugins/${{ matrix.plugin }}
          if [ -f package.json ]; then
            npm install
            npm run build
          fi

      - name: Package plugin
        run: |
          ./scripts/package-plugin.sh ${{ matrix.plugin }}

      - name: Deploy to WordPress.org
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SLUG: ${{ matrix.plugin }}
          BUILD_DIR: ./plugins/${{ matrix.plugin }}
          ASSETS_DIR: ./plugins/${{ matrix.plugin }}/.wordpress-org
        with:
          generate-zip: true

      - name: Create Release
        uses: actions/create-release@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tag_name: ${{ matrix.plugin }}-v${{ steps.version.outputs.version }}
          release_name: ${{ matrix.plugin }} v${{ steps.version.outputs.version }}
          draft: false
          prerelease: false
```

### 3. Plugin Packaging Script

Create `scripts/package-plugin.sh`:

```bash
#!/bin/bash

PLUGIN_NAME=$1
if [ -z "$PLUGIN_NAME" ]; then
    echo "Usage: $0 <plugin-name>"
    exit 1
fi

PLUGIN_DIR="plugins/$PLUGIN_NAME"
if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Plugin directory not found: $PLUGIN_DIR"
    exit 1
fi

# Get version from plugin file
VERSION=$(grep "Version:" "$PLUGIN_DIR/$PLUGIN_NAME.php" | sed 's/.*Version: *//' | sed 's/ *$//')

# Create build directory
BUILD_DIR="build"
mkdir -p "$BUILD_DIR"

# Create zip package
zip -r "$BUILD_DIR/$PLUGIN_NAME-v$VERSION.zip" "$PLUGIN_DIR" \
    -x "$PLUGIN_DIR/.git*" \
    -x "$PLUGIN_DIR/.DS_Store" \
    -x "$PLUGIN_DIR/node_modules/*" \
    -x "$PLUGIN_DIR/src/*" \
    -x "$PLUGIN_DIR/*.log"

echo "Created package: $BUILD_DIR/$PLUGIN_NAME-v$VERSION.zip"
```

### 4. Testing Workflow

Create `.github/workflows/test-plugins.yml`:

```yaml
name: Test WordPress Plugins

on:
  pull_request:
    paths:
      - 'plugins/**'
  push:
    branches: [ main, develop ]
    paths:
      - 'plugins/**'

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: [7.4, 8.0, 8.1, 8.2]
        wordpress-version: [5.9, 6.0, 6.1, 6.2, 6.3]
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mysqli, zip, gd
          tools: composer, phpunit

      - name: Setup WordPress
        run: |
          wget https://wordpress.org/wordpress-${{ matrix.wordpress-version }}.tar.gz
          tar -xzf wordpress-${{ matrix.wordpress-version }}.tar.gz
          cp -r plugins/* wordpress/wp-content/plugins/

      - name: Configure WordPress
        run: |
          cd wordpress
          cp wp-config-sample.php wp-config.php
          sed -i 's/database_name_here/wordpress_test/' wp-config.php
          sed -i 's/username_here/root/' wp-config.php
          sed -i 's/password_here/root/' wp-config.php
          sed -i 's/localhost/127.0.0.1/' wp-config.php

      - name: Install WordPress
        run: |
          cd wordpress
          php -S localhost:8000 &
          sleep 5
          curl -d "weblog_title=Test&user_name=admin&admin_password=password&admin_password2=password&admin_email=test@test.com" http://localhost:8000/wp-admin/install.php?step=2

      - name: Run Plugin Tests
        run: |
          # Add your plugin-specific tests here
          echo "Running plugin tests..."
          # Example: PHPUnit tests, WordPress coding standards, etc.
```

## 🚀 Deployment Process

### Automatic Deployment
1. **Make changes** to any plugin in the `plugins/` directory
2. **Commit and push** to main branch
3. **Create a tag** for the release:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```
4. **GitHub Actions automatically:**
   - Detects changed plugins
   - Builds and packages them
   - Deploys to WordPress.org
   - Creates GitHub releases

### Manual Deployment
Use workflow dispatch to deploy specific plugins:
1. Go to Actions tab in GitHub
2. Select "Deploy WordPress Plugins"
3. Choose plugin to deploy
4. Run workflow

## 🔧 Repository Secrets

Add these secrets to your GitHub repository:

```
SVN_USERNAME     # WordPress.org username
SVN_PASSWORD     # WordPress.org password
```

## 📦 Migration Steps

To migrate your existing plugins to this structure:

```bash
# 1. Create new repository
git clone https://github.com/build-archetype/archetype-wordpress-plugins.git
cd archetype-wordpress-plugins

# 2. Copy your plugins
mkdir -p plugins
cp -r /path/to/wp-ant-media-stream-access plugins/
cp -r /path/to/wp-video-library plugins/
cp -r /path/to/wp-rocket-chat-embed plugins/

# 3. Copy the development environment files
cp /path/to/docker-compose.yml .
cp /path/to/dev-setup.sh .
cp /path/to/dev-helpers.sh .

# 4. Update docker-compose.yml plugin paths
# Change "./wp-plugin-name/" to "./plugins/wp-plugin-name/"

# 5. Commit and push
git add .
git commit -m "Initial migration of WordPress plugins"
git push origin main

# 6. Create first release
git tag v1.0.0
git push origin v1.0.0
```

## ✅ Benefits of Consolidated Approach

- **Single repository** for all plugins
- **Centralized CI/CD** pipeline
- **Shared development environment**
- **Unified testing** across plugins
- **Easier dependency management**
- **Consistent coding standards**

## ⚖️ Trade-offs

**Pros:**
- Easier to manage multiple plugins
- Shared tooling and configuration
- Better for team collaboration
- Unified release process

**Cons:**
- Larger repository size
- All plugins must use same WordPress/PHP versions
- More complex deployment logic
- Potential for merge conflicts

## 🎯 Recommendation

For **development and testing**, use the **local Docker environment** (Solution 1) as it provides:
- Instant hot reloading
- Full debugging capabilities
- Isolated testing environment
- No deployment overhead

For **production deployment**, consider the **consolidated repository** approach if:
- You want centralized management
- You have multiple plugins to maintain
- You prefer unified CI/CD pipelines
- Your team collaborates on multiple plugins

Both solutions can work together - use local development for coding and testing, then push to the consolidated repo for deployment! 