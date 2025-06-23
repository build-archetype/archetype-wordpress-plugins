<?php
if (!defined('ABSPATH')) exit;

/**
 * ========================================
 * SIMPLIFIED VIDEO LIBRARY FUNCTIONS
 * ========================================
 * Path/Category-based filtering instead of user tiers
 */

/**
 * Get videos by path/category/tag instead of tier
 */
function get_videos_by_filter($args = []) {
    // Log the function call for debugging
    video_library_log('get_videos_by_filter called with args: ' . json_encode($args), 'debug');
    
    $defaults = [
        'limit' => 12,
        'offset' => 0,
        'orderby' => 'date',
        'order' => 'DESC',
        'path' => '',           // S3 path filter
        'category' => '',       // Video category
        'tag' => '',           // Video tag
        's3_prefix' => '',     // S3 prefix filter
        'search' => ''         // Search term
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // First check for WordPress posts
    $query_args = [
        'post_type' => 'video_library_item',
        'post_status' => 'publish',
        'posts_per_page' => $args['limit'],
        'offset' => $args['offset'],
        'orderby' => $args['orderby'],
        'order' => $args['order'],
        'meta_query' => [],
        'tax_query' => []
    ];
    
    // Add search if provided
    if (!empty($args['search'])) {
        $query_args['s'] = $args['search'];
    }
    
    // Filter by category
    if (!empty($args['category'])) {
        $query_args['tax_query'][] = [
            'taxonomy' => 'video_category',
            'field' => 'slug',
            'terms' => $args['category']
        ];
    }
    
    // Filter by tag
    if (!empty($args['tag'])) {
        $query_args['tax_query'][] = [
            'taxonomy' => 'video_tag',
            'field' => 'slug',
            'terms' => $args['tag']
        ];
    }
    
    // Filter by S3 path/prefix
    if (!empty($args['path']) || !empty($args['s3_prefix'])) {
        $path_filter = !empty($args['path']) ? $args['path'] : $args['s3_prefix'];
        $query_args['meta_query'][] = [
            'key' => '_video_s3_key',
            'value' => $path_filter,
            'compare' => 'LIKE'
        ];
    }
    
    if (!empty($query_args['tax_query']) && count($query_args['tax_query']) > 1) {
        $query_args['tax_query']['relation'] = 'AND';
    }
    
    $query = new WP_Query($query_args);
    $videos = $query->posts;
    
    video_library_log('Found ' . count($videos) . ' videos from WordPress posts', 'debug');
    
    // If no WordPress posts found, try to scan S3 bucket for videos
    if (empty($videos) && function_exists('scan_s3_bucket_for_videos')) {
        video_library_log('No WordPress posts found, scanning S3 bucket...', 'debug');
        $s3_videos = scan_s3_bucket_for_videos($args);
        if ($s3_videos) {
            video_library_log('Found ' . count($s3_videos) . ' videos from S3 bucket', 'debug');
            $videos = array_merge($videos, $s3_videos);
        }
    }
    
    return $videos;
}

/**
 * Video Library Shortcode - Simplified
 */
function video_library_shortcode_simplified($atts) {
    video_library_log('video_library_shortcode_simplified called', 'debug');
    
    // Don't display if conditions not met
    if (!should_display_video_library()) {
        video_library_log('should_display_video_library returned false', 'warning');
        return '<div class="video-library-disabled">Video library is currently unavailable.</div>';
    }
    
    video_library_log('Video library display conditions met', 'debug');
    
    $atts = shortcode_atts([
        'videos_per_page' => 12,
        'show_search' => 'true',
        'show_categories' => 'true',
        'category' => '',           // Filter by category
        'tag' => '',               // Filter by tag  
        'path' => '',              // Filter by S3 path
        's3_prefix' => '',         // Filter by S3 prefix
        'orderby' => 'date',       // Order by (date, title, etc.)
        'order' => 'DESC',         // Order direction
        'search' => '',             // Pre-filter search
        'layout' => 'youtube'      // Layout type: 'youtube' or 'grid'
    ], $atts);
    
    video_library_log('Shortcode attributes: ' . json_encode($atts), 'debug');
    
    // Start output buffering
    ob_start();
    
    ?>
    <div class="video-library-container simplified" data-atts='<?php echo esc_attr(json_encode($atts)); ?>'>
        
        <?php if ($atts['show_search'] === 'true' && empty($atts['search'])): ?>
        <div class="video-library-search">
            <input type="text" id="video-search" placeholder="Search videos..." value="<?php echo esc_attr($atts['search']); ?>">
            <button type="button" id="video-search-btn">Search</button>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['show_categories'] === 'true' && empty($atts['category'])): ?>
        <div class="video-library-filters">
            <select id="video-category-filter">
                <option value="">All Categories</option>
                <?php
                $categories = get_terms(['taxonomy' => 'video_category', 'hide_empty' => true]);
                if ($categories && !is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        $selected = ($atts['category'] === $category->slug) ? 'selected' : '';
                        echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        <?php endif; ?>
        
        <?php
        video_library_log('About to call get_videos_by_filter for initial load', 'debug');
        
        // Get videos based on shortcode parameters
        $videos = get_videos_by_filter([
            'limit' => $atts['videos_per_page'],
            'category' => $atts['category'],
            'tag' => $atts['tag'],
            'path' => $atts['path'],
            's3_prefix' => $atts['s3_prefix'],
            'search' => $atts['search'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order']
        ]);
        
        video_library_log('get_videos_by_filter returned ' . count($videos) . ' videos', 'info');
        
        if ($atts['layout'] === 'youtube' && !empty($videos)): ?>
            <!-- YouTube-style Layout -->
            <div class="video-library-youtube-layout" id="video-library-youtube">
                <!-- Main Player -->
                <div class="video-library-main-player">
                    <?php 
                    $featured_video = $videos[0]; // Use first video as featured
                    echo render_youtube_main_player($featured_video);
                    ?>
                </div>
                
                <!-- Sidebar with other videos -->
                <div class="video-library-sidebar">
                    <div class="video-library-sidebar-header">
                        <h3>More Videos</h3>
                        <span class="video-library-sidebar-count"><?php echo count($videos) - 1; ?> videos</span>
                    </div>
                    <div class="video-library-sidebar-list" id="video-sidebar-list">
                        <?php
                        // Show remaining videos in sidebar (skip first one)
                        for ($i = 1; $i < count($videos); $i++) {
                            echo render_youtube_sidebar_item($videos[$i], $i);
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php elseif ($atts['layout'] === 'youtube' && empty($videos)): ?>
            <!-- No Videos State for YouTube Layout -->
            <div class="video-library-no-videos-youtube">
                <div class="video-library-no-videos-main">
                    <div class="no-videos-content">
                        <div class="no-videos-icon">🎬</div>
                        <h3>No Videos Available</h3>
                        <p>Your video library will appear here when videos are added.</p>
                    </div>
                </div>
                <div class="video-library-no-videos-sidebar">
                    <h4>Video Sidebar</h4>
                    <p>Additional videos will be listed here for easy browsing.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Traditional Grid Layout -->
            <div class="video-library-grid" id="video-library-grid">
                <?php
                if ($videos) {
                    foreach ($videos as $video) {
                        echo render_video_card_simple($video);
                    }
                } else {
                    video_library_log('No videos found, showing skeleton cards', 'info');
                    // Show skeleton cards with a message overlay
                    echo '<div class="no-videos-overlay">
                        <div class="no-videos-message-floating">
                            <div class="no-videos-icon">🎬</div>
                            <h3>No Videos Found</h3>
                            <p>No videos match your current criteria. This is how your video library will look when populated!</p>
                        </div>
                    </div>';
                    
                    // Show 12 skeleton cards
                    for ($i = 0; $i < 12; $i++) {
                        echo render_skeleton_video_card();
                    }
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($videos && count($videos) >= $atts['videos_per_page']): ?>
        <div class="video-library-load-more">
            <button type="button" id="load-more-videos">Load More Videos</button>
        </div>
        <?php endif; ?>
        
    </div>
    <?php
    
    video_library_log('Shortcode rendering complete', 'debug');
    return ob_get_clean();
}

/**
 * Render video card HTML - Simplified
 */
function render_video_card_simple($video) {
    // Handle both WordPress posts and virtual S3 videos
    if (isset($video->is_s3_virtual) && $video->is_s3_virtual) {
        // Virtual S3 video
        $video_id = $video->ID;
        $title = $video->post_title;
        $description = $video->post_excerpt;
        $thumbnail_url = VL_PLUGIN_URL . 'assets/images/video-placeholder.svg'; // Use SVG placeholder
        $duration = 'Unknown';
        $s3_key = $video->s3_key;
        $video_url = $video->video_url;
        $file_size_formatted = 'Unknown';
        $video_date = 'Auto-discovered';
        
        video_library_log('Rendering virtual S3 video card: ' . $title, 'debug');
    } else {
        // WordPress post
        $video_id = $video->ID;
        $title = get_the_title($video_id);
        $description = get_the_excerpt($video_id) ?: wp_trim_words($video->post_content, 20);
        $thumbnail_url = get_video_thumbnail_url($video_id);
        $duration = get_video_duration_formatted($video_id);
        $s3_key = get_post_meta($video_id, '_video_s3_key', true);
        $video_url = get_video_streaming_url($video_id);
        
        // Get file size if available
        $file_size = get_post_meta($video_id, '_video_file_size', true);
        $file_size_formatted = $file_size ? format_file_size($file_size) : 'Unknown';
        
        // Get video date
        $video_date = get_the_date('M j, Y', $video_id);
        
        video_library_log('Rendering WordPress post video card: ' . $title, 'debug');
    }
    
    ob_start();
    ?>
    <div class="video-card" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-duration="<?php echo esc_attr($duration); ?>"
         data-video-size="<?php echo esc_attr($file_size_formatted); ?>"
         data-video-date="<?php echo esc_attr($video_date); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>"
         data-is-virtual="<?php echo isset($video->is_s3_virtual) ? 'true' : 'false'; ?>">
        <div class="video-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
            <div class="video-duration"><?php echo esc_html($duration); ?></div>
            <div class="video-play-button">▶</div>
        </div>
        <div class="video-info">
            <h3 class="video-title"><?php echo esc_html($title); ?></h3>
            <?php if ($description): ?>
            <p class="video-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
            <div class="video-meta">
                <span class="video-date"><?php echo esc_html(is_string($video_date) ? $video_date : get_time_ago(get_the_date('Y-m-d H:i:s', $video_id))); ?></span>
                <span class="video-path">Path: <?php echo esc_html(dirname($s3_key ?: 'unknown')); ?></span>
            </div>
            <?php
            // Only show categories for WordPress posts
            if (!isset($video->is_s3_virtual)):
                $categories = get_video_categories($video_id);
                if ($categories):
            ?>
            <div class="video-categories">
                <?php foreach ($categories as $category): ?>
                <span class="video-category-tag"><?php echo esc_html($category); ?></span>
                <?php endforeach; ?>
            </div>
            <?php 
                endif;
            else:
                // For virtual videos, show file extension as category
                $extension = pathinfo($s3_key, PATHINFO_EXTENSION);
                if ($extension):
            ?>
            <div class="video-categories">
                <span class="video-category-tag"><?php echo esc_html(strtoupper($extension)); ?></span>
                <span class="video-category-tag">Auto-discovered</span>
            </div>
            <?php endif; endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Scan S3 bucket for video files and create virtual video objects
 */
function scan_s3_bucket_for_videos($args = []) {
    video_library_log('Starting S3 bucket scan...', 'debug');
    
    // Get S3 settings
    $bucket = get_option('video_library_s3_bucket');
    $region = get_option('video_library_s3_region', 'nyc3');
    $endpoint = get_option('video_library_s3_endpoint', "https://{$region}.digitaloceanspaces.com");
    $access_key = get_option('video_library_s3_access_key');
    $secret_key = get_option('video_library_s3_secret_key');
    
    if (empty($bucket)) {
        video_library_log('No S3 bucket configured', 'warning');
        return [];
    }
    
    if (empty($access_key) || empty($secret_key)) {
        video_library_log('S3 credentials not configured, cannot scan bucket', 'warning');
        return [];
    }
    
    try {
        // Use S3 integration to list files
        if (class_exists('Video_Library_S3')) {
            $s3 = new Video_Library_S3();
            $files = $s3->list_bucket_files();
            
            if (!$files) {
                video_library_log('No files found in S3 bucket or listing failed', 'debug');
                return [];
            }
            
            $video_extensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v'];
            $videos = [];
            
            foreach ($files as $file) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                if (in_array($extension, $video_extensions)) {
                    // Create virtual video object
                    $video = create_virtual_video_from_s3($file, $bucket, $endpoint);
                    if ($video) {
                        $videos[] = $video;
                    }
                }
            }
            
            video_library_log('Created ' . count($videos) . ' virtual videos from S3 files', 'debug');
            return $videos;
        }
    } catch (Exception $e) {
        video_library_log('Error scanning S3 bucket: ' . $e->getMessage(), 'error');
        return [];
    }
    
    return [];
}

/**
 * Create a virtual video object from S3 file
 */
function create_virtual_video_from_s3($s3_key, $bucket, $endpoint) {
    $filename = basename($s3_key);
    $title = pathinfo($filename, PATHINFO_FILENAME);
    $title = str_replace(['_', '-'], ' ', $title);
    $title = ucwords($title);
    
    // Create a virtual WP_Post object
    $video = new stdClass();
    $video->ID = 's3_' . md5($s3_key); // Virtual ID
    $video->post_title = $title;
    $video->post_content = 'Video file: ' . $filename;
    $video->post_excerpt = 'Auto-discovered from S3 bucket';
    $video->post_type = 'video_library_item';
    $video->post_status = 'publish';
    $video->post_date = date('Y-m-d H:i:s');
    $video->post_date_gmt = gmdate('Y-m-d H:i:s');
    
    // Add S3 metadata
    $video->s3_key = $s3_key;
    $video->s3_bucket = $bucket;
    $video->s3_endpoint = $endpoint;
    
    video_library_log('Attempting to generate presigned URL for: ' . $s3_key, 'debug');
    
    // Generate presigned URL for secure access
    if (class_exists('Video_Library_S3')) {
        video_library_log('Video_Library_S3 class found, generating presigned URL...', 'debug');
        
        try {
            $presigned_url = Video_Library_S3::get_presigned_url($s3_key);
            video_library_log('Presigned URL function returned: ' . ($presigned_url ? 'SUCCESS' : 'FALSE'), 'debug');
            
            if ($presigned_url) {
                $video->video_url = $presigned_url;
                video_library_log('Generated presigned URL for video: ' . $title, 'info');
                video_library_log('Presigned URL: ' . substr($presigned_url, 0, 100) . '...', 'debug');
            } else {
                // Fallback to direct URL (won't work if bucket is private)
                $video->video_url = rtrim($endpoint, '/') . '/' . $bucket . '/' . $s3_key;
                video_library_log('Failed to generate presigned URL, using direct URL for: ' . $title, 'warning');
                video_library_log('Direct URL: ' . $video->video_url, 'debug');
            }
        } catch (Exception $e) {
            video_library_log('Exception generating presigned URL: ' . $e->getMessage(), 'error');
            // Fallback to direct URL
            $video->video_url = rtrim($endpoint, '/') . '/' . $bucket . '/' . $s3_key;
            video_library_log('Using direct URL due to exception: ' . $video->video_url, 'warning');
        }
    } else {
        // Fallback to direct URL
        $video->video_url = rtrim($endpoint, '/') . '/' . $bucket . '/' . $s3_key;
        video_library_log('S3 class not available, using direct URL for: ' . $title, 'warning');
        video_library_log('Direct URL: ' . $video->video_url, 'debug');
    }
    
    $video->is_s3_virtual = true; // Flag to identify virtual videos
    
    video_library_log('Created virtual video: ' . $title . ' from ' . $s3_key, 'debug');
    video_library_log('Final video URL: ' . substr($video->video_url, 0, 100) . '...', 'debug');
    
    return $video;
}

/**
 * Get video streaming URL with presigned URL support
 */
function get_video_streaming_url($video_id) {
    $s3_key = get_post_meta($video_id, '_video_s3_key', true);
    if (!$s3_key) return '';
    
    // Try to generate presigned URL for WordPress videos too
    if (class_exists('Video_Library_S3')) {
        $presigned_url = Video_Library_S3::get_presigned_url($s3_key);
        if ($presigned_url) {
            video_library_log('Generated presigned URL for WordPress video ID: ' . $video_id, 'debug');
            return $presigned_url;
        }
    }
    
    // Fallback to direct URL construction
    $settings = get_option('video_library_settings', []);
    $bucket_name = $settings['s3_bucket'] ?? '';
    $region = $settings['s3_region'] ?? 'nyc3';
    $endpoint = $settings['s3_endpoint'] ?? "https://{$region}.digitaloceanspaces.com";
    
    // Clean endpoint to ensure it doesn't have trailing slash
    $endpoint = rtrim($endpoint, '/');
    
    return "{$endpoint}/{$bucket_name}/{$s3_key}";
}

/**
 * Render skeleton video card HTML
 */
function render_skeleton_video_card() {
    ob_start();
    ?>
    <div class="video-card skeleton">
        <div class="video-thumbnail skeleton-shimmer">
            <div class="video-duration skeleton-duration">0:00</div>
            <div class="video-play-button skeleton-play">▶</div>
        </div>
        <div class="video-info">
            <h3 class="video-title skeleton-title"></h3>
            <p class="video-description skeleton-description-line"></p>
            <p class="video-description skeleton-description-line short"></p>
            <div class="video-meta">
                <span class="skeleton-meta"></span>
                <span class="skeleton-meta"></span>
            </div>
            <div class="video-categories">
                <span class="video-category-tag skeleton-tag"></span>
                <span class="video-category-tag skeleton-tag"></span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Format file size for display
 */
function format_file_size($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Register the simplified shortcode
add_shortcode('video_library_simple', 'video_library_shortcode_simplified');

/**
 * Main shortcode function (alias for the simplified version)
 * This is what the Elementor widget calls
 */
if (!function_exists('video_library_shortcode')) {
    function video_library_shortcode($atts) {
        return video_library_shortcode_simplified($atts);
    }
    
    // Register the main shortcode
    add_shortcode('video_library', 'video_library_shortcode');
}

/**
 * Debug helper function to test logging
 */
if (!function_exists('video_library_debug_test')) {
    function video_library_debug_test() {
        // Force enable debug mode for testing
        update_option('video_library_debug_mode', true);
        
        video_library_log('=== DEBUG TEST STARTED ===', 'info');
        video_library_log('This is a test debug message', 'debug');
        video_library_log('This is a test info message', 'info');
        video_library_log('This is a test warning message', 'warning');
        video_library_log('This is a test error message', 'error');
        
        // Test S3 configuration
        $bucket = get_option('video_library_s3_bucket');
        $access_key = get_option('video_library_s3_access_key');
        $secret_key = get_option('video_library_s3_secret_key');
        
        video_library_log('S3 Bucket: ' . ($bucket ? $bucket : 'NOT SET'), 'info');
        video_library_log('S3 Access Key: ' . ($access_key ? 'SET' : 'NOT SET'), 'info');
        video_library_log('S3 Secret Key: ' . ($secret_key ? 'SET' : 'NOT SET'), 'info');
        
        // Test should_display_video_library
        $should_display = should_display_video_library();
        video_library_log('should_display_video_library: ' . ($should_display ? 'true' : 'false'), 'info');
        
        if (!$should_display) {
            $library_enabled = get_option('video_library_enabled', 'true') === 'true';
            $user_logged_in = is_user_logged_in();
            $has_required_settings = !empty(get_option('video_library_s3_bucket'));
            
            video_library_log('Library enabled: ' . ($library_enabled ? 'true' : 'false'), 'info');
            video_library_log('User logged in: ' . ($user_logged_in ? 'true' : 'false'), 'info');
            video_library_log('Has S3 bucket: ' . ($has_required_settings ? 'true' : 'false'), 'info');
        }
        
        video_library_log('=== DEBUG TEST COMPLETED ===', 'info');
        
        return 'Debug test completed. Check the logs in Settings → Video Library.';
    }

    /**
     * Add debug shortcode for testing
     */
    add_shortcode('video_library_debug', 'video_library_debug_test');
}

/**
 * Render YouTube-style main player
 */
function render_youtube_main_player($video) {
    // Handle both WordPress posts and virtual S3 videos
    if (isset($video->is_s3_virtual) && $video->is_s3_virtual) {
        // Virtual S3 video
        $video_id = $video->ID;
        $title = $video->post_title;
        $description = $video->post_excerpt;
        $thumbnail_url = VL_PLUGIN_URL . 'assets/images/video-placeholder.svg'; // Use SVG placeholder
        $duration = 'Unknown';
        $s3_key = $video->s3_key;
        $video_url = $video->video_url;
        $file_size_formatted = 'Unknown';
        $video_date = 'Auto-discovered';
    } else {
        // WordPress post
        $video_id = $video->ID;
        $title = get_the_title($video_id);
        $description = get_the_excerpt($video_id) ?: wp_trim_words($video->post_content, 30);
        $thumbnail_url = get_video_thumbnail_url($video_id);
        $duration = get_video_duration_formatted($video_id);
        $s3_key = get_post_meta($video_id, '_video_s3_key', true);
        $video_url = get_video_streaming_url($video_id);
        
        // Get file size if available
        $file_size = get_post_meta($video_id, '_video_file_size', true);
        $file_size_formatted = $file_size ? format_file_size($file_size) : 'Unknown';
        
        // Get video date
        $video_date = get_the_date('M j, Y', $video_id);
    }
    
    ob_start();
    ?>
    <div class="video-library-main-video" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-duration="<?php echo esc_attr($duration); ?>"
         data-video-size="<?php echo esc_attr($file_size_formatted); ?>"
         data-video-date="<?php echo esc_attr($video_date); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>"
         data-is-virtual="<?php echo isset($video->is_s3_virtual) ? 'true' : 'false'; ?>">
        
        <video id="main-video-player" controls style="display: none;">
            Your browser does not support the video tag.
        </video>
        
        <div class="video-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
            <div class="video-play-button">▶</div>
            <div class="video-duration"><?php echo esc_html($duration); ?></div>
        </div>
    </div>
    
    <div class="video-library-main-info">
        <h2><?php echo esc_html($title); ?></h2>
        <div class="video-meta">
            <span>📅 <?php echo esc_html($video_date); ?></span>
            <span>⏱ <?php echo esc_html($duration); ?></span>
            <span>💾 <?php echo esc_html($file_size_formatted); ?></span>
        </div>
        <?php if ($description): ?>
        <div class="video-description"><?php echo esc_html($description); ?></div>
        <?php endif; ?>
        <?php
        // Only show categories for WordPress posts
        if (!isset($video->is_s3_virtual)):
            $categories = get_video_categories($video_id);
            if ($categories):
        ?>
        <div class="video-categories">
            <?php foreach ($categories as $category): ?>
            <span class="video-category-tag"><?php echo esc_html($category); ?></span>
            <?php endforeach; ?>
        </div>
        <?php 
            endif;
        else:
            // For virtual videos, show file extension as category
            $extension = pathinfo($s3_key, PATHINFO_EXTENSION);
            if ($extension):
        ?>
        <div class="video-categories">
            <span class="video-category-tag"><?php echo esc_html(strtoupper($extension)); ?></span>
            <span class="video-category-tag">Auto-discovered</span>
        </div>
        <?php endif; endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render YouTube-style sidebar item
 */
function render_youtube_sidebar_item($video, $index = 0) {
    // Handle both WordPress posts and virtual S3 videos
    if (isset($video->is_s3_virtual) && $video->is_s3_virtual) {
        // Virtual S3 video
        $video_id = $video->ID;
        $title = $video->post_title;
        $description = $video->post_excerpt;
        $thumbnail_url = VL_PLUGIN_URL . 'assets/images/video-placeholder.svg'; // Use SVG placeholder
        $duration = 'Unknown';
        $s3_key = $video->s3_key;
        $video_url = $video->video_url;
        $file_size_formatted = 'Unknown';
        $video_date = 'Auto-discovered';
    } else {
        // WordPress post
        $video_id = $video->ID;
        $title = get_the_title($video_id);
        $description = get_the_excerpt($video_id) ?: wp_trim_words($video->post_content, 15);
        $thumbnail_url = get_video_thumbnail_url($video_id);
        $duration = get_video_duration_formatted($video_id);
        $s3_key = get_post_meta($video_id, '_video_s3_key', true);
        $video_url = get_video_streaming_url($video_id);
        
        // Get file size if available
        $file_size = get_post_meta($video_id, '_video_file_size', true);
        $file_size_formatted = $file_size ? format_file_size($file_size) : 'Unknown';
        
        // Get video date
        $video_date = get_the_date('M j, Y', $video_id);
    }
    
    ob_start();
    ?>
    <div class="video-sidebar-item" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-duration="<?php echo esc_attr($duration); ?>"
         data-video-size="<?php echo esc_attr($file_size_formatted); ?>"
         data-video-date="<?php echo esc_attr($video_date); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>"
         data-is-virtual="<?php echo isset($video->is_s3_virtual) ? 'true' : 'false'; ?>">
        <div class="sidebar-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
            <div class="video-duration"><?php echo esc_html($duration); ?></div>
        </div>
        <div class="sidebar-info">
            <h4 class="sidebar-title"><?php echo esc_html($title); ?></h4>
            <div class="sidebar-meta">
                <span><?php echo esc_html($video_date); ?></span>
                <span><?php echo esc_html($file_size_formatted); ?></span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} 