<?php
if (!defined('ABSPATH')) exit;

/**
 * User Tier Detection Logic (shared with other plugins)
 */
function get_user_tier() {
    if (!is_user_logged_in()) {
        return null;
    }
    
    $user = wp_get_current_user();
    
    // Check user roles first
    if (in_array('platinum', $user->roles)) {
        return 'platinum';
    }
    if (in_array('gold', $user->roles)) {
        return 'gold';
    }
    if (in_array('silver', $user->roles)) {
        return 'silver';
    }
    
    // Check user meta as fallback
    $user_tier = get_user_meta($user->ID, 'stream_tier', true);
    if (in_array($user_tier, ['platinum', 'gold', 'silver'])) {
        return $user_tier;
    }
    
    // Default tier for logged-in users
    return 'silver';
}

/**
 * Video Access Control
 */
function user_can_access_video($video_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false; // No access for non-logged-in users
    }
    
    // Check if video library is enabled
    if (!should_display_video_library()) {
        return false;
    }
    
    $user_tier = get_user_tier();
    $required_tier = get_post_meta($video_id, '_video_required_tier', true);
    
    if (!$required_tier) {
        $required_tier = 'silver'; // Default tier
    }
    
    $tier_hierarchy = ['silver' => 1, 'gold' => 2, 'platinum' => 3];
    $user_level = isset($tier_hierarchy[$user_tier]) ? $tier_hierarchy[$user_tier] : 0;
    $required_level = isset($tier_hierarchy[$required_tier]) ? $tier_hierarchy[$required_tier] : 1;
    
    $has_access = ($user_level >= $required_level);
    
    // Allow filtering of access control
    return apply_filters('video_library_user_can_access_video', $has_access, $video_id, $user_id, $user_tier, $required_tier);
}

/**
 * External Control Functions
 */
function set_video_library_state($state) {
    $old_state = get_option('video_library_enabled', 'true');
    $new_state = $state ? 'true' : 'false';
    
    update_option('video_library_enabled', $new_state);
    
    // Trigger action hook when state changes
    if ($old_state !== $new_state) {
        do_action('video_library_state_changed', $new_state === 'true');
        video_library_log("Video library state changed from {$old_state} to {$new_state}", 'info');
    }
}

function should_display_video_library() {
    // Always allow in Elementor editor mode
    if (defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return true;
    }
    
    // Always allow in WordPress admin (for admin video library page)
    if (is_admin()) {
        return true;
    }
    
    $library_enabled = get_option('video_library_enabled', 'true') === 'true';
    $user_logged_in = is_user_logged_in();
    
    // Check if we have S3 configuration - bucket name OR endpoint with embedded bucket
    $s3_bucket = get_option('video_library_s3_bucket', '');
    $s3_endpoint = get_option('video_library_s3_endpoint', '');
    $s3_access_key = get_option('video_library_s3_access_key', '');
    
    // We have valid S3 config if:
    // 1. We have a bucket name set, OR
    // 2. We have an endpoint that contains a bucket name (like bucket.region.digitaloceanspaces.com), OR  
    // 3. We have access keys (indicating S3 is configured)
    $has_s3_config = !empty($s3_bucket) || 
                     !empty($s3_endpoint) || 
                     !empty($s3_access_key);
    
    // Default display logic - require basic S3 config
    $should_display = $library_enabled && $user_logged_in && $has_s3_config;
    
    // Allow filtering of display logic
    return apply_filters('video_library_should_display', $should_display);
}

function get_video_library_state() {
    return get_option('video_library_enabled', 'true') === 'true';
}

/**
 * Video Metadata Helpers
 */
function get_video_duration_formatted($video_id) {
    $duration = get_post_meta($video_id, '_video_duration', true);
    if (!$duration) {
        return 'Unknown';
    }
    
    $minutes = floor($duration / 60);
    $seconds = $duration % 60;
    
    return sprintf('%d:%02d', $minutes, $seconds);
}

