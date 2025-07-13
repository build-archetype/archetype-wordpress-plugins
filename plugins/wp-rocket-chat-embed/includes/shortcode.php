<?php
if (!defined('ABSPATH')) exit;

add_shortcode('rocketchat_iframe', function($atts) {
    // Check if chat should be displayed using our filtering function
    if (!should_display_rocket_chat()) {
        return '<p>Chat is currently unavailable.</p>';
    }

    // Default attributes
    $atts = shortcode_atts([
        'channel' => get_option('rocket_chat_default_channel', 'general'),
        'width' => '100%',
        'height' => '600px',
        'style' => '',
        'check_stream_status' => 'true',
        'auto_tier' => 'false',
        'show_sidebar' => 'true',
    ], $atts);

    $host_url = get_option('rocket_chat_host_url');
    if (empty($host_url)) {
        return '<p>Rocket.Chat configuration is incomplete. Please configure in Settings → Rocket Chat Widget.</p>';
    }

    // Check if we should integrate with stream status
    $check_stream_status = $atts['check_stream_status'] === 'true';
    
    // DEBUG: Log what's happening
    if (function_exists('rocket_chat_log')) {
        rocket_chat_log("🔍 SHORTCODE DEBUG: check_stream_status = " . ($check_stream_status ? 'true' : 'false'), 'info');
        rocket_chat_log("🔍 SHORTCODE DEBUG: rocket_chat_stream_is_live exists = " . (function_exists('rocket_chat_stream_is_live') ? 'true' : 'false'), 'info');
    }
    
    // Simple server-side check using our new system
    $stream_is_live = false;
    if ($check_stream_status) {
        if (function_exists('rocket_chat_stream_is_live')) {
            $stream_is_live = rocket_chat_stream_is_live();
            
            if (function_exists('rocket_chat_log')) {
                rocket_chat_log("🔍 SHORTCODE DEBUG: stream_is_live = " . ($stream_is_live ? 'true' : 'false'), 'info');
            }
        } else {
            // Stream checking is enabled but ant-media plugin is not active
            if (function_exists('rocket_chat_log')) {
                rocket_chat_log("🔍 SHORTCODE DEBUG: Stream checking enabled but ant-media plugin not available", 'warning');
            }
            $stream_is_live = false; // Assume offline if integration not available
        }
        
        // If no streams are live, show offline message
        if (!$stream_is_live) {
            if (function_exists('rocket_chat_log')) {
                rocket_chat_log("🔍 SHORTCODE DEBUG: Returning offline message", 'info');
            }
            return '<div class="rocket-chat-offline-message" style="padding: 20px; text-align: center; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; color: #666;">
                <p><strong>💬 Chat is currently offline</strong></p>
                <p>Chat will appear when streaming begins.</p>
            </div>';
        }
    } else {
        // Stream checking is disabled, proceed to show chat
        if (function_exists('rocket_chat_log')) {
            rocket_chat_log("🔍 SHORTCODE DEBUG: Stream checking disabled - showing chat", 'info');
        }
    }

    // Set channel context for this request
    $channel = sanitize_text_field($atts['channel']);
    $auto_tier = $atts['auto_tier'] === 'true';
    
    // If auto_tier is enabled, determine the user's highest tier channel
    if ($auto_tier && is_user_logged_in()) {
        $user_tier = get_user_meta(get_current_user_id(), 'membership_tier', true);
        $tier_channels = [
            'premium' => 'premium-chat',
            'gold' => 'gold-chat',
            'silver' => 'silver-chat',
            'bronze' => 'bronze-chat'
        ];
        
        if (!empty($user_tier) && isset($tier_channels[$user_tier])) {
            $channel = $tier_channels[$user_tier];
        }
    }

    // Authenticate the user
    $auth_result = authenticate_and_get_login_token();
    if (!$auth_result['success']) {
        return '<div class="rocket-chat-error">Authentication failed: ' . esc_html($auth_result['message']) . '</div>';
    }

    // Generate unique iframe ID
    $iframe_id = 'rocket-chat-' . uniqid();
    $container_id = 'rce-container-' . substr($iframe_id, -8);

    // Build the iframe URL
    $iframe_url = esc_url($host_url);
    $iframe_url .= '/channel/' . urlencode($channel) . '?layout=embedded';

    // Determine initial visibility
    $initial_display = $stream_is_live ? 'block' : 'none';

    // Build the HTML
    $iframe_html = sprintf(
        '<div id="%s" class="rocket-chat-container" style="display: %s; width: %s; height: %s; %s" data-rocket-chat="true">
            <div class="rocket-chat-loading" style="display: flex; align-items: center; justify-content: center; height: 100%%; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px;">
                <div style="text-align: center;">
                    <div class="loading-spinner" style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%%; animation: spin 1s linear infinite; margin: 0 auto 16px;"></div>
                    <p>Loading chat...</p>
                </div>
            </div>
            <iframe id="%s" src="%s" style="width: 100%%; height: 100%%; border: none; display: none;"></iframe>
        </div>',
        esc_attr($container_id),
        esc_attr($initial_display),
        esc_attr($atts['width']),
        esc_attr($atts['height']),
        esc_attr($atts['style']),
        esc_attr($iframe_id),
        esc_url($iframe_url)
    );

    // Add JavaScript for authentication and stream integration
    $iframe_html .= sprintf('
    <script>
    (function() {
        const iframe = document.getElementById("%s");
        const container = document.getElementById("%s");
        const loading = container.querySelector(".loading-spinner").parentElement;
        
        // Chat container functions
        function showChat() {
            console.log("Rocket Chat: SHOWING chat");
            container.style.display = "block";
        }
        
        function hideChat() {
            console.log("Rocket Chat: HIDING chat");
            container.style.display = "none";
        }
        
        // Register with the stream integration system
        if (!window.rocketChatContainers) {
            window.rocketChatContainers = [];
        }
        window.rocketChatContainers.push({
            show: showChat,
            hide: hideChat,
            element: container
        });
        
        // Handle iframe load
        iframe.onload = function() {
            console.log("Rocket Chat: iframe loaded");
            
            // Authenticate with Rocket.Chat
            const authData = {
                authToken: "%s",
                userId: "%s"
            };
            
            // Send authentication via postMessage
            setTimeout(function() {
                iframe.contentWindow.postMessage({
                    externalCommand: "login-with-token",
                    token: authData.authToken,
                    userId: authData.userId
                }, "*");
                
                // Hide loading and show iframe
                loading.style.display = "none";
                iframe.style.display = "block";
            }, 1000);
        };
        
        // Initial state based on server-side check
        if (%s) {
            showChat();
        } else {
            hideChat();
        }
        
        console.log("Rocket Chat: Container registered for stream integration");
    })();
    </script>
    
    <style>
    @keyframes spin {
        0%% { transform: rotate(0deg); }
        100%% { transform: rotate(360deg); }
    }
    </style>',
        esc_js($iframe_id),
        esc_js($container_id),
        esc_js($auth_result['authToken']),
        esc_js($auth_result['userId']),
        $stream_is_live ? 'true' : 'false'
    );

    // Add channel navigation script
    $iframe_html .= sprintf('
    <script>
    window.rocketChatNavigate = function(channel) {
        const iframe = document.getElementById("%s");
        if (iframe) {
            const newUrl = "%s/channel/" + encodeURIComponent(channel) + "?layout=embedded";
            iframe.src = newUrl;
            console.log("Rocket Chat: Navigated to channel:", channel);
        }
    };
    </script>',
        esc_js($iframe_id),
        esc_js($host_url)
    );

    return $iframe_html;
});

