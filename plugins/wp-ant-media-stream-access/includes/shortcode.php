<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple Ant Media Stream Shortcode
 */
add_shortcode('antmedia_simple', 'ant_media_stream_shortcode');

function ant_media_stream_shortcode($atts) {
    // Ensure jQuery is enqueued and localize script for AJAX
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'antMediaAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ant_media_nonce')
    ]);
    
    // Check if stream should be displayed
    if (!should_display_ant_media_stream()) {
        ant_media_log('Stream shortcode called but display is disabled', 'warning');
        return '<div class="ant-media-error">Stream is currently unavailable.</div>';
    }
    
    $defaults = [
        'stream_id' => '',
        'server_url' => get_option('ant_media_server_url'),
        'app_name' => get_option('ant_media_app_name', 'live'),
        'width' => '100%',
        'height' => '500px',
        'offline_title' => 'Nothing is streaming live now.',
        'offline_message' => '',
        'cta_text' => '',
        'cta_link' => '',
        'token' => '',
        'autoplay' => 'true',
        'muted' => 'true',
        'play_order' => 'webrtc,hls'
    ];
    
    $atts = shortcode_atts($defaults, $atts);
    
    // Validate required parameters
    if (empty($atts['stream_id'])) {
        ant_media_log('Stream shortcode called without stream_id', 'error');
        return '<div class="ant-media-error">Error: stream_id parameter is required.</div>';
    }
    
    if (empty($atts['server_url'])) {
        ant_media_log('Stream shortcode called without server_url', 'error');
        return '<div class="ant-media-error">Error: Server URL not configured. Please configure in Settings → Ant Media Stream.</div>';
    }
    
    $stream_id = sanitize_text_field($atts['stream_id']);
    $player_id = 'ant-media-player-' . uniqid();
    
    ant_media_log("Rendering stream shortcode for stream_id: {$stream_id}", 'info');
    
    // Build iframe URL
    $iframe_url = build_ant_media_iframe_url($stream_id, [
        'server_url' => $atts['server_url'],
        'app_name' => $atts['app_name'],
        'autoplay' => $atts['autoplay'],
        'muted' => $atts['muted'],
        'play_order' => $atts['play_order'],
        'token' => $atts['token']
    ]);
    
    if (!$iframe_url) {
        ant_media_log("Failed to build iframe URL for stream {$stream_id}", 'error');
        return '<div class="ant-media-error">Error: Unable to build stream URL.</div>';
    }
    
    // Check if stream is currently live - CRITICAL for live streaming platform
    $is_live = false;
    if (!empty($stream_id)) {
        // For live streaming platforms: ALWAYS check actual API status on page load
        // This ensures visitors see live streams immediately, even if they visit mid-stream
        $is_live = check_ant_media_stream_status($stream_id, $atts['server_url'], $atts['app_name']);
        ant_media_log("🔴 LIVE CHECK: Stream {$stream_id} API status: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
        
        // FORCE CONSOLE DEBUG - Add JavaScript to show API result in browser console
        add_action('wp_footer', function() use ($stream_id, $is_live) {
            echo "<script>console.warn('🚨 API DEBUG: Stream {$stream_id} returned " . ($is_live ? 'LIVE' : 'OFFLINE') . "');</script>";
        });
        
        // Update the recent streams transient for chat integration
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
        
        // CRITICAL: Update shared WordPress option immediately so chat plugin gets correct state
        $any_streams_live = in_array(true, $recent_streams, true);
        update_option('amsa_streams_currently_live', $any_streams_live);
        
        ant_media_log("🔴 PAGE LOAD: Updated amsa_streams_currently_live to " . ($any_streams_live ? 'TRUE' : 'FALSE') . " based on API check", 'info');
    }
    
    ob_start();
    ?>
    <div class="ant-media-container" 
         id="<?php echo esc_attr($player_id); ?>-container"
         data-stream-id="<?php echo esc_attr($stream_id); ?>"
         data-server-url="<?php echo esc_attr($atts['server_url']); ?>"
         data-app-name="<?php echo esc_attr($atts['app_name']); ?>"
         style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>; position: relative;">
        
        <!-- Ant Media iframe -->
        <iframe 
            id="<?php echo esc_attr($player_id); ?>-iframe"
            src="<?php echo esc_url($iframe_url); ?>"
            width="100%" 
            height="100%"
            frameborder="0" 
            allowfullscreen
            allow="autoplay; fullscreen; microphone; camera"
            style="display: <?php echo $is_live ? 'block' : 'none'; ?>; background: #000;">
        </iframe>
        
        <!-- Offline message overlay -->
        <div id="<?php echo esc_attr($player_id); ?>-offline" class="ant-media-offline" style="display: <?php echo $is_live ? 'none' : 'flex'; ?>;">
            <div class="offline-content">
                <div class="offline-icon">📺</div>
                <h3 class="offline-title"><?php echo esc_html($atts['offline_title']); ?></h3>
                <?php if (!empty($atts['offline_message'])): ?>
                    <p class="offline-description"><?php echo esc_html($atts['offline_message']); ?></p>
                <?php endif; ?>
                <?php if (!empty($atts['cta_text']) && !empty($atts['cta_link'])): ?>
                    <a href="<?php echo esc_url($atts['cta_link']); ?>" class="ant-media-cta-button" target="_blank" rel="noopener">
                        <?php echo esc_html($atts['cta_text']); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        antMediaInitPlayer('<?php echo esc_js($player_id); ?>', {
            streamId: '<?php echo esc_js($stream_id); ?>',
            serverUrl: '<?php echo esc_js($atts['server_url']); ?>',
            appName: '<?php echo esc_js($atts['app_name']); ?>',
            isLive: <?php echo $is_live ? 'true' : 'false'; ?>
        });

        // FALLBACK: Monitor iframe for actual player events as backup detection
        const iframe = document.getElementById('<?php echo esc_js($player_id); ?>-iframe');
        if (iframe) {
            let playerEventDetected = false;
            let statusCheckTimer = null;
            
            // Override console.log to catch player events from iframe
            const originalConsoleLog = console.log;
            console.log = function(...args) {
                originalConsoleLog.apply(console, args);
                
                const message = args.join(' ').toLowerCase();
                
                // Detect when stream is actually playing
                if ((message.includes('player event:') && message.includes('playing')) ||
                    message.includes('data received:') && !playerEventDetected) {
                    
                    playerEventDetected = true;
                    console.warn('🎬 PLAYER DETECTED: Stream actually playing - updating status');
                    
                    // Update WordPress immediately via AJAX
                    if (typeof antMediaAjax !== 'undefined') {
                        fetch(antMediaAjax.ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'amsa_update_stream_status',
                                nonce: antMediaAjax.nonce,
                                stream_id: '<?php echo esc_js($stream_id); ?>',
                                status: 'playing'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('✅ Updated WordPress with player-detected status');
                                
                                // Update UI immediately
                                if (iframe) iframe.style.display = 'block';
                                const offlineDiv = document.getElementById('<?php echo esc_js($player_id); ?>-offline');
                                if (offlineDiv) offlineDiv.style.display = 'none';
                                
                                // Update chat visibility
                                if (typeof window.updateChatVisibility === 'function') {
                                    window.updateChatVisibility(true);
                                }
                            }
                        })
                        .catch(err => console.error('Failed to update stream status:', err));
                    }
                }
                
                // Detect when stream ends
                if (message.includes('no_stream_exist') || message.includes('stream is ended')) {
                    if (playerEventDetected) {
                        console.warn('🛑 PLAYER DETECTED: Stream ended');
                        playerEventDetected = false;
                        
                        // Update WordPress
                        if (typeof antMediaAjax !== 'undefined') {
                            fetch(antMediaAjax.ajaxurl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'amsa_update_stream_status',
                                    nonce: antMediaAjax.nonce,
                                    stream_id: '<?php echo esc_js($stream_id); ?>',
                                    status: 'ended'
                                })
                            })
                            .then(() => {
                                // Update UI
                                if (iframe) iframe.style.display = 'none';
                                const offlineDiv = document.getElementById('<?php echo esc_js($player_id); ?>-offline');
                                if (offlineDiv) offlineDiv.style.display = 'flex';
                                
                                // Update chat
                                if (typeof window.updateChatVisibility === 'function') {
                                    window.updateChatVisibility(false);
                                }
                            });
                        }
                    }
                }
            };
        }
        
    });
    </script>
    <?php
    
    return ob_get_clean();
}

