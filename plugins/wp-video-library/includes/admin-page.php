<?php
if (!defined('ABSPATH')) exit;

/**
 * Video Library Admin Page - Dashboard Integration
 * Creates a self-contained video library page within the WordPress dashboard
 */

// Add admin menu item
add_action('admin_menu', 'video_library_add_admin_page');

function video_library_add_admin_page() {
    add_menu_page(
        'Video Library',           // Page title
        'Video Library',           // Menu title
        'read',                   // Capability - allow all users to view
        'video-library-archive',   // Menu slug
        'video_library_admin_page_content', // Callback function
        'dashicons-video-alt3',    // Icon
        30                        // Position
    );
}

// Admin page content
function video_library_admin_page_content() {
    // Check if user can access video library
    if (!should_display_video_library()) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'video-library'));
    }

    // Get settings
    $no_live_message = get_option('video_library_no_live_message', 'No live stream is currently active. Please check the calendar for the next live session.');
    $featured_video_id = get_option('video_library_featured_video', '');
    $videos_per_page = get_option('video_library_videos_per_page', 12);
    
    // Get initial video data
    $videos = get_videos_by_filter([
        'limit' => $videos_per_page,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    // Get featured video or use first video
    $featured_video = null;
    if ($featured_video_id) {
        $featured_post = get_post($featured_video_id);
        if ($featured_post && $featured_post->post_type === 'video_library_item') {
            $featured_video = $featured_post;
        }
    }
    
    if (!$featured_video && !empty($videos)) {
        $featured_video = $videos[0];
    }
    
    ?>
    <div class="wrap video-library-dashboard">
        <div class="video-library-header">
            <h1 class="video-library-title">
                <span class="dashicons dashicons-video-alt3"></span>
                Video Archive
            </h1>
            <div class="video-library-controls">
                <div class="video-library-search-container">
                    <input type="text" id="video-dashboard-search" placeholder="Search videos..." class="video-search-input" />
                    <button type="button" id="video-dashboard-search-btn" class="button">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </div>
                <div class="video-library-filters-container">
                    <select id="video-dashboard-category" class="video-filter-select">
                        <option value="">All Categories</option>
                        <?php
                        $categories = get_terms(['taxonomy' => 'video_category', 'hide_empty' => true]);
                        if ($categories && !is_wp_error($categories)) {
                            foreach ($categories as $category) {
                                echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <select id="video-dashboard-path" class="video-filter-select">
                        <option value="">All Paths</option>
                        <?php
                        $suggested_paths = get_option('video_library_suggested_paths', "premium/\ntutorials/\nwebinars/\nlive-streams/");
                        $paths = array_filter(explode("\n", $suggested_paths));
                        foreach ($paths as $path) {
                            $path = trim($path);
                            if ($path) {
                                echo '<option value="' . esc_attr($path) . '">' . esc_html(rtrim($path, '/')) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="video-library-dashboard-content">
            <?php if ($featured_video || !empty($videos)): ?>
                <!-- Main Layout: Video Player + Sidebar -->
                <div class="video-dashboard-layout">
                    <!-- Main Video Player Section -->
                    <div class="video-dashboard-main">
                        <div class="video-dashboard-player" id="dashboard-main-player">
                            <?php if ($featured_video): ?>
                                <?php echo render_dashboard_main_player($featured_video); ?>
                            <?php else: ?>
                                <div class="video-placeholder">
                                    <div class="video-placeholder-content">
                                        <span class="dashicons dashicons-video-alt3"></span>
                                        <h3>No Featured Video</h3>
                                        <p><?php echo esc_html($no_live_message); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Video Information Panel -->
                        <div class="video-dashboard-info" id="dashboard-video-info">
                            <?php if ($featured_video): ?>
                                <?php echo render_dashboard_video_info($featured_video); ?>
                            <?php else: ?>
                                <div class="video-info-placeholder">
                                    <h2>Welcome to Your Video Library</h2>
                                    <p>Select a video from the sidebar to start watching, or use the search and filters above to find specific content.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Video Sidebar -->
                    <div class="video-dashboard-sidebar">
                        <div class="video-sidebar-header">
                            <h3>Video Collection</h3>
                            <span class="video-count" id="video-count-display">
                                <?php echo count($videos); ?> video<?php echo count($videos) !== 1 ? 's' : ''; ?>
                            </span>
                        </div>
                        
                        <div class="video-sidebar-list" id="dashboard-video-list">
                            <?php
                            if (!empty($videos)) {
                                foreach ($videos as $video) {
                                    echo render_dashboard_sidebar_item($video);
                                }
                            } else {
                                echo '<div class="no-videos-sidebar">
                                    <div class="no-videos-icon">🎬</div>
                                    <p>No videos found. Videos will appear here when they are uploaded to your S3 bucket.</p>
                                </div>';
                            }
                            ?>
                        </div>
                        
                        <div class="video-sidebar-footer">
                            <button type="button" id="load-more-dashboard-videos" class="button button-secondary" style="display: none;">
                                Load More Videos
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Videos State -->
                <div class="video-dashboard-empty">
                    <div class="video-dashboard-empty-main">
                        <div class="empty-state-icon">
                            <span class="dashicons dashicons-video-alt3"></span>
                        </div>
                        <h2>No Videos Available</h2>
                        <p><?php echo esc_html($no_live_message); ?></p>
                        <div class="empty-state-actions">
                            <a href="<?php echo admin_url('options-general.php?page=video-library'); ?>" class="button button-primary">
                                Configure Video Library
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Modal for Fullscreen Playback -->
    <div id="video-dashboard-modal" class="video-dashboard-modal">
        <div class="video-modal-content">
            <button class="video-modal-close" id="dashboard-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
            <div class="video-modal-player">
                <video id="dashboard-modal-video" controls playsinline>
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    // Initialize dashboard video library
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof VideoLibraryDashboard !== 'undefined') {
            VideoLibraryDashboard.init();
        }
    });
    </script>
    <?php
}

/**
 * Render the main video player for dashboard
 */
function render_dashboard_main_player($video) {
    $video_id = $video->ID;
    $video_url = get_video_streaming_url($video_id);
    $thumbnail_url = get_video_thumbnail_url($video_id);
    $duration = get_video_duration_formatted($video_id);
    $title = get_the_title($video_id);
    $s3_key = get_post_meta($video_id, '_video_s3_key', true);
    
    ob_start();
    ?>
    <div class="main-video-container" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>">
        
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
 * Render video information panel for dashboard
 */
function render_dashboard_video_info($video) {
    $video_id = $video->ID;
    $title = get_the_title($video_id);
    $description = get_the_excerpt($video_id) ?: wp_trim_words($video->post_content, 30);
    $duration = get_video_duration_formatted($video_id);
    $file_size = get_post_meta($video_id, '_video_file_size', true);
    $file_size_formatted = $file_size ? format_file_size($file_size) : 'Unknown';
    $video_date = get_the_date('F j, Y', $video_id);
    $categories = get_video_categories($video_id);
    
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
 * Render sidebar video item for dashboard
 */
function render_dashboard_sidebar_item($video) {
    $video_id = $video->ID;
    $title = get_the_title($video_id);
    $thumbnail_url = get_video_thumbnail_url($video_id);
    $duration = get_video_duration_formatted($video_id);
    $video_date = get_the_date('M j, Y', $video_id);
    $video_url = get_video_streaming_url($video_id);
    $s3_key = get_post_meta($video_id, '_video_s3_key', true);
    
    ob_start();
    ?>
    <div class="sidebar-video-item" 
         data-video-id="<?php echo esc_attr($video_id); ?>"
         data-video-url="<?php echo esc_attr($video_url); ?>"
         data-video-title="<?php echo esc_attr($title); ?>"
         data-s3-key="<?php echo esc_attr($s3_key); ?>">
        
        <div class="sidebar-video-thumbnail" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');">
            <?php if ($duration): ?>
            <div class="sidebar-video-duration"><?php echo esc_html($duration); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-video-details">
            <h4 class="sidebar-video-title"><?php echo esc_html($title); ?></h4>
            <div class="sidebar-video-meta">
                <span class="sidebar-video-date"><?php echo esc_html($video_date); ?></span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Enqueue admin assets for video library dashboard
add_action('admin_enqueue_scripts', 'video_library_enqueue_dashboard_assets');

function video_library_enqueue_dashboard_assets($hook) {
    // Only enqueue on video library admin page
    if ($hook !== 'toplevel_page_video-library-archive') {
        return;
    }
    
    // Enqueue CSS
    wp_enqueue_style(
        'video-library-dashboard',
        VL_PLUGIN_URL . 'assets/css/dashboard.css',
        [],
        VL_VERSION
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'video-library-dashboard',
        VL_PLUGIN_URL . 'assets/js/dashboard.js',
        ['jquery'],
        VL_VERSION,
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('video-library-dashboard', 'videoLibraryDashboard', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('video_library_dashboard_nonce'),
        'pluginUrl' => VL_PLUGIN_URL,
        'strings' => [
            'loading' => __('Loading...', 'video-library'),
            'noVideos' => __('No videos found', 'video-library'),
            'error' => __('Error loading videos', 'video-library'),
        ]
    ]);
} 