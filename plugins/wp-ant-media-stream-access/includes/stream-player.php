<?php
if (!defined('ABSPATH')) exit;

/**
 * Advanced Stream Player with Iframe Embedding and PostMessage Communication
 * Supports real-time status updates for chat integration
 */
class AMSA_Stream_Player {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Render enhanced stream player with messaging capabilities
     */
    public static function render_enhanced_player($atts = []) {
        // Check if stream should be displayed
        if (!should_display_stream()) {
            return self::render_access_denied_message();
        }
        
        if (!is_user_logged_in()) {
            return self::render_login_required_message();
        }
        
        $stream_id = get_stream_id_for_user();
        if (!$stream_id) {
            return self::render_no_stream_message();
        }
        
        $defaults = [
            'width' => '100%',
            'height' => '500px',
            'format' => 'hls', // hls, embed, webrtc
            'controls' => 'true',
            'autoplay' => 'false',
            'style' => '',
            'enable_messaging' => 'true',
            'enable_analytics' => 'true',
            'enable_chat_integration' => 'true',
            'reconnect_attempts' => '3',
            'token_refresh' => 'true',
            'show_status' => 'true',
            'poster' => '',
            'muted' => 'false'
        ];
        
        $options = array_merge($defaults, $atts);
        
        // Generate secure tokens
        $token = generate_stream_token($stream_id);
        if (!$token) {
            return self::render_token_error_message();
        }
        
        $stream_url = get_stream_url($stream_id, $options['format']);
        if (!$stream_url) {
            return self::render_url_error_message();
        }
        
        $user_id = get_current_user_id();
        $user_tier = get_user_tier();
        $player_id = 'amsa-player-' . uniqid();
        $session_id = self::generate_session_id($user_id, $stream_id);
        
        ant_media_log("Rendering enhanced player for stream: {$stream_id}, user: {$user_id}, tier: {$user_tier}", 'info');
        
        // Build player HTML based on format
        switch ($options['format']) {
            case 'embed':
                return self::render_iframe_player($player_id, $stream_url, $options, $stream_id, $session_id);
                
            case 'webrtc':
                return self::render_webrtc_player($player_id, $stream_url, $options, $stream_id, $session_id);
                
            case 'hls':
            default:
                return self::render_hls_player($player_id, $stream_url, $options, $stream_id, $session_id);
        }
    }
    