function get_video_file_size_formatted($video_id) {
    $file_size = get_post_meta($video_id, '_video_file_size', true);
    if (!$file_size) {
        return 'Unknown';
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($file_size, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

function get_video_thumbnail_url($video_id) {
    $thumbnail = get_post_meta($video_id, '_video_thumbnail', true);
    
    if ($thumbnail) {
        // If S3 URL, generate presigned URL for thumbnail
        if (strpos($thumbnail, 's3://') === 0 || strpos($thumbnail, 'spaces.') !== false) {
            return Video_Library_S3::get_presigned_url($thumbnail, 3600); // 1 hour for thumbnails
        }
        return $thumbnail;
    }
    
    // Fallback to featured image
    if (has_post_thumbnail($video_id)) {
        return get_the_post_thumbnail_url($video_id, 'medium');
    }
    
    // Default video placeholder
    return VL_PLUGIN_URL . 'assets/images/video-placeholder.jpg';
}

/**
 * Video Query Helpers
 */
function get_videos_by_tier($tier = null, $limit = 12, $offset = 0, $orderby = 'date', $order = 'DESC') {
    if (!$tier) {
        $tier = get_user_tier();
    }
    
    $tier_hierarchy = ['silver' => 1, 'gold' => 2, 'platinum' => 3];
    $user_level = isset($tier_hierarchy[$tier]) ? $tier_hierarchy[$tier] : 0;
    
    $args = [
        'post_type' => 'video_library_item',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'orderby' => $orderby,
        'order' => $order,
        'meta_query' => []
    ];
    
    // Add tier-based access control
    if ($user_level > 0) {
        $allowed_tiers = [];
        foreach ($tier_hierarchy as $t => $level) {
            if ($level <= $user_level) {
                $allowed_tiers[] = $t;
            }
        }
        
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_video_required_tier',
                'value' => $allowed_tiers,
                'compare' => 'IN'
            ],
            [
                'key' => '_video_required_tier',
                'compare' => 'NOT EXISTS'
            ]
        ];
    }
    
    $query = new WP_Query($args);
    return $query->posts;
}

function get_featured_video() {
    $featured_id = get_option('video_library_featured_video');
    
    if ($featured_id && user_can_access_video($featured_id)) {
        return get_post($featured_id);
    }
    
    // Fallback to most recent video user can access
    $videos = get_videos_by_tier(null, 1);
    return !empty($videos) ? $videos[0] : null;
}

function search_videos($search_term, $tier = null, $limit = 12) {
    if (!$tier) {
        $tier = get_user_tier();
    }
    
    $tier_hierarchy = ['silver' => 1, 'gold' => 2, 'platinum' => 3];
    $user_level = isset($tier_hierarchy[$tier]) ? $tier_hierarchy[$tier] : 0;
    
    $args = [
        'post_type' => 'video_library_item',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        's' => $search_term,
        'meta_query' => []
    ];
    
    // Add tier-based access control
    if ($user_level > 0) {
        $allowed_tiers = [];
        foreach ($tier_hierarchy as $t => $level) {
            if ($level <= $user_level) {
                $allowed_tiers[] = $t;
            }
        }
        
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_video_required_tier',
                'value' => $allowed_tiers,
                'compare' => 'IN'
            ],
            [
                'key' => '_video_required_tier',
                'compare' => 'NOT EXISTS'
            ]
        ];
    }
    
    $query = new WP_Query($args);
    return $query->posts;
}

/**
 * Video Categories and Tags
 */
function get_video_categories($video_id) {
    return wp_get_post_terms($video_id, 'video_category', ['fields' => 'names']);
}

function get_video_tags($video_id) {
    return wp_get_post_terms($video_id, 'video_tag', ['fields' => 'names']);
}

/**
 * Enhanced logging function with structured data and debug support
 */
function video_library_log($message, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    
    // Create structured log entry
    $log_entry = [
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message
    ];
    
    // Always store in transient for admin display (even if debug disabled)
    $logs = get_transient('video_library_logs');
    if (!is_array($logs)) $logs = [];
    $logs[] = $log_entry;
    if (count($logs) > 100) array_shift($logs);
    set_transient('video_library_logs', $logs, 60 * 60 * 24); // 24 hour retention
    
    // Always log errors and warnings to WordPress error log
    if (in_array($level, ['error', 'warning'])) {
        error_log("Video Library [$level]: $message");
    }
    
    // Log debug/info messages only if debug mode is enabled
    if (get_option('video_library_debug_mode', false) && in_array($level, ['info', 'debug'])) {
        error_log("Video Library [$level]: $message");
    }
}

/**
 * Format view count for display
 */
function format_view_count($count) {
    if ($count >= 1000000) {
        return round($count / 1000000, 1) . 'M';
    } elseif ($count >= 1000) {
        return round($count / 1000, 1) . 'K';
    }
    return number_format($count);
}

/**
 * Get time ago string
 */
function get_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

/**
 * License Management Functions
 */

// Check if premium features are active
function vl_is_premium_active() {
    // Check for free access override first
    if (vl_has_free_access()) {
        return true;
    }
    
    $license_key = get_option('vl_license_key');
    $license_status = get_option('vl_license_status');
    $last_check = get_option('vl_license_last_check', 0);
    
    // If no license key, return false
    if (empty($license_key)) {
        return false;
    }
    
    // Recheck license every 24 hours
    if (time() - $last_check > 86400) {
        vl_validate_license($license_key);
    }
    
    return $license_status === 'active';
}

