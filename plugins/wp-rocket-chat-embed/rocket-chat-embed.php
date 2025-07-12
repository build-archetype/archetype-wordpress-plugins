<?php
/*
Plugin Name: Rocket.Chat Embed for WordPress
Description: Easily embed Rocket.Chat in your WordPress site with SSO, user sync, and more.
Version: 1.0.87
Author: Build Archetype Solutions (https://buildarchetype.dev)

This plugin is not affiliated with or endorsed by Rocket.Chat. Rocket.Chat is a registered trademark of Rocket.Chat Technologies Corp.
*/

if (!defined('ABSPATH')) exit;

// Define plugin constants
if (!defined('RCE_VERSION')) define('RCE_VERSION', '1.0.87');
if (!defined('RCE_PLUGIN_DIR')) define('RCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('RCE_PLUGIN_URL')) define('RCE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once RCE_PLUGIN_DIR . 'includes/helpers.php';
require_once RCE_PLUGIN_DIR . 'includes/logging.php';
require_once RCE_PLUGIN_DIR . 'includes/settings.php';
require_once RCE_PLUGIN_DIR . 'includes/shortcode.php';

// WordPress Heartbeat integration: Chat syncs automatically with Ant Media stream status
// No separate polling needed - uses WordPress's built-in Heartbeat API (15-second intervals)

// Hook-based stream status integration with Ant Media plugin
add_action('init', 'rce_setup_stream_hooks', 20);

function rce_setup_stream_hooks() {
    // Listen for ant-media stream events and update our simple flag
    add_action('ant_media_stream_status_updated', 'rce_handle_stream_status_update', 10, 3);
    add_action('amsa_stream_status_change', 'rce_handle_stream_status_change', 10, 3);
    
    // Also hook into WebSocket updates via JavaScript (if available)
    add_action('wp_footer', 'rce_add_stream_status_listener');
}

/**
 * Handle stream status updates from ant-media plugin
 */
function rce_handle_stream_status_update($stream_id, $status, $error = null) {
    $is_live = ($status === 'playing' || $status === 'broadcasting' || $status === 'live');
    
    // Update our simple flag
    update_option('amsa_streams_currently_live', $is_live);
    
    rocket_chat_log("🎯 STREAM HOOK: {$stream_id} status '{$status}' -> setting streams_live to " . ($is_live ? 'TRUE' : 'FALSE'), 'info');
    
    // Trigger real-time update via AJAX if possible
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Send immediate update to any listening chat widgets
        wp_send_json_success([
            'stream_status_changed' => true,
            'any_live' => $is_live,
            'stream_id' => $stream_id,
            'status' => $status
        ]);
    }
}

/**
 * Handle stream status changes from ant-media plugin  
 */
function rce_handle_stream_status_change($stream_id, $user_id, $action) {
    $is_live = ($action === 'play' || $action === 'playing');
    
    // Update our simple flag
    update_option('amsa_streams_currently_live', $is_live);
    
    rocket_chat_log("🎯 STREAM HOOK: {$stream_id} action '{$action}' -> setting streams_live to " . ($is_live ? 'TRUE' : 'FALSE'), 'info');
}

/**
 * Add JavaScript listener for real-time WebSocket updates
 */
function rce_add_stream_status_listener() {
    // Only add on pages with chat
    if (!is_user_logged_in()) return;
    
    ?>
    <script>
    // Listen for WebSocket stream events from ant-media plugin
    window.addEventListener('DOMContentLoaded', function() {
        // Hook into existing updateChatVisibility function
        if (typeof window.updateChatVisibility === 'function') {
            const original = window.updateChatVisibility;
            window.updateChatVisibility = function(isLive, source) {
                console.log('🎯 RCE Hook: Stream status changed via', source, '- isLive:', isLive);
                
                // Update our WordPress option via AJAX
                if (typeof rocketChatAjax !== 'undefined') {
                    fetch(rocketChatAjax.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'rce_update_stream_flag',
                            nonce: rocketChatAjax.nonce,
                            streams_live: isLive ? '1' : '0',
                            source: source || 'websocket'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('🎯 RCE Hook: Updated WordPress stream flag via AJAX');
                        }
                    })
                    .catch(err => console.warn('🎯 RCE Hook: AJAX update failed:', err));
                }
                
                // Call original function
                if (original) {
                    original(isLive, source);
                }
            };
        }
        
        console.log('🎯 RCE Hook: Stream status listener initialized');
    });
    </script>
    <?php
}