    /**
     * Render iframe-based player with postMessage communication
     */
    private static function render_iframe_player($player_id, $stream_url, $options, $stream_id, $session_id) {
        $user_tier = get_user_tier();
        $iframe_src = self::build_iframe_src($stream_url, $options, $stream_id, $session_id);
        
        ob_start();
        ?>
        <div class="amsa-player-container iframe-player" 
             id="<?php echo esc_attr($player_id); ?>-container"
             data-player-id="<?php echo esc_attr($player_id); ?>"
             data-stream-id="<?php echo esc_attr($stream_id); ?>"
             data-session-id="<?php echo esc_attr($session_id); ?>"
             data-user-tier="<?php echo esc_attr($user_tier); ?>"
             data-enable-messaging="<?php echo esc_attr($options['enable_messaging']); ?>"
             data-enable-analytics="<?php echo esc_attr($options['enable_analytics']); ?>"
             data-enable-chat-integration="<?php echo esc_attr($options['enable_chat_integration']); ?>"
             style="width: <?php echo esc_attr($options['width']); ?>; height: <?php echo esc_attr($options['height']); ?>; <?php echo esc_attr($options['style']); ?>">
            
            <?php if ($options['show_status'] === 'true'): ?>
            <div class="amsa-stream-status" id="<?php echo esc_attr($player_id); ?>-status">
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span class="status-text">Initializing...</span>
                </div>
                <div class="stream-info">
                    <span class="tier-badge tier-<?php echo esc_attr($user_tier); ?>"><?php echo esc_html(ucfirst($user_tier)); ?></span>
                    <span class="viewer-count" id="<?php echo esc_attr($player_id); ?>-viewers">-- viewers</span>
                </div>
            </div>
            <?php endif; ?>
            
            <iframe 
                id="<?php echo esc_attr($player_id); ?>"
                src="<?php echo esc_url($iframe_src); ?>"
                width="100%" 
                height="100%"
                frameborder="0" 
                allowfullscreen
                allow="autoplay; fullscreen; microphone; camera"
                style="background: #000; border-radius: 8px;">
            </iframe>
            
            <?php if ($options['enable_messaging'] === 'true'): ?>
            <div class="amsa-player-overlay" id="<?php echo esc_attr($player_id); ?>-overlay" style="display: none;">
                <div class="overlay-content">
                    <div class="loading-spinner"></div>
                    <div class="overlay-text">Connecting to stream...</div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="amsa-player-meta">
                <span class="stream-id">Stream: <?php echo esc_html($stream_id); ?></span>
                <span class="session-id">Session: <?php echo esc_html(substr($session_id, -8)); ?></span>
                <?php if ($options['enable_analytics'] === 'true'): ?>
                <span class="analytics-status">📊 Analytics enabled</span>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.amsaInitializePlayer === 'function') {
                window.amsaInitializePlayer('<?php echo esc_js($player_id); ?>', {
                    streamId: '<?php echo esc_js($stream_id); ?>',
                    sessionId: '<?php echo esc_js($session_id); ?>',
                    format: 'iframe',
                    enableMessaging: <?php echo $options['enable_messaging'] === 'true' ? 'true' : 'false'; ?>,
                    enableAnalytics: <?php echo $options['enable_analytics'] === 'true' ? 'true' : 'false'; ?>,
                    enableChatIntegration: <?php echo $options['enable_chat_integration'] === 'true' ? 'true' : 'false'; ?>,
                    tokenRefresh: <?php echo $options['token_refresh'] === 'true' ? 'true' : 'false'; ?>
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render HLS video player with advanced features
     */
    private static function render_hls_player($player_id, $stream_url, $options, $stream_id, $session_id) {
        $user_tier = get_user_tier();
        $controls = ($options['controls'] === 'true') ? 'controls' : '';
        $autoplay = ($options['autoplay'] === 'true') ? 'autoplay' : '';
        $muted = ($options['muted'] === 'true') ? 'muted' : '';
        $poster = !empty($options['poster']) ? 'poster="' . esc_attr($options['poster']) . '"' : '';
        
        ob_start();
        ?>
        <div class="amsa-player-container hls-player" 
             id="<?php echo esc_attr($player_id); ?>-container"
             data-player-id="<?php echo esc_attr($player_id); ?>"
             data-stream-id="<?php echo esc_attr($stream_id); ?>"
             data-session-id="<?php echo esc_attr($session_id); ?>"
             data-user-tier="<?php echo esc_attr($user_tier); ?>"
             data-enable-messaging="<?php echo esc_attr($options['enable_messaging']); ?>"
             data-enable-analytics="<?php echo esc_attr($options['enable_analytics']); ?>"
             data-enable-chat-integration="<?php echo esc_attr($options['enable_chat_integration']); ?>"
             style="width: <?php echo esc_attr($options['width']); ?>; height: <?php echo esc_attr($options['height']); ?>; position: relative; <?php echo esc_attr($options['style']); ?>">
            
            <?php if ($options['show_status'] === 'true'): ?>
            <div class="amsa-stream-status" id="<?php echo esc_attr($player_id); ?>-status">
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span class="status-text">Loading...</span>
                </div>
                <div class="stream-info">
                    <span class="tier-badge tier-<?php echo esc_attr($user_tier); ?>"><?php echo esc_html(ucfirst($user_tier)); ?></span>
                    <span class="viewer-count" id="<?php echo esc_attr($player_id); ?>-viewers">-- viewers</span>
                    <span class="quality-indicator" id="<?php echo esc_attr($player_id); ?>-quality">Auto</span>
                </div>
            </div>
            <?php endif; ?>
            
            <video 
                id="<?php echo esc_attr($player_id); ?>"
                width="100%" 
                height="100%"
                <?php echo $controls; ?>
                <?php echo $autoplay; ?>
                <?php echo $muted; ?>
                <?php echo $poster; ?>
                playsinline
                style="background: #000; border-radius: 8px; width: 100%; height: 100%;">
                <source src="<?php echo esc_url($stream_url); ?>" type="application/x-mpegURL">
                Your browser does not support HLS video streaming.
            </video>
            
            <div class="amsa-player-overlay" id="<?php echo esc_attr($player_id); ?>-overlay" style="display: none;">
                <div class="overlay-content">
                    <div class="loading-spinner"></div>
                    <div class="overlay-text">Connecting to stream...</div>
                </div>
            </div>
            
            <div class="amsa-player-meta">
                <span class="stream-id">Stream: <?php echo esc_html($stream_id); ?></span>
                <span class="session-id">Session: <?php echo esc_html(substr($session_id, -8)); ?></span>
                <?php if ($options['enable_analytics'] === 'true'): ?>
                <span class="analytics-status">📊 Analytics enabled</span>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.amsaInitializePlayer === 'function') {
                window.amsaInitializePlayer('<?php echo esc_js($player_id); ?>', {
                    streamId: '<?php echo esc_js($stream_id); ?>',
                    sessionId: '<?php echo esc_js($session_id); ?>',
                    streamUrl: '<?php echo esc_js($stream_url); ?>',
                    format: 'hls',
                    enableMessaging: <?php echo $options['enable_messaging'] === 'true' ? 'true' : 'false'; ?>,
                    enableAnalytics: <?php echo $options['enable_analytics'] === 'true' ? 'true' : 'false'; ?>,
                    enableChatIntegration: <?php echo $options['enable_chat_integration'] === 'true' ? 'true' : 'false'; ?>,
                    tokenRefresh: <?php echo $options['token_refresh'] === 'true' ? 'true' : 'false'; ?>,
                    reconnectAttempts: <?php echo intval($options['reconnect_attempts']); ?>
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render WebRTC player (experimental)
     */
    private static function render_webrtc_player($player_id, $stream_url, $options, $stream_id, $session_id) {
        $user_tier = get_user_tier();
        
        ob_start();
        ?>
        <div class="amsa-player-container webrtc-player" 
             id="<?php echo esc_attr($player_id); ?>-container"
             data-player-id="<?php echo esc_attr($player_id); ?>"
             data-stream-id="<?php echo esc_attr($stream_id); ?>"
             data-session-id="<?php echo esc_attr($session_id); ?>"
             data-user-tier="<?php echo esc_attr($user_tier); ?>"
             style="width: <?php echo esc_attr($options['width']); ?>; height: <?php echo esc_attr($options['height']); ?>; position: relative; <?php echo esc_attr($options['style']); ?>">
            
            <div class="amsa-stream-status" id="<?php echo esc_attr($player_id); ?>-status">
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span class="status-text">WebRTC Connecting...</span>
                </div>
                <div class="stream-info">
                    <span class="tier-badge tier-<?php echo esc_attr($user_tier); ?>"><?php echo esc_html(ucfirst($user_tier)); ?></span>
                    <span class="latency-indicator">Low latency</span>
                </div>
            </div>
            
            <video 
                id="<?php echo esc_attr($player_id); ?>"
                width="100%" 
                height="100%"
                autoplay
                muted
                playsinline
                style="background: #000; border-radius: 8px;">
            </video>
            
            <div class="amsa-player-meta">
                <span class="stream-id">Stream: <?php echo esc_html($stream_id); ?> (WebRTC)</span>
                <span class="session-id">Session: <?php echo esc_html(substr($session_id, -8)); ?></span>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.amsaInitializeWebRTC === 'function') {
                window.amsaInitializeWebRTC('<?php echo esc_js($player_id); ?>', {
                    streamId: '<?php echo esc_js($stream_id); ?>',
                    sessionId: '<?php echo esc_js($session_id); ?>',
                    streamUrl: '<?php echo esc_js($stream_url); ?>',
                    enableAnalytics: <?php echo $options['enable_analytics'] === 'true' ? 'true' : 'false'; ?>,
                    enableChatIntegration: <?php echo $options['enable_chat_integration'] === 'true' ? 'true' : 'false'; ?>
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Build iframe source URL with messaging parameters
     */
    private static function build_iframe_src($stream_url, $options, $stream_id, $session_id) {
        $params = [
            'autoplay' => $options['autoplay'] === 'true' ? '1' : '0',
            'controls' => $options['controls'] === 'true' ? '1' : '0',
            'muted' => $options['muted'] === 'true' ? '1' : '0',
            'messaging' => $options['enable_messaging'] === 'true' ? '1' : '0',
            'analytics' => $options['enable_analytics'] === 'true' ? '1' : '0',
            'session_id' => $session_id,
            'stream_id' => $stream_id,
            'origin' => home_url(),
            'v' => AMSA_VERSION
        ];
        
        $separator = strpos($stream_url, '?') !== false ? '&' : '?';
        return $stream_url . $separator . http_build_query($params);
    }
    
    /**
     * Generate session ID for tracking
     */
    private static function generate_session_id($user_id, $stream_id) {
        return 'amsa_' . $user_id . '_' . md5($stream_id . time() . wp_rand());
    }
    
    /**
     * Render access denied message
     */
    private static function render_access_denied_message() {
        return '<div class="amsa-message amsa-access-denied">
            <div class="message-icon">🚫</div>
            <h3>Stream Access Disabled</h3>
            <p>Stream access is currently unavailable. Please try again later.</p>
        </div>';
    }
    
    /**
     * Render login required message
     */
    private static function render_login_required_message() {
        $login_url = wp_login_url(get_permalink());
        return '<div class="amsa-message amsa-login-required">
            <div class="message-icon">🔐</div>
            <h3>Login Required</h3>
            <p>Please log in to access the live stream.</p>
            <a href="' . esc_url($login_url) . '" class="amsa-login-button">Login</a>
        </div>';
    }
    
    /**
     * Render no stream message
     */
    private static function render_no_stream_message() {
        $user_tier = get_user_tier();
        return '<div class="amsa-message amsa-no-stream">
            <div class="message-icon">📺</div>
            <h3>No Stream Available</h3>
            <p>No stream is available for your tier: <strong>' . esc_html(ucfirst($user_tier)) . '</strong></p>
            <p>Please contact support if you believe this is an error.</p>
        </div>';
    }
    
    /**
     * Render token error message
     */
    private static function render_token_error_message() {
        return '<div class="amsa-message amsa-token-error">
            <div class="message-icon">⚠️</div>
            <h3>Authentication Error</h3>
            <p>Unable to generate access token. Please refresh the page and try again.</p>
            <button onclick="window.location.reload()" class="amsa-refresh-button">Refresh Page</button>
        </div>';
    }
    
    /**
     * Render URL error message
     */
    private static function render_url_error_message() {
        return '<div class="amsa-message amsa-url-error">
            <div class="message-icon">🔗</div>
            <h3>Stream URL Error</h3>
            <p>Unable to generate stream URL. Please check your configuration.</p>
        </div>';
    }
}

/**
 * Enhanced stream player shortcode function
 */
function render_stream_player($atts = []) {
    return AMSA_Stream_Player::render_enhanced_player($atts);
}

/**
 * PostMessage API for iframe communication
 * This allows the iframe to communicate stream status to the parent window
 */
add_action('wp_footer', function() {
    if (!is_admin()) {
        ?>
        <script>
        // Global PostMessage listener for stream iframe communication
        window.addEventListener('message', function(event) {
            // Verify origin for security
            const allowedOrigins = [
                '<?php echo esc_js(home_url()); ?>',
                '<?php echo esc_js(get_option("ant_media_server_url", "")); ?>'
            ];
            
            if (!allowedOrigins.some(origin => event.origin.startsWith(origin))) {
                return;
            }
            
            try {
                const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                
                if (data.type && data.type.startsWith('amsa_')) {
                    console.log('AMSA Stream Message:', data);
                    
                    // Trigger custom events for external integration
                    const customEvent = new CustomEvent('amsaStreamEvent', {
                        detail: data
                    });
                    window.dispatchEvent(customEvent);
                    
                    // Handle specific stream events
                    switch (data.type) {
                        case 'amsa_stream_play':
                            console.log('Stream started playing:', data.streamId);
                            // Notify chat or other systems
                            if (typeof window.notifyStreamStatus === 'function') {
                                window.notifyStreamStatus('playing', data);
                            }
                            break;
                            
                        case 'amsa_stream_pause':
                            console.log('Stream paused:', data.streamId);
                            if (typeof window.notifyStreamStatus === 'function') {
                                window.notifyStreamStatus('paused', data);
                            }
                            break;
                            
                        case 'amsa_stream_stop':
                            console.log('Stream stopped:', data.streamId);
                            if (typeof window.notifyStreamStatus === 'function') {
                                window.notifyStreamStatus('stopped', data);
                            }
                            break;
                            
                        case 'amsa_stream_error':
                            console.error('Stream error:', data.error);
                            if (typeof window.notifyStreamStatus === 'function') {
                                window.notifyStreamStatus('error', data);
                            }
                            break;
                            
                        case 'amsa_viewer_count':
                            console.log('Viewer count update:', data.count);
                            // Update viewer count display
                            const viewerElements = document.querySelectorAll('.viewer-count');
                            viewerElements.forEach(el => {
                                el.textContent = data.count + ' viewers';
                            });
                            break;
                    }
                    
                    // Record analytics if enabled
                    if (data.sessionId && data.streamId) {
                        if (typeof window.amsaRecordEvent === 'function') {
                            window.amsaRecordEvent(data.streamId, data.type, data);
                        }
                    }
                }
            } catch (e) {
                console.warn('Error parsing stream message:', e);
            }
        });
        
        // Example function for external chat integration
        window.notifyStreamStatus = function(status, data) {
            console.log('Stream status notification:', status, data);
            
            // Example: Notify chat system about stream status
            if (typeof window.chatAPI !== 'undefined') {
                window.chatAPI.notifyStreamStatus(status, {
                    streamId: data.streamId,
                    userId: data.userId,
                    tier: data.tier,
                    timestamp: new Date().toISOString()
                });
            }
            
            // Example: Send custom webhook
            if (status === 'playing') {
                // Could send to external systems
                console.log('Stream is playing - could notify external systems');
            }
        };
        </script>
        <?php
    }
}); 