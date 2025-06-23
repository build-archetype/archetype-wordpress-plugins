# WordPress.org Submission Guide

## 🎯 **Quick Start for Rocket.Chat Embed**

### 1. **Create WordPress.org Account**

- Sign up: [https://wordpress.org/support/register.php](https://wordpress.org/support/register.php)
- Verify email and complete profile

### 2. **Submit Plugin for Review**

Submit at: [https://wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/)

```
Plugin Name: Rocket.Chat Embed
Plugin Description: Easily embed Rocket.Chat in your WordPress site with simple shortcode integration. Connect your Rocket.Chat server and display chat widgets anywhere using [rocketchat_iframe] shortcode.
Plugin URL: https://github.com/yourusername/wp-rocket-chat-embed
```

### 3. **Prepare Assets**

Create these images in `.wordpress-org/` directory:

- `banner-1544x500.jpg` - Main banner
- `banner-772x250.jpg` - Smaller banner
- `icon-128x128.png` - Plugin icon
- `icon-256x256.png` - High-res icon
- `screenshot-1.jpg` - Settings page
- `screenshot-2.jpg` - Chat widget
- `screenshot-3.jpg` - License management

### 4. **Wait for Approval (7-14 days)**

WordPress team will email you with:

- SVN repository access
- Your plugin slug (usually matches name)

### 5. **Set Up GitHub Secrets**

After approval, add to GitHub repository secrets:

```
SVN_USERNAME: your-wordpress-org-username
SVN_PASSWORD: your-wordpress-org-password
```

### 6. **Deploy via Git Tags**

```bash
git tag v1.0.0
git push origin v1.0.0
```

GitHub Actions will automatically deploy to WordPress.org!

## 🚀 **Free vs Premium Strategy**

**Free Version Features:**

- Basic Rocket.Chat embedding
- Simple configuration
- WordPress user integration
- Chat state control

**Premium Version Features:**

- SSO integration
- Auto-login
- Custom CSS styling
- Guest access support
- Priority support

## 📈 **Success Tips**

1. **Respond quickly** to WordPress.org support tickets
2. **Maintain high ratings** by providing good support
3. **Update regularly** to fix issues and add features
4. **Market premium version** through upgrade notices
5. **Track conversions** from free to premium

## 🛠️ **Development Workflow**

```bash
# Feature development
git checkout -b feature/new-feature
# Make changes...
git commit -m "Add new feature"
git push origin feature/new-feature

# Release
git checkout main
git merge feature/new-feature
git tag v1.0.1
git push origin main --tags
# Auto-deploys via GitHub Actions
```

## 📊 **Monitor Performance**

Track these metrics:

- WordPress.org downloads
- Active installations
- User ratings/reviews
- Support ticket volume
- Premium conversion rate

The license system is already implemented - users just need to enter their Lemon Squeezy license key to unlock premium features!
