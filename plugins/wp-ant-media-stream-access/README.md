# WP Ant Media Stream Access - Simple Iframe Integration

A WordPress plugin that provides simple iframe embedding for Ant Media Server streams with automatic stream status detection and customizable offline messages.

## Overview

This plugin simplifies Ant Media Server integration by:
This WordPress plugin provides secure access control for live streams hosted on Ant Media Server. It automatically detects user tiers (platinum, gold, silver) and grants access to appropriate streams using JWT token authentication. Perfect for premium content, tiered memberships, and secure streaming.

---

## Features

### Core Functionality

- **User Tier Detection:** Automatically detects user tier from WordPress roles or custom meta fields
- **JWT Token Authentication:** Secure stream access using signed JWT tokens
- **Stream Routing:** Routes users to appropriate streams based on their tier level
- **Multiple Stream Formats:** Supports HLS, WebRTC, and embedded player formats
- **External Control:** WordPress hooks for programmatic access control
- **Debug & Logging:** Comprehensive logging system for troubleshooting

### User Experience

- **Seamless Integration:** Simple shortcode and Elementor widget
- **Responsive Design:** Works on desktop and mobile devices
- **Auto-tier Assignment:** Default silver tier for all logged-in users
- **Access Control:** Clear messaging when streams are unavailable
- **Multiple Player Options:** HLS, embedded iframe, or WebRTC playback

### Administration

- **Easy Configuration:** Simple admin interface for all settings
- **Stream Mapping:** JSON-based tier-to-stream configuration
- **Token Management:** Configurable token expiry times
- **Access Toggle:** Quick enable/disable for maintenance
- **Real-time Testing:** Built-in token generation testing

---

## Setup Requirements

### For Basic Setup

- WordPress site with user authentication
- Running Ant Media Server (cloud or self-hosted)
- JWT secret key configured in Ant Media Server
- Live streams published to your Ant Media Server

### For Advanced Features

- Custom WordPress user roles (platinum, gold, silver) OR user meta fields
- Ant Media Server with proper CORS and embedding permissions
- SSL certificate for secure token transmission

---

## Quick Start

1. **Install the plugin** on your WordPress site
2. **Configure settings** in WordPress Admin → Settings → Ant Media Stream Access:
   - Set your Ant Media Server URL
   - Add your JWT secret key
   - Configure stream mappings for each tier
3. **Add the shortcode** `[antmedia_stream]` to any page or post
4. **Assign user tiers** using WordPress roles or user meta fields
5. **Test access** with different user accounts

---

## Configuration

### Stream Tier Mapping

Configure which streams users can access based on their tier:

```json
{
  "platinum": "premium_hd_stream",
  "gold": "standard_stream",
  "silver": "basic_stream"
}
```

### User Tier Assignment

**Option 1: WordPress Roles**

- Create custom roles: `platinum`, `gold`, `silver`
- Assign users to appropriate roles

**Option 2: User Meta Fields**

- Set user meta key: `stream_tier`
- Values: `platinum`, `gold`, or `silver`

**Fallback:** All logged-in users without specific assignment get `silver` tier

---

## Usage Examples

### Basic Shortcode

```php
[antmedia_stream]
```

### Customized Player

```php
[antmedia_stream width="800px" height="600px" format="hls" controls="true" autoplay="false"]
```

### Admin Direct Access

```php
[antmedia_stream_direct stream_id="specific_stream_123"]
```

### External Control (PHP)

```php
// Enable/disable stream access programmatically
set_stream_access_state(false); // Disable all streams

// Hook into state changes
add_action('ant_media_access_state_changed', function($is_enabled) {
    if ($is_enabled) {
        // Streams were enabled
        error_log('Live streaming is now active');
    } else {
        // Streams were disabled
        error_log('Live streaming disabled for maintenance');
    }
});

// Filter display logic
add_filter('ant_media_should_display_stream', function($should_display) {
    // Custom logic for when streams should be shown
    if (is_page('maintenance')) {
        return false; // Never show on maintenance page
    }
    return $should_display;
});
```

---

## WordPress Hooks

### Action Hooks

```php
// Triggered when stream access state changes
do_action('ant_media_access_state_changed', $is_enabled);
```

### Filter Hooks

