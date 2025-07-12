# Ant Media Stream Access

A WordPress plugin that provides secure access control for live streams hosted on Ant Media Server. It automatically detects user tiers (platinum, gold, silver) and grants access to appropriate streams using JWT token authentication. Perfect for premium content, tiered memberships, and secure streaming.

## Features

### Core Functionality

- **User Tier Detection:** Automatically detects user tier from WordPress roles or custom meta fields
- **JWT Token Authentication:** Secure stream access using signed JWT tokens  
- **Stream Routing:** Routes users to appropriate streams based on their tier level
- **Multiple Stream Formats:** Supports HLS, WebRTC, and embedded player formats
- **Rocket Chat Integration:** Chat visibility tied to stream status
- **Debug & Logging:** Comprehensive logging system for troubleshooting

### User Experience

- **Seamless Integration:** Simple shortcode and Elementor widgets
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

## Quick Start

1. **Install the plugin** on your WordPress site
2. **Configure settings** in WordPress Admin → Settings → Ant Media Stream Access:
   - Set your Ant Media Server URL
   - Add your JWT secret key
   - Configure stream mappings for each tier
3. **Add the shortcode** `[antmedia_stream]` to any page or post
4. **Assign user tiers** using WordPress roles or user meta fields
5. **Test access** with different user accounts

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
set_ant_media_stream_state(false); // Disable all streams

// Hook into state changes
add_action('ant_media_state_changed', function($is_enabled) {
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

## Rocket Chat Integration

The plugin integrates with wp-rocket-chat-embed to show/hide chat based on stream status:

- Chat appears when any configured stream is live
- Chat hides when all streams are offline
- Real-time status monitoring with AJAX
- Configurable check intervals
- Combined Elementor widget for stream + chat layouts

## WordPress Hooks

### Action Hooks

```php
// Triggered when stream access state changes
do_action('ant_media_state_changed', $is_enabled);
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

## Shortcode Parameters

| Parameter  | Default | Description                             |
| ---------- | ------- | --------------------------------------- |
| `width`    | `100%`  | Player width (px or %)                  |
| `height`   | `500`   | Player height (px)                      |
| `format`   | `hls`   | Stream format: `hls`, `embed`, `webrtc` |
| `controls` | `true`  | Show player controls: `true`/`false`    |
| `autoplay` | `false` | Auto-start playback: `true`/`false`     |
| `style`    | `""`    | Custom CSS styles                       |

## Security Features

- **JWT Token Authentication:** All stream access uses signed JWT tokens
- **Token Expiration:** Configurable token expiry (5 minutes to 24 hours)
- **User Validation:** Tokens include user ID and tier for server-side validation
- **Secure Transmission:** HTTPS recommended for token security
- **Access Logging:** All access attempts are logged for auditing

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

## Technical Requirements

- **WordPress:** 5.0+
- **PHP:** 7.4+
- **Ant Media Server:** Any version with JWT support
- **Browser Support:** Modern browsers with HTML5 video support
- **Optional:** Elementor for drag-and-drop widget support

## License

GPL v2 or later

## Disclaimer

This plugin is designed to work with Ant Media Server but is not affiliated with or endorsed by Ant Media. Ant Media is a trademark of Ant Media Inc.
