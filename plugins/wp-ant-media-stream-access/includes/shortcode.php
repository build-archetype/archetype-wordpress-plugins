<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple Ant Media Stream Shortcode
 */
add_shortcode('antmedia_simple', 'ant_media_stream_shortcode');

function ant_media_stream_shortcode($atts) {
    // Check if stream should be displayed
    if (!should_display_ant_media_stream()) {
        ant_media_log('Stream shortcode called but display is disabled', 'warning');
        return '<div class="ant-media-error">Stream is currently unavailable.</div>';
    }
    
    $defaults = [
        'stream_id' => '',
        'server_url' => get_option('ant_media_server_url'),
        'app_name' => 'live',
        'width' => '100%',
        'height' => '500px',
        'offline_message' => 'Stream is currently offline. Please check back later.',
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
    
    // Check if stream is currently live (optional, don't fail if this doesn't work)
    $is_live = check_ant_media_stream_status($stream_id, $atts['server_url'], $atts['app_name']);
    ant_media_log("Stream {$stream_id} live status: " . ($is_live ? 'true' : 'false'), 'debug');
    
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
            style="display: block; background: #000;">
        </iframe>
        
        <!-- Offline message overlay (hidden by default) -->
        <div id="<?php echo esc_attr($player_id); ?>-offline" class="ant-media-offline" style="display: none;">
            <div class="offline-content">
                <div class="offline-icon">📺</div>
                <p class="offline-message"><?php echo esc_html($atts['offline_message']); ?></p>
                <button class="ant-media-refresh" onclick="antMediaRefresh('<?php echo esc_js($player_id); ?>')">
                    🔄 Refresh
                </button>
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
    });
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * AJAX handler for checking stream status
 */
add_action('wp_ajax_ant_media_check_status', 'handle_ant_media_check_status');
add_action('wp_ajax_nopriv_ant_media_check_status', 'handle_ant_media_check_status');

function handle_ant_media_check_status() {
    check_ajax_referer('ant_media_nonce', 'nonce');
    
    $stream_id = sanitize_text_field($_POST['stream_id'] ?? '');
    $server_url = sanitize_text_field($_POST['server_url'] ?? '');
    $app_name = sanitize_text_field($_POST['app_name'] ?? 'live');
    
    if (empty($stream_id) || empty($server_url)) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    ant_media_log("AJAX stream status check for {$stream_id}", 'debug');
    
    $is_live = check_ant_media_stream_status($stream_id, $server_url, $app_name);
    
    wp_send_json_success([
        'is_live' => $is_live,
        'stream_id' => $stream_id,
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
    
    .offline-message {
        font-size: 16px;
        line-height: 1.5;
        margin: 0 0 20px 0;
        color: #d1d5db;
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
 * Add simple JavaScript for player functionality
 */
function ant_media_add_scripts() {
    ?>
    <script>
    // Simple player initialization
    window.antMediaInitPlayer = function(playerId, options) {
        console.log('Ant Media: Initializing player', playerId, options);
        
        const iframe = document.getElementById(playerId + '-iframe');
        const offlineDiv = document.getElementById(playerId + '-offline');
        
        // Simple status check every 30 seconds
        setInterval(function() {
            checkStreamStatus(playerId, options);
        }, 30000);
        
        // Initial status check if not live
        if (!options.isLive) {
            setTimeout(function() {
                checkStreamStatus(playerId, options);
            }, 5000);
        }
    };
    
    // Check stream status via AJAX
    function checkStreamStatus(playerId, options) {
        if (typeof antMediaAjax === 'undefined') return;
        
        fetch(antMediaAjax.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'ant_media_check_status',
                nonce: antMediaAjax.nonce,
                stream_id: options.streamId,
                server_url: options.serverUrl,
                app_name: options.appName
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Ant Media: Status check result', data);
            
            const iframe = document.getElementById(playerId + '-iframe');
            const offlineDiv = document.getElementById(playerId + '-offline');
            
            if (data.success && data.data.is_live) {
                // Stream is live, show iframe
                if (iframe) iframe.style.display = 'block';
                if (offlineDiv) offlineDiv.style.display = 'none';
            } else {
                // Stream is offline, show offline message
                if (iframe) iframe.style.display = 'none';
                if (offlineDiv) offlineDiv.style.display = 'flex';
            }
        })
        .catch(error => {
            console.error('Ant Media: Status check failed', error);
        });
    }
    
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