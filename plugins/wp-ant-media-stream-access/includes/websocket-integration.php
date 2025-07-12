<?php
/**
 * WebSocket Integration for Real-Time Ant Media Stream Notifications
 * 
 * This provides INSTANT (0-second delay) stream status updates via WebSocket
 * while keeping the existing WordPress Heartbeat as a reliable fallback.
 */

if (!defined('ABSPATH')) exit;

class AMSA_WebSocket_Integration {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_websocket_script']);
        add_action('admin_init', [$this, 'add_websocket_settings']);
    }
    
    /**
     * Add WebSocket settings to admin
     */
    public function add_websocket_settings() {
        // Add settings section
        add_settings_section(
            'amsa_websocket_integration',
            'Real-Time WebSocket Integration',
            [$this, 'render_websocket_section'],
            'ant-media-settings'
        );
        
        // WebSocket enabled setting
        add_settings_field(
            'amsa_websocket_enabled',
            'Enable WebSocket Real-Time Updates',
            [$this, 'render_websocket_enabled_field'],
            'ant-media-settings',
            'amsa_websocket_integration'
        );
        
        // Register settings
        register_setting('ant-media-settings', 'amsa_websocket_enabled');
    }
    
    /**
     * Render WebSocket section description
     */
    public function render_websocket_section() {
        echo '<p>Enable real-time stream notifications via WebSocket for <strong>instant</strong> chat visibility updates (0-second delay). WordPress Heartbeat will continue as a reliable fallback.</p>';
    }
    
    /**
     * Render WebSocket enabled checkbox
     */
    public function render_websocket_enabled_field() {
        $enabled = get_option('amsa_websocket_enabled', true); // Default enabled
        ?>
        <label>
            <input type="checkbox" name="amsa_websocket_enabled" value="1" <?php checked($enabled); ?> />
            Enable real-time WebSocket notifications (instant stream status updates)
        </label>
        <p class="description">When enabled, chat will appear/disappear instantly when streams start/stop. WordPress Heartbeat (5-second updates) continues as backup.</p>
        <?php
    }
    
    /**
     * Enqueue WebSocket script
     */
    public function enqueue_websocket_script() {
        // Only load on pages that have streams
        if (!$this->page_has_streams()) {
            return;
        }
        
        // Check if WebSocket is enabled
        if (!get_option('amsa_websocket_enabled', true)) {
            return;
        }
        
        // Get Ant Media server settings
        $server_url = get_option('ant_media_server_url');
        $app_name = get_option('ant_media_app_name', 'live');
        
        if (empty($server_url)) {
            return;
        }
        
        // Convert HTTP to WebSocket URL
        $websocket_url = $this->build_websocket_url($server_url, $app_name);
        
        wp_add_inline_script('heartbeat', '
        document.addEventListener("DOMContentLoaded", function() {
            console.log("🔌 WebSocket: Initializing real-time stream notifications...");
            
            // Enhanced updateChatVisibility with WebSocket indicator
            const originalUpdateChatVisibility = window.updateChatVisibility;
            window.updateChatVisibility = function(isLive, source) {
                console.log("🔄 Chat Visibility Update:", isLive, "from", source || "unknown");
                
                // Call original function
                if (originalUpdateChatVisibility) {
                    originalUpdateChatVisibility(isLive);
                }
                
                // Update all stream elements
                Object.keys(window.antMediaPlayers || {}).forEach(function(playerId) {
                    const iframe = document.getElementById(playerId + "-iframe");
                    const offlineDiv = document.getElementById(playerId + "-offline");
                    
                    if (isLive) {
                        if (iframe) iframe.style.display = "block";
                        if (offlineDiv) offlineDiv.style.display = "none";
                    } else {
                        if (iframe) iframe.style.display = "none";
                        if (offlineDiv) offlineDiv.style.display = "flex";
                    }
                });
                
                // Update chat containers
                if (window.chatContainers) {
                    window.chatContainers.forEach(function(chat) {
                        if (chat.element) {
                            if (isLive) {
                                chat.show(source + ": streams live");
                            } else {
                                chat.hide(source + ": no streams");
                            }
                        }
                    });
                }
            };
            
            // WebSocket connection
            let websocket = null;
            let reconnectAttempts = 0;
            const maxReconnectAttempts = 5;
            
            function connectWebSocket() {
                const wsUrl = "' . esc_js($websocket_url) . '";
                
                if (!wsUrl) {
                    console.warn("🔌 WebSocket: No URL configured, using heartbeat only");
                    return;
                }
                
                console.log("🔌 WebSocket: Connecting to", wsUrl);
                
                try {
                    websocket = new WebSocket(wsUrl);
                    
                    websocket.onopen = function() {
                        console.log("✅ WebSocket: Connected for real-time notifications");
                        reconnectAttempts = 0;
                        
                        // Send ping to keep connection alive
                        websocket.send(JSON.stringify({ command: "ping" }));
                        
                        // Set up periodic ping
                        window.websocketPingInterval = setInterval(function() {
                            if (websocket && websocket.readyState === WebSocket.OPEN) {
                                websocket.send(JSON.stringify({ command: "ping" }));
                            }
                        }, 30000);
                    };
                    
                    websocket.onmessage = function(event) {
                        try {
                            const message = JSON.parse(event.data);
                            console.log("🔌 WebSocket: Received", message);
                            
                            if (message.command === "notification") {
                                switch (message.definition) {
                                    case "publish_started":
                                        console.log("🚀 WebSocket: Stream STARTED - " + message.streamId);
                                        window.updateChatVisibility(true, "WebSocket Real-Time");
                                        
                                        // Update WordPress cache immediately
                                        updateWordPressCacheImmediately(message.streamId, true);
                                        break;
                                        
                                    case "publish_finished":
                                        console.log("🛑 WebSocket: Stream STOPPED - " + message.streamId);
                                        
                                        // Check if ANY other streams are still live before hiding
                                        checkOtherStreamsBeforeHiding(message.streamId);
                                        break;
                                }
                            } else if (message.command === "pong") {
                                console.log("🏓 WebSocket: Pong received");
                            }
                        } catch (error) {
                            console.warn("🔌 WebSocket: Invalid message", error, event.data);
                        }
                    };
                    
                    websocket.onerror = function(error) {
                        console.error("❌ WebSocket: Error", error);
                    };
                    
                    websocket.onclose = function() {
                        console.log("🔌 WebSocket: Connection closed");
                        
                        // Clear ping interval
                        if (window.websocketPingInterval) {
                            clearInterval(window.websocketPingInterval);
                        }
                        
                        // Try to reconnect
                        if (reconnectAttempts < maxReconnectAttempts) {
                            reconnectAttempts++;
                            console.log("🔌 WebSocket: Reconnecting... attempt", reconnectAttempts);
                            setTimeout(connectWebSocket, 2000 * reconnectAttempts);
                        } else {
                            console.warn("🔌 WebSocket: Max reconnection attempts reached. Using heartbeat only.");
                        }
                    };
                    
                } catch (error) {
                    console.error("🔌 WebSocket: Connection failed", error);
                }
            }
            
            // Helper function to update WordPress cache immediately
            function updateWordPressCacheImmediately(streamId, isLive) {
                fetch("' . admin_url('admin-ajax.php') . '", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({
                        action: "amsa_websocket_cache_update",
                        stream_id: streamId,
                        is_live: isLive ? "1" : "0",
                        nonce: "' . wp_create_nonce('amsa_websocket_nonce') . '"
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log("📦 WebSocket: Updated WordPress cache", data);
                })
                .catch(error => {
                    console.warn("📦 WebSocket: Cache update failed", error);
                });
            }
            
            // Helper function to check other streams before hiding chat
            function checkOtherStreamsBeforeHiding(stoppedStreamId) {
                // Quick check of other players on the page
                let anyOtherLive = false;
                
                Object.keys(window.antMediaPlayers || {}).forEach(function(playerId) {
                    const options = window.antMediaPlayers[playerId];
                    if (options.streamId !== stoppedStreamId) {
                        // Check if this other stream appears to be playing
                        const iframe = document.getElementById(playerId + "-iframe");
                        if (iframe && iframe.style.display !== "none") {
                            anyOtherLive = true;
                        }
                    }
                });
                
                if (!anyOtherLive) {
                    console.log("🛑 WebSocket: No other streams live, hiding chat");
                    window.updateChatVisibility(false, "WebSocket Real-Time");
                    updateWordPressCacheImmediately(stoppedStreamId, false);
                } else {
                    console.log("🔄 WebSocket: Other streams still live, keeping chat visible");
                }
            }
            
            // Start WebSocket connection
            connectWebSocket();
            
            console.log("🔌 WebSocket: Real-time integration initialized");
        });
        ');
    }
    
    /**
     * Build WebSocket URL from HTTP URL
     */
    private function build_websocket_url($server_url, $app_name) {
        // Remove trailing slash
        $server_url = rtrim($server_url, '/');
        
        // Convert HTTP to WebSocket
        if (strpos($server_url, 'https://') === 0) {
            $ws_url = str_replace('https://', 'wss://', $server_url);
        } else {
            $ws_url = str_replace('http://', 'ws://', $server_url);
        }
        
        // Add WebSocket endpoint
        return $ws_url . '/' . $app_name . '/websocket';
    }
    
    /**
     * Check if current page has stream shortcodes
     */
    private function page_has_streams() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return (
            has_shortcode($post->post_content, 'antmedia_stream') ||
            has_shortcode($post->post_content, 'antmedia_stream_direct') ||
            has_shortcode($post->post_content, 'antmedia_simple') ||
            has_shortcode($post->post_content, 'ant_media_stream') ||
            has_shortcode($post->post_content, 'ant_media_player')
        );
    }
}

