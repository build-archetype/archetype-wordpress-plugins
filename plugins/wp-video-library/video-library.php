<?php
/**
 * Plugin Name: Simple Video Library
 * Plugin URI: https://archetype.services
 * Description: A simple, modern video library system for WordPress with DigitalOcean Spaces integration.
 * Version: 2.1.2
 * Author: Archetype Services
 * Author URI: https://archetype.services
 * Text Domain: video-library
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('VL_VERSION', '1.7.0');
define('VL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VL_PLUGIN_FILE', __FILE__);
define('VL_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once VL_PLUGIN_DIR . 'includes/logging.php';
require_once VL_PLUGIN_DIR . 'includes/database.php';
require_once VL_PLUGIN_DIR . 'includes/helpers.php';
require_once VL_PLUGIN_DIR . 'includes/simplified-helpers.php';
require_once VL_PLUGIN_DIR . 'includes/s3-integration.php';
require_once VL_PLUGIN_DIR . 'includes/analytics.php';
require_once VL_PLUGIN_DIR . 'includes/settings.php';
require_once VL_PLUGIN_DIR . 'includes/shortcode.php';
require_once VL_PLUGIN_DIR . 'includes/post-types.php';
require_once VL_PLUGIN_DIR . 'includes/admin-page.php';

// Safety check for critical functions to prevent fatal errors
if (!function_exists('video_library_log')) {
    function video_library_log($message, $level = 'info') {
        // Fallback logging function
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Video Library [$level]: $message");
        }
    }
}

// Check if plugin loaded correctly
function video_library_check_loaded() {
    $required_functions = [
        'video_library_shortcode',
        'should_display_video_library', 
        'get_videos_by_filter'
    ];
    
    foreach ($required_functions as $function) {
        if (!function_exists($function)) {
            add_action('admin_notices', function() use ($function) {
                echo '<div class="notice notice-error"><p><strong>Video Library Error:</strong> Missing function ' . esc_html($function) . '. Please deactivate and reactivate the plugin.</p></div>';
            });
            return false;
        }
    }
    return true;
}
add_action('init', 'video_library_check_loaded', 5);

/**
 * Enqueue frontend assets
 */
function video_library_enqueue_assets() {
    // Only enqueue on frontend
    if (is_admin()) return;
    
    // Always enqueue if shortcode might be used (themes/plugins can call it dynamically)
    // We'll do a more lightweight check instead of parsing post content
    $should_enqueue = false;
    
    // Check current post
    global $post;
    if ($post && (has_shortcode($post->post_content, 'video_library') || 
                  has_shortcode($post->post_content, 'video_library_simple'))) {
        $should_enqueue = true;
    }
    
    // Check if we're on a page that might use the shortcode
    if (!$should_enqueue && (is_page() || is_single() || is_home() || is_front_page())) {
        $should_enqueue = true; // Load on all content pages to be safe
    }
    
    if (!$should_enqueue) return;
    
    // Enqueue CSS
    wp_enqueue_style(
        'video-library-style',
        VL_PLUGIN_URL . 'assets/css/video-library.css',
        [],
        VL_VERSION
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'video-library-script',
        VL_PLUGIN_URL . 'assets/js/video-library.js',
        [], // Remove jQuery dependency as we're using vanilla JS
        VL_VERSION,
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('video-library-script', 'videoLibraryAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('video_library_nonce'),
        'pluginUrl' => VL_PLUGIN_URL
    ]);
}
add_action('wp_enqueue_scripts', 'video_library_enqueue_assets');

/**
 * Enqueue admin assets
 */
