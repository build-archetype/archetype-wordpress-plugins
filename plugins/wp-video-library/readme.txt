=== Video Library ===
Contributors: buildarchetype
Tags: video, library, private, streaming, membership
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a private YouTube-like video library with tier-based access control and secure storage.

== Description ==

Video Library transforms your WordPress site into a private video platform with secure storage, access control, and YouTube-like interface. Perfect for online courses, membership sites, and premium content.

**Free Features:**
* Upload up to 5 videos
* Basic video player
* Simple access control
* WordPress user integration
* Mobile responsive design

**Premium Features:**
* Unlimited videos
* S3/DigitalOcean Spaces integration
* Advanced analytics and view tracking
* User favorites system
* Search and filtering
* Featured video system
* Tier-based access control
* Video thumbnails and metadata
* Export analytics data
* Priority support

**Usage:**
Use the shortcode `[video_library]` to display your video collection.

Parameters:
* `videos_per_page="12"` - Number of videos per page
* `show_search="true"` - Enable search functionality
* `tier="gold"` - Filter by user tier

== Installation ==

1. Upload plugin files to `/wp-content/plugins/video-library/`
2. Activate the plugin
3. Go to Video Library → Settings
4. Configure storage settings (S3 for premium)
5. Add videos via Video Library → Add New
6. Use shortcode to display videos

== Frequently Asked Questions ==

= Do I need cloud storage? =
Free version works with WordPress media library. Premium version supports S3/DigitalOcean Spaces for better security and performance.

= Can I restrict video access? =
Yes, videos can be restricted based on user tiers (premium feature) or simple login requirements (free).

= Are videos secure? =
Premium version uses pre-signed URLs and secure cloud storage. Free version provides basic protection.

= Can users track their progress? =
Premium version includes comprehensive analytics, favorites, and viewing history.

== Screenshots ==

1. Video library grid view
2. Video player modal
3. Admin settings panel
4. Analytics dashboard (premium)

== Changelog ==

= 1.0.0 =
* Initial release
* Basic video library functionality
* WordPress integration
* Mobile responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release of Video Library plugin. 