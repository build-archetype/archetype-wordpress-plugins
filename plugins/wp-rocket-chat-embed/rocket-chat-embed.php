<?php
/*
Plugin Name: Rocket.Chat Embed for WordPress
Description: Easily embed Rocket.Chat in your WordPress site with SSO, user sync, and more.
Version: 1.0.38
Author: Build Archetype Solutions (https://buildarchetype.dev)

This plugin is not affiliated with or endorsed by Rocket.Chat. Rocket.Chat is a registered trademark of Rocket.Chat Technologies Corp.
*/

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('RCE_VERSION', '1.0.39');
define('RCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RCE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once RCE_PLUGIN_DIR . 'includes/helpers.php';
require_once RCE_PLUGIN_DIR . 'includes/logging.php';
require_once RCE_PLUGIN_DIR . 'includes/settings.php';
require_once RCE_PLUGIN_DIR . 'includes/shortcode.php';

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