// Helper function to authenticate user
function authenticate_and_get_login_token() {
    if (!is_user_logged_in()) {
        return ['success' => false, 'message' => 'User must be logged in'];
    }
    
    // Get user info
    $current_user = wp_get_current_user();
    $rocket_chat_user = get_user_meta($current_user->ID, 'rocket_chat_user_id', true);
    $rocket_chat_token = get_user_meta($current_user->ID, 'rocket_chat_auth_token', true);
    
    if (empty($rocket_chat_user) || empty($rocket_chat_token)) {
        return ['success' => false, 'message' => 'Rocket.Chat authentication not configured'];
    }
    
    return [
        'success' => true,
        'authToken' => $rocket_chat_token,
        'userId' => $rocket_chat_user
    ];
}

/**
 * Debug shortcode to show user channel information
 */
add_shortcode('rocket_chat_debug', 'rocket_chat_debug_shortcode');

function rocket_chat_debug_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view debug information.</p>';
    }
    
    $current_user = wp_get_current_user();
    $api = get_rocket_chat_api();
    
    $debug_output = '<div style="background: #f8f9fa; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 4px; font-family: monospace; font-size: 14px;">';
    $debug_output .= '<h3 style="margin-top: 0;">🔧 Rocket.Chat Debug Information</h3>';
    
    // User information
    $debug_output .= '<p><strong>WordPress User:</strong> ' . esc_html($current_user->user_login) . '</p>';
    $debug_output .= '<p><strong>WordPress Roles:</strong> ' . esc_html(implode(', ', $current_user->roles)) . '</p>';
    
    // Determine appropriate channels
    $user_channels = get_user_appropriate_channels($current_user);
    $debug_output .= '<p><strong>Appropriate Channels:</strong> ' . esc_html(implode(', ', $user_channels)) . '</p>';
    
    // Check Rocket.Chat user
    $rc_user = $api->get_user($current_user->user_login);
    if ($rc_user && isset($rc_user['user'])) {
        $debug_output .= '<p><strong>Rocket.Chat User:</strong> ✅ Exists (ID: ' . esc_html($rc_user['user']['_id']) . ')</p>';
    } else {
        $debug_output .= '<p><strong>Rocket.Chat User:</strong> ❌ Does not exist</p>';
    }
    
    // Check channel access
    $debug_output .= '<p><strong>Channel Access:</strong></p><ul>';
    foreach ($user_channels as $channel) {
        $channel_info = $api->get_channel_info($channel);
        if ($channel_info) {
            $room_type = $channel_info['room_type'];
            $debug_output .= '<li>✅ <strong>' . esc_html($channel) . '</strong> - Accessible (' . esc_html($room_type) . ')</li>';
        } else {
            $debug_output .= '<li>❌ <strong>' . esc_html($channel) . '</strong> - Not accessible</li>';
        }
    }
    $debug_output .= '</ul>';
    
    // Test enhanced management
    $debug_output .= '<p><strong>Testing Enhanced Management:</strong></p>';
    $test_result = ensure_user_in_rocket_chat_channels($current_user->user_login, 'general');
    $debug_output .= '<p>Result: ' . ($test_result ? '✅ Success' : '❌ Failed') . '</p>';
    
    $debug_output .= '</div>';
    
    return $debug_output;
}