/**
 * AJAX handler to update stream flag from JavaScript
 */
add_action('wp_ajax_rce_update_stream_flag', 'rce_ajax_update_stream_flag');
add_action('wp_ajax_nopriv_rce_update_stream_flag', 'rce_ajax_update_stream_flag');

function rce_ajax_update_stream_flag() {
    check_ajax_referer('rocket_chat_nonce', 'nonce');
    
    $streams_live = $_POST['streams_live'] === '1';
    $source = sanitize_text_field($_POST['source'] ?? 'unknown');
    
    update_option('amsa_streams_currently_live', $streams_live);
    
    rocket_chat_log("🎯 STREAM AJAX: Updated streams_live to " . ($streams_live ? 'TRUE' : 'FALSE') . " from {$source}", 'info');
    
    wp_send_json_success([
        'streams_live' => $streams_live,
        'source' => $source,
        'timestamp' => time()
    ]);
}

// Load Elementor widget if Elementor is active (modern method)
add_action('elementor/widgets/register', function($widgets_manager) {
    require_once RCE_PLUGIN_DIR . 'elementor-widgets/rocket-chat-embed-widget.php';
    $widgets_manager->register( new \Elementor\Rocket_Chat_Embed_Widget() );
});

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=rocket-chat-embed') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Register activation hook
register_activation_hook(__FILE__, function() {
    // Initialize the stream status flag to false (new hook-based system)
    add_option('amsa_streams_currently_live', false);
    
    // Create default options
    add_option('rocket_chat_host_url', '');
    add_option('rocket_chat_admin_user', '');
    add_option('rocket_chat_admin_pass', '');
    add_option('rocket_chat_default_channel', 'general');
    add_option('rocket_chat_debug_mode', false);
    add_option('chat_open', 'true');
    
    // License options
    add_option('rce_license_key', '');
    add_option('rce_license_status', '');
    add_option('rce_license_last_check', 0);
    add_option('rce_free_access_enabled', false);
    
    // Flush rewrite rules in case we add any
    flush_rewrite_rules();
});

// Register deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear any scheduled events or transients
    delete_transient('rocket_chat_logs');
});

// Add admin notice if settings are not configured
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $host_url = get_option('rocket_chat_host_url');
    $admin_user = get_option('rocket_chat_admin_user');
    $admin_pass = get_option('rocket_chat_admin_pass');

    if (empty($host_url) || empty($admin_user) || empty($admin_pass)) {
        $settings_url = admin_url('options-general.php?page=rocket-chat-embed');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Rocket Chat Embed:</strong> Please configure the plugin settings in 
                <a href="<?php echo esc_url($settings_url); ?>">Settings → Rocket Chat Widget</a>
            </p>
        </div>
        <?php
    }
});

// Enqueue admin scripts for license management
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'settings_page_rocket-chat-embed') {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'rceAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'license_nonce' => wp_create_nonce('rce_license_nonce')
    ]);
});

// Enqueue frontend scripts for AJAX functionality
add_action('wp_enqueue_scripts', function() {
    // Only enqueue on frontend when needed
    if (is_admin()) return;
    
    // Check if shortcode or widget might be used
    $should_enqueue = false;
    
    // Check current post for shortcodes
    global $post;
    if ($post && (has_shortcode($post->post_content, 'rocket_chat') || 
                  has_shortcode($post->post_content, 'rocket_chat_debug'))) {
        $should_enqueue = true;
    }
    
    // Check if we're using Elementor and might have rocket chat widgets
    if (!$should_enqueue && defined('ELEMENTOR_VERSION')) {
        // Always load on Elementor pages since widgets might be present
        if (is_page() || is_single() || is_home() || is_front_page()) {
            $should_enqueue = true;
        }
    }
    
    // Check if we're on a page that might use the shortcode
    if (!$should_enqueue && (is_page() || is_single() || is_home() || is_front_page())) {
        $should_enqueue = true; // Load on all content pages to be safe
    }
    
    if (!$should_enqueue) return;
    
    // Create a minimal script handle for localization
    wp_enqueue_script('rocket-chat-frontend', 'data:text/javascript;base64,' . base64_encode('// Rocket Chat Frontend Script'), [], RCE_VERSION, true);
    
    // Localize the script with ajaxurl and nonces
    wp_localize_script('rocket-chat-frontend', 'rocketChatAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rocket_chat_nonce'),
        'ant_media_nonce' => wp_create_nonce('ant_media_nonce')
    ]);
});