// Check for free access override
function vl_has_free_access() {
    // Option 1: Check for special option (you can set this for specific users)
    if (get_option('vl_free_access_enabled')) {
        return true;
    }
    
    // Option 2: Check for specific user meta or capability
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->has_cap('vl_free_access')) {
        return true;
    }
    
    // Option 3: Check for magic constant (you can define this in wp-config.php)
    if (defined('VL_FREE_ACCESS') && VL_FREE_ACCESS === true) {
        return true;
    }
    
    return false;
}

// Validate license with Lemon Squeezy
function vl_validate_license($license_key) {
    if (empty($license_key)) {
        update_option('vl_license_status', 'invalid');
        return false;
    }
    
    // Lemon Squeezy API endpoint for license validation
    $api_url = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    
    $response = wp_remote_post($api_url, [
        'headers' => [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ],
        'body' => json_encode([
            'data' => [
                'type' => 'license-key-instances',
                'attributes' => [
                    'license_key' => $license_key
                ]
            ]
        ]),
        'timeout' => 30
    ]);
    
    update_option('vl_license_last_check', time());
    
    if (is_wp_error($response)) {
        // On API error, keep existing status if it was active
        $current_status = get_option('vl_license_status');
        if ($current_status !== 'active') {
            update_option('vl_license_status', 'error');
        }
        return $current_status === 'active';
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['data']['attributes']['status']) && $data['data']['attributes']['status'] === 'active') {
        update_option('vl_license_status', 'active');
        update_option('vl_license_data', $data['data']['attributes']);
        return true;
    } else {
        update_option('vl_license_status', 'invalid');
        delete_option('vl_license_data');
        return false;
    }
}

// Get license status message
function vl_get_license_status_message() {
    if (vl_has_free_access()) {
        return ['status' => 'free', 'message' => 'Free access enabled - all premium features unlocked!'];
    }
    
    $license_key = get_option('vl_license_key');
    $license_status = get_option('vl_license_status');
    
    if (empty($license_key)) {
        return ['status' => 'none', 'message' => 'No license key entered. Limited to 5 videos and basic features.'];
    }
    
    switch ($license_status) {
        case 'active':
            return ['status' => 'active', 'message' => 'License active - premium features unlocked!'];
        case 'invalid':
            return ['status' => 'invalid', 'message' => 'Invalid license key. Please check your key.'];
        case 'error':
            return ['status' => 'error', 'message' => 'License validation error. Trying again later.'];
        default:
            return ['status' => 'unknown', 'message' => 'License status unknown. Validating...'];
    }
}

// AJAX handler for license activation
add_action('wp_ajax_vl_activate_license', 'vl_handle_license_activation');
function vl_handle_license_activation() {
    check_ajax_referer('vl_license_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $license_key = sanitize_text_field($_POST['license_key']);
    
    if (empty($license_key)) {
        wp_send_json_error('License key is required');
    }
    
    update_option('vl_license_key', $license_key);
    $is_valid = vl_validate_license($license_key);
    
    if ($is_valid) {
        wp_send_json_success(vl_get_license_status_message());
    } else {
        wp_send_json_error(vl_get_license_status_message()['message']);
    }
}

// Premium feature wrapper
function vl_premium_feature($callback, $fallback_message = null) {
    if (vl_is_premium_active()) {
        return call_user_func($callback);
    } else {
        if ($fallback_message) {
            return '<div class="vl-upgrade-notice">' . $fallback_message . ' <a href="https://your-site.com/upgrade" target="_blank">Upgrade to Premium</a></div>';
        }
        return false;
    }
}

// Check if user can access video based on license limits
function vl_check_video_limit() {
    if (vl_is_premium_active()) {
        return true; // No limit for premium
    }
    
    // Free version: limit to 5 videos
    $video_count = wp_count_posts('video_library_item')->publish;
    return $video_count < 5;
}

// Get video limit message
function vl_get_video_limit_message() {
    if (vl_is_premium_active()) {
        return '';
    }
    
    $video_count = wp_count_posts('video_library_item')->publish;
    $remaining = max(0, 5 - $video_count);
    
    if ($remaining === 0) {
        return '<div class="notice notice-warning"><p><strong>Video Limit Reached:</strong> Free version is limited to 5 videos. <a href="https://your-site.com/upgrade" target="_blank">Upgrade to Premium</a> for unlimited videos.</p></div>';
    } else {
        return '<div class="notice notice-info"><p><strong>Free Version:</strong> You can add ' . $remaining . ' more videos. <a href="https://your-site.com/upgrade" target="_blank">Upgrade to Premium</a> for unlimited videos and advanced features.</p></div>';
    }
} 