```php
// Filter whether streams should be displayed
apply_filters('ant_media_should_display_stream', $should_display);

// Filter JWT token payload before signing
apply_filters('ant_media_jwt_payload', $payload, $stream_id, $user_id);

// Filter user stream access permissions
apply_filters('ant_media_user_can_access_stream', $has_access, $stream_id, $user_id);
```

---

## Shortcode Parameters

| Parameter  | Default | Description                             |
| ---------- | ------- | --------------------------------------- |
| `width`    | `100%`  | Player width (px or %)                  |
| `height`   | `500`   | Player height (px)                      |
| `format`   | `hls`   | Stream format: `hls`, `embed`, `webrtc` |
| `controls` | `true`  | Show player controls: `true`/`false`    |
| `autoplay` | `false` | Auto-start playback: `true`/`false`     |
| `style`    | `""`    | Custom CSS styles                       |

---

## Security Features

- **JWT Token Authentication:** All stream access uses signed JWT tokens
- **Token Expiration:** Configurable token expiry (5 minutes to 24 hours)
- **User Validation:** Tokens include user ID and tier for server-side validation
- **Secure Transmission:** HTTPS recommended for token security
- **Access Logging:** All access attempts are logged for auditing

---

## Troubleshooting

### Common Issues

1. **"Unable to generate stream access token"**

   - Check JWT secret key configuration
   - Verify user is logged in and has appropriate tier

2. **"No stream available for your tier"**

   - Check user tier assignment
   - Verify stream mapping configuration

3. **Player shows but no video loads**
   - Verify Ant Media Server URL is correct
   - Check stream is actually live and published
   - Ensure CORS is properly configured on Ant Media Server

### Debug Mode

Enable debug mode in plugin settings to:

- View detailed logs in admin interface
- See user tier detection process
- Monitor JWT token generation
- Track stream access attempts

---

## Version Comparison

| Feature                  | Basic Setup | Advanced Setup |
| ------------------------ | :---------: | :------------: |
| Stream tier routing      |     ✅      |       ✅       |
| JWT authentication       |     ✅      |       ✅       |
| HLS/WebRTC/Embed support |     ✅      |       ✅       |
| WordPress hooks          |     ✅      |       ✅       |
| Elementor widget         |     ✅      |       ✅       |
| Custom user roles        |     ❌      |       ✅       |
| Advanced access control  |     ❌      |       ✅       |
| Custom stream mapping    |     ❌      |       ✅       |
| Detailed logging         |     ❌      |       ✅       |

---

## Technical Requirements

- **WordPress:** 5.0+
- **PHP:** 7.4+
- **Ant Media Server:** Any version with JWT support
- **Browser Support:** Modern browsers with HTML5 video support
- **Optional:** Elementor for drag-and-drop widget support

---

## Support & Development

This plugin provides a robust foundation for secure streaming access control. The modular architecture makes it easy to extend with custom tier detection logic, additional stream formats, or integration with membership plugins.

---

## Disclaimer

This plugin is designed to work with Ant Media Server but is not affiliated with or endorsed by Ant Media. Ant Media is a trademark of Ant Media Inc.

# WP Ant Media Stream Access - Simple Iframe Integration

A WordPress plugin that provides simple iframe embedding for Ant Media Server streams with automatic stream status detection and customizable offline messages.

## Overview

This plugin simplifies Ant Media Server integration by:
- Using Ant Media's existing `play.html` iframe
- Listening for postMessage events from the iframe
- Checking stream status via Ant Media's REST API
- Showing custom offline messages when streams aren't available
- Providing a debug console to understand iframe communication

## Key Features

- **Simple Iframe Embedding**: Uses Ant Media's built-in `play.html` player
- **Automatic Status Detection**: Checks if streams are live or offline
- **PostMessage Event Listening**: Captures events from Ant Media's iframe
- **Customizable Offline Messages**: Show custom messages when streams are down
- **Debug Mode**: When `WP_DEBUG` is enabled, shows all postMessage events
- **REST API Integration**: Uses Ant Media's API to verify stream status
- **Responsive Design**: Works on desktop, tablet, and mobile
- **WordPress Integration**: Simple shortcode with extensive customization

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings > Ant Media Stream to configure your server URL

## Basic Usage

### Simple Stream Embed
```
[antmedia_simple stream_id="your_stream_id"]
```

### With Custom Settings
```
[antmedia_simple stream_id="my_stream" width="800px" height="450px" no_stream_message="We'll be right back!" check_interval="15"]
```

