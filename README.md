# Archetype WordPress Plugins Monorepo

A consolidated repository for all WordPress plugins developed by Archetype, featuring automated deployment, comprehensive testing, and a complete local development environment with hot reloading.

[![Deploy WordPress Plugins](https://github.com/build-archetype/archetype-wordpress-plugins/workflows/Deploy%20WordPress%20Plugins/badge.svg)](https://github.com/build-archetype/archetype-wordpress-plugins/actions)
[![Test WordPress Plugins](https://github.com/build-archetype/archetype-wordpress-plugins/workflows/Test%20WordPress%20Plugins/badge.svg)](https://github.com/build-archetype/archetype-wordpress-plugins/actions)

## 🚀 Quick Start

### Prerequisites
- [Docker](https://www.docker.com/get-started) installed and running
- [GitHub CLI](https://cli.github.com/) (for repository management)
- Git

### Local Development Setup
1. **Clone and start the environment:**
   ```bash
   git clone https://github.com/build-archetype/archetype-wordpress-plugins.git
   cd archetype-wordpress-plugins
   chmod +x dev-setup.sh dev-helpers.sh scripts/*.sh
   ./dev-setup.sh
   ```

2. **Access your development site:**
   - **WordPress Site:** http://localhost:8080
   - **Admin Panel:** http://localhost:8080/wp-admin
     - Username: `admin`
     - Password: `password`
   - **phpMyAdmin:** http://localhost:8082

## 📦 Included Plugins

### 1. WP Ant Media Stream Access
- **Directory:** `plugins/wp-ant-media-stream-access/`
- **Description:** Advanced streaming media access control and management
- **Main File:** `wp-ant-media-stream-access.php`

### 2. WP Video Library
- **Directory:** `plugins/wp-video-library/`
- **Description:** Comprehensive video library management system
- **Main File:** `video-library.php`

### 3. WP Rocket Chat Embed
- **Directory:** `plugins/wp-rocket-chat-embed/`
- **Description:** Easy integration of Rocket Chat into WordPress
- **Main File:** `rocket-chat-embed.php`

## 🔄 Hot Reloading Development

Your plugin files are directly mounted into the WordPress container. Any changes you make to files in:
- `plugins/wp-ant-media-stream-access/`
- `plugins/wp-video-library/`
- `plugins/wp-rocket-chat-embed/`

Will be **immediately visible** in your WordPress site! No need to rebuild or restart.

## 🛠️ Development Commands

### Environment Management
```bash
# Start/stop environment
./dev-helpers.sh start
./dev-helpers.sh stop
./dev-helpers.sh restart

# View logs and debug
./dev-helpers.sh logs
./dev-helpers.sh debug-on
./dev-helpers.sh debug-off

# Environment status
./dev-helpers.sh status
```

### Plugin Management
```bash
# Build all plugins
./scripts/build-all.sh

# Build specific plugin
./scripts/package-plugin.sh wp-ant-media-stream-access

# WordPress CLI
./dev-helpers.sh wp plugin list
./dev-helpers.sh wp [any-wp-cli-command]
```

### Database Operations
```bash
# Backup database
./dev-helpers.sh backup

# Restore database
./dev-helpers.sh restore backup-20240101-120000.sql
```

## 🚀 Automated Deployment & CI/CD

### GitHub Actions Workflows

#### 1. **Deploy WordPress Plugins** (`.github/workflows/deploy-plugins.yml`)
- **Triggers:** 
  - Tag creation (`v*`)
  - Manual workflow dispatch
- **Features:**
  - Automatic change detection
  - Plugin versioning
  - WordPress.org deployment
  - GitHub releases
  - Artifact generation

#### 2. **Test WordPress Plugins** (`.github/workflows/test-plugins.yml`)
- **Triggers:** 
  - Pull requests affecting `plugins/`
  - Push to `main`/`develop` branches
- **Features:**
  - PHP lint checking
  - WordPress coding standards
  - Multi-version testing (PHP 7.4-8.2, WordPress 6.0-6.4)
  - Plugin header validation

### Deployment Options

#### Automatic Deployment (Recommended)
1. **Make changes** to any plugin in the `plugins/` directory
2. **Commit and push** to main branch
3. **Create a version tag:**
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```
4. **GitHub Actions automatically:**
   - Detects changed plugins
   - Builds and packages them
   - Deploys to WordPress.org
   - Creates GitHub releases

#### Manual Deployment
Use workflow dispatch for selective deployment:
1. Go to **Actions** tab in GitHub
2. Select **"Deploy WordPress Plugins"**
3. Choose plugin to deploy (or "all")
4. Click **"Run workflow"**

## 🏗️ Repository Structure

```
archetype-wordpress-plugins/
├── .github/
│   └── workflows/
│       ├── deploy-plugins.yml    # Deployment automation
│       └── test-plugins.yml      # Testing automation
├── plugins/
│   ├── wp-ant-media-stream-access/
│   ├── wp-video-library/
│   └── wp-rocket-chat-embed/
├── scripts/
│   ├── build-all.sh             # Build all plugins
│   └── package-plugin.sh        # Package individual plugin
├── tests/                       # Shared test utilities
├── docker-compose.yml           # Development environment
├── dev-setup.sh                # Environment setup
├── dev-helpers.sh               # Development utilities
└── README.md
```

## 🔧 Configuration & Secrets

### Required GitHub Secrets
Add these secrets to your GitHub repository for WordPress.org deployment:

```
SVN_USERNAME     # WordPress.org username
SVN_PASSWORD     # WordPress.org password/app password
```

### Environment Variables
The development environment supports customization through environment variables:

```yaml
# docker-compose.override.yml
services:
  wordpress:
    environment:
      WP_MEMORY_LIMIT: 256M
      WORDPRESS_DEBUG: 1
      WP_DEBUG_LOG: 1
```

## 🐛 Debugging & Development Tools

### Pre-installed Development Plugins
- **Query Monitor:** Performance monitoring and debugging
- **Debug Bar:** Additional debugging information

### Debug Commands
```bash
# Enable debug mode
./dev-helpers.sh debug-on

# View WordPress logs
./dev-helpers.sh logs

# Access WordPress shell
./dev-helpers.sh shell

# Access database shell
./dev-helpers.sh db-shell
```

### Log Files
- **WordPress Debug Log:** Inside container at `/var/www/html/wp-content/debug.log`
- **Container Logs:** `docker-compose logs wordpress`

## 🧪 Testing

### Local Testing
```bash
# Run all tests
./scripts/build-all.sh

# Test specific plugin
./scripts/package-plugin.sh wp-video-library

# WordPress coding standards
phpcs --standard=WordPress plugins/
```

### Automated Testing
- **Continuous Integration:** Tests run on every push and pull request
- **Multi-version Testing:** PHP 7.4-8.2 × WordPress 6.0-6.4
- **Code Quality:** WordPress coding standards validation
- **Plugin Integrity:** Header validation and syntax checking

## 🤝 Contributing

### Development Workflow
1. **Clone the repository**
2. **Create a feature branch:**
   ```bash
   git checkout -b feature/my-new-feature
   ```
3. **Make changes** to plugin files
4. **Test locally** using the development environment
5. **Commit and push:**
   ```bash
   git add .
   git commit -m "Add new feature"
   git push origin feature/my-new-feature
   ```
6. **Create a pull request**

### Adding New Plugins
1. **Create plugin directory:**
   ```bash
   mkdir plugins/my-new-plugin
   ```
2. **Add plugin files** with proper WordPress headers
3. **Update docker-compose.yml** to mount the new plugin:
   ```yaml
   volumes:
     - ./plugins/my-new-plugin:/var/www/html/wp-content/plugins/my-new-plugin
   ```
4. **Update workflows** to include the new plugin in deployment options
5. **Test and submit PR**

### Version Management
- **Development:** Work in feature branches
- **Releases:** Use semantic versioning tags (`v1.0.0`)
- **Plugin Versions:** Update version in main plugin file
- **Changelog:** Document changes in plugin README files

## 📋 Troubleshooting

### Common Issues

**Port Conflicts:**
```bash
# Change ports in docker-compose.yml
ports:
  - "9090:80"  # WordPress (instead of 8080)
  - "9091:80"  # phpMyAdmin (instead of 8082)
```

**Permission Issues:**
```bash
# Fix file permissions
sudo chown -R $USER:$USER .
chmod +x scripts/*.sh dev-*.sh
```

**Plugin Not Appearing:**
```bash
# Check plugin mounting
./dev-helpers.sh shell
ls -la /var/www/html/wp-content/plugins/
```

**Database Connection Issues:**
```bash
# Reset environment
./dev-helpers.sh reset
```

### Getting Help
- **Check logs:** `./dev-helpers.sh logs`
- **Environment status:** `./dev-helpers.sh status`
- **Access container:** `./dev-helpers.sh shell`
- **GitHub Issues:** [Create an issue](https://github.com/build-archetype/archetype-wordpress-plugins/issues)

## 📄 License

This project is licensed under the GPL v2 or later - see individual plugin files for specific license information.

## 🔗 Links

- **WordPress.org Plugin Directory:** [View Published Plugins](https://wordpress.org/plugins/search/archetype/)
- **GitHub Actions:** [View Workflows](https://github.com/build-archetype/archetype-wordpress-plugins/actions)
- **Issues & Support:** [GitHub Issues](https://github.com/build-archetype/archetype-wordpress-plugins/issues)

---

**Built with ❤️ by [Archetype Services](https://archetype.services)**
