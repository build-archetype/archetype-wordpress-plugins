# Video Library

Private YouTube-like video platform for WordPress with S3 storage, tier-based access control, and comprehensive analytics.

## What is Video Library?

Video Library transforms your WordPress site into a private video platform. Store videos securely in S3/DigitalOcean Spaces, control access by user tiers, and get detailed viewing analytics. Perfect for premium content, courses, and membership sites.

---

## Features

### Core Functionality

- **S3 Storage Integration:** Secure video storage with AWS S3 or DigitalOcean Spaces
- **Pre-signed URL Security:** Videos accessed via secure, expiring URLs only
- **Tier-based Access Control:** Platinum, Gold, Silver user tier system
- **Featured Video System:** Highlight important content at the top
- **Search & Filtering:** Find videos by title, description, category, or tags
- **Responsive Video Player:** Modern HTML5 player with modal viewing
- **Favorites System:** Users can favorite videos for quick access

### Analytics & Tracking

- **View Tracking:** Detailed view counts and unique viewer metrics
- **Watch Time Analytics:** Track how long users watch videos
- **User Analytics:** Individual user viewing patterns and preferences
- **Popular Content:** Most viewed videos and trending analytics
- **Engagement Metrics:** Favorites, completion rates, and user interactions
- **CSV Export:** Export analytics data for external analysis

### User Experience

- **YouTube-like Interface:** Familiar grid layout with video cards
- **Modal Video Player:** Clean, distraction-free viewing experience
- **Mobile Responsive:** Works perfectly on all devices
- **Infinite Scroll:** Load more videos seamlessly
- **Video Thumbnails:** Custom thumbnails or auto-generated previews
- **Duration Display:** Shows video length on each card

### Administration

- **Complete Video Management:** Add, edit, organize videos with metadata
- **Custom Post Type:** Native WordPress integration with categories/tags
- **Analytics Dashboard:** View detailed reports and insights
- **S3 Connection Testing:** Built-in tools to verify storage connectivity
- **Access Control:** Quick enable/disable and maintenance mode
- **Database Health:** Monitor and maintain analytics data

---

## Setup Requirements

### For Basic Setup

- WordPress 5.0+
- PHP 7.4+
- S3-compatible storage (AWS S3 or DigitalOcean Spaces)
- S3 access credentials (access key and secret)
- User authentication system

### For Advanced Features

- Custom user roles (platinum, gold, silver) OR user meta fields
- SSL certificate for secure video streaming
- Adequate server resources for video analytics processing

---

## Quick Start

1. **Install the plugin** on your WordPress site
2. **Configure S3 storage** in WordPress Admin → Settings → Video Library:
   - Set your S3 bucket name and region
   - Add your access key and secret key
   - Test the connection
3. **Add videos** in WordPress Admin → Video Library → Add New Video:
   - Enter video title and description
   - Set the S3 object key (path to video file)
   - Choose required user tier
   - Add thumbnail and metadata
4. **Assign user tiers** using WordPress roles or user meta fields
5. **Display the library** using `[video_library]` shortcode
6. **View analytics** in WordPress Admin → Video Library → Analytics

---

## Configuration

### S3 Storage Setup

#### AWS S3

```
Bucket: your-video-bucket
Region: us-east-1
Access Key: AKIA...
Secret Key: xyz...
Endpoint: (leave empty for AWS)
```

#### DigitalOcean Spaces

```
Bucket: your-space-name
Region: nyc3
Access Key: DO00...
Secret Key: abc...
Endpoint: https://nyc3.digitaloceanspaces.com
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

### Basic Video Library

```php
[video_library]
```

### Customized Display

```php
[video_library videos_per_page="16" show_featured="true" show_search="true"]
```

### Category-Specific Library

```php
[video_library category="tutorials" show_categories="false"]
```

### External Control (PHP)

```php
// Enable/disable video library
set_video_library_state(false); // Disable library

// Hook into state changes
add_action('video_library_state_changed', function($is_enabled) {
    if ($is_enabled) {
        // Library was enabled
        error_log('Video library is now active');
    } else {
        // Library was disabled
        error_log('Video library disabled for maintenance');
    }
});

// Filter display logic
add_filter('video_library_should_display', function($should_display) {
    // Custom logic for when library should be shown
    if (is_page('maintenance')) {
        return false; // Never show on maintenance page
    }
    return $should_display;
});

// Filter video access
add_filter('video_library_user_can_access_video', function($has_access, $video_id, $user_id, $user_tier, $required_tier) {
    // Custom access logic
    return $has_access;
});
```

---

## WordPress Hooks

### Action Hooks

```php
// Triggered when library access state changes
do_action('video_library_state_changed', $is_enabled);
```

### Filter Hooks

```php
// Filter whether library should be displayed
apply_filters('video_library_should_display', $should_display);

