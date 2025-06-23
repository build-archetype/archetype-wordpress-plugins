<?php
if (!defined('ABSPATH')) exit;

/**
 * Video Library Shortcode Registration
 * 
 * The actual shortcode function is defined in helpers.php
 * This file handles the registration and any additional shortcode-related functionality
 */

// The shortcode is already registered in helpers.php
// This file can be used for additional shortcode-related functionality

/**
 * AJAX handler for loading more videos
 */
add_action('wp_ajax_load_more_videos', 'handle_load_more_videos');
add_action('wp_ajax_nopriv_load_more_videos', 'handle_load_more_videos');

function handle_load_more_videos() {
    check_ajax_referer('video_library_nonce', 'nonce');
    
    $offset = intval($_POST['offset']);
    $atts = $_POST['atts'];
    
    // Sanitize the attributes
    $atts = array_map('sanitize_text_field', $atts);
    
    // Get videos with offset
    $videos = get_videos_by_filter([
        'limit' => $atts['videos_per_page'],
        'offset' => $offset,
        'category' => $atts['category'],
        'tag' => $atts['tag'],
        'path' => $atts['path'],
        's3_prefix' => $atts['s3_prefix'],
        'search' => $atts['search'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order']
    ]);
    
    if ($videos) {
        $html = '';
        foreach ($videos as $video) {
            $html .= render_video_card($video, false);
        }
        wp_send_json_success(['html' => $html, 'has_more' => count($videos) >= $atts['videos_per_page']]);
    } else {
        wp_send_json_success(['html' => '', 'has_more' => false]);
    }
}

/**
 * AJAX handler for video search
 */
add_action('wp_ajax_search_videos', 'handle_search_videos');
add_action('wp_ajax_nopriv_search_videos', 'handle_search_videos');

function handle_search_videos() {
    check_ajax_referer('video_library_nonce', 'nonce');
    
    $search_term = sanitize_text_field($_POST['search']);
    $atts = $_POST['atts'];
    
    // Sanitize the attributes
    $atts = array_map('sanitize_text_field', $atts);
    $atts['search'] = $search_term;
    
    // Get videos matching search
    $videos = get_videos_by_filter([
        'limit' => $atts['videos_per_page'],
        'offset' => 0,
        'category' => $atts['category'],
        'tag' => $atts['tag'],
        'path' => $atts['path'],
        's3_prefix' => $atts['s3_prefix'],
        'search' => $atts['search'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order']
    ]);
    
    if ($videos) {
        $html = '';
        foreach ($videos as $video) {
            $html .= render_video_card($video, false);
        }
        wp_send_json_success(['html' => $html, 'has_more' => count($videos) >= $atts['videos_per_page']]);
    } else {
        wp_send_json_success(['html' => '<div class="no-videos-message">No videos found matching your search.</div>', 'has_more' => false]);
    }
} 