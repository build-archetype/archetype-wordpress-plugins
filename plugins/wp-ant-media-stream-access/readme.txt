=== Ant Media Stream Access ===
Contributors: buildarchetype
Tags: streaming, ant media, video, live stream, access control, jwt, rocket chat
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure live stream access control with user tier-based routing and Rocket Chat integration for Ant Media Server.

== Description ==

Ant Media Stream Access provides advanced secure access control for live streams based on user tiers. Perfect for membership sites, educational platforms, and premium content delivery with integrated chat functionality.

**Key Features:**
* **Real-time Webhook Notifications** - TRUE 0-second stream detection (RECOMMENDED)
* Multi-tier access control (Platinum, Gold, Silver)
* JWT token authentication for secure streams
* WordPress user role and meta integration
* Rocket Chat integration with stream-based visibility
* Elementor widgets for easy layout design
* WordPress Heartbeat fallback monitoring (5-second intervals)
* Comprehensive debug logging and diagnostics

**Stream Access Control:**
Users are automatically assigned stream access based on their WordPress role or custom meta fields. The plugin supports three tiers: Platinum (premium content), Gold (standard content), and Silver (basic content).

**Rocket Chat Integration:**
Chat widgets automatically show/hide based on stream status. When streams are live, chat appears. When all streams are offline, chat is hidden. Perfect for live event engagement.

**Usage:**
Use the shortcode `[antmedia_stream]` to display streams based on user access level, or use the combined stream+chat Elementor widget for advanced layouts.

Supported shortcode parameters:
* `width="100%"` - Set player width  
* `height="450px"` - Set player height
* `format="hls"` - Stream format (hls, webrtc, embed)
* `controls="true"` - Show player controls
* `autoplay="false"` - Auto-play streams

== Installation ==

1. Upload plugin files to `/wp-content/plugins/ant-media-stream-access/`
2. Activate the plugin
3. Go to Settings → Ant Media Stream Access
4. Configure your Ant Media Server details and JWT secret
5. Set up user tier mappings for your streams
6. Optionally configure Rocket Chat integration
7. Use shortcode in posts/pages or Elementor widgets

== Frequently Asked Questions ==

= Do I need Ant Media Server? =
Yes, you need access to an Ant Media Server instance with JWT authentication configured.

= How are user tiers determined? =
The plugin checks WordPress user roles (platinum, gold, silver) and custom meta fields to determine access levels. All logged-in users get silver tier by default.

= Is the streaming secure? =
Yes, the plugin uses JWT tokens for secure stream access with configurable expiration times and user validation.

= Does this work with Rocket Chat? =
Yes, the plugin integrates with wp-rocket-chat-embed to show/hide chat based on stream status automatically.

= Can I use this with Elementor? =
Yes, the plugin provides both individual stream widgets and combined stream+chat widgets for Elementor.

== Screenshots ==

1. Admin settings panel with server configuration
2. Stream player with tier-based access control
3. Rocket Chat integration settings
4. Combined stream and chat Elementor widget
5. Debug logging interface

== Changelog ==

= 2.0.5 =
* Fixed function name conflicts (amsa_get_time_ago, amsa_get_user_tier)
* Enhanced Rocket Chat integration stability
* Improved error handling and logging
* Updated build system compatibility

= 2.0.0 =
* Added Rocket Chat integration
* New combined stream+chat Elementor widgets  
* Enhanced JWT token management
* Improved user tier detection
* Real-time stream status monitoring
* Advanced admin settings interface

= 1.0.0 =
* Initial release
* Basic stream access control
* JWT token authentication
* User tier integration

== Upgrade Notice ==

= 2.0.5 =
Important update fixing function conflicts with other plugins. Recommended for all users.

= 2.0.0 =
Major update adding Rocket Chat integration and enhanced streaming features. Please backup before upgrading. 