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
        'check_stream_status' => 'true', // New parameter to control stream integration
        'auto_tier' => 'false', // New parameter to auto-join based on user tier
        'show_sidebar' => 'true', // New parameter to control sidebar visibility
    ], $atts);

    $host_url = get_option('rocket_chat_host_url');
    if (empty($host_url)) {
        return '<p>Rocket.Chat configuration is incomplete. Please configure in Settings → Rocket Chat Widget.</p>';
    }

    // Check if we should integrate with stream status
    $check_stream_status = $atts['check_stream_status'] === 'true';
    
    // Check stream status server-side for initial page load
    $any_stream_live = false;
    if ($check_stream_status && function_exists('should_display_ant_media_stream')) {
        // Simple check: Use WordPress option set by ant-media hooks
        $any_stream_live = get_option('amsa_streams_currently_live', false);
        
        if (function_exists('rocket_chat_log')) {
            rocket_chat_log("💬 CHAT: Hook-based status check - streams live: " . ($any_stream_live ? 'true' : 'false'), 'info');
        }
        
        // If no streams are live, hide the chat initially 
        if (!$any_stream_live) {
            if (function_exists('rocket_chat_log')) {
                rocket_chat_log("💬 CHAT: No streams live - showing offline message", 'info');
            }
            return '<div class="rocket-chat-offline-message" style="padding: 20px; text-align: center; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; color: #666;">
                <p><strong>💬 Chat is currently offline</strong></p>
                <p>Chat will appear when streaming begins.</p>
            </div>';
        } else {
            if (function_exists('rocket_chat_log')) {
                rocket_chat_log("💬 CHAT: Streams are live - showing chat", 'info');
            }
        }
    }

    // Set channel context for this request
    $channel = sanitize_text_field($atts['channel']);
    $auto_tier = $atts['auto_tier'] === 'true';
    
    // If auto_tier is enabled, determine the user's highest tier channel
    if ($auto_tier && is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_channels = get_user_appropriate_channels($current_user);
        
        // Find the highest tier channel (priority: platinum > gold > silver > general)
        $tier_priority = ['platinum', 'gold', 'silver', 'general'];
        $best_channel = 'general'; // fallback
        
        foreach ($tier_priority as $tier) {
            if (in_array($tier, $user_channels)) {
                $best_channel = $tier;
                break;
            }
        }
        
        $channel = $best_channel;
        rocket_chat_log("Auto-tier enabled: User {$current_user->user_login} assigned to channel: $channel", 'info');
    }
    
    set_current_rocket_chat_channel($channel);

    // Check license status
    $is_premium = rce_is_premium_active();
    
    // Note: Users have unlimited license - no restrictions needed
    // Premium features are always available

    $width = sanitize_text_field($atts['width']);
    $height = sanitize_text_field($atts['height']);
    $style = sanitize_text_field($atts['style']);

    // Ensure user exists in Rocket.Chat and is joined to appropriate channels
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        
        // Use different management based on auto_tier setting
        if ($auto_tier) {
            // Auto-tier mode: join user to all channels based on their roles
            $result = ensure_user_in_rocket_chat_channels_auto_tier($current_user->user_login);
        } else {
            // Normal mode: join user only to the specific channel they're accessing
            $result = ensure_user_in_rocket_chat_channels($current_user->user_login, $channel);
        }
        
        // Handle error response with user-friendly message
        if (is_array($result) && isset($result['success']) && !$result['success']) {
            $error_info = isset($result['error']) ? $result['error'] : null;
            
            if ($error_info) {
                return '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                       '<div style="display: flex; align-items: center; margin-bottom: 12px;">' .
                       '<span style="font-size: 18px; margin-right: 8px;">⚠️</span>' .
                       '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">' . esc_html($error_info['title']) . '</h4>' .
                       '</div>' .
                       '<p style="margin: 0 0 12px 0; line-height: 1.5;">' . esc_html($error_info['message']) . '</p>' .
                       '<p style="margin: 0; line-height: 1.5; font-weight: 500;">' . esc_html($error_info['action']) . '</p>' .
                       '</div>';
            } else {
                return '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                       '<div style="display: flex; align-items: center; margin-bottom: 12px;">' .
                       '<span style="font-size: 18px; margin-right: 8px;">⚠️</span>' .
                       '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Chat Access Issue</h4>' .
                       '</div>' .
                       '<p style="margin: 0 0 12px 0; line-height: 1.5;">We encountered an issue setting up your chat access.</p>' .
                       '<p style="margin: 0; line-height: 1.5; font-weight: 500;">Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>' .
                       '</div>';
            }
        }
        
        // Backward compatibility for boolean responses
        if (is_bool($result) && !$result) {
            rocket_chat_log("Failed to ensure user {$current_user->user_login} has appropriate channel access", 'warning');
            return '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                   '<div style="display: flex; align-items: center; margin-bottom: 12px;">' .
                   '<span style="font-size: 18px; margin-right: 8px;">⚠️</span>' .
                   '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Chat Access Issue</h4>' .
                   '</div>' .
                   '<p style="margin: 0 0 12px 0; line-height: 1.5;">We encountered an issue setting up your chat access.</p>' .
                   '<p style="margin: 0; line-height: 1.5; font-weight: 500;">Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>' .
                   '</div>';
        }
        
        // Validate channel access after user management
        $api = get_rocket_chat_api();
        $room_type = 'channel'; // default
        if ($channel !== 'general') {
            $channel_info = $api->get_channel_info($channel);
            if (!$channel_info) {
                rocket_chat_log("Shortcode: Channel '$channel' not accessible after user management, falling back to general", 'warning');
                $channel = 'general'; // Fallback to general
                $room_type = 'channel';
            } else {
                $room_type = $channel_info['room_type'];
                rocket_chat_log("Shortcode: Channel '$channel' is accessible as room type: $room_type", 'info');
            }
        }
    }

    // Premium features (always available with unlimited license)
    $iframe_params = [];
    
    // SSO and auto-login available to all users with unlimited license
    $sso_enabled = get_option('rocket_chat_sso_enabled', false);
    $auto_login = get_option('rocket_chat_auto_login', false);
    
    if ($sso_enabled && is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $iframe_params['username'] = $current_user->user_login;
        $iframe_params['email'] = $current_user->user_email;
        $iframe_params['name'] = $current_user->display_name;
    }
    
    if ($auto_login) {
        $iframe_params['autologin'] = 'true';
    }
    
    // Basic user info for logged in users (even if SSO not enabled)
    if (is_user_logged_in() && !$sso_enabled) {
        $current_user = wp_get_current_user();
        $iframe_params['username'] = $current_user->user_login;
    }

    // Build iframe URL based on sidebar preference and room type
    $show_sidebar = $atts['show_sidebar'] === 'true';
    
    if ($show_sidebar) {
        // Show full interface with sidebar so users can see all channels
        $iframe_url = rtrim($host_url, '/') . '/home?layout=embedded';
    } else {
        // Show specific channel/group only - use correct endpoint based on room type
        if ($room_type === 'group') {
            $iframe_url = rtrim($host_url, '/') . '/group/' . $channel . '?layout=embedded';
        } else {
            $iframe_url = rtrim($host_url, '/') . '/channel/' . $channel . '?layout=embedded';
        }
    }
    
    // Add basic iframe parameters for user context
    $iframe_params = [];
    if (!empty($iframe_params)) {
        $iframe_url .= '&' . http_build_query($iframe_params);
    }

    // Custom CSS (always available with unlimited license)
    $custom_css = '';
    $custom_css_option = get_option('rocket_chat_custom_css', '');
    if (!empty($custom_css_option)) {
        $custom_css = '<style>' . wp_strip_all_tags($custom_css_option) . '</style>';
    }

    // No upgrade notice needed - unlimited license active!

    // Loading animation
    $iframe_id = 'rce-iframe-' . uniqid();
    $loading_css = '<style>
        .rce-container { position: relative; }
        .rce-loading {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .rce-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e3e3e3;
            border-top: 4px solid #0073aa;
            border-radius: 50%;
            animation: rce-spin 1s linear infinite;
        }
        @keyframes rce-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .rce-iframe { opacity: 0; transition: opacity 0.3s ease; }
        .rce-iframe.loaded { opacity: 1; }
    </style>';

    // Generate the iframe HTML
    $iframe_html = sprintf(
        '%s%s
        <div class="rce-container" id="rce-container-%s" style="width: %s; height: %s; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; %s">
            <div class="rce-loading">
                <div class="rce-spinner"></div>
            </div>
            <iframe id="%s" class="rce-iframe" src="%s" width="100%%" height="100%%" frameborder="0" title="Rocket.Chat" onload="this.classList.add(\'loaded\'); this.parentNode.querySelector(\'.rce-loading\').style.display=\'none\'"></iframe>
        </div>',
        $loading_css,
        $custom_css,
        uniqid(),
        esc_attr($width),
        esc_attr($height),
        esc_attr($style),
        esc_attr($iframe_id),
        esc_url($iframe_url)
    );

    // Add real-time integration script if stream checking is enabled
    if ($check_stream_status && function_exists('should_display_ant_media_stream')) {
        $container_id = 'rce-container-' . substr($iframe_id, -8);
        $iframe_html .= sprintf('
        <script>
        (function() {
            const containerEl = document.getElementById("%s");
            
            function showChat(reason) {
                console.log("💬 WordPress Heartbeat: SHOWING chat:", reason);
                if (containerEl && containerEl.style.display === "none") {
                    containerEl.style.display = "block";
                }
            }
            
            function hideChat(reason) {
                console.log("💬 WordPress Heartbeat: HIDING chat:", reason);
                if (containerEl && containerEl.style.display !== "none") {
                    containerEl.style.display = "none";
                }
            }
            
            // Register with WordPress Heartbeat chat visibility system
            if (!window.chatContainers) {
                window.chatContainers = [];
            }
            window.chatContainers.push({
                element: containerEl,
                id: "%s",
                show: showChat,
                hide: hideChat
            });
            
            // Initial status check - use WordPress Heartbeat data, NO AJAX CALLS
            setTimeout(function() {
                console.log("💬 WordPress Heartbeat: Checking initial chat visibility from server data");
                
                // Use server-side determined status - chat is already showing if streams are live
                // WordPress Heartbeat will handle updates every 5 seconds automatically
                var serverDetectedLive = ' . ($any_stream_live ? 'true' : 'false') . ';
                
                console.log("💬 WordPress Heartbeat: Server detected streams live:", serverDetectedLive);
                
                if (serverDetectedLive) {
                    showChat("initial check: server confirmed streams live");
                } else {
                    hideChat("initial check: server confirmed no streams");
                }
            }, 1000);
            
            // Listen for the WordPress Heartbeat stream status events
            jQuery(document).on("amsa-stream-status-update", function(event, statusData) {
                console.log("💬 WordPress Heartbeat: Chat received status update", statusData);
                if (statusData && typeof statusData.any_live !== "undefined") {
                    if (statusData.any_live) {
                        showChat("WordPress Heartbeat event: streams live");
                    } else {
                        hideChat("WordPress Heartbeat event: no streams");
                    }
                }
            });
            
            console.log("💬 WordPress Heartbeat: Chat integrated with stream monitoring");
        })();
        </script>',
        substr($iframe_id, -8),
        substr($iframe_id, -8)
        );
    }

    // Add channel navigation script for all shortcode instances
    $iframe_html .= sprintf('
    <script>
    (function() {
        var iframeId = "%s";
        var hostUrl = "%s";
        var preferredChannel = "%s";
        var showSidebar = %s;
        var roomType = "%s";
        
        // Listen for Rocket.Chat events - only navigate to channel if showing full interface
        window.addEventListener("message", function(e) {
            if (e.origin === hostUrl && e.data.eventName === "Custom_Script_Logged_In") {
                // User is logged in, navigate to preferred channel if showing full interface and channel is specified
                if (showSidebar && preferredChannel && preferredChannel !== "general") {
                    setTimeout(function() {
                        var iframe = document.getElementById(iframeId);
                        if (iframe && iframe.contentWindow) {
                            var navigationPath = roomType === "group" ? "/group/" + preferredChannel : "/channel/" + preferredChannel;
                            console.log("Navigating to preferred room:", preferredChannel, "using path:", navigationPath);
                            iframe.contentWindow.postMessage({
                                externalCommand: "go",
                                path: navigationPath
                            }, hostUrl);
                        }
                    }, 1000);
                }
            }
        });
    })();
    </script>',
    esc_js($iframe_id),
    esc_js($host_url),
    esc_js($channel),
    $show_sidebar ? 'true' : 'false',
    esc_js($room_type)
    );

    // Fire action hook for tracking
    do_action('rocket_chat_iframe_displayed', [
        'channel' => $channel,
        'user_logged_in' => is_user_logged_in(),
        'is_premium' => $is_premium
    ]);

    return $iframe_html;
});

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