// Filter user access to specific videos
apply_filters('video_library_user_can_access_video', $has_access, $video_id, $user_id, $user_tier, $required_tier);
```

---

## Shortcode Parameters

| Parameter         | Default | Description                          |
| ----------------- | ------- | ------------------------------------ |
| `videos_per_page` | `12`    | Number of videos to show per page    |
| `show_featured`   | `true`  | Display featured video section       |
| `show_search`     | `true`  | Show search functionality            |
| `show_categories` | `true`  | Show category filter dropdown        |
| `layout`          | `grid`  | Display layout (grid only currently) |
| `category`        | `""`    | Filter to specific category          |
| `tag`             | `""`    | Filter to specific tag               |

---

## Video Management

### Adding Videos

1. Go to **Video Library → Add New Video**
2. Enter **title and description**
3. Set **S3 object key** (e.g., `videos/my-video.mp4`)
4. Choose **required user tier**
5. Add **video metadata**:
   - Duration (seconds)
   - File size (bytes)
   - Video quality (240p - 4K)
   - Video format (MP4, WebM, etc.)
6. Set **custom thumbnail** (optional)
7. **Test S3 connection** to verify file exists
8. **Publish the video**

### Featured Videos

- Check "Set as Featured Video" in the Access Control meta box
- Only one video can be featured at a time
- Featured videos appear prominently at the top of the library

### Categories & Tags

- Organize videos using WordPress taxonomies
- Categories are hierarchical (like folders)
- Tags are flat (like keywords)
- Both support filtering in the frontend

---

## Analytics Features

### View Tracking

- **Total Views:** Complete view count across all videos
- **Unique Viewers:** Individual users who watched
- **Watch Duration:** How long users actually watched
- **Completion Rates:** Percentage of video watched

### Popular Content

- **Most Viewed Videos:** Top performing content
- **Trending Videos:** Recently popular content
- **User Favorites:** Most favorited videos
- **Category Performance:** Views by video category

### User Insights

- **Individual User Analytics:** Personal viewing history
- **Watch Time Tracking:** Total time spent watching
- **Engagement Patterns:** When and how users watch
- **Favorite Videos:** User's saved content

### Reporting

- **Daily/Weekly/Monthly Reports:** Configurable time periods
- **CSV Export:** Download data for external analysis
- **Real-time Dashboard:** Live analytics in WordPress admin
- **Custom Date Ranges:** Flexible reporting periods

---

## Security Features

- **Pre-signed URLs:** All video access uses secure, expiring URLs
- **Token Expiration:** Configurable expiry (5 minutes to 24 hours)
- **User Validation:** Tier-based access control with server verification
- **Secure Storage:** Videos never exposed publicly
- **Access Logging:** All viewing attempts logged for security

---

## Database Schema

### Custom Tables

- **`video_library_views`:** View tracking and analytics
- **`video_library_favorites`:** User favorite videos
- **`video_library_sessions`:** Detailed session analytics
- **`video_library_searches`:** Search query analytics

### Automatic Maintenance

- **Data Cleanup:** Automatic removal of old analytics data
- **Database Optimization:** Regular table optimization
- **Health Monitoring:** Database health status checks
- **Backup Support:** Built-in backup functionality

---

## Performance Optimization

### Video Delivery

- **CDN-friendly:** Works with CloudFront and other CDNs
- **Efficient Loading:** Videos loaded only when played
- **Responsive Images:** Optimized thumbnails for all devices
- **Lazy Loading:** Videos loaded as needed

### Database Performance

- **Indexed Queries:** Optimized database structure
- **Data Retention:** Configurable analytics data cleanup
- **Caching Support:** Compatible with WordPress caching plugins
- **Efficient Queries:** Minimal database overhead

---

## Troubleshooting

### Common Issues

1. **"Unable to generate stream access token"**

   - Check S3 credentials configuration
   - Verify bucket permissions
   - Test S3 connection in video edit screen

2. **"No videos available for your tier"**

   - Check user tier assignment
   - Verify video tier requirements
   - Ensure videos are published

3. **Videos don't load/play**
   - Verify S3 object key is correct
   - Check file exists in S3 storage
   - Ensure CORS is configured for video playback

### Debug Mode

Enable debug mode in plugin settings to:

- View detailed logs in admin interface
- Monitor S3 API calls and responses
- Track user tier detection
- Debug analytics data collection

---

## Version Comparison

| Feature                   | Basic WordPress | Video Library Plugin |
| ------------------------- | :-------------: | :------------------: |
| Secure video storage      |       ❌        |          ✅          |
| Tier-based access control |       ❌        |          ✅          |
| Video analytics           |       ❌        |          ✅          |
| S3 integration            |       ❌        |          ✅          |
| Pre-signed URL security   |       ❌        |          ✅          |
| User favorites            |       ❌        |          ✅          |
| Featured videos           |       ❌        |          ✅          |
| Search & filtering        |      Basic      |       Advanced       |
| Mobile responsive         |     Depends     |          ✅          |
| Custom video player       |       ❌        |          ✅          |

---

## Technical Requirements

- **WordPress:** 5.0+
- **PHP:** 7.4+ (8.0+ recommended)
- **MySQL:** 5.7+ or MariaDB 10.2+
- **S3 Storage:** AWS S3 or compatible service
- **Browser Support:** Modern browsers with HTML5 video support
- **Optional:** Elementor for drag-and-drop widget support

---

## Support & Development

Video Library provides a complete foundation for private video platforms. The modular architecture makes it easy to extend with custom features, integrate with membership plugins, or adapt for specific use cases like online courses or premium content delivery.

Built with WordPress best practices, the plugin is secure, scalable, and ready for production use.

---

## License & Disclaimer

This plugin is designed to work with S3-compatible storage services but is not affiliated with Amazon Web Services or DigitalOcean. All trademarks are property of their respective owners.