// AJAX Handlers
add_action('wp_ajax_amsa_check_integration_status', 'handle_amsa_check_integration_status');
add_action('wp_ajax_nopriv_amsa_check_integration_status', 'handle_amsa_check_integration_status');
add_action('wp_ajax_ant_media_check_status', 'handle_ant_media_check_status');
add_action('wp_ajax_nopriv_ant_media_check_status', 'handle_ant_media_check_status');
add_action('wp_ajax_check_any_streams_live', 'handle_check_any_streams_live');
add_action('wp_ajax_nopriv_check_any_streams_live', 'handle_check_any_streams_live');
add_action('wp_ajax_amsa_update_stream_status', 'handle_amsa_update_stream_status');
add_action('wp_ajax_nopriv_amsa_update_stream_status', 'handle_amsa_update_stream_status');

function handle_check_any_streams_live() {
    check_ajax_referer('ant_media_nonce', 'nonce');
    
    ant_media_log("💬 CHAT AJAX: Checking if any streams are live from {$_SERVER['REMOTE_ADDR']}", 'debug');
    
    // Get recent stream status from transient
    $recent_streams = get_transient('ant_media_recent_streams');
    $any_live = false;
    $live_streams = [];
    
    if (is_array($recent_streams)) {
        foreach ($recent_streams as $stream_id => $status) {
            if ($status === true) {
                $any_live = true;
                $live_streams[] = $stream_id;
            }
        }
    }
    
    ant_media_log("💬 CHAT AJAX: Found " . count($live_streams) . " live streams: [" . 
                  implode(', ', $live_streams) . "], any_live: " . ($any_live ? 'true' : 'false'), 'info');
    
    $response_data = [
        'any_live' => $any_live,
        'stream_count' => is_array($recent_streams) ? count($recent_streams) : 0,
        'live_streams' => $live_streams,
        'timestamp' => time(),
        'all_streams' => $recent_streams ?: []
    ];
    
    ant_media_log("💬 CHAT AJAX: Sending response: " . json_encode($response_data), 'debug');
    
    wp_send_json_success($response_data);
}

