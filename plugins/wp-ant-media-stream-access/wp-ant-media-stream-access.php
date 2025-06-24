<?php
/**
 * Plugin Name: WP Ant Media Stream Access
 * Plugin URI: https://archetype.services
 * Description: Simple iframe embedding for Ant Media Server streams with automatic stream status detection and customizable offline messages.
 * Version: 1.0.1
 * Author: Archetype Services
 * Author URI: https://archetype.services
 * Text Domain: ant-media-stream
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
define('AMSA_VERSION', '1.0.1');
define('AMSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMSA_PLUGIN_FILE', __FILE__);
define('AMSA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once AMSA_PLUGIN_DIR . 'includes/logging.php';
require_once AMSA_PLUGIN_DIR . 'includes/helpers.php';
require_once AMSA_PLUGIN_DIR . 'includes/shortcode.php';
require_once AMSA_PLUGIN_DIR . 'includes/settings.php';
require_once AMSA_PLUGIN_DIR . 'includes/rocket-chat-integration.php';

// Safety check for critical functions to prevent fatal errors
if (!function_exists('ant_media_log')) {
    function ant_media_log($message, $level = 'info') {
        // Fallback logging function
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Ant Media Stream [$level]: $message");
        }
    }
}

// Check if plugin loaded correctly
function ant_media_check_loaded() {
    $required_functions = [
        'ant_media_stream_shortcode',
        'should_display_ant_media_stream'
    ];
    
    foreach ($required_functions as $function) {
        if (!function_exists($function)) {
            add_action('admin_notices', function() use ($function) {
                echo '<div class="notice notice-error"><p><strong>Ant Media Stream Error:</strong> Missing function ' . esc_html($function) . '. Please deactivate and reactivate the plugin.</p></div>';
            });
            return false;
        }
    }
    return true;
}
add_action('init', 'ant_media_check_loaded', 5);

/**
 * Enqueue frontend assets
 */
function ant_media_enqueue_assets() {
    // Only enqueue on frontend
    if (is_admin()) return;
    
    // Check if shortcode might be used
    $should_enqueue = false;
    
    // Check current post
    global $post;
    if ($post && has_shortcode($post->post_content, 'antmedia_simple')) {
        $should_enqueue = true;
    }
    
    // Check if we're on a page that might use the shortcode
    if (!$should_enqueue && (is_page() || is_single() || is_home() || is_front_page())) {
        $should_enqueue = true; // Load on all content pages to be safe
    }
    
    if (!$should_enqueue) return;
    
    // Enqueue CSS
    wp_enqueue_style(
        'ant-media-stream-style',
        AMSA_PLUGIN_URL . 'assets/css/ant-media-stream.css',
        [],
        AMSA_VERSION
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'ant-media-stream-script',
        AMSA_PLUGIN_URL . 'assets/js/ant-media-stream.js',
        [],
        AMSA_VERSION,
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('ant-media-stream-script', 'antMediaAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ant_media_nonce'),
        'pluginUrl' => AMSA_PLUGIN_URL
    ]);
}
add_action('wp_enqueue_scripts', 'ant_media_enqueue_assets');

/**
 * Add settings link to plugins page
 */
add_filter('plugin_action_links_' . AMSA_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ant-media-stream') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Plugin activation hook
 */
function ant_media_activate() {
    try {
        error_log('Ant Media Stream: Activation started for version ' . AMSA_VERSION);
        
        // Simple activation - just flush rewrite rules
        flush_rewrite_rules();
        
        // Set basic options only if they don't exist
        if (!get_option('ant_media_enabled')) {
            add_option('ant_media_enabled', 'true');
        }
        
        // Always update version to current
        update_option('ant_media_version', AMSA_VERSION);
        
        // Force enable debug mode for initial testing
        update_option('ant_media_debug_mode', true);
        
        // Set activation flag
        update_option('ant_media_just_activated', true);
        
        error_log('Ant Media Stream: Activation completed successfully for version ' . AMSA_VERSION);
        
    } catch (Exception $e) {
        // Log error but don't stop activation
        error_log('Ant Media Stream activation error: ' . $e->getMessage());
    } catch (Error $e) {
        // Handle fatal errors during activation
        error_log('Ant Media Stream activation fatal error: ' . $e->getMessage());
    }
}
register_activation_hook(__FILE__, 'ant_media_activate');

/**
 * Check if plugin was just activated and show notice
 */
add_action('admin_notices', function() {
    if (get_option('ant_media_just_activated')) {
        delete_option('ant_media_just_activated');
        $settings_url = admin_url('options-general.php?page=ant-media-stream');
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>Ant Media Stream v<?php echo AMSA_VERSION; ?> activated successfully!</strong>
                <a href="<?php echo esc_url($settings_url); ?>">Configure Settings</a>
            </p>
        </div>
        <?php
    }
});

/**
 * Plugin deactivation hook
 */
function ant_media_deactivate() {
    try {
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('Ant Media Stream deactivation error: ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, 'ant_media_deactivate');

/**
 * Add admin notice if settings are not configured
 */
add_action('admin_notices', function() {
    try {
        if (!current_user_can('manage_options')) {
            return;
        }

        $server_url = get_option('ant_media_server_url');

        if (empty($server_url)) {
            $settings_url = admin_url('options-general.php?page=ant-media-stream');
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Ant Media Stream:</strong> Please configure the server URL in 
                    <a href="<?php echo esc_url($settings_url); ?>">Settings → Ant Media Stream</a>
                </p>
            </div>
            <?php
        }
    } catch (Exception $e) {
        error_log('Ant Media Stream admin notice error: ' . $e->getMessage());
    }
});

/**
 * Plugin update handler - runs early to catch updates
 */
function ant_media_check_version() {
    try {
        $installed_version = get_option('ant_media_version', '1.0.1');
        
        // Force update if version is different
        if (version_compare($installed_version, AMSA_VERSION, '!=')) {
            ant_media_update($installed_version);
            update_option('ant_media_version', AMSA_VERSION);
            
            // Clear any cached data
            wp_cache_flush();
            
            // Log the update
            error_log("Ant Media Stream: Updated from {$installed_version} to " . AMSA_VERSION);
        }
    } catch (Exception $e) {
        error_log('Ant Media Stream version check error: ' . $e->getMessage());
    }
}
// Run on multiple hooks to ensure it catches updates
add_action('plugins_loaded', 'ant_media_check_version', 1);
add_action('init', 'ant_media_check_version', 1);
add_action('admin_init', 'ant_media_check_version', 1);

/**
 * Handle plugin updates
 */
function ant_media_update($from_version) {
    try {
        error_log("Ant Media Stream: Running update from {$from_version} to " . AMSA_VERSION);
        
        // Flush rewrite rules for any new features
        flush_rewrite_rules();
        
        // Ensure all options exist
        if (!get_option('ant_media_enabled')) {
            add_option('ant_media_enabled', 'true');
        }
        
        // Force enable debug mode temporarily for update testing
        update_option('ant_media_debug_mode', true);
        
        if (function_exists('ant_media_log')) {
            ant_media_log('Updated to version ' . AMSA_VERSION, 'info');
        }
        
        error_log('Ant Media Stream: Successfully updated to ' . AMSA_VERSION);
    } catch (Exception $e) {
        error_log('Ant Media Stream update error: ' . $e->getMessage());
    } catch (Error $e) {
        error_log('Ant Media Stream update fatal error: ' . $e->getMessage());
    }
} 