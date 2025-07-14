<?php
/**
 * Plugin Name: Ant Media Stream Access
 * Plugin URI: https://archetype.services
 * Description: Advanced stream access control with JWT authentication, tier-based routing, iframe embedding, and real-time chat integration for Ant Media Server.
 * Version: 2.2.0
 * Author: Archetype Services
 * Author URI: https://archetype.services
 * Text Domain: ant-media-stream-access
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
define('AMSA_VERSION', '2.2.0');
define('AMSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMSA_PLUGIN_FILE', __FILE__);
define('AMSA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once AMSA_PLUGIN_DIR . 'includes/logging.php';
require_once AMSA_PLUGIN_DIR . 'includes/database.php';
require_once AMSA_PLUGIN_DIR . 'includes/helpers.php';
require_once AMSA_PLUGIN_DIR . 'includes/stream-player.php';
require_once AMSA_PLUGIN_DIR . 'includes/analytics.php';
require_once AMSA_PLUGIN_DIR . 'includes/settings.php';
require_once AMSA_PLUGIN_DIR . 'includes/shortcode.php';
require_once AMSA_PLUGIN_DIR . 'includes/stream-sync.php';
require_once AMSA_PLUGIN_DIR . 'includes/webhook-notifications.php';

// Safety check for critical functions to prevent fatal errors
if (!function_exists('ant_media_log')) {
    function ant_media_log($message, $level = 'info') {
        // Fallback logging function
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Ant Media Stream Access [$level]: $message");
        }
    }
}

// Check if plugin loaded correctly
function amsa_check_loaded() {
    $required_functions = [
        'render_stream_player',
        'should_display_ant_media_stream', 
        'generate_stream_token'
    ];
    
    foreach ($required_functions as $function) {
        if (!function_exists($function)) {
            add_action('admin_notices', function() use ($function) {
                echo '<div class="notice notice-error"><p><strong>Ant Media Stream Access Error:</strong> Missing function ' . esc_html($function) . '. Please deactivate and reactivate the plugin.</p></div>';
            });
            return false;
        }
    }
    return true;
}
add_action('init', 'amsa_check_loaded', 5);

/**
 * Enqueue frontend assets
 */
function amsa_enqueue_assets() {
    // Only enqueue on frontend
    if (is_admin()) return;
    
    // Check if stream shortcode might be used
    $should_enqueue = false;
    
    // Check current post for shortcodes
    global $post;
    if ($post && (has_shortcode($post->post_content, 'antmedia_stream') || 
                  has_shortcode($post->post_content, 'antmedia_stream_direct') ||
                  has_shortcode($post->post_content, 'antmedia_simple'))) {
        $should_enqueue = true;
    }
    
    // Check if we're using Elementor and have stream widgets
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
    
    // Enqueue CSS
    wp_enqueue_style(
        'amsa-style',
        AMSA_PLUGIN_URL . 'assets/css/ant-media-stream.css',
        [],
        AMSA_VERSION
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'amsa-script',
        AMSA_PLUGIN_URL . 'assets/js/ant-media-stream.js',
        [], // No jQuery dependency for modern JS
        AMSA_VERSION,
        true
    );
    
    // Enqueue HLS.js for video streaming
    wp_enqueue_script(
        'hls-js',
        'https://cdn.jsdelivr.net/npm/hls.js@latest',
        [],
        'latest',
        true
    );
    
    // Localize script for AJAX and messaging
    wp_localize_script('amsa-script', 'amsaAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amsa_nonce'),
        'pluginUrl' => AMSA_PLUGIN_URL,
        'streamConfig' => [
            'tokenRefreshInterval' => 30000, // 30 seconds
            'heartbeatInterval' => 10000,    // 10 seconds
            'reconnectAttempts' => 3
        ]
    ]);
}
add_action('wp_enqueue_scripts', 'amsa_enqueue_assets');

/**
 * Enqueue admin assets
 */