function handle_ant_media_check_status() {
    check_ajax_referer('ant_media_nonce', 'nonce');
    
    $stream_id = sanitize_text_field($_POST['stream_id'] ?? '');
    $server_url = sanitize_text_field($_POST['server_url'] ?? '');
    $app_name = sanitize_text_field($_POST['app_name'] ?? get_option('ant_media_app_name', 'live'));
    
    if (empty($stream_id) || empty($server_url)) {
        ant_media_log("❌ AJAX: Missing parameters - stream_id: '$stream_id', server_url: '$server_url'", 'error');
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    ant_media_log("🔍 AJAX: Checking stream status for {$stream_id} from {$_SERVER['REMOTE_ADDR']}", 'debug');
    
    // Record request timing
    $start_time = microtime(true);
    $is_live = check_ant_media_stream_status($stream_id, $server_url, $app_name);
    $end_time = microtime(true);
    $duration = round(($end_time - $start_time) * 1000, 2);
    
    ant_media_log("⏱️  AJAX: API call took {$duration}ms, result: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
    
    // Update the recent streams transient for chat integration
    $recent_streams = get_transient('ant_media_recent_streams');
    if (!is_array($recent_streams)) {
        $recent_streams = [];
    }
    
    // Log previous state for comparison
    $previous_state = $recent_streams[$stream_id] ?? null;
    $state_changed = ($previous_state !== $is_live);
    
    if ($state_changed) {
        ant_media_log("🚨 AJAX: State change detected! {$stream_id}: " . 
                     ($previous_state === null ? 'NEW' : ($previous_state ? 'LIVE' : 'OFFLINE')) . 
                     " → " . ($is_live ? 'LIVE' : 'OFFLINE'), 'warning');
    }
    
    // Update this stream's status
    $recent_streams[$stream_id] = $is_live;
    
    // Clean up old entries (keep only last 10 streams)
    if (count($recent_streams) > 10) {
        $recent_streams = array_slice($recent_streams, -10, null, true);
    }
    
    // Store for 5 minutes
    set_transient('ant_media_recent_streams', $recent_streams, 300);
    
    $response_data = [
        'is_live' => $is_live,
        'stream_id' => $stream_id,
        'timestamp' => time(),
        'duration_ms' => $duration,
        'state_changed' => $state_changed,
        'previous_state' => $previous_state
    ];
    
    ant_media_log("📤 AJAX: Sending response: " . json_encode($response_data), 'debug');
    
    wp_send_json_success($response_data);
}

function handle_amsa_update_stream_status() {
    check_ajax_referer('ant_media_nonce', 'nonce');
    
    $stream_id = sanitize_text_field($_POST['stream_id'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? '');
    $error = $_POST['error'] ?? null;
    
    if (empty($stream_id) || empty($status)) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    ant_media_log("Immediate stream status update: {$stream_id} = {$status}", 'info');
    
    // Update the recent streams transient immediately
    $recent_streams = get_transient('ant_media_recent_streams');
    if (!is_array($recent_streams)) {
        $recent_streams = [];
    }
    
    // Update this stream's status based on the status value
    $is_live = ($status === 'playing');
    $recent_streams[$stream_id] = $is_live;
    
    // Log the error if provided
    if ($error && !$is_live) {
        ant_media_log("Stream {$stream_id} failed with error: " . (is_string($error) ? $error : json_encode($error)), 'warning');
    }
    
    // Clean up old entries (keep only last 10 streams)
    if (count($recent_streams) > 10) {
        $recent_streams = array_slice($recent_streams, -10, null, true);
    }
    
    // Store for 5 minutes
    set_transient('ant_media_recent_streams', $recent_streams, 300);
    
    // Update the shared WordPress option that chat plugin uses
    $any_streams_live = in_array(true, $recent_streams, true);
    update_option('amsa_streams_currently_live', $any_streams_live);
    
    ant_media_log("🎬 IMMEDIATE UPDATE: {$stream_id} status '{$status}' -> setting amsa_streams_currently_live to " . ($any_streams_live ? 'TRUE' : 'FALSE'), 'info');
    
    // Fire an action for other plugins to hook into
    do_action('ant_media_stream_status_updated', $stream_id, $status, $error);
    
    wp_send_json_success([
        'stream_id' => $stream_id,
        'status' => $status,
        'is_live' => $is_live,
        'timestamp' => time()
    ]);
}

/**
 * Clear stale stream cache data
 */
function clear_stale_stream_cache() {
    ant_media_log("Clearing stale stream cache data", 'info');
    delete_transient('ant_media_recent_streams');
    delete_transient('ant_media_stream_cache');
    
    // Clear any individual stream caches
    $cache_keys = wp_cache_get_multiple([
        'ant_media_stream_status',
        'ant_media_last_check',
        'amsa_stream_heartbeat'
    ]);
    
    foreach ($cache_keys as $key => $value) {
        if ($value !== false) {
            wp_cache_delete($key);
        }
    }
}

// Add AJAX handler to manually reset stream status cache
add_action('wp_ajax_amsa_reset_stream_cache', 'handle_amsa_reset_stream_cache');
add_action('wp_ajax_nopriv_amsa_reset_stream_cache', 'handle_amsa_reset_stream_cache');

function handle_amsa_reset_stream_cache() {
    check_ajax_referer('ant_media_nonce', 'nonce');
    
    ant_media_log("Manual stream cache reset requested from {$_SERVER['REMOTE_ADDR']}", 'info');
    
    // Clear all stream-related cache
    clear_stale_stream_cache();
    
    // Re-check all streams on page to get fresh status
    $recent_streams = [];
    
    // If there are specific streams to check, add them back with current status
    $streams_to_check = $_POST['streams'] ?? [];
    if (is_array($streams_to_check)) {
        foreach ($streams_to_check as $stream_data) {
            if (isset($stream_data['stream_id'], $stream_data['server_url'], $stream_data['app_name'])) {
                $stream_id = sanitize_text_field($stream_data['stream_id']);
                $server_url = sanitize_text_field($stream_data['server_url']);
                $app_name = sanitize_text_field($stream_data['app_name']);
                
                $is_live = check_ant_media_stream_status($stream_id, $server_url, $app_name);
                $recent_streams[$stream_id] = $is_live;
                
                ant_media_log("Cache reset: Re-checked {$stream_id}: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
            }
        }
    }
    
    // Update with fresh data
    if (!empty($recent_streams)) {
        set_transient('ant_media_recent_streams', $recent_streams, 300);
    }
    
    $any_live = in_array(true, $recent_streams, true);
    
    wp_send_json_success([
        'cache_cleared' => true,
        'streams_rechecked' => count($recent_streams),
        'any_live' => $any_live,
        'fresh_status' => $recent_streams,
        'timestamp' => time()
    ]);
}

/**
 * Add simple CSS styles
 */
function ant_media_add_styles() {
    ?>
    <style>
    .ant-media-container {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        background: #000;
    }
    
    .ant-media-offline {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        z-index: 5;
    }
    
    .offline-content {
        max-width: 300px;
        padding: 20px;
    }
    
    .offline-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.7;
    }
    
    .offline-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #d1d5db;
    }
    
    .offline-description {
        font-size: 16px;
        line-height: 1.5;
        margin: 0 0 20px 0;
        color: #d1d5db;
    }
    
    .ant-media-cta-button {
        display: inline-block;
        background: #3b82f6;
        color: white !important;
        text-decoration: none !important;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: inherit;
        font-family: inherit;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        margin: 0 0 16px 0;
        border: 2px solid #3b82f6;
        text-align: center;
        line-height: 1.4;
    }
    
    .ant-media-cta-button:hover,
    .ant-media-cta-button:focus {
        background: #2563eb;
        border-color: #2563eb;
        color: white !important;
        text-decoration: none !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    
    .ant-media-refresh {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .ant-media-refresh:hover {
        background: #2563eb;
    }
    
    .ant-media-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
        padding: 12px 16px;
        border-radius: 6px;
        font-size: 14px;
        margin: 10px 0;
    }
    </style>
    <?php
}
add_action('wp_head', 'ant_media_add_styles');

/**
 * Add simple JavaScript for player functionality with WordPress Heartbeat
 */
function ant_media_add_scripts() {
    ?>
    <script>
    // WordPress Heartbeat integration
    window.antMediaInitPlayer = function(playerId, options) {
        console.log('WordPress Heartbeat: Initializing stream monitoring for', playerId, options);
        
        // Store player info for heartbeat updates
        if (!window.antMediaPlayers) {
            window.antMediaPlayers = {};
        }
        window.antMediaPlayers[playerId] = options;
        
        // Set up chat visibility function if not exists
        if (!window.updateChatVisibility) {
            window.updateChatVisibility = function(isLive) {
                console.log('WordPress Heartbeat: Updating chat visibility:', isLive);
                
                // Update all stream elements based on status
                Object.keys(window.antMediaPlayers || {}).forEach(function(pid) {
                    const iframe = document.getElementById(pid + '-iframe');
                    const offlineDiv = document.getElementById(pid + '-offline');
                    
                    if (isLive) {
                        if (iframe) iframe.style.display = 'block';
                        if (offlineDiv) offlineDiv.style.display = 'none';
                    } else {
                        if (iframe) iframe.style.display = 'none';
                        if (offlineDiv) offlineDiv.style.display = 'flex';
                    }
                });
                
                // Update all registered chat containers
                if (window.chatContainers) {
                    window.chatContainers.forEach(function(chat) {
                        if (chat.element) {
                            if (isLive) {
                                chat.show("WordPress Heartbeat: streams live");
                            } else {
                                chat.hide("WordPress Heartbeat: no streams");
                            }
                        }
                    });
                }
            };
        }
        
        // Manual cache reset function (available in browser console)
        window.clearStreamCache = function() {
            console.warn('🧹 MANUALLY clearing stream cache...');
            
            if (typeof antMediaAjax === 'undefined') {
                console.error('❌ antMediaAjax not available for cache reset');
                return;
            }
            
            // Get all streams on page for re-checking
            const streamsToCheck = [];
            Object.keys(window.antMediaPlayers || {}).forEach(function(playerId) {
                const container = document.getElementById(playerId + '-container');
                if (container) {
                    streamsToCheck.push({
                        stream_id: container.dataset.streamId,
                        server_url: container.dataset.serverUrl,
                        app_name: container.dataset.appName
                    });
                }
            });
            
            const formData = new FormData();
            formData.append('action', 'amsa_reset_stream_cache');
            formData.append('nonce', antMediaAjax.nonce);
            formData.append('streams', JSON.stringify(streamsToCheck));
            
            fetch(antMediaAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.warn('✅ Stream cache reset successfully:', data);
                
                // Force update chat visibility based on fresh data
                if (data.success) {
                    window.updateChatVisibility(data.data.any_live);
                }
            })
            .catch(err => {
                console.error('❌ Failed to reset stream cache:', err);
            });
        };
        
        // Initial status check on page load - use actual stream status, not iframe visibility
        setTimeout(function() {
            console.log('WordPress Heartbeat: Checking initial stream status for', '<?php echo esc_js($stream_id); ?>');
            // Only update chat visibility if we know the stream is actually live from server
            if (<?php echo $is_live ? 'true' : 'false'; ?>) {
                console.log('WordPress Heartbeat: Stream confirmed live by server, updating chat');
                if (typeof window.updateChatVisibility === 'function') {
                    window.updateChatVisibility(true);
                }
            } else {
                console.log('WordPress Heartbeat: Stream confirmed offline by server, hiding chat');
                if (typeof window.updateChatVisibility === 'function') {
                    window.updateChatVisibility(false);
                }
            }
        }, 2000);
        
        console.log('WordPress Heartbeat: Player initialized, waiting for heartbeat updates...');
    };
    
    // Add a visible cache reset button for debugging (only show if URL has debug=1)
    if (window.location.search.includes('debug=1')) {
        document.addEventListener('DOMContentLoaded', function() {
            const resetButton = document.createElement('button');
            resetButton.textContent = '🧹 Reset Stream Cache';
            resetButton.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 9999; background: #ff6b35; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer;';
            resetButton.onclick = function() {
                if (typeof window.clearStreamCache === 'function') {
                    window.clearStreamCache();
                } else {
                    console.error('clearStreamCache function not available');
                }
            };
            document.body.appendChild(resetButton);
        });
    }
    
    // Simple refresh function for manual use
    window.antMediaRefresh = function(playerId) {
        console.log('WordPress Heartbeat: Manual refresh requested for', playerId);
        
        const iframe = document.getElementById(playerId + '-iframe');
        if (iframe) {
            iframe.src = iframe.src; // Reload iframe
        }
        
        // Force heartbeat check
        if (typeof wp !== 'undefined' && wp.heartbeat) {
            wp.heartbeat.interval = 1; // Force immediate heartbeat
        }
    };
    
    // Refresh function
    window.antMediaRefresh = function(playerId) {
        console.log('Ant Media: Refreshing player', playerId);
        
        const iframe = document.getElementById(playerId + '-iframe');
        if (iframe) {
            // Reload the iframe
            iframe.src = iframe.src;
        }
        
        // Check status after a short delay
        const container = document.getElementById(playerId + '-container');
        if (container) {
            setTimeout(function() {
                const options = {
                    streamId: container.dataset.streamId,
                    serverUrl: container.dataset.serverUrl,
                    appName: container.dataset.appName
                };
                checkStreamStatus(playerId, options);
            }, 2000);
        }
    };
    </script>
    <?php
}
add_action('wp_footer', 'ant_media_add_scripts'); 