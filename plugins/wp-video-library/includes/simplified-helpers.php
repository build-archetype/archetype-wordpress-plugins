<?php
if (!defined('ABSPATH')) exit;

/**
 * ========================================
 * SIMPLIFIED VIDEO LIBRARY FUNCTIONS
 * ========================================
 * Path/Category-based filtering instead of user tiers
 */

/**
 * Get videos by filter - S3 only
 */
function get_videos_by_filter($args = []) {
    // Log the function call for debugging
    video_library_log('get_videos_by_filter called with args: ' . json_encode($args), 'debug');
    
    $defaults = [
        'limit' => 12,
        'offset' => 0,
        'orderby' => 'date',
        'order' => 'DESC',
        'path' => '',           // S3 path filter (single)
        'paths' => [],          // S3 path filters (multiple)
        'category' => '',       // Video category
        'tag' => '',           // Video tag
        's3_prefix' => '',     // S3 prefix filter
        'search' => ''         // Search term
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Scan S3 bucket for videos (S3-only system)
    $videos = [];
    if (function_exists('scan_s3_bucket_for_videos')) {
        video_library_log('Scanning S3 bucket for videos...', 'debug');
        $videos = scan_s3_bucket_for_videos($args);
        video_library_log('Found ' . count($videos) . ' videos from S3 bucket', 'debug');
    } else {
        video_library_log('S3 scanning function not available', 'warning');
    }
    
    return $videos;
}

/**
 * Video Library Shortcode - Simplified S3-only
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
            <!-- Clean YouTube-style Layout -->
            <div class="youtube-layout-clean" id="youtube-layout">
                
                <!-- Simple Search Bar -->
                <?php if ($atts['show_search'] === 'true'): ?>
                <div class="youtube-search-clean">
                    <input type="text" id="video-search" placeholder="🔍 Search videos..." value="<?php echo esc_attr($atts['search']); ?>" class="youtube-search-input">
                    <button type="button" id="video-search-btn" class="youtube-search-btn">Search</button>
                </div>
                <?php endif; ?>

                <!-- Main YouTube Layout -->
                <div class="youtube-layout-grid">
                    <!-- Main Player -->
                    <div class="youtube-main-player">
                        <div class="youtube-player-container" id="main-player-container">
                            <?php 
                            // Get first video as featured
                            $featured_video = $videos[0];
                            echo render_youtube_player_clean($featured_video);
                            ?>
                        </div>
                        
                        <div class="youtube-video-info" id="main-video-info">
                            <?php echo render_youtube_info_clean($featured_video); ?>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="youtube-sidebar-clean">
                        <div class="youtube-sidebar-header">
                            <h4>More Videos</h4>
                            <span class="video-count"><?php echo count($videos); ?></span>
                        </div>
                        
                        <div class="youtube-sidebar-videos" id="sidebar-videos">
                            <?php
                            foreach ($videos as $index => $video) {
                                echo render_youtube_sidebar_clean($video, $index);
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- JavaScript Initialization -->
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    console.log('Initializing YouTube Layout Clean...');
                    
                    // Check if the YouTube layout initialization function exists
                    if (typeof initializeYouTubeLayoutClean === 'function') {
                        initializeYouTubeLayoutClean();
                        console.log('YouTube Layout Clean initialized');
                    } else {
                        console.warn('initializeYouTubeLayoutClean function not found - videos may not be clickable');
                        
                        // Basic fallback functionality
                        $('.youtube-sidebar-item').on('click', function() {
                            console.log('Sidebar item clicked');
                            var videoUrl = $(this).data('video-url');
                            var videoTitle = $(this).data('video-title');
                            var videoDescription = $(this).data('video-description');
                            
                            if (videoUrl) {
                                // Update main player
                                var videoElement = $('#main-video-element');
                                if (videoElement.length) {
                                    videoElement.attr('src', videoUrl);
                                    videoElement.show();
                                    $('.youtube-thumbnail').hide();
                                }
                                
                                // Update video info
                                $('.youtube-title').text(videoTitle);
                                $('.youtube-description p').text(videoDescription);
                                
                                // Update active state
                                $('.youtube-sidebar-item').removeClass('active');
                                $(this).addClass('active');
                            }
                        });
                        
                        // Main play button
                        $('.youtube-play-button, #main-play-btn').on('click', function() {
                            console.log('Play button clicked');
                            var playerContainer = $(this).closest('.youtube-player-clean');
                            var videoUrl = playerContainer.data('video-url');
                            
                            if (videoUrl) {
                                var videoElement = $('#main-video-element');
                                if (videoElement.length) {
                                    videoElement.attr('src', videoUrl);
                                    videoElement.show();
                                    $('.youtube-thumbnail').hide();
                                    videoElement[0].play();
                                }
                            }
                        });
                    }
                });
                </script>
            </div>
        <?php else: ?>
            <!-- No Videos State for Clean YouTube Layout -->
            <div class="youtube-layout-clean" id="youtube-layout">
                <!-- Simple Search Bar -->
                <?php if ($atts['show_search'] === 'true'): ?>
                <div class="youtube-search-clean">
                    <input type="text" id="video-search" placeholder="🔍 Search videos..." value="<?php echo esc_attr($atts['search']); ?>" class="youtube-search-input">
                    <button type="button" id="video-search-btn" class="youtube-search-btn">Search</button>
                </div>
                <?php endif; ?>
                
                <div class="youtube-layout-grid">
                    <div class="youtube-main-player">
                        <div class="youtube-player-container" id="main-player-container">
                            <div class="youtube-player-clean no-videos">
                                <div class="youtube-thumbnail no-videos-state">
                                    <div class="no-videos-content">
                                        <div class="no-videos-icon">🎬</div>
                                        <h3>No Videos Available</h3>
                                        <p>Your video library will appear here when videos are added.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="youtube-video-info" id="main-video-info">
                            <div class="youtube-info-clean">
                                <h2 class="youtube-title">Video Library</h2>
                                <div class="youtube-meta">
                                    <span class="youtube-date">Ready to load your videos</span>
                                </div>
                                <div class="youtube-description">
                                    <p>Configure your S3 bucket settings to display videos from your storage.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="youtube-sidebar-clean">
                        <div class="youtube-sidebar-header">
                            <h4>Video Sidebar</h4>
                            <span class="video-count">0</span>
                        </div>
                        <div class="youtube-sidebar-videos" id="sidebar-videos">
                            <p style="padding: 20px; text-align: center; color: #999;">Videos will appear here when available</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php
    
    video_library_log('Shortcode rendering complete', 'debug');
    return ob_get_clean();
}

/**
 * Render video card HTML - S3 only
 */
function render_video_card_simple($video) {
    // S3-only video rendering
    $video_id = $video->ID;
    $title = $video->post_title;
    $description = $video->post_excerpt;
    $thumbnail_url = !empty($video->video_thumbnail) ? $video->video_thumbnail : VL_PLUGIN_URL . 'assets/images/video-placeholder.svg';
    $duration = !empty($video->video_duration) ? $video->video_duration : '';
    $s3_key = $video->s3_key;
    $video_url = $video->video_url;
    $file_size_formatted = !empty($video->video_file_size) ? format_file_size($video->video_file_size) : '';
    $video_date = !empty($video->post_date) ? date('M j, Y', strtotime($video->post_date)) : date('M j, Y');
    
    video_library_log('Rendering S3 video card: ' . $title, 'debug');
    
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
         data-source="s3">
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
                <span class="video-date"><?php echo esc_html($video_date); ?></span>
                <span class="video-path">Path: <?php echo esc_html(dirname($s3_key ?: 'unknown')); ?></span>
            </div>
            <?php
            // Show S3 video metadata as categories
            $categories = [];
            
            // Add file extension
            $extension = pathinfo($s3_key, PATHINFO_EXTENSION);
            if ($extension) {
                $categories[] = strtoupper($extension);
            }
            
            // Add video category if available
            if (!empty($video->video_category)) {
                $categories[] = $video->video_category;
            }
            
            // Add tags if available
            if (!empty($video->video_tags)) {
                $tags = explode(', ', $video->video_tags);
                $categories = array_merge($categories, array_slice($tags, 0, 2)); // Only first 2 tags
            }
            
            if (!empty($categories)):
            ?>
            <div class="video-categories">
                <?php foreach ($categories as $category): ?>
                <span class="video-category-tag"><?php echo esc_html($category); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
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
    
    // Auto-extract bucket name from endpoint if not explicitly set
    if (empty($bucket) && !empty($endpoint)) {
        $clean_endpoint = str_replace(['http://', 'https://'], '', $endpoint);
        if (strpos($clean_endpoint, '.digitaloceanspaces.com') !== false) {
            $parts = explode('.', $clean_endpoint);
            if (count($parts) >= 3) {
                $bucket = $parts[0];
                video_library_log("Auto-extracted bucket name from endpoint: {$bucket}", 'info');
            }
        }
    }
    
    if (empty($bucket)) {
        video_library_log('No S3 bucket configured and could not extract from endpoint', 'warning');
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
                
                if (!in_array($extension, $video_extensions)) {
                    continue;
                }
                
                // Apply path filtering - if paths are specified, only include videos from those paths
                $path_matches = true;
                if (!empty($args['paths']) && is_array($args['paths'])) {
                    $path_matches = false;
                    foreach ($args['paths'] as $path) {
                        // Check if file starts with the specified path
                        if (strpos($file, trim($path)) === 0) {
                            $path_matches = true;
                            break;
                        }
                    }
                } elseif (!empty($args['path'])) {
                    // Single path filtering for backward compatibility
                    $path_matches = strpos($file, trim($args['path'])) === 0;
                }
                
                if (!$path_matches) {
                    continue;
                }
                
                // Create virtual video object
                $video = create_virtual_video_from_s3($file, $bucket, $endpoint);
                if ($video) {
                    // Apply additional filtering
                    $include_video = true;
                    
                    // Category filtering
                    if (!empty($args['category']) && $video->video_category !== $args['category']) {
                        $include_video = false;
                    }
                    
                    // Search filtering
                    if ($include_video && !empty($args['search'])) {
                        $search_term = strtolower($args['search']);
                        $title = strtolower($video->post_title);
                        $description = strtolower($video->post_excerpt);
                        
                        if (strpos($title, $search_term) === false && strpos($description, $search_term) === false) {
                            $include_video = false;
                        }
                    }
                    
                    if ($include_video) {
                        $videos[] = $video;
                    }
                }
            }
            
            // Sort videos
            usort($videos, function($a, $b) use ($args) {
                $order = strtoupper($args['order']) === 'ASC' ? 1 : -1;
                
                switch ($args['orderby']) {
                    case 'title':
                        return $order * strcmp($a->post_title, $b->post_title);
                    case 'filename':
                        return $order * strcmp($a->s3_key, $b->s3_key);
                    case 'date':
                    default:
                        return $order * (strtotime($b->post_date) - strtotime($a->post_date));
                }
            });
            
            // Apply limit and offset
            if (isset($args['offset']) && $args['offset'] > 0) {
                $videos = array_slice($videos, $args['offset']);
            }
            
            if (isset($args['limit']) && $args['limit'] > 0) {
                $videos = array_slice($videos, 0, $args['limit']);
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
 * Create a virtual video object from S3 file with enhanced metadata extraction
 */
function create_virtual_video_from_s3($s3_key, $bucket, $endpoint) {
    $filename = basename($s3_key);
    $title = pathinfo($filename, PATHINFO_FILENAME);
    
    // Enhanced title parsing from filename
    $metadata = extract_metadata_from_filename($filename);
    
    // Use extracted title or clean up filename
    $title = $metadata['title'] ?: str_replace(['_', '-', '.'], ' ', $title);
    $title = ucwords(trim($title));
    
    // Create a virtual WP_Post object
    $video = new stdClass();
    $video->ID = 's3_' . md5($s3_key); // Virtual ID
    $video->post_title = $title;
    $video->post_content = $metadata['description'] ?: 'Video file: ' . $filename;
    $video->post_excerpt = $metadata['description'] ?: generate_description_from_path($s3_key);
    $video->post_type = 'video_library_item';
    $video->post_status = 'publish';
    
    // Use metadata date or file modification date if available, otherwise current date
    $video_date = $metadata['date'] ?: date('Y-m-d H:i:s');
    $video->post_date = $video_date;
    $video->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime($video_date));
    
    // Add S3 metadata
    $video->s3_key = $s3_key;
    $video->s3_bucket = $bucket;
    $video->s3_endpoint = $endpoint;
    
    // Enhanced metadata
    $video->video_duration = $metadata['duration'] ?: '';
    $video->video_thumbnail = $metadata['thumbnail'] ?: find_thumbnail_for_video($s3_key, $bucket, $endpoint);
    $video->video_category = $metadata['category'] ?: extract_category_from_path($s3_key);
    $video->video_tags = $metadata['tags'] ?: extract_tags_from_path($s3_key);
    $video->video_file_size = $metadata['file_size'] ?: '';
    
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
    
    // All videos are from S3 in this system
    
    video_library_log('Created virtual video: ' . $title . ' from ' . $s3_key, 'debug');
    video_library_log('Final video URL: ' . substr($video->video_url, 0, 100) . '...', 'debug');
    
    return $video;
}

/**
 * Extract metadata from video filename
 * Supports various filename patterns and metadata conventions
 */
function extract_metadata_from_filename($filename) {
    $metadata = [
        'title' => '',
        'description' => '',
        'duration' => '',
        'date' => '',
        'category' => '',
        'tags' => '',
        'thumbnail' => '',
        'file_size' => ''
    ];
    
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    
    // Pattern 1: Title_YYYY-MM-DD_Duration_Category.ext
    // Example: "Trading Basics_2024-01-15_1200_tutorial.mp4"
    if (preg_match('/^(.+?)_(\d{4}-\d{2}-\d{2})_(\d+)_(.+)$/', $basename, $matches)) {
        $metadata['title'] = str_replace(['_', '-'], ' ', $matches[1]);
        $metadata['date'] = $matches[2] . ' 00:00:00';
        $metadata['duration'] = seconds_to_duration($matches[3]);
        $metadata['category'] = str_replace(['_', '-'], ' ', $matches[4]);
    }
    // Pattern 2: YYYY-MM-DD_Title_Category.ext
    // Example: "2024-01-15_Market Analysis_premium.mp4"
    elseif (preg_match('/^(\d{4}-\d{2}-\d{2})_(.+?)_(.+)$/', $basename, $matches)) {
        $metadata['date'] = $matches[1] . ' 00:00:00';
        $metadata['title'] = str_replace(['_', '-'], ' ', $matches[2]);
        $metadata['category'] = str_replace(['_', '-'], ' ', $matches[3]);
    }
    // Pattern 3: Title_Duration_YYYY-MM-DD.ext
    // Example: "Options Trading Webinar_3600_2024-01-15.mp4"
    elseif (preg_match('/^(.+?)_(\d+)_(\d{4}-\d{2}-\d{2})$/', $basename, $matches)) {
        $metadata['title'] = str_replace(['_', '-'], ' ', $matches[1]);
        $metadata['duration'] = seconds_to_duration($matches[2]);
        $metadata['date'] = $matches[3] . ' 00:00:00';
    }
    // Pattern 4: Category_Title.ext
    // Example: "tutorial_basic-trading-concepts.mp4"
    elseif (preg_match('/^([^_]+)_(.+)$/', $basename, $matches)) {
        $metadata['category'] = str_replace(['_', '-'], ' ', $matches[1]);
        $metadata['title'] = str_replace(['_', '-'], ' ', $matches[2]);
    }
    
    // Extract tags from brackets or parentheses
    if (preg_match_all('/[\[\(]([^\]\)]+)[\]\)]/', $basename, $tag_matches)) {
        $metadata['tags'] = implode(', ', $tag_matches[1]);
    }
    
    return $metadata;
}

/**
 * Generate description from S3 path
 */
function generate_description_from_path($s3_key) {
    $path_parts = explode('/', dirname($s3_key));
    $path_parts = array_filter($path_parts); // Remove empty parts
    
    if (empty($path_parts)) {
        return 'Video from the main library';
    }
    
    $folder_name = end($path_parts);
    $folder_name = str_replace(['_', '-'], ' ', $folder_name);
    $folder_name = ucwords($folder_name);
    
    return "Video from the {$folder_name} collection";
}

/**
 * Extract category from S3 path
 */
function extract_category_from_path($s3_key) {
    $path_parts = explode('/', dirname($s3_key));
    $path_parts = array_filter($path_parts);
    
    if (empty($path_parts)) {
        return 'General';
    }
    
    // Use the first folder as category
    $category = reset($path_parts);
    $category = str_replace(['_', '-'], ' ', $category);
    return ucwords($category);
}

/**
 * Extract tags from S3 path
 */
function extract_tags_from_path($s3_key) {
    $path_parts = explode('/', dirname($s3_key));
    $path_parts = array_filter($path_parts);
    
    if (count($path_parts) <= 1) {
        return '';
    }
    
    // Use folder names as tags
    $tags = array_map(function($part) {
        return ucwords(str_replace(['_', '-'], ' ', $part));
    }, $path_parts);
    
    return implode(', ', $tags);
}

/**
 * Find thumbnail for video
 */
function find_thumbnail_for_video($s3_key, $bucket, $endpoint) {
    $video_path = pathinfo($s3_key);
    $possible_thumbnails = [
        $video_path['dirname'] . '/' . $video_path['filename'] . '.jpg',
        $video_path['dirname'] . '/' . $video_path['filename'] . '.png',
        $video_path['dirname'] . '/' . $video_path['filename'] . '_thumb.jpg',
        $video_path['dirname'] . '/' . $video_path['filename'] . '_thumbnail.jpg',
        $video_path['dirname'] . '/thumbnails/' . $video_path['filename'] . '.jpg',
        $video_path['dirname'] . '/thumbs/' . $video_path['filename'] . '.jpg'
    ];
    
    // Check if any of these thumbnail files exist
    // For now, return placeholder - in production you'd check S3
    foreach ($possible_thumbnails as $thumb_path) {
        // TODO: Check if thumbnail exists in S3
        // For now, just return the first potential path
        if (class_exists('Video_Library_S3')) {
            $thumb_url = Video_Library_S3::get_presigned_url($thumb_path, 3600);
            if ($thumb_url) {
                return $thumb_url;
            }
        }
    }
    
    return VL_PLUGIN_URL . 'assets/images/video-placeholder.svg';
}

/**
 * Convert seconds to duration format (MM:SS or HH:MM:SS)
 */
function seconds_to_duration($seconds) {
    if (!is_numeric($seconds) || $seconds <= 0) {
        return '';
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } else {
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
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
        
        // This is an S3-only video library system
        video_library_log('System configured for S3-only video storage', 'info');
        
        // Test S3 class availability
        $s3_class_exists = class_exists('Video_Library_S3');
        video_library_log('Video_Library_S3 class exists: ' . ($s3_class_exists ? 'true' : 'false'), 'info');
        
        // Test S3 scanning if class exists
        if ($s3_class_exists && $bucket && $access_key && $secret_key) {
            video_library_log('Testing S3 bucket scan...', 'info');
            $s3_videos = scan_s3_bucket_for_videos();
            video_library_log('S3 videos found: ' . count($s3_videos), 'info');
            
            if (!empty($s3_videos)) {
                $first_video = $s3_videos[0];
                video_library_log('First S3 video title: ' . $first_video->post_title, 'info');
                video_library_log('First S3 video URL: ' . substr($first_video->video_url, 0, 100) . '...', 'info');
            }
        } else {
            video_library_log('Cannot test S3 scanning - missing class or configuration', 'warning');
        }
        
        // Test get_videos_by_filter function
        video_library_log('Testing get_videos_by_filter...', 'info');
        $all_videos = get_videos_by_filter(['limit' => 5]);
        video_library_log('get_videos_by_filter returned: ' . count($all_videos) . ' videos', 'info');
        
        if (!empty($all_videos)) {
            $first_video = $all_videos[0];
            video_library_log('First video title: ' . $first_video->post_title, 'info');
            video_library_log('First video S3 key: ' . $first_video->s3_key, 'info');
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
    // S3-only video data extraction
    $video_id = $video->ID;
    $title = $video->post_title;
    $description = $video->post_excerpt;
    $thumbnail_url = !empty($video->video_thumbnail) ? $video->video_thumbnail : VL_PLUGIN_URL . 'assets/images/video-placeholder.svg';
    $duration = !empty($video->video_duration) ? $video->video_duration : '';
    $s3_key = $video->s3_key;
    $video_url = $video->video_url;
    $file_size_formatted = !empty($video->video_file_size) ? format_file_size($video->video_file_size) : '';
    $video_date = !empty($video->post_date) ? date('M j, Y', strtotime($video->post_date)) : date('M j, Y');
    
    ob_start();
    ?>
    <div class="main-video-container" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-duration="<?php echo esc_attr($duration); ?>"
         data-video-size="<?php echo esc_attr($file_size_formatted); ?>"
         data-video-date="<?php echo esc_attr($video_date); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>"
         data-source="s3">
         
        <div class="main-video-player">
            <video id="main-dashboard-video" controls playsinline style="display: none;">
                Your browser does not support the video tag.
            </video>
            
            <div class="main-video-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
                <div class="main-video-play-button">
                    <span class="dashicons dashicons-controls-play"></span>
                </div>
                <?php if ($duration): ?>
                <div class="main-video-duration"><?php echo esc_html($duration); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render YouTube-style video info panel
 */
function render_youtube_video_info($video) {
    // S3-only video data extraction
    $video_id = $video->ID;
    $title = $video->post_title;
    $description = $video->post_excerpt;
    $duration = !empty($video->video_duration) ? $video->video_duration : '';
    $s3_key = $video->s3_key;
    $file_size_formatted = !empty($video->video_file_size) ? format_file_size($video->video_file_size) : '';
    $video_date = !empty($video->post_date) ? date('F j, Y', strtotime($video->post_date)) : date('F j, Y');
    
    // Extract categories from S3 metadata
    $categories = [];
    if (!empty($video->video_category)) {
        $categories[] = $video->video_category;
    }
    if (!empty($video->video_tags)) {
        $tags = explode(', ', $video->video_tags);
        $categories = array_merge($categories, array_slice($tags, 0, 2));
    }
    
    ob_start();
    ?>
    <div class="dashboard-video-info">
        <h2 class="video-info-title"><?php echo esc_html($title); ?></h2>
        
        <div class="video-info-meta">
            <span class="video-meta-item">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html($video_date); ?>
            </span>
            <?php if ($duration): ?>
            <span class="video-meta-item">
                <span class="dashicons dashicons-clock"></span>
                <?php echo esc_html($duration); ?>
            </span>
            <?php endif; ?>
            <span class="video-meta-item">
                <span class="dashicons dashicons-database"></span>
                <?php echo esc_html($file_size_formatted); ?>
            </span>
        </div>
        
        <?php if ($description): ?>
        <div class="video-info-description">
            <p><?php echo esc_html($description); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($categories): ?>
        <div class="video-info-categories">
            <?php foreach ($categories as $category): ?>
            <span class="video-category-badge"><?php echo esc_html($category); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="video-info-actions">
            <button type="button" class="button button-primary" id="play-fullscreen-btn">
                <span class="dashicons dashicons-fullscreen-alt"></span>
                Play Fullscreen
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Clean YouTube player render function - S3 only
 */
function render_youtube_player_clean($video) {
    // S3-only video data extraction
    $video_id = $video->ID;
    $title = $video->post_title;
    $thumbnail_url = !empty($video->video_thumbnail) ? $video->video_thumbnail : VL_PLUGIN_URL . 'assets/images/video-placeholder.svg';
    $duration = !empty($video->video_duration) ? $video->video_duration : '';
    $s3_key = $video->s3_key;
    $video_url = $video->video_url;
    $description = $video->post_excerpt;
    $category = !empty($video->video_category) ? $video->video_category : '';
    
    ob_start();
    ?>
    <div class="youtube-player-clean" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-duration="<?php echo esc_attr($duration); ?>"
         data-video-category="<?php echo esc_attr($category); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>"
         data-source="s3">
        
        <!-- Video Element (Hidden initially) -->
        <video id="main-video-element" class="youtube-video-element" controls style="display: none;">
            Your browser does not support video playback.
        </video>
        
        <!-- Thumbnail with Play Button -->
        <div class="youtube-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
            <div class="youtube-play-button" id="main-play-btn">
                <svg width="68" height="48" viewBox="0 0 68 48">
                    <path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79 0 34 0 34 0S12.21 0 6.9 1.55c-2.93.78-4.63 3.26-5.42 6.19C0 13.05 0 24 0 24s0 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 48 34 48 34 48s21.79 0 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C68 34.95 68 24 68 24s0-10.95-1.48-16.26z" fill="#ff0000"/>
                    <path d="M45 24L27 14v20l18-10z" fill="#fff"/>
                </svg>
            </div>
            <?php if ($duration): ?>
            <div class="youtube-duration"><?php echo esc_html($duration); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Clean YouTube video info render function
 */
function render_youtube_info_clean($video) {
    // S3-only video data extraction
    $title = $video->post_title;
    $description = $video->post_excerpt;
    $video_date = !empty($video->post_date) ? date('M j, Y', strtotime($video->post_date)) : date('M j, Y');
    $duration = !empty($video->video_duration) ? $video->video_duration : '';
    $category = !empty($video->video_category) ? $video->video_category : '';
    $tags = !empty($video->video_tags) ? $video->video_tags : '';
    
    ob_start();
    ?>
    <div class="youtube-info-clean">
        <h2 class="youtube-title"><?php echo esc_html($title); ?></h2>
        <div class="youtube-meta">
            <span class="youtube-date"><?php echo esc_html($video_date); ?></span>
            <?php if ($duration): ?>
            <span class="youtube-duration">• <?php echo esc_html($duration); ?></span>
            <?php endif; ?>
            <?php if ($category): ?>
            <span class="youtube-category">• <?php echo esc_html($category); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($description): ?>
        <div class="youtube-description">
            <p><?php echo esc_html($description); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($tags): ?>
        <div class="youtube-tags">
            <span class="tags-label">Tags:</span>
            <?php 
            $tag_array = explode(', ', $tags);
            foreach ($tag_array as $tag):
            ?>
            <span class="youtube-tag"><?php echo esc_html(trim($tag)); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Clean YouTube sidebar item render function - S3 only
 */
function render_youtube_sidebar_clean($video, $index = 0) {
    // S3-only video data extraction
    $video_id = $video->ID;
    $title = $video->post_title;
    $thumbnail_url = !empty($video->video_thumbnail) ? $video->video_thumbnail : VL_PLUGIN_URL . 'assets/images/video-placeholder.svg';
    $duration = !empty($video->video_duration) ? $video->video_duration : '';
    $s3_key = $video->s3_key;
    $video_url = $video->video_url;
    $video_date = !empty($video->post_date) ? date('M j', strtotime($video->post_date)) : date('M j');
    $description = $video->post_excerpt;
    $category = !empty($video->video_category) ? $video->video_category : '';
    
    $active_class = $index === 0 ? ' active' : '';
    
    ob_start();
    ?>
    <div class="youtube-sidebar-item<?php echo $active_class; ?>" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-video-description="<?php echo esc_attr($description); ?>"
         data-video-duration="<?php echo esc_attr($duration); ?>"
         data-video-category="<?php echo esc_attr($category); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>"
         data-source="s3">
        
        <div class="youtube-sidebar-thumb" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
            <?php if ($duration): ?>
            <div class="youtube-sidebar-duration"><?php echo esc_html($duration); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="youtube-sidebar-info">
            <h4 class="youtube-sidebar-title"><?php echo esc_html($title); ?></h4>
            <div class="youtube-sidebar-date"><?php echo esc_html($video_date); ?></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} 