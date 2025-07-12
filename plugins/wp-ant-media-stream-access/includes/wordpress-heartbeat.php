<?php
/**
 * WordPress Heartbeat Integration for Ant Media Stream Status
 * 
 * This is the PROPER WordPress way to handle real-time updates
 * No more aggressive AJAX polling - use WordPress's built-in system
 */

if (!defined('ABSPATH')) exit;

class AMSA_WordPress_Heartbeat {
    
    public function __construct() {
        // Hook into WordPress Heartbeat
        add_filter('heartbeat_received', [$this, 'handle_heartbeat'], 10, 2);
        add_filter('heartbeat_settings', [$this, 'modify_heartbeat_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_heartbeat_script']);
    }
    
    /**
     * Modify heartbeat settings for our needs
     */
    public function modify_heartbeat_settings($settings) {
        // Set heartbeat interval to 5 seconds for live streaming responsiveness
        // This balances real-time updates with server performance
        $settings['interval'] = 5;
        return $settings;
    }
    
    /**
     * Handle heartbeat requests
     */
    public function handle_heartbeat($response, $data) {
        // Only process our stream status requests
        if (!isset($data['amsa_check_streams'])) {
            return $response;
        }
        
        // Get or update stream status using proper caching
        $stream_status = $this->get_cached_stream_status();
        
        // Return the current status
        $response['amsa_stream_status'] = [
            'any_live' => $stream_status['any_live'],
            'live_streams' => $stream_status['live_streams'],
            'timestamp' => time(),
            'method' => 'wordpress_heartbeat'
        ];
        
        return $response;
    }
    
    /**
     * Get stream status with intelligent caching
     */
    public function get_cached_stream_status() {
        // Check if we have recent cached data (10 seconds for responsive live streaming)
        $cached = get_transient('amsa_stream_status_cache');
        if ($cached && (time() - $cached['last_check']) < 10) {
            return $cached;
        }
        
        // Time to refresh - but do it smartly
        $all_streams = $this->get_all_configured_streams();
        $live_streams = [];
        $any_live = false;
        
        foreach ($all_streams as $stream_id => $config) {
            // Check stream status with timeout protection
            $is_live = $this->check_single_stream_cached($stream_id, $config);
            if ($is_live) {
                $live_streams[] = $stream_id;
                $any_live = true;
            }
        }
        
        // Cache the result for 10 seconds (faster refresh for live streaming)
        $result = [
            'any_live' => $any_live,
            'live_streams' => $live_streams,
            'all_streams' => $all_streams,
            'last_check' => time()
        ];
        
        set_transient('amsa_stream_status_cache', $result, 30); // Cache for 30 seconds
        
        return $result;
    }
    
    /**
     * Get all configured streams from WordPress settings
     */
    private function get_all_configured_streams() {
        // Get from WordPress options/settings
        $streams = [];
        
        // Add streams from shortcodes that are currently on pages
        $recent_streams = get_transient('ant_media_recent_streams');
        if (is_array($recent_streams)) {
            foreach ($recent_streams as $stream_id => $status) {
                $streams[$stream_id] = [
                    'server_url' => get_option('ant_media_server_url'),
                    'app_name' => get_option('ant_media_app_name', 'live')
                ];
            }
        }
        
        return $streams;
    }
    
    /**
     * Check single stream with individual caching
     */
    private function check_single_stream_cached($stream_id, $config) {
        // Individual stream cache (30 seconds for responsive live streaming)
        $cache_key = 'amsa_stream_' . md5($stream_id);
        $cached_status = get_transient($cache_key);
        
        if ($cached_status !== false) {
            return $cached_status;
        }
        
        // Actually check the stream
        $is_live = check_ant_media_stream_status(
            $stream_id, 
            $config['server_url'], 
            $config['app_name']
        );
        
        // Cache individual stream status for 30 seconds (faster refresh for live streaming)
        set_transient($cache_key, $is_live, 30);
        
        return $is_live;
    }
    
