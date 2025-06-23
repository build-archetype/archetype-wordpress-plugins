<?php
/**
 * Rocket Chat Integration for Ant Media Stream Access
 * 
 * This file handles the integration between Ant Media streams and Rocket Chat visibility.
 * When streams are active, chat becomes visible. When no streams are active, chat is hidden.
 */

if (!defined('ABSPATH')) exit;

class AMSA_RocketChat_Integration {
    
    private $monitored_streams = [];
    private $integration_enabled = false;
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Only initialize if Rocket Chat plugin is active
        if (!$this->is_rocket_chat_active()) {
            return;
        }
        
        $this->integration_enabled = get_option('amsa_rocket_chat_integration_enabled', false);
        $this->monitored_streams = $this->get_monitored_streams();
        
        if ($this->integration_enabled) {
            // Hook into Rocket Chat's display filter
            add_filter('rocket_chat_should_display', [$this, 'filter_chat_display'], 10, 1);
            
            // Add AJAX endpoint for real-time status updates
            add_action('wp_ajax_amsa_check_integration_status', [$this, 'ajax_check_integration_status']);
            add_action('wp_ajax_nopriv_amsa_check_integration_status', [$this, 'ajax_check_integration_status']);
            
            // Add JavaScript for real-time updates
            add_action('wp_footer', [$this, 'add_integration_javascript']);
        }
        
        // Always add admin hooks for settings
        add_action('admin_init', [$this, 'add_integration_settings']);
    }
    
    /**
     * Check if Rocket Chat plugin is active
     */
    private function is_rocket_chat_active() {
        return function_exists('should_display_rocket_chat');
    }
    
    /**
     * Filter chat display based on stream status
     */
    public function filter_chat_display($should_display) {
        // If already false, keep it false
        if (!$should_display) {
            return false;
        }
        
        // If no streams to monitor, use default behavior
        if (empty($this->monitored_streams)) {
            return $should_display;
        }
        
        // Check if any monitored stream is live
        $any_stream_live = $this->check_any_stream_live();
        
        ant_media_log("Rocket Chat integration - Any stream live: " . ($any_stream_live ? 'true' : 'false'), 'debug');
        
        return $any_stream_live;
    }
    
    /**
     * Check if any monitored stream is currently live
     */
    private function check_any_stream_live() {
        foreach ($this->monitored_streams as $stream_config) {
            $stream_id = $stream_config['stream_id'];
            $server_url = $stream_config['server_url'] ?? null;
            $app_name = $stream_config['app_name'] ?? 'live';
            
            if (check_ant_media_stream_status($stream_id, $server_url, $app_name)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get monitored streams from settings
     */
    private function get_monitored_streams() {
        $streams_setting = get_option('amsa_rocket_chat_monitored_streams', '');
        
        if (empty($streams_setting)) {
            return [];
        }
        
        $streams = [];
        $lines = explode("\n", $streams_setting);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse line: stream_id|server_url|app_name
            $parts = explode('|', $line);
            if (count($parts) >= 1) {
                $streams[] = [
                    'stream_id' => trim($parts[0]),
                    'server_url' => isset($parts[1]) ? trim($parts[1]) : null,
                    'app_name' => isset($parts[2]) ? trim($parts[2]) : 'live'
                ];
            }
        }
        
        return $streams;
    }
    
    /**
     * AJAX handler for checking integration status
     */
    public function ajax_check_integration_status() {
        if (!$this->integration_enabled) {
            wp_die('Integration not enabled');
        }
        
        $any_stream_live = $this->check_any_stream_live();
        
        wp_send_json_success([
            'any_stream_live' => $any_stream_live,
            'chat_should_display' => apply_filters('rocket_chat_should_display', true),
            'monitored_streams_count' => count($this->monitored_streams)
        ]);
    }
    
    /**
     * Add integration settings to the admin page
     */
    public function add_integration_settings() {
        // Add settings section
        add_settings_section(
            'amsa_rocket_chat_integration',
            'Rocket Chat Integration',
            [$this, 'render_integration_section'],
            'ant-media-settings'
        );
        
        // Integration enabled setting
        add_settings_field(
            'amsa_rocket_chat_integration_enabled',
            'Enable Chat Integration',
            [$this, 'render_integration_enabled_field'],
            'ant-media-settings',
            'amsa_rocket_chat_integration'
        );
        
        // Monitored streams setting
        add_settings_field(
            'amsa_rocket_chat_monitored_streams',
            'Monitored Streams',
            [$this, 'render_monitored_streams_field'],
            'ant-media-settings',
            'amsa_rocket_chat_integration'
        );
        
        // Register settings
        register_setting('ant-media-settings', 'amsa_rocket_chat_integration_enabled');
        register_setting('ant-media-settings', 'amsa_rocket_chat_monitored_streams');
    }
    
    /**
     * Render integration section description
     */
    public function render_integration_section() {
        echo '<p>Configure integration with Rocket Chat plugin. When enabled, chat will only be visible when monitored streams are active.</p>';
        
        if (!$this->is_rocket_chat_active()) {
            echo '<div class="notice notice-warning"><p><strong>Notice:</strong> Rocket Chat Embed plugin is not active. Please activate it to use this integration.</p></div>';
        }
    }
    
    /**
     * Render integration enabled checkbox
     */
    public function render_integration_enabled_field() {
        $enabled = get_option('amsa_rocket_chat_integration_enabled', false);
        ?>
        <label>
            <input type="checkbox" name="amsa_rocket_chat_integration_enabled" value="1" <?php checked($enabled); ?> />
            Enable Rocket Chat integration (chat visibility tied to stream status)
        </label>
        <p class="description">When enabled, Rocket Chat will only be visible when at least one monitored stream is live.</p>
        <?php
    }
    
    /**
     * Render monitored streams textarea
     */
    public function render_monitored_streams_field() {
        $streams = get_option('amsa_rocket_chat_monitored_streams', '');
        ?>
        <textarea 
            name="amsa_rocket_chat_monitored_streams" 
            rows="5" 
            cols="50" 
            placeholder="stream1&#10;stream2|custom-server-url&#10;stream3|custom-server-url|custom-app"
        ><?php echo esc_textarea($streams); ?></textarea>
        <p class="description">
            Enter stream IDs to monitor, one per line. Format: <code>stream_id</code> or <code>stream_id|server_url</code> or <code>stream_id|server_url|app_name</code><br>
            Leave server_url empty to use plugin defaults. Default app_name is 'live'.
        </p>
        <?php
    }
}

// Initialize the integration
new AMSA_RocketChat_Integration();
