<?php
/**
 * Stream Synchronization System
 * 
 * This is the ONLY file that should handle stream status detection and communication.
 * It provides a single source of truth for stream status across all plugins.
 * 
 * WordPress Engineer's Approach:
 * 1. Single authoritative source (WordPress option)
 * 2. WordPress hooks for communication
 * 3. Simple, reliable detection with fallbacks
 * 4. Real-time updates via WordPress Heartbeat
 */

if (!defined('ABSPATH')) exit;

class AMSA_StreamSync {
    
    private static $instance = null;
    
    // Single source of truth
    private const STREAM_STATUS_OPTION = 'amsa_streams_live';
    private const STREAM_DATA_TRANSIENT = 'amsa_stream_data';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Hook into WordPress Heartbeat for real-time updates
        add_filter('heartbeat_received', [$this, 'heartbeat_received'], 10, 2);
        add_filter('heartbeat_settings', [$this, 'heartbeat_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX handlers for manual checks
        add_action('wp_ajax_amsa_sync_stream_status', [$this, 'sync_stream_status']);
        add_action('wp_ajax_nopriv_amsa_sync_stream_status', [$this, 'sync_stream_status']);
        
        // WebSocket integration (if available)
        if (class_exists('AMSA_WebSocket_Integration')) {
            add_action('amsa_websocket_stream_started', [$this, 'handle_stream_started']);
            add_action('amsa_websocket_stream_stopped', [$this, 'handle_stream_stopped']);
        }
    }
    
    /**
     * Configure WordPress Heartbeat for optimal live streaming
     */
    public function heartbeat_settings($settings) {
        // ALWAYS enable heartbeat when plugins are active - fixes Elementor detection
        $settings['interval'] = 5; // 5-second updates for live streaming
        return $settings;
    }
    
    /**
     * Handle WordPress Heartbeat requests
     */
    public function heartbeat_received($response, $data) {
        $timestamp = date('Y-m-d H:i:s');
        
        if (!isset($data['amsa_stream_check'])) {
            return $response;
        }
        
        if (function_exists('ant_media_log')) {
            ant_media_log("💓 [{$timestamp}] WordPress Heartbeat: Received stream check request", 'info');
        }
        
        $stream_status = $this->get_current_stream_status();
        
        if (function_exists('ant_media_log')) {
            ant_media_log("💓 [{$timestamp}] WordPress Heartbeat: Returning status: " . ($stream_status ? 'LIVE' : 'OFFLINE'), 'info');
        }
        
        $response['amsa_stream_status'] = [
            'any_live' => $stream_status,
            'timestamp' => time(),
            'method' => 'heartbeat'
        ];
        
        return $response;
    }
    
    /**
     * Enqueue JavaScript for real-time updates
     */
    public function enqueue_scripts() {
        // ALWAYS enqueue on frontend when plugins are active - fixes Elementor pages
        if (!is_admin()) {
            wp_enqueue_script('heartbeat');
            wp_add_inline_script('heartbeat', $this->get_heartbeat_script());
        }
    }
    
    /**
     * Get the authoritative stream status
     */
    public function get_current_stream_status() {
        $timestamp = date('Y-m-d H:i:s');
        
        // Check cache first (5-second cache for live streaming responsiveness)
        $cached_status = get_transient(self::STREAM_DATA_TRANSIENT);
        if ($cached_status !== false) {
            // Convert empty string back to boolean false (WordPress transient quirk)
            $actual_status = $cached_status === '' ? false : (bool) $cached_status;
            
            if (function_exists('ant_media_log')) {
                ant_media_log("🔄 [{$timestamp}] Stream Sync: Using cached status: " . ($actual_status ? 'LIVE' : 'OFFLINE'), 'info');
            }
            
            return $actual_status;
        }
        
        if (function_exists('ant_media_log')) {
            ant_media_log("🔄 [{$timestamp}] Stream Sync: No cache found, checking streams live...", 'info');
        }
        
        // Check actual stream status
        $is_live = $this->check_streams_live();
        
        if (function_exists('ant_media_log')) {
            ant_media_log("🔄 [{$timestamp}] Stream Sync: API check result: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
        }
        
        // Cache the result
        set_transient(self::STREAM_DATA_TRANSIENT, $is_live ? 'true' : '', 5);
        
        // Update the authoritative option
        $old_status = get_option(self::STREAM_STATUS_OPTION, false);
        if ($old_status !== $is_live) {
            update_option(self::STREAM_STATUS_OPTION, $is_live);
            
            if (function_exists('ant_media_log')) {
                ant_media_log("🔄 [{$timestamp}] Stream Sync: Status changed from " . ($old_status ? 'LIVE' : 'OFFLINE') . " to " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
            }
            
            // Trigger WordPress action for other plugins
            do_action('amsa_stream_status_changed', $is_live, $old_status);
        }
        
        return $is_live;
    }
    
    /**
     * Check if any streams are actually live
     */
    private function check_streams_live() {
        $stream_ids = $this->get_monitored_streams();
        
        if (empty($stream_ids)) {
            // No specific streams - check the default stream directly via API
            $default_stream_id = 'asx6N6h2KfK0jmE42665303919153337'; // Your main stream
            $server_url = get_option('ant_media_server_url', 'https://stream.triplepointtrading.net');
            $app_name = get_option('ant_media_app_name', 'TriplePointTradingStreaming');
            
            if (!empty($server_url)) {
                return $this->check_single_stream($default_stream_id, $server_url, $app_name);
            }
            
            // Fallback to WordPress option if no server configured
            $global_status = get_option('amsa_streams_currently_live', false);
            if ($global_status === '') {
                return false; // Empty string = offline
            }
            return (bool) $global_status; // Convert to boolean
        }
        
        $server_url = get_option('ant_media_server_url');
        $app_name = get_option('ant_media_app_name', 'live');
        
        if (empty($server_url)) {
            return false;
        }
        
        foreach ($stream_ids as $stream_id) {
            if ($this->check_single_stream($stream_id, $server_url, $app_name)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check a single stream's status
     */
    private function check_single_stream($stream_id, $server_url, $app_name) {
        $api_url = rtrim($server_url, '/') . '/' . $app_name . '/rest/v2/broadcasts/' . $stream_id;
        
        $response = wp_remote_get($api_url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return false; // Fail safe: offline if API unavailable
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['status'])) {
            return false;
        }
        
        $live_statuses = ['broadcasting', 'live', 'playing', 'active', 'started'];
        return in_array(strtolower($data['status']), $live_statuses);
    }
    
    /**
     * Update stream status and notify all listeners
     */
    public function update_stream_status($is_live) {
        $previous_status = get_option(self::STREAM_STATUS_OPTION, false);
        
        // Only update if status changed
        if ($previous_status !== $is_live) {
            update_option(self::STREAM_STATUS_OPTION, $is_live);
            
            // Fire WordPress hook for other plugins
            do_action('amsa_stream_status_changed', $is_live, $previous_status);
            
            // Log the change
            ant_media_log("Stream status changed: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
        }
    }
    
    /**
     * WebSocket event handlers
     */
    public function handle_stream_started($stream_id) {
        $this->invalidate_cache();
        $this->update_stream_status(true);
    }
    
    public function handle_stream_stopped($stream_id) {
        $this->invalidate_cache();
        // Re-check in case other streams are still live
        $this->get_current_stream_status();
    }
    
    /**
     * AJAX handler for manual status sync
     */
    public function sync_stream_status() {
        check_ajax_referer('amsa_sync_nonce', 'nonce');
        
        $this->invalidate_cache();
        $status = $this->get_current_stream_status();
        
        wp_send_json_success([
            'is_live' => $status,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Invalidate cache to force fresh check
     */
    private function invalidate_cache() {
        delete_transient(self::STREAM_DATA_TRANSIENT);
    }
    
    /**
     * Get list of streams to monitor
     */
    private function get_monitored_streams() {
        // Get streams from current page or global settings
        global $post;
        
        $streams = [];
        
        if ($post && has_shortcode($post->post_content, 'ant_media_stream')) {
            // Extract stream IDs from shortcodes
            preg_match_all('/\[ant_media_stream[^\]]*stream_id="([^"]*)"/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $streams = array_merge($streams, $matches[1]);
            }
        }
        
        // Fallback to global settings
        if (empty($streams)) {
            $default_stream = get_option('ant_media_default_stream_id');
            if (!empty($default_stream)) {
                $streams[] = $default_stream;
            }
        }
        
        return array_unique($streams);
    }
    
    /**
     * Check if current page has streams
     */
    private function page_has_streams() {
        global $post;
        return $post && (
            has_shortcode($post->post_content, 'ant_media_stream') ||
            has_shortcode($post->post_content, 'rocketchat_iframe')
        );
    }
    
    /**
     * Generate JavaScript for WordPress Heartbeat integration
     */
    private function get_heartbeat_script() {
        return '
        (function($) {
            console.log("💓 WordPress Heartbeat: Initializing AMSA stream monitoring");
            
            // Hook into WordPress Heartbeat
            $(document).on("heartbeat-send", function(event, data) {
                const timestamp = new Date().toISOString();
                console.log("💓 [" + timestamp + "] WordPress Heartbeat: Sending stream check request");
                data.amsa_stream_check = true;
            });
            
            $(document).on("heartbeat-tick", function(event, data) {
                const timestamp = new Date().toISOString();
                
                if (data.amsa_stream_status) {
                    console.log("💓 Heartbeat: Stream status = " + (data.amsa_stream_status.any_live ? "LIVE" : "OFFLINE"));
                    
                    // Update stream players
                    if (window.streamPlayerContainers && window.streamPlayerContainers.length > 0) {
                        console.log("💓 Updating " + window.streamPlayerContainers.length + " registered stream players");
                        window.streamPlayerContainers.forEach(function(container) {
                            if (container.update) {
                                container.update(data.amsa_stream_status.any_live, "WordPress Heartbeat");
                            }
                        });
                    }
                    
                    // FALLBACK: Update ANY ant media containers found on page (for iframe embeds)
                    var containers = document.querySelectorAll(".ant-media-container");
                    if (containers.length > 0) {
                        console.log("💓 Found " + containers.length + " ant media containers");
                        containers.forEach(function(container) {
                            var iframe = container.querySelector("iframe");
                            var offlineDiv = container.querySelector(".ant-media-offline");
                            
                            if (data.amsa_stream_status.any_live) {
                                if (iframe) iframe.style.display = "block";
                                if (offlineDiv) offlineDiv.style.display = "none";
                                console.log("💓 SHOWING ant media iframe");
                            } else {
                                if (iframe) iframe.style.display = "none";
                                if (offlineDiv) offlineDiv.style.display = "flex";
                                console.log("💓 HIDING ant media iframe");
                            }
                        });
                    }
                    
                    // Update chat containers
                    if (window.chatContainers && window.chatContainers.length > 0) {
                        console.log("💓 Updating " + window.chatContainers.length + " chat containers");
                        window.chatContainers.forEach(function(container) {
                            if (data.amsa_stream_status.any_live) {
                                if (container.show) container.show("WordPress Heartbeat");
                            } else {
                                if (container.hide) container.hide("WordPress Heartbeat");
                            }
                        });
                    }
                    
                    // Trigger jQuery event for Elementor widgets
                    $(document).trigger("amsa-stream-status-update", [data.amsa_stream_status]);
                    
                    // Trigger native event for vanilla JS
                    document.dispatchEvent(new CustomEvent("amsaStreamStatusChanged", {
                        detail: {
                            isLive: data.amsa_stream_status.any_live,
                            method: data.amsa_stream_status.method,
                            timestamp: data.amsa_stream_status.timestamp
                        }
                    }));
                }
            });
            
            // Monitor heartbeat settings
            $(document).on("heartbeat-settings", function(event, settings) {
                console.log("💓 WordPress Heartbeat: Settings updated:", settings);
            });
            
            console.log("💓 WordPress Heartbeat: AMSA stream monitoring initialized");
        })(jQuery);
        ';
    }
}

// Initialize the system
AMSA_StreamSync::get_instance();

/**
 * Public API Functions
 */
function amsa_is_stream_live() {
    return AMSA_StreamSync::get_instance()->get_current_stream_status();
}

function amsa_force_stream_check() {
    return AMSA_StreamSync::get_instance()->get_current_stream_status();
} 