    /**
     * Enqueue the heartbeat script
     */
    public function enqueue_heartbeat_script() {
        // Only load on pages that actually have streams
        if (!$this->page_has_streams()) {
            return;
        }
        
        // Ensure WordPress Heartbeat is enabled and enqueued
        wp_enqueue_script('heartbeat');
        
        // Add our heartbeat handler with proper WordPress integration
        wp_add_inline_script('heartbeat', '
            jQuery(document).ready(function($) {
                console.log("WordPress Heartbeat: Initializing heartbeat integration...");
                
                // Force enable heartbeat if not active
                if (typeof wp !== "undefined" && wp.heartbeat) {
                    console.log("WordPress Heartbeat: Found wp.heartbeat, configuring...");
                    
                    // Ensure heartbeat is running at optimal frequency for live streaming
                    wp.heartbeat.interval(5); // 5 second intervals for responsive stream updates
                    
                    // Hook into WordPress Heartbeat
                    $(document).on("heartbeat-send", function(e, data) {
                        console.log("WordPress Heartbeat: Sending heartbeat request");
                        data.amsa_check_streams = true;
                    });
                    
                    $(document).on("heartbeat-tick", function(e, data) {
                        console.log("WordPress Heartbeat: Received heartbeat response", data);
                        
                        if (data.amsa_stream_status) {
                            console.log("WordPress Heartbeat: Stream status update", data.amsa_stream_status);
                            
                            // Update chat visibility
                            if (typeof window.updateChatVisibility === "function") {
                                window.updateChatVisibility(data.amsa_stream_status.any_live);
                            }
                            
                            // Fire event for other integrations
                            $(document).trigger("amsa-stream-status-update", [data.amsa_stream_status]);
                        }
                    });
                    
                    // Force an immediate heartbeat to test
                    setTimeout(function() {
                        console.log("WordPress Heartbeat: Forcing initial heartbeat...");
                        wp.heartbeat.connectNow();
                    }, 2000);
                    
                    console.log("WordPress Heartbeat: Stream status monitoring initialized");
                } else {
                    console.warn("WordPress Heartbeat: wp.heartbeat not available, falling back to basic polling");
                    
                    // Fallback: basic polling if heartbeat fails
                    setInterval(function() {
                        console.log("WordPress Heartbeat: Fallback polling check");
                        
                                                 fetch("' . admin_url('admin-ajax.php') . '", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: new URLSearchParams({
                                action: "amsa_manual_status_check", 
                                nonce: "' . wp_create_nonce('amsa_nonce') . '"
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data) {
                                console.log("WordPress Heartbeat: Fallback status update", data.data);
                                if (typeof window.updateChatVisibility === "function") {
                                    window.updateChatVisibility(data.data.any_live);
                                }
                                $(document).trigger("amsa-stream-status-update", [data.data]);
                            }
                        })
                                                 .catch(error => console.warn("WordPress Heartbeat: Fallback polling failed", error));
                     }, 5000); // 5 second fallback polling to match heartbeat frequency
                }
            });
        ');
    }
    
    /**
     * Check if current page has stream shortcodes
     */
    private function page_has_streams() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check if post content contains our shortcodes
        return (
            has_shortcode($post->post_content, 'ant_media_stream') ||
            has_shortcode($post->post_content, 'ant_media_player')
        );
    }
    
    /**
     * Manual cache invalidation when stream status changes
     */
    public static function invalidate_cache($stream_id = null) {
        if ($stream_id) {
            // Clear individual stream cache
            $cache_key = 'amsa_stream_' . md5($stream_id);
            delete_transient($cache_key);
        }
        
        // Clear overall cache
        delete_transient('amsa_stream_status_cache');
    }
}

// Initialize the WordPress Heartbeat integration
new AMSA_WordPress_Heartbeat();

/**
 * Helper function to manually update stream status
 */
function amsa_update_stream_status_wp($stream_id, $is_live) {
    // Update individual cache (30 seconds for responsive live streaming)
    $cache_key = 'amsa_stream_' . md5($stream_id);
    set_transient($cache_key, $is_live, 30);
    
    // Invalidate main cache to force refresh
    AMSA_WordPress_Heartbeat::invalidate_cache();
    
    // Log the update
    ant_media_log("WordPress: Updated stream status - {$stream_id}: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
}

// Add AJAX handler for manual status checks (fallback)
add_action('wp_ajax_amsa_manual_status_check', 'amsa_manual_status_check');
add_action('wp_ajax_nopriv_amsa_manual_status_check', 'amsa_manual_status_check');

function amsa_manual_status_check() {
    check_ajax_referer('amsa_nonce', 'nonce');
    
    try {
        $heartbeat = new AMSA_WordPress_Heartbeat();
        $stream_status = $heartbeat->get_cached_stream_status();
        
        wp_send_json_success($stream_status);
    } catch (Exception $e) {
        wp_send_json_error('Failed to check stream status: ' . $e->getMessage());
    }
} 