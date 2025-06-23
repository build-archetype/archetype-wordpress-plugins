=== Ant Media Stream Access ===
Contributors: buildarchetype
Tags: streaming, ant media, video, live stream, access control
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure live stream access control with user tier-based routing for Ant Media Server.

== Description ==

Ant Media Stream Access provides secure access control for live streams based on user tiers. Perfect for membership sites, educational platforms, and premium content delivery.

**Free Features:**
* Single tier stream access
* Basic JWT token authentication
* WordPress user integration
* Simple stream embedding

**Premium Features:**
* Multi-tier access control (Platinum, Gold, Silver)
* Advanced JWT token management
* Secure stream routing
* Custom tier mapping
* Analytics and reporting
* Priority support

**Usage:**
Use the shortcode `[antmedia_stream]` to display streams based on user access level.

Supported parameters:
* `tier="gold"` - Force specific tier
* `width="100%"` - Set player width
* `height="450px"` - Set player height

== Installation ==

1. Upload plugin files to `/wp-content/plugins/ant-media-stream-access/`
2. Activate the plugin
3. Go to Settings → Ant Media Stream Access
4. Configure your Ant Media Server details
5. Set up user tier mappings
6. Use shortcode in posts/pages

== Frequently Asked Questions ==

= Do I need Ant Media Server? =
Yes, you need access to an Ant Media Server instance with proper configuration.

= How are user tiers determined? =
The plugin checks WordPress user roles and custom meta fields to determine access levels.

= Is the streaming secure? =
Yes, the plugin uses JWT tokens for secure stream access with configurable expiration times.

== Screenshots ==

1. Admin settings panel
2. Stream player in action
3. Tier configuration interface

== Changelog ==

= 1.0.0 =
* Initial release
* Basic stream access control
* JWT token authentication
* User tier integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Ant Media Stream Access plugin. 