# YouTube-Like Layout for Video Library

## Overview

The Video Library plugin now supports a YouTube-like layout that features:
- **Main Featured Player**: The most recent video displayed prominently with inline playback
- **Scrollable Sidebar**: All other videos listed in a scrollable sidebar for easy browsing
- **Click-to-Switch**: Click any video in the sidebar to switch the main player
- **Auto-Play Next**: Automatically plays the next video when current video ends
- **Responsive Design**: Adapts to mobile and tablet layouts

## Usage

### Basic YouTube Layout
```php
[video_library layout="youtube"]
```

### YouTube Layout with Filters
```php
[video_library layout="youtube" category="tutorials" show_search="true"]
```

### Traditional Grid Layout (Default)
```php
[video_library layout="grid"]
// or simply
[video_library]
```

## Shortcode Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `layout` | `youtube` | Layout type: `youtube` or `grid` |
| `videos_per_page` | `12` | Number of videos to load |
| `show_search` | `true` | Show search functionality |
| `show_categories` | `true` | Show category filter |
| `category` | `` | Filter by specific category |
| `tag` | `` | Filter by specific tag |
| `path` | `` | Filter by S3 path |
| `s3_prefix` | `` | Filter by S3 prefix |
| `orderby` | `date` | Order by field |
| `order` | `DESC` | Order direction |

## Features

### Main Player
- **Inline Playback**: Videos play directly in the main player area
- **Thumbnail Preview**: Shows video thumbnail before playback
- **Video Controls**: Full HTML5 video controls
- **Auto-Hide Thumbnail**: Thumbnail disappears when video starts playing
- **Error Handling**: Graceful error handling for failed video loads

### Sidebar
- **Scrollable List**: Up to 600px height with custom scrollbar
- **Active State**: Currently playing video is highlighted
- **Click to Switch**: Click any sidebar video to switch main player
- **Responsive Thumbnails**: Thumbnails adapt to screen size

### Responsive Design
- **Desktop**: Full two-column layout (main player + sidebar)
- **Tablet**: Stacked layout with sidebar below main player
- **Mobile**: Optimized for touch with smaller thumbnails

## Technical Implementation

### CSS Classes
- `.video-library-youtube-layout` - Main container
- `.video-library-main-player` - Featured video area
- `.video-library-main-video` - Video player container
- `.video-library-sidebar` - Sidebar container
- `.video-sidebar-item` - Individual sidebar video items
- `.video-sidebar-item.active` - Active/currently playing video

### JavaScript Events
- **Sidebar Click**: Switches main player video
- **Main Thumbnail Click**: Starts video playback
- **Video End**: Auto-plays next video
- **Error Handling**: Manages playback errors

### S3 Integration
- **Presigned URLs**: Automatically handles expired S3 URLs
- **Auto-Discovery**: Scans S3 bucket for videos
- **Thumbnail Fallback**: Uses SVG placeholder when thumbnails unavailable

## Customization

### CSS Customization
```css
/* Customize main player aspect ratio */
.video-library-main-video {
    aspect-ratio: 21/9; /* Ultra-wide */
}

/* Customize sidebar height */
.video-library-sidebar-list {
    max-height: 800px; /* Taller sidebar */
}

/* Customize colors */
.video-sidebar-item.active {
    background-color: #ff0000; /* YouTube red */
}
```

### PHP Hooks
```php
// Filter video data before rendering
add_filter('video_library_video_data', function($video_data, $video_id) {
    // Customize video data
    return $video_data;
}, 10, 2);

// Modify shortcode attributes
add_filter('video_library_shortcode_atts', function($atts) {
    // Force YouTube layout
    $atts['layout'] = 'youtube';
    return $atts;
});
```

## Browser Support
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers with HTML5 video support

## Performance Notes
- Videos are loaded on-demand (not preloaded)
- Thumbnails are optimized SVG placeholders when unavailable
- Presigned URLs are refreshed automatically when expired
- Responsive images reduce bandwidth on mobile devices 