function amsa_enqueue_admin_assets($hook) {
    // Only enqueue on ant media admin pages
    if (strpos($hook, 'ant-media-stream-access') === false) {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    wp_enqueue_style(
        'amsa-admin-style',
        AMSA_PLUGIN_URL . 'assets/css/admin.css',
        [],
        AMSA_VERSION
    );
    
    wp_enqueue_script(
        'amsa-admin-script',
        AMSA_PLUGIN_URL . 'assets/js/admin.js',
        ['jquery'],
        AMSA_VERSION,
        true
    );
    
    wp_localize_script('amsa-admin-script', 'amsaAdmin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('amsa_admin_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'amsa_enqueue_admin_assets');

/**
 * AJAX handler for stream token refresh
 */
function amsa_ajax_refresh_token() {
    check_ajax_referer('amsa_nonce', 'nonce');
    
    $stream_id = sanitize_text_field($_POST['stream_id'] ?? '');
    
    if (empty($stream_id)) {
        wp_send_json_error('Stream ID is required');
        return;
    }
    
    if (!user_can_access_stream($stream_id)) {
        wp_send_json_error('Access denied to this stream');
        return;
    }
    
    try {
        $token = generate_stream_token($stream_id);
        
        if ($token) {
            wp_send_json_success([
                'token' => $token,
                'expires_in' => get_option('ant_media_token_expiry', 3600)
            ]);
        } else {
            wp_send_json_error('Failed to generate token');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error generating token: ' . $e->getMessage());
    }
}
add_action('wp_ajax_amsa_refresh_token', 'amsa_ajax_refresh_token');
add_action('wp_ajax_nopriv_amsa_refresh_token', 'amsa_ajax_refresh_token');

/**
 * AJAX handler for stream heartbeat/status
 */
function amsa_ajax_stream_heartbeat() {
    check_ajax_referer('amsa_nonce', 'nonce');
    
    $stream_id = sanitize_text_field($_POST['stream_id'] ?? '');
    $user_id = get_current_user_id();
    $action = sanitize_text_field($_POST['action_type'] ?? ''); // 'play', 'pause', 'stop'
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    try {
        // Record analytics
        if (function_exists('amsa_record_stream_event')) {
            amsa_record_stream_event($stream_id, $user_id, $action);
        }
        
        // Trigger action for external integrations (like chat)
        do_action('amsa_stream_status_change', $stream_id, $user_id, $action);
        
        wp_send_json_success([
            'status' => 'recorded',
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        wp_send_json_error('Error recording heartbeat: ' . $e->getMessage());
    }
}
add_action('wp_ajax_amsa_stream_heartbeat', 'amsa_ajax_stream_heartbeat');

/**
 * Add settings link to plugins page
 */
add_filter('plugin_action_links_' . AMSA_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ant-media-stream-access') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Plugin activation hook
 */
function amsa_activate() {
    try {
        error_log('Ant Media Stream Access: Activation started for version ' . AMSA_VERSION);
        
        // Simple activation - just flush rewrite rules
        flush_rewrite_rules();
        
        // Try to create database tables (with error handling)
        if (class_exists('AMSA_Database')) {
            AMSA_Database::create_tables();
        }
        
        // Set basic options only if they don't exist
        if (!get_option('ant_media_server_url')) {
            add_option('ant_media_server_url', '');
        }
        if (!get_option('ant_media_jwt_secret')) {
            add_option('ant_media_jwt_secret', '');
        }
        if (!get_option('ant_media_streams_config')) {
            add_option('ant_media_streams_config', json_encode([
                'platinum' => 'stream_platinum',
                'gold' => 'stream_gold',
                'silver' => 'stream_silver'
            ]));
        }
        if (!get_option('ant_media_token_expiry')) {
            add_option('ant_media_token_expiry', 3600);
        }
        if (!get_option('ant_media_debug_mode')) {
            add_option('ant_media_debug_mode', true); // Enable debug by default
        }
        if (!get_option('stream_access_enabled')) {
            add_option('stream_access_enabled', 'true');
        }
        
        // License options
        if (!get_option('amsa_license_key')) {
            add_option('amsa_license_key', '');
        }
        if (!get_option('amsa_license_status')) {
            add_option('amsa_license_status', '');
        }
        if (!get_option('amsa_license_last_check')) {
            add_option('amsa_license_last_check', 0);
        }
        if (!get_option('amsa_free_access_enabled')) {
            add_option('amsa_free_access_enabled', false);
        }
        
        // Initialize stream status flag for offline message display (hook-based system)
        if (!get_option('amsa_streams_currently_live')) {
            add_option('amsa_streams_currently_live', false);
        }
        
        // Always update version to current
        update_option('amsa_version', AMSA_VERSION);
        
        // Set activation flag
        update_option('amsa_just_activated', true);
        
        error_log('Ant Media Stream Access: Activation completed successfully for version ' . AMSA_VERSION);
        
    } catch (Exception $e) {
        // Log error but don't stop activation
        error_log('Ant Media Stream Access activation error: ' . $e->getMessage());
    } catch (Error $e) {
        // Handle fatal errors during activation
        error_log('Ant Media Stream Access activation fatal error: ' . $e->getMessage());
    }
}
register_activation_hook(__FILE__, 'amsa_activate');

/**
 * Check if plugin was just activated/updated and show notice
 */
add_action('admin_notices', function() {
    // Just activated notice
    if (get_option('amsa_just_activated')) {
        delete_option('amsa_just_activated');
        $settings_url = admin_url('options-general.php?page=ant-media-stream-access');
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>Ant Media Stream Access v<?php echo AMSA_VERSION; ?> activated successfully!</strong>
                <a href="<?php echo esc_url($settings_url); ?>">Configure Settings</a>
            </p>
        </div>
        <?php
    }
    
    // Just updated notice
    if (get_option('amsa_just_updated')) {
        $update_info = get_option('amsa_just_updated');
        delete_option('amsa_just_updated');
        $settings_url = admin_url('options-general.php?page=ant-media-stream-access');
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>✅ Ant Media Stream Access updated successfully!</strong><br>
                <span style="color: #666;">
                    Updated from v<?php echo esc_html($update_info['from']); ?> to v<?php echo esc_html($update_info['to']); ?>
                    • Plugin remained active during update
                    • All settings preserved
                </span>
                <br>
                <a href="<?php echo esc_url($settings_url); ?>">View Settings</a> | 
                <a href="<?php echo esc_url($settings_url); ?>#debug">View Debug Logs</a>
            </p>
        </div>
        <?php
    }
});

/**
 * Plugin deactivation hook
 */
function amsa_deactivate() {
    try {
        // Clear any scheduled events or transients
        delete_transient('ant_media_logs');
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('Ant Media Stream Access deactivation error: ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, 'amsa_deactivate');

/**
 * Add admin notice if settings are not configured
 */
add_action('admin_notices', function() {
    try {
        if (!current_user_can('manage_options')) {
            return;
        }

        $server_url = get_option('ant_media_server_url');
        $jwt_secret = get_option('ant_media_jwt_secret');

        if (empty($server_url) || empty($jwt_secret)) {
            $settings_url = admin_url('options-general.php?page=ant-media-stream-access');
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Ant Media Stream Access:</strong> Please configure the server settings in 
                    <a href="<?php echo esc_url($settings_url); ?>">Settings → Ant Media Stream Access</a>
                </p>
            </div>
            <?php
        }
    } catch (Exception $e) {
        error_log('Ant Media Stream Access admin notice error: ' . $e->getMessage());
    }
});

// Load Elementor widget if Elementor is active
add_action('elementor/widgets/register', function($widgets_manager) {
    try {
        // Original Ant Media Stream widget
        $widget_file = AMSA_PLUGIN_DIR . 'elementor-widgets/ant-media-stream-widget.php';
        if (file_exists($widget_file)) {
            require_once $widget_file;
            if (class_exists('Ant_Media_Stream_Widget')) {
                $widgets_manager->register(new Ant_Media_Stream_Widget());
            }
        }
        
        // Combined Stream + Chat widget
        $combined_widget_file = AMSA_PLUGIN_DIR . 'elementor-widgets/stream-chat-widget.php';
        if (file_exists($combined_widget_file)) {
            require_once $combined_widget_file;
            if (class_exists('Stream_Chat_Combined_Widget')) {
                $widgets_manager->register(new Stream_Chat_Combined_Widget());
            }
        }
    } catch (Exception $e) {
        error_log('Ant Media Stream Access Elementor widget error: ' . $e->getMessage());
    }
});

/**
 * Plugin update handler - runs early to catch updates
 */
function amsa_check_version() {
    try {
        $installed_version = get_option('amsa_version', '1.0.0');
        
        // Force update if version is different
        if (version_compare($installed_version, AMSA_VERSION, '!=')) {
            // Store update info for admin notice
            update_option('amsa_just_updated', [
                'from' => $installed_version,
                'to' => AMSA_VERSION,
                'timestamp' => time()
            ]);
            
            // Run update procedures
            amsa_update($installed_version);
            
            // Update stored version
            update_option('amsa_version', AMSA_VERSION);
            
            // Clear any cached data
            wp_cache_flush();
            
            // Log the update
            error_log("Ant Media Stream Access: Successfully updated from {$installed_version} to " . AMSA_VERSION);
            
            // Log to plugin debug system
            if (function_exists('ant_media_log')) {
                ant_media_log("Plugin updated from v{$installed_version} to v" . AMSA_VERSION, 'info');
            }
        }
    } catch (Exception $e) {
        error_log('Ant Media Stream Access version check error: ' . $e->getMessage());
        if (function_exists('ant_media_log')) {
            ant_media_log("Update check failed: " . $e->getMessage(), 'error');
        }
    }
}
// Run on multiple hooks to ensure it catches updates
add_action('plugins_loaded', 'amsa_check_version', 1);
add_action('init', 'amsa_check_version', 1);
add_action('admin_init', 'amsa_check_version', 1);

/**
 * Handle plugin updates
 */
function amsa_update($from_version) {
    try {
        error_log("Ant Media Stream Access: Running update from {$from_version} to " . AMSA_VERSION);
        
        // Preserve activation status during update
        $was_active = is_plugin_active(AMSA_PLUGIN_BASENAME);
        
        // Flush rewrite rules for any new functionality
        flush_rewrite_rules();
        
        // Handle database updates if needed
        if (class_exists('AMSA_Database')) {
            AMSA_Database::create_tables();
        }
        
        // Ensure all current options exist (for new installations and updates)
        $default_options = [
            'ant_media_server_url' => '',
            'ant_media_jwt_secret' => '',
            'ant_media_app_name' => 'live',
            'ant_media_enabled' => 'true',
            'ant_media_debug_mode' => true,
            'ant_media_token_expiry' => 3600,
            'ant_media_streams_config' => json_encode([
                'platinum' => 'stream_platinum',
                'gold' => 'stream_gold',
                'silver' => 'stream_silver'
            ])
        ];
        
        foreach ($default_options as $option_name => $default_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $default_value);
            }
        }
        
        // Version-specific updates
        if (version_compare($from_version, '2.0.0', '<')) {
            // Version 2.0.0 adds enhanced streaming features
            if (function_exists('ant_media_log')) {
                ant_media_log('Updated to version 2.0.0 with enhanced streaming features', 'info');
            }
        }
        
        if (version_compare($from_version, '2.0.30', '<')) {
            // Versions 2.0.30+ have improved API integration
            if (function_exists('ant_media_log')) {
                ant_media_log('Updated with improved API integration and IP whitelisting support', 'info');
            }
        }
        
        if (version_compare($from_version, '2.0.33', '<')) {
            // Version 2.0.33 adds connection testing
            if (function_exists('ant_media_log')) {
                ant_media_log('Updated with API connection testing and debugging features', 'info');
            }
        }
        
        if (version_compare($from_version, '2.0.34', '<')) {
            // Version 2.0.34 adds seamless update handling
            if (function_exists('ant_media_log')) {
                ant_media_log('Updated with seamless update handling - plugin stays active during updates', 'info');
            }
        }
        
        if (version_compare($from_version, '2.0.35', '<')) {
            // Version 2.0.35 adds faster real-time stream status detection
            if (function_exists('ant_media_log')) {
                ant_media_log('Updated with faster real-time stream status detection - chat responds immediately to stream changes', 'info');
            }
        }
        
        // Ensure plugin stays active if it was active before update
        if ($was_active && !is_plugin_active(AMSA_PLUGIN_BASENAME)) {
            activate_plugin(AMSA_PLUGIN_BASENAME);
        }
        
        error_log("Ant Media Stream Access: Update completed successfully from {$from_version} to " . AMSA_VERSION);
        
    } catch (Exception $e) {
        error_log('Ant Media Stream Access update error: ' . $e->getMessage());
        if (function_exists('ant_media_log')) {
            ant_media_log("Update failed: " . $e->getMessage(), 'error');
        }
    } catch (Error $e) {
        error_log('Ant Media Stream Access update fatal error: ' . $e->getMessage());
        if (function_exists('ant_media_log')) {
            ant_media_log("Update fatal error: " . $e->getMessage(), 'error');
        }
    }
}

/**
 * Prevent WordPress from deactivating plugin during updates
 */
add_filter('pre_update_option_active_plugins', function($new_value, $old_value) {
    // If our plugin was active and is being removed, add it back
    $our_plugin = AMSA_PLUGIN_BASENAME;
    
    if (in_array($our_plugin, $old_value) && !in_array($our_plugin, $new_value)) {
        // Check if this is during an update (not manual deactivation)
        if (get_option('amsa_just_updated') || defined('WP_UPGRADING')) {
            $new_value[] = $our_plugin;
            error_log('Ant Media Stream Access: Prevented deactivation during update');
        }
    }
    
    return $new_value;
}, 10, 2); 

// Hook system to update stream status for offline message display
add_action('init', 'amsa_setup_offline_message_hooks', 15);

function amsa_setup_offline_message_hooks() {
    // Listen for our own stream events and update the global status
    add_action('ant_media_stream_status_updated', 'amsa_handle_offline_message_update', 10, 3);
    add_action('amsa_stream_status_change', 'amsa_handle_offline_message_change', 10, 3);
}

/**
 * Handle stream status updates for offline message display
 */
function amsa_handle_offline_message_update($stream_id, $status, $error = null) {
    $is_live = ($status === 'playing' || $status === 'broadcasting' || $status === 'live');
    
    // Update our global flag
    update_option('amsa_streams_currently_live', $is_live);
    
    ant_media_log("🎬 OFFLINE MESSAGE: {$stream_id} status '{$status}' -> setting streams_live to " . ($is_live ? 'TRUE' : 'FALSE'), 'info');
}

/**
 * Handle stream status changes for offline message display
 */
function amsa_handle_offline_message_change($stream_id, $user_id, $action) {
    $is_live = ($action === 'play' || $action === 'playing');
    
    // Update our global flag
    update_option('amsa_streams_currently_live', $is_live);
    
    ant_media_log("🎬 OFFLINE MESSAGE: {$stream_id} action '{$action}' -> setting streams_live to " . ($is_live ? 'TRUE' : 'FALSE'), 'info');
} 

// Initialize StreamSync system for real-time stream monitoring
add_action('init', function() {
    if (class_exists('AMSA_StreamSync')) {
        AMSA_StreamSync::get_instance();
        ant_media_log("🔄 StreamSync: Real-time monitoring system initialized", 'info');
    }
}); 