function video_library_enqueue_admin_assets($hook) {
    // Only enqueue on video library admin pages
    if (strpos($hook, 'video-library') === false) {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    wp_enqueue_style(
        'video-library-admin-style',
        VL_PLUGIN_URL . 'assets/css/video-library.css',
        [],
        VL_VERSION
    );
}
add_action('admin_enqueue_scripts', 'video_library_enqueue_admin_assets');

/**
 * AJAX handler for video filtering
 */
function video_library_ajax_filter_videos() {
    // Safety check
    if (!function_exists('get_videos_by_filter') || !function_exists('render_video_card_simple')) {
        wp_send_json_error('Plugin functions not loaded correctly');
        return;
    }
    
    check_ajax_referer('video_library_nonce', 'nonce');
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');
    $type = sanitize_text_field($_POST['type'] ?? '');
    $path = sanitize_text_field($_POST['path'] ?? '');
    $page = intval($_POST['page'] ?? 1);
    $per_page = 12;
    
    try {
        $videos = get_videos_by_filter([
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'category' => $category,
            'path' => $path,
            'search' => $search
        ]);
        
        $html = '';
        if ($videos) {
            foreach ($videos as $video) {
                $html .= render_video_card_simple($video);
            }
        }
        
        // Check if there are more videos
        $total_videos = get_videos_by_filter([
            'limit' => -1,
            'category' => $category,
            'path' => $path,
            'search' => $search
        ]);
        
        $total_count = count($total_videos);
        $has_more = $total_count > ($page * $per_page);
        
        wp_send_json_success([
            'html' => $html,
            'hasMore' => $has_more,
            'total' => $total_count,
            'page' => $page
        ]);
    } catch (Exception $e) {
        wp_send_json_error('Error loading videos: ' . $e->getMessage());
    }
}
add_action('wp_ajax_filter_videos', 'video_library_ajax_filter_videos');
add_action('wp_ajax_nopriv_filter_videos', 'video_library_ajax_filter_videos');

/**
 * AJAX handler for getting fresh presigned URLs
 */
function video_library_ajax_get_fresh_presigned_url() {
    check_ajax_referer('video_library_nonce', 'nonce');
    
    $s3_key = sanitize_text_field($_POST['s3_key'] ?? '');
    
    if (empty($s3_key)) {
        wp_send_json_error('S3 key is required');
        return;
    }
    
    try {
        if (class_exists('Video_Library_S3')) {
            $presigned_url = Video_Library_S3::get_presigned_url($s3_key);
            
            if ($presigned_url) {
                wp_send_json_success([
                    'url' => $presigned_url,
                    'expires_in' => get_option('video_library_presigned_expiry', 3600)
                ]);
            } else {
                wp_send_json_error('Failed to generate presigned URL');
            }
        } else {
            wp_send_json_error('S3 integration not available');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error generating presigned URL: ' . $e->getMessage());
    }
}
add_action('wp_ajax_get_fresh_presigned_url', 'video_library_ajax_get_fresh_presigned_url');
add_action('wp_ajax_nopriv_get_fresh_presigned_url', 'video_library_ajax_get_fresh_presigned_url');

/**
 * AJAX handler for toggling video favorites
 */
function video_library_ajax_toggle_favorite() {
    check_ajax_referer('video_library_nonce', 'nonce');
    
    $video_id = intval($_POST['video_id'] ?? 0);
    $favorited = $_POST['favorited'] === '1';
    
    if (!$video_id) {
        wp_send_json_error('Invalid video ID');
    }
    
    // Store favorite in user meta or session/cookie for non-logged users
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $favorites = get_user_meta($user_id, 'video_favorites', true) ?: [];
        
        if ($favorited) {
            $favorites[] = $video_id;
            $favorites = array_unique($favorites);
        } else {
            $favorites = array_diff($favorites, [$video_id]);
        }
        
        update_user_meta($user_id, 'video_favorites', $favorites);
    }
    
    wp_send_json_success(['favorited' => $favorited]);
}
add_action('wp_ajax_toggle_video_favorite', 'video_library_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_toggle_video_favorite', 'video_library_ajax_toggle_favorite');

/**
 * Add settings link to plugins page
 */
add_filter('plugin_action_links_' . VL_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=video-library') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Plugin activation hook
 */
function video_library_activate() {
    try {
        error_log('Video Library: Activation started for version ' . VL_VERSION);
        
        // Simple activation - just flush rewrite rules
        flush_rewrite_rules();
        
        // Initialize post types first (with safety check)
        if (function_exists('video_library_register_post_types')) {
            video_library_register_post_types();
        }
        
        // Try to create database tables (with error handling)
        if (class_exists('Video_Library_Database')) {
            Video_Library_Database::create_tables();
        }
        
        // Set basic options only if they don't exist
        if (!get_option('video_library_enabled')) {
            add_option('video_library_enabled', 'true');
        }
        
        // Always update version to current
        update_option('video_library_version', VL_VERSION);
        
        // Force enable debug mode for initial testing
        update_option('video_library_debug_mode', true);
        
        // Set activation flag
        update_option('video_library_just_activated', true);
        
        error_log('Video Library: Activation completed successfully for version ' . VL_VERSION);
        
    } catch (Exception $e) {
        // Log error but don't stop activation
        error_log('Video Library activation error: ' . $e->getMessage());
    } catch (Error $e) {
        // Handle fatal errors during activation
        error_log('Video Library activation fatal error: ' . $e->getMessage());
    }
}
register_activation_hook(__FILE__, 'video_library_activate');

/**
 * Check if plugin was just activated and show notice
 */
add_action('admin_notices', function() {
    if (get_option('video_library_just_activated')) {
        delete_option('video_library_just_activated');
        $settings_url = admin_url('options-general.php?page=video-library');
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>Simple Video Library v<?php echo VL_VERSION; ?> activated successfully!</strong>
                <a href="<?php echo esc_url($settings_url); ?>">Configure Settings</a>
            </p>
        </div>
        <?php
    }
});

/**
 * Plugin deactivation hook
 */
function video_library_deactivate() {
    try {
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('Video Library deactivation error: ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, 'video_library_deactivate');

/**
 * Add admin notice if settings are not configured
 */
add_action('admin_notices', function() {
    try {
        if (!current_user_can('manage_options')) {
            return;
        }

        $s3_bucket = get_option('video_library_s3_bucket');
        $s3_access_key = get_option('video_library_s3_access_key');

        if (empty($s3_bucket) || empty($s3_access_key)) {
            $settings_url = admin_url('options-general.php?page=video-library');
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Simple Video Library:</strong> Please configure the S3 storage settings in 
                    <a href="<?php echo esc_url($settings_url); ?>">Settings → Simple Video Library</a>
                </p>
            </div>
            <?php
        }
    } catch (Exception $e) {
        error_log('Video Library admin notice error: ' . $e->getMessage());
    }
});

// Load Elementor widget if Elementor is active (simple approach like rocket-chat)
add_action('elementor/widgets/register', function($widgets_manager) {
    try {
        $widget_file = VL_PLUGIN_DIR . 'elementor-widgets/video-library-widget.php';
        if (file_exists($widget_file)) {
            require_once $widget_file;
        }
    } catch (Exception $e) {
        error_log('Video Library Elementor widget error: ' . $e->getMessage());
    }
});

/**
 * Plugin update handler - runs early to catch updates
 */
function video_library_check_version() {
    try {
        $installed_version = get_option('video_library_version', '1.0.0');
        
        // Force update if version is different
        if (version_compare($installed_version, VL_VERSION, '!=')) {
            video_library_update($installed_version);
            update_option('video_library_version', VL_VERSION);
            
            // Clear any cached data
            wp_cache_flush();
            
            // Log the update
            error_log("Video Library: Updated from {$installed_version} to " . VL_VERSION);
        }
    } catch (Exception $e) {
        error_log('Video Library version check error: ' . $e->getMessage());
    }
}
// Run on multiple hooks to ensure it catches updates
add_action('plugins_loaded', 'video_library_check_version', 1);
add_action('init', 'video_library_check_version', 1);
add_action('admin_init', 'video_library_check_version', 1);

/**
 * Handle plugin updates
 */
function video_library_update($from_version) {
    try {
        error_log("Video Library: Running update from {$from_version} to " . VL_VERSION);
        
        // Flush rewrite rules for any new post types
        flush_rewrite_rules();
        
        // Handle database updates if needed
        if (class_exists('Video_Library_Database')) {
            Video_Library_Database::create_tables();
        }
        
        // Version-specific updates
        if (version_compare($from_version, '1.2.3', '<')) {
            // Version 1.2.3 fixes S3 bucket listing signature issues
            // Ensure all options exist
            if (!get_option('video_library_enabled')) {
                add_option('video_library_enabled', 'true');
            }
            
            // Force enable debug mode temporarily for update testing
            update_option('video_library_debug_mode', true);
            
            if (function_exists('video_library_log')) {
                video_library_log('Updated to version 1.2.3 with S3 bucket listing fix', 'info');
            }
            
            error_log('Video Library: Successfully updated to 1.2.3 - Fixed S3 bucket listing');
        }
    } catch (Exception $e) {
        error_log('Video Library update error: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('Video Library update fatal error: ' . $e->getMessage());
    }
}