// Initialize WebSocket integration
new AMSA_WebSocket_Integration();

// Add AJAX handler for WebSocket cache updates
add_action('wp_ajax_amsa_websocket_cache_update', 'handle_websocket_cache_update');
add_action('wp_ajax_nopriv_amsa_websocket_cache_update', 'handle_websocket_cache_update');

function handle_websocket_cache_update() {
    check_ajax_referer('amsa_websocket_nonce', 'nonce');
    
    $stream_id = sanitize_text_field($_POST['stream_id'] ?? '');
    $is_live = ($_POST['is_live'] ?? '0') === '1';
    
    if (empty($stream_id)) {
        wp_send_json_error('Missing stream_id');
        return;
    }
    
    ant_media_log("🔌 WebSocket: Immediate cache update - {$stream_id}: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
    
    // Update WordPress cache immediately using the same system as the shortcode
    $recent_streams = get_transient('ant_media_recent_streams');
    if (!is_array($recent_streams)) {
        $recent_streams = [];
    }
    
    // Update this stream's status
    $recent_streams[$stream_id] = $is_live;
    
    // Clean up old entries (keep only last 10 streams)
    if (count($recent_streams) > 10) {
        $recent_streams = array_slice($recent_streams, -10, null, true);
    }
    
    // Store for 5 minutes
    set_transient('ant_media_recent_streams', $recent_streams, 300);
    
    // Update the shared WordPress option that chat plugin uses
    $any_streams_live = in_array(true, $recent_streams, true);
    update_option('amsa_streams_currently_live', $any_streams_live);
    
    ant_media_log("🔌 WEBSOCKET IMMEDIATE: {$stream_id} -> setting amsa_streams_currently_live to " . ($any_streams_live ? 'TRUE' : 'FALSE'), 'info');
    
    // Fire the hooks that the rocket-chat plugin listens to
    do_action('ant_media_stream_status_updated', $stream_id, ($is_live ? 'playing' : 'ended'), null);
    
    wp_send_json_success([
        'stream_id' => $stream_id,
        'is_live' => $is_live,
        'timestamp' => time(),
        'source' => 'websocket'
    ]);
}