<?php
/**
 * Plugin Name: WP Ant Media Stream Access (Minimal Test)
 * Plugin URI: https://archetype.services
 * Description: Minimal test version to isolate fatal error issues
 * Version: 1.0.0-test
 * Author: Archetype Services
 */

if (!defined('ABSPATH')) exit;

// Define basic constants
define('AMSA_VERSION', '1.0.0-test');
define('AMSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMSA_PLUGIN_FILE', __FILE__);
define('AMSA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Basic logging function
if (!function_exists('ant_media_log')) {
    function ant_media_log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Ant Media Stream [$level]: $message");
        }
    }
}

// Basic activation hook
register_activation_hook(__FILE__, function() {
    ant_media_log('Minimal plugin activated successfully', 'info');
});

// Admin menu
add_action('admin_menu', function() {
    add_options_page(
        'Ant Media Test',
        'Ant Media Test',
        'manage_options',
        'ant-media-test',
        function() {
            echo '<div class="wrap"><h1>Ant Media Test Plugin</h1><p>Minimal test version activated successfully!</p></div>';
        }
    );
});

ant_media_log('Minimal plugin loaded', 'info');
