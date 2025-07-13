<?php
/*
Plugin Name: Rocket.Chat Embed for WordPress
Description: Easily embed Rocket.Chat in your WordPress site with SSO, user sync, and more.
Version: 1.0.88
Author: Build Archetype Solutions (https://buildarchetype.dev)

This plugin is not affiliated with or endorsed by Rocket.Chat. Rocket.Chat is a registered trademark of Rocket.Chat Technologies Corp.
*/

if (!defined('ABSPATH')) exit;

// Define plugin constants
if (!defined('RCE_VERSION')) define('RCE_VERSION', '1.0.88');
if (!defined('RCE_PLUGIN_DIR')) define('RCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('RCE_PLUGIN_URL')) define('RCE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once RCE_PLUGIN_DIR . 'includes/helpers.php';
require_once RCE_PLUGIN_DIR . 'includes/logging.php';
require_once RCE_PLUGIN_DIR . 'includes/settings.php';
require_once RCE_PLUGIN_DIR . 'includes/shortcode.php';

// NEW: Include the clean stream integration system
require_once RCE_PLUGIN_DIR . 'includes/stream-integration.php';

// DEPRECATED: Old complex hook system (removed)
// The new system uses clean WordPress hooks and events

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