### With Security Token
```
[antmedia_simple stream_id="secure_stream" token="your_jwt_token"]
```

## Shortcode Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `stream_id` | (required) | The stream ID to play |
| `server_url` | From settings | Override default server URL |
| `app_name` | "live" | Application name on Ant Media Server |
| `width` | "100%" | Player width |
| `height` | "500px" | Player height |
| `autoplay` | "true" | Auto-play when stream is available |
| `muted` | "true" | Start muted (required for autoplay) |
| `play_order` | "webrtc,hls" | Playback technology preference |
| `no_stream_message` | "Stream is currently offline..." | Custom offline message |
| `no_stream_title` | "Stream Offline" | Custom offline title |
| `no_stream_icon` | "📺" | Custom offline icon |
| `show_status` | "true" | Show status bar with stream info |
| `check_interval` | "30" | Status check interval (seconds) |
| `token` | "" | JWT token for secure streams |

## Understanding PostMessage Events

When `WP_DEBUG` is enabled, the plugin shows a debug console below each player that displays all postMessage events received from Ant Media's iframe. This helps you understand:

- What events Ant Media sends during playback
- When streams start/stop/error
- Player state changes
- Custom data from Ant Media

Common events you might see:
- Player initialization events
- Play/pause/stop events  
- Buffering states
- Error conditions
- Viewer count updates

## Stream Status Detection

The plugin uses multiple methods to detect stream status:

1. **PostMessage Events**: Listens for events from Ant Media's iframe
2. **REST API Checks**: Periodically checks stream status via API
3. **Manual Refresh**: Users can manually refresh to check status

### REST API Endpoint Used
```
GET {server_url}/{app_name}/rest/v2/broadcasts/{stream_id}
```

The plugin checks if `data.status === 'broadcasting'` to determine if a stream is live.

## Customizing Offline Messages

You can customize what users see when streams are offline:

```
[antmedia_simple 
    stream_id="my_stream" 
    no_stream_title="Live Event Paused" 
    no_stream_message="Our live event will resume shortly. Thank you for your patience!"
    no_stream_icon="⏸️"
]
```

## Technical Architecture

### Simple Approach
Unlike complex WebRTC implementations, this plugin:
- Uses Ant Media's existing iframe player
- Leverages their built-in protocol fallback (WebRTC → HLS)
- Listens for standard postMessage communication
- Adds a status layer for better UX

### Files Structure
```
wp-ant-media-stream-access/
├── wp-ant-media-stream-access.php (Main plugin file)
├── includes/
│   ├── simple-iframe-shortcode.php (Shortcode implementation)
│   └── styles.php (CSS styles)
└── README.md
```

## Debugging

### Enable Debug Mode
Add to your `wp-config.php`:
```php
define('WP_DEBUG', true);
```

This will show a debug console below each player that displays:
- All postMessage events from Ant Media
- Event timestamps and data
- Origin verification
- Message parsing results

### Common Issues

**Stream not playing:**
1. Check if stream is actually broadcasting
2. Verify server URL is correct
3. Check browser console for errors
4. Ensure CORS is properly configured on Ant Media Server

**PostMessage events not working:**
1. Verify iframe is from the same domain or properly configured for cross-origin
2. Check browser security settings
3. Ensure Ant Media Server version supports postMessage

## Ant Media Server Configuration

### CORS Settings
If your WordPress site and Ant Media Server are on different domains, ensure CORS is configured:

```xml
<!-- In Ant Media Server web.xml -->
<filter>
    <filter-name>CorsFilter</filter-name>
    <filter-class>org.apache.catalina.filters.CorsFilter</filter-class>
    <init-param>
        <param-name>cors.allowed.origins</param-name>
        <param-value>https://your-wordpress-site.com</param-value>
    </init-param>
</filter>
```

### SSL Configuration
For production use, ensure both WordPress and Ant Media Server use HTTPS to avoid mixed content issues.

## Contributing

This plugin takes a simple approach to Ant Media integration. If you need more advanced features like:
- Direct WebRTC integration
- Custom player controls
- Advanced analytics
- Multi-stream support

Consider extending this plugin or using Ant Media's JavaScript SDK directly.

## Support

For issues related to:
- **WordPress integration**: Create an issue in this repository
- **Ant Media Server**: Check their documentation at https://antmedia.io/docs/
- **PostMessage events**: Enable debug mode to see what events are being sent

## License

GPL v2 or later
