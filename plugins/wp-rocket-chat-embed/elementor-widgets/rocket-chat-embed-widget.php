<?php
namespace Elementor;

if (!defined('ABSPATH')) exit;

// Safety check for Elementor
if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

// Plugin: Rocket.Chat Embed for WordPress

class Rocket_Chat_Embed_Widget extends Widget_Base {
    public function get_name() {
        return 'rocket_chat_embed';
    }
    public function get_title() {
        return 'Rocket Chat Embed';
    }
    public function get_icon() {
        return 'eicon-editor-code';
    }
    public function get_categories() {
        return ['general'];
    }
    protected function _register_controls() {
        $this->start_controls_section('section_content', ['label' => __('Settings')]);
        $this->add_control('channel', [
            'label' => __('Channel'),
            'type' => Controls_Manager::TEXT,
            'default' => get_option('rocket_chat_default_channel', 'general'),
        ]);
        $this->add_control('width', [
            'label' => __('Width'),
            'type' => Controls_Manager::TEXT,
            'default' => '100%',
        ]);
        $this->add_control('height', [
            'label' => __('Height'),
            'type' => Controls_Manager::TEXT,
            'default' => '900',
        ]);
        $this->add_control('show_debug', [
            'label' => __('Show Debug Info'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'no',
        ]);
        $this->add_control('auto_tier', [
            'label' => __('Auto-Tier Assignment'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'no',
            'description' => __('Automatically assign users to their highest tier channel (platinum > gold > silver)'),
        ]);
        $this->add_control('interface_mode', [
            'label' => __('Interface Mode'),
            'type' => Controls_Manager::SELECT,
            'default' => 'full',
            'options' => [
                'full' => __('Full Interface (Show all channels with sidebar)'),
                'channel' => __('Specific Channel Only'),
            ],
            'description' => __('Choose whether users can see all their channels or just a specific one'),
        ]);
        $this->add_control('check_stream_status', [
            'label' => __('Stream Integration'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('Hide chat when no streams are live (requires Ant Media Stream plugin)'),
        ]);
        $this->end_controls_section();
    }
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get server URL and validate
        $host = rtrim(get_option('rocket_chat_host_url'), '/');
        if (empty($host)) {
            echo '<div class="rocket-chat-error">Rocket.Chat server not configured.</div>';
            return;
        }
        
        $channel = !empty($settings['channel']) ? $settings['channel'] : 'general';
        $height = !empty($settings['height']) ? $settings['height'] : '400px';
        $interface_mode = !empty($settings['interface_mode']) ? $settings['interface_mode'] : 'full';
        $iframe_id = 'rocket-chat-iframe-' . uniqid();
        $debug_output = '';
        
        // COMPREHENSIVE LOGIN DEBUG - Let's see exactly what's happening
        $is_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        
        rocket_chat_log("🔍 LOGIN DEBUG: is_user_logged_in() = " . ($is_logged_in ? 'TRUE' : 'FALSE'), 'info');
        rocket_chat_log("🔍 LOGIN DEBUG: current_user->ID = " . ($current_user ? $current_user->ID : 'NULL'), 'info');
        rocket_chat_log("🔍 LOGIN DEBUG: current_user->user_login = " . ($current_user ? $current_user->user_login : 'NULL'), 'info');
        rocket_chat_log("🔍 LOGIN DEBUG: current_user->user_email = " . ($current_user ? $current_user->user_email : 'NULL'), 'info');
        rocket_chat_log("🔍 LOGIN DEBUG: current_user->user_status = " . ($current_user ? $current_user->user_status : 'NULL'), 'info');
        
        // Add debug output to page if debug mode is enabled
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<div class="rocket-chat-debug" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">';
            $debug_output .= '<h4>🔍 Rocket Chat Login Debug</h4>';
            $debug_output .= '<p><strong>WordPress Login Status:</strong> ' . ($is_logged_in ? 'LOGGED IN' : 'NOT LOGGED IN') . '</p>';
            if ($current_user && $current_user->ID) {
                $debug_output .= '<p><strong>User ID:</strong> ' . $current_user->ID . '</p>';
                $debug_output .= '<p><strong>Username:</strong> ' . $current_user->user_login . '</p>';
                $debug_output .= '<p><strong>Email:</strong> ' . ($current_user->user_email ?: 'NO EMAIL') . '</p>';
                $debug_output .= '<p><strong>Display Name:</strong> ' . $current_user->display_name . '</p>';
                $debug_output .= '<p><strong>User Status:</strong> ' . $current_user->user_status . '</p>';
                
                // Check Rocket Chat setup
                $rocket_chat_user = get_user_meta($current_user->ID, 'rocket_chat_user_id', true);
                $rocket_chat_password = get_user_meta($current_user->ID, 'rocket_chat_encrypted_password', true);
                $debug_output .= '<p><strong>Rocket Chat User ID:</strong> ' . ($rocket_chat_user ?: 'NOT SET') . '</p>';
                $debug_output .= '<p><strong>Rocket Chat Password:</strong> ' . ($rocket_chat_password ? 'SET' : 'NOT SET') . '</p>';
            }
            $debug_output .= '</div>';
        }
        
        // Show debug output immediately
        if ($debug_output) {
            echo $debug_output;
        }

        if (!is_user_logged_in()) {
            rocket_chat_log("Widget render attempted by non-logged-in user", 'info');
            echo '<p>Please log in to access the chat.</p>';
            return;
        }

        $current_user = wp_get_current_user();
        $debug_output = '';

        if ($settings['show_debug'] === 'yes') {
            $debug_output = '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
            $debug_output .= '<h3>Debug Information</h3>';
            $debug_output .= '<p>WordPress User: ' . esc_html($current_user->user_login) . '</p>';
        }

        $host = rtrim(get_option('rocket_chat_host_url'), '/');
        $channel = esc_attr($settings['channel']);
        $width = esc_attr($settings['width']);
        $height = esc_attr($settings['height']);
        $auto_tier = $settings['auto_tier'] === 'yes';
        $interface_mode = $settings['interface_mode'];

        // If auto_tier is enabled, determine the user's highest tier channel
        if ($auto_tier) {
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
            rocket_chat_log("Widget: Auto-tier enabled: User {$current_user->user_login} assigned to channel: $channel", 'info');
            
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p><strong>Auto-Tier:</strong> User assigned to channel "' . esc_html($channel) . '"</p>';
            }
        }

        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p>Rocket.Chat Host: ' . esc_html($host) . '</p>';
            $debug_output .= '<p>Channel: ' . esc_html($channel) . '</p>';
            $debug_output .= '<p>Interface Mode: ' . esc_html($interface_mode) . '</p>';
        }

        // Ensure user exists in Rocket.Chat and is joined to appropriate channels
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<h4>Enhanced User & Channel Management</h4>';
        }
        
        rocket_chat_log("Widget: Using enhanced channel management for user: {$current_user->user_login}", 'info');
        
        // Use different management based on auto_tier setting
        if ($auto_tier) {
            // Auto-tier mode: join user to all channels based on their roles
            $result = ensure_user_in_rocket_chat_channels_auto_tier($current_user->user_login);
        } else {
            // Normal mode: join user only to the specific channel they're accessing
            $result = ensure_user_in_rocket_chat_channels($current_user->user_login, $channel);
        }
        
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p>Enhanced management result: ' . ($result ? 'SUCCESS' : 'FAILED') . '</p>';
            
            if ($auto_tier) {
                // Show user's determined channels for auto-tier
                $user_channels = get_user_appropriate_channels($current_user);
                $debug_output .= '<p>Auto-tier channels: ' . esc_html(implode(', ', $user_channels)) . '</p>';
            } else {
                // Show the specific channel being accessed
                $debug_output .= '<p>Requested channel: ' . esc_html($channel) . '</p>';
            }
        }
        
        if (!$result) {
            rocket_chat_log("Widget: Enhanced user management failed for user: {$current_user->user_login}", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Enhanced channel management failed.</p>';
                echo $debug_output;
            }
            echo '<p style="color: red;">Error: Unable to ensure chat access. See debug info above.</p>';
            return;
        }
        
        // Handle new error response format
        if (is_array($result) && isset($result['success']) && !$result['success']) {
            rocket_chat_log("Widget: Enhanced user management failed for user: {$current_user->user_login}", 'error');
            
            $error_info = isset($result['error']) ? $result['error'] : null;
            
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Enhanced channel management failed.</p>';
                if ($error_info) {
                    $debug_output .= '<p style="color: red;">Error details: ' . esc_html(json_encode($error_info)) . '</p>';
                }
                echo $debug_output;
            }
            
            // Display user-friendly error message
            if ($error_info) {
                echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
                echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
                echo '<span style="font-size: 18px; margin-right: 8px;">⚠️</span>';
                echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">' . esc_html($error_info['title']) . '</h4>';
                echo '</div>';
                echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">' . esc_html($error_info['message']) . '</p>';
                echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">' . esc_html($error_info['action']) . '</p>';
                echo '</div>';
            } else {
                // Fallback for unknown errors
                echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
                echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
                echo '<span style="font-size: 18px; margin-right: 8px;">⚠️</span>';
                echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Chat Access Issue</h4>';
                echo '</div>';
                echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">We encountered an issue setting up your chat access.</p>';
                echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>';
                echo '</div>';
            }
            return;
        }
        
        // Backward compatibility: handle old boolean response
        if (is_bool($result) && !$result) {
            rocket_chat_log("Widget: Enhanced user management failed for user: {$current_user->user_login}", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Enhanced channel management failed.</p>';
                echo $debug_output;
            }
            
            // Show generic friendly error for legacy responses
            echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<span style="font-size: 18px; margin-right: 8px;">⚠️</span>';
            echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Chat Access Issue</h4>';
            echo '</div>';
            echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">We encountered an issue setting up your chat access.</p>';
            echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>';
            echo '</div>';
            return;
        }

        // Validate channel access before loading iframe
        $api = get_rocket_chat_api();
        $channel_accessible = false;
        $room_type = 'channel'; // default
        
        // Check if the user can access the requested channel
        if ($channel === 'general') {
            $channel_accessible = true; // Everyone can access general
        } else {
            // Check if the channel exists and user has access
            $channel_info = $api->get_channel_info($channel);
            if ($channel_info) {
                $channel_accessible = true;
                $room_type = $channel_info['room_type'];
                rocket_chat_log("Widget: Channel '$channel' is accessible as room type: $room_type", 'info');
            } else {
                rocket_chat_log("Widget: Channel '$channel' not accessible, falling back to general", 'warning');
                $channel = 'general'; // Fallback to general
                $channel_accessible = true;
                $room_type = 'channel';
            }
        }
        
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p><strong>Channel Access:</strong> ' . ($channel_accessible ? 'ALLOWED' : 'DENIED') . '</p>';
            $debug_output .= '<p><strong>Final Channel:</strong> ' . esc_html($channel) . '</p>';
            $debug_output .= '<p><strong>Room Type:</strong> ' . esc_html($room_type) . '</p>';
        }

        // Get user details for login (API instance already created above)
        $wp_user_id = get_current_user_id();

        // Always get the encrypted password after user creation/management
        $encrypted_password = get_user_meta($wp_user_id, 'rocket_chat_password', true);
        if (!$encrypted_password) {
            rocket_chat_log("Widget: No encrypted password found for user: {$current_user->user_login} after management", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Error: No password found after user management.</p>';
                echo $debug_output;
            }
            
            echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<span style="font-size: 18px; margin-right: 8px;">🔐</span>';
            echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Authentication Setup Issue</h4>';
            echo '</div>';
            echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">There was an issue setting up your chat authentication.</p>';
            echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>';
            echo '</div>';
            return;
        }
        rocket_chat_log("Widget: Found encrypted password for user: {$current_user->user_login}", 'info');

        // Login as the Rocket.Chat user using the encrypted password from user meta
        $login_success = false;
        $login_token = null;
        if ($encrypted_password) {
            rocket_chat_log("Widget: Attempting to log in as user: {$current_user->user_login}", 'info');
            $login_success = $api->login_as_user($current_user->user_login, $encrypted_password);
            if ($login_success) {
                $login_token = $api->get_user_auth_token();
            }
        } else {
            rocket_chat_log("Widget: No encrypted password found for user: {$current_user->user_login}", 'error');
        }
        
        // Extra debug output for login result
        rocket_chat_log("Widget: login_success value: " . var_export($login_success, true), 'info');
        rocket_chat_log("Widget: login_token value: " . var_export($login_token, true), 'info');
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p><strong>login_success:</strong> ' . esc_html(var_export($login_success, true)) . '</p>';
            $debug_output .= '<p><strong>login_token:</strong> ' . esc_html(var_export($login_token, true)) . '</p>';
        }
        
        if (!$login_success || !$login_token) {
            rocket_chat_log("Widget: Failed to log in as user: {$current_user->user_login}", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Error: Unable to log in as chat user.</p>';
                echo $debug_output;
            }
            
            echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<span style="font-size: 18px; margin-right: 8px;">🔐</span>';
            echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Chat Login Issue</h4>';
            echo '</div>';
            echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">There was an issue logging you into the chat system.</p>';
            echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">This is usually temporary. Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>';
            echo '</div>';
            return;
        }
        rocket_chat_log("Widget: Successfully logged in as user: {$current_user->user_login}", 'info');

        // Use the login token from login_as_user
        $iframe_id = 'rocket-chat-' . uniqid();

        // Build the iframe HTML with authentication script and loading state
        $iframe_url = esc_url($host);
        if ($interface_mode === 'full') {
            $iframe_url .= '/home?layout=embedded';
        } else {
            // Use correct endpoint based on room type
            if ($room_type === 'group') {
                $iframe_url .= '/group/' . $channel . '?layout=embedded';
            } else {
                $iframe_url .= '/channel/' . $channel . '?layout=embedded';
            }
        }
        
        // Determine initial visibility based on stream integration setting
        $initial_display = '';
        $server_streams_live = false;
        if ($settings['check_stream_status'] === 'yes') {
            // NEW: Use the clean stream integration system
            if (function_exists('rocket_chat_stream_is_live')) {
                $server_streams_live = rocket_chat_stream_is_live();
                rocket_chat_log("💬 ELEMENTOR CHAT: Clean system check - streams live: " . ($server_streams_live ? 'true' : 'false'), 'info');
            } else {
                // Fallback: Direct WordPress option check if new system not available
                // FIX: Use the same option name that ant-media plugin updates
                $server_streams_live = get_option('amsa_streams_currently_live', false);
                rocket_chat_log("💬 ELEMENTOR CHAT: Fallback check - streams live: " . ($server_streams_live ? 'true' : 'false'), 'warning');
            }
            
            // Start hidden if no streams are live
            if (!$server_streams_live) {
                $initial_display = 'display: none; ';
                rocket_chat_log("💬 ELEMENTOR CHAT: No streams live - hiding widget initially", 'info');
            } else {
                rocket_chat_log("💬 ELEMENTOR CHAT: Streams are live - showing widget", 'info');
            }
        }
        
        // Check if user is logged in OR if streams are live (for public viewing)
        if (!is_user_logged_in()) {
            // Allow anonymous access when streams are live for public viewing
            $server_streams_live = function_exists('rocket_chat_stream_is_live') ? rocket_chat_stream_is_live() : false;
            
            if ($server_streams_live) {
                rocket_chat_log("Widget: Anonymous access granted - stream is live", 'info');
                
                // Create simple iframe for anonymous users (read-only chat)
                $guest_iframe_url = $host . '/channel/' . $channel . '?layout=embedded';
                
                echo '<div id="' . esc_attr($iframe_id) . '-container" style="' . esc_attr($initial_display) . '">';
                echo '<iframe id="' . esc_attr($iframe_id) . '" src="' . esc_url($guest_iframe_url) . '" width="100%" height="' . esc_attr($height) . '" frameborder="0" allowfullscreen></iframe>';
                
                // Add stream integration for anonymous users
                if ($settings['check_stream_status'] === 'yes') {
                    echo '<script>
                    (function() {
                        const containerEl = document.getElementById("' . esc_js($iframe_id) . '-container");
                        if (!window.chatContainers) window.chatContainers = [];
                        window.chatContainers.push({
                            element: containerEl,
                            id: "' . esc_js($iframe_id) . '-anonymous",
                            show: function(reason) { 
                                const timestamp = new Date().toISOString();
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Status changed to VISIBLE");
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Triggered by: " + reason);
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Container ID: ' . esc_js($iframe_id) . '-container");
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Container exists: " + (containerEl ? "YES" : "NO"));
                                
                                if (containerEl) {
                                    containerEl.style.display = "block";
                                    console.log("💬 [" + timestamp + "] Anonymous Chat: ✅ SHOWING container element");
                                    console.log("💬 [" + timestamp + "] Anonymous Chat: container.style.display = " + containerEl.style.display);
                                } else {
                                    console.error("💬 [" + timestamp + "] Anonymous Chat: ❌ container element not found!");
                                }
                                
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Page visibility: " + document.visibilityState);
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Window focused: " + document.hasFocus());
                            },
                            hide: function(reason) { 
                                const timestamp = new Date().toISOString();
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Status changed to HIDDEN");
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Triggered by: " + reason);
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Container ID: ' . esc_js($iframe_id) . '-container");
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Container exists: " + (containerEl ? "YES" : "NO"));
                                
                                if (containerEl) {
                                    containerEl.style.display = "none";
                                    console.log("💬 [" + timestamp + "] Anonymous Chat: ✅ HIDING container element");
                                    console.log("💬 [" + timestamp + "] Anonymous Chat: container.style.display = " + containerEl.style.display);
                                } else {
                                    console.error("💬 [" + timestamp + "] Anonymous Chat: ❌ container element not found!");
                                }
                                
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Page visibility: " + document.visibilityState);
                                console.log("💬 [" + timestamp + "] Anonymous Chat: Window focused: " + document.hasFocus());
                            }
                        });
                        
                        console.log("💬 Anonymous Chat: Real-time updates initialized for ' . esc_js($iframe_id) . '");
                        console.log("💬 Anonymous Chat: Initial state - container display: " + (containerEl ? containerEl.style.display : "NOT_FOUND"));
                        console.log("💬 Anonymous Chat: Initial state - server_streams_live: ' . ($server_streams_live ? 'true' : 'false') . '");
                    })();
                    </script>';
                }
                
                echo '</div>';
                rocket_chat_log("Widget: Anonymous chat rendered for live stream", 'info');
                return;
            } else {
                rocket_chat_log("Widget render attempted by non-logged-in user", 'info');
                return; // No chat when offline and not logged in
            }
        }

        // Validate channel access before loading iframe
        $api = get_rocket_chat_api();
        $channel_accessible = false;
        $room_type = 'channel'; // default
        
        // Check if the user can access the requested channel
        if ($channel === 'general') {
            $channel_accessible = true; // Everyone can access general
        } else {
            // Check if the channel exists and user has access
            $channel_info = $api->get_channel_info($channel);
            if ($channel_info) {
                $channel_accessible = true;
                $room_type = $channel_info['room_type'];
                rocket_chat_log("Widget: Channel '$channel' is accessible as room type: $room_type", 'info');
            } else {
                rocket_chat_log("Widget: Channel '$channel' not accessible, falling back to general", 'warning');
                $channel = 'general'; // Fallback to general
                $channel_accessible = true;
                $room_type = 'channel';
            }
        }
        
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p><strong>Channel Access:</strong> ' . ($channel_accessible ? 'ALLOWED' : 'DENIED') . '</p>';
            $debug_output .= '<p><strong>Final Channel:</strong> ' . esc_html($channel) . '</p>';
            $debug_output .= '<p><strong>Room Type:</strong> ' . esc_html($room_type) . '</p>';
        }

        // Get user details for login (API instance already created above)
        $wp_user_id = get_current_user_id();

        // Always get the encrypted password after user creation/management
        $encrypted_password = get_user_meta($wp_user_id, 'rocket_chat_password', true);
        if (!$encrypted_password) {
            rocket_chat_log("Widget: No encrypted password found for user: {$current_user->user_login} after management", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Error: No password found after user management.</p>';
                echo $debug_output;
            }
            
            echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<span style="font-size: 18px; margin-right: 8px;">🔐</span>';
            echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Authentication Setup Issue</h4>';
            echo '</div>';
            echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">There was an issue setting up your chat authentication.</p>';
            echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>';
            echo '</div>';
            return;
        }
        rocket_chat_log("Widget: Found encrypted password for user: {$current_user->user_login}", 'info');

        // Login as the Rocket.Chat user using the encrypted password from user meta
        $login_success = false;
        $login_token = null;
        if ($encrypted_password) {
            rocket_chat_log("Widget: Attempting to log in as user: {$current_user->user_login}", 'info');
            $login_success = $api->login_as_user($current_user->user_login, $encrypted_password);
            if ($login_success) {
                $login_token = $api->get_user_auth_token();
            }
        } else {
            rocket_chat_log("Widget: No encrypted password found for user: {$current_user->user_login}", 'error');
        }
        
        // Extra debug output for login result
        rocket_chat_log("Widget: login_success value: " . var_export($login_success, true), 'info');
        rocket_chat_log("Widget: login_token value: " . var_export($login_token, true), 'info');
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p><strong>login_success:</strong> ' . esc_html(var_export($login_success, true)) . '</p>';
            $debug_output .= '<p><strong>login_token:</strong> ' . esc_html(var_export($login_token, true)) . '</p>';
        }
        
        if (!$login_success || !$login_token) {
            rocket_chat_log("Widget: Failed to log in as user: {$current_user->user_login}", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Error: Unable to log in as chat user.</p>';
                echo $debug_output;
            }
            
            echo '<div class="rocket-chat-error" style="background: #fff2f2; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; color: #721c24; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px;">';
            echo '<span style="font-size: 18px; margin-right: 8px;">🔐</span>';
            echo '<h4 style="margin: 0; font-size: 16px; font-weight: 600;">Chat Login Issue</h4>';
            echo '</div>';
            echo '<p style="margin: 0 0 12px 0; line-height: 1.5;">There was an issue logging you into the chat system.</p>';
            echo '<p style="margin: 0; line-height: 1.5; font-weight: 500;">This is usually temporary. Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.</p>';
            echo '</div>';
            return;
        }
        rocket_chat_log("Widget: Successfully logged in as user: {$current_user->user_login}", 'info');

        // Use the login token from login_as_user
        $iframe_id = 'rocket-chat-' . uniqid();

        // Build the iframe HTML with authentication script and loading state
        $iframe_url = esc_url($host);
        if ($interface_mode === 'full') {
            $iframe_url .= '/home?layout=embedded';
        } else {
            // Use correct endpoint based on room type
            if ($room_type === 'group') {
                $iframe_url .= '/group/' . $channel . '?layout=embedded';
            } else {
                $iframe_url .= '/channel/' . $channel . '?layout=embedded';
            }
        }
        
        // Determine initial visibility based on stream integration setting
        $initial_display = '';
        $server_streams_live = false;
        if ($settings['check_stream_status'] === 'yes') {
            // NEW: Use the clean stream integration system
            if (function_exists('rocket_chat_stream_is_live')) {
                $server_streams_live = rocket_chat_stream_is_live();
                rocket_chat_log("💬 ELEMENTOR CHAT: Clean system check - streams live: " . ($server_streams_live ? 'true' : 'false'), 'info');
            } else {
                // Fallback: Direct WordPress option check if new system not available
                // FIX: Use the same option name that ant-media plugin updates
                $server_streams_live = get_option('amsa_streams_currently_live', false);
                rocket_chat_log("💬 ELEMENTOR CHAT: Fallback check - streams live: " . ($server_streams_live ? 'true' : 'false'), 'warning');
            }
            
            // Start hidden if no streams are live
            if (!$server_streams_live) {
                $initial_display = 'display: none; ';
                rocket_chat_log("💬 ELEMENTOR CHAT: No streams live - hiding widget initially", 'info');
            } else {
                rocket_chat_log("💬 ELEMENTOR CHAT: Streams are live - showing widget", 'info');
            }
        }
        
        $html = sprintf(<<<HTML
<div class="rocket-chat-container" id="%s-container" style="%swidth: %s; height: %s !important; min-height: %s !important;">
    <iframe id="%s" class="rocket-chat-iframe loaded" src="%s" width="100%%" height="%s" style="height: %s !important; min-height: %s !important; width: 100%% !important;" frameborder="0"></iframe>
    <script>
        var loginTokenSent = false;
        var iframeId = "%s";
        var hostUrl = "%s";
        var loginToken = "%s";
        var preferredChannel = "%s";
        var interfaceMode = "%s";
        var roomType = "%s";
        
        function sendLoginToken() {
            if (loginTokenSent) return;
            
            var iframe = document.getElementById(iframeId);
            if (iframe && iframe.contentWindow) {
                console.log("Sending login token to Rocket.Chat iframe");
                iframe.contentWindow.postMessage({
                    externalCommand: "login-with-token",
                    token: loginToken
                }, hostUrl);
                loginTokenSent = true;
                
                // After login, navigate to preferred channel if in full interface mode and channel is specified
                if (interfaceMode === "full" && preferredChannel && preferredChannel !== "general") {
                    setTimeout(function() {
                        var navigationPath = roomType === "group" ? "/group/" + preferredChannel : "/channel/" + preferredChannel;
                        console.log("Navigating to preferred room:", preferredChannel, "using path:", navigationPath);
                        iframe.contentWindow.postMessage({
                            externalCommand: "go",
                            path: navigationPath
                        }, hostUrl);
                    }, 2000);
                }
            } else {
                console.error("Iframe not found or not ready:", iframeId);
            }
        }
        
        window.addEventListener("message", function(e) {
            console.log("Widget received message:", e.origin, e.data);
            if (e.origin === hostUrl) {
                if (e.data.eventName === "startup") {
                    setTimeout(sendLoginToken, 500);
                } else if (e.data.eventName === "Custom_Script_Logged_In") {
                    // User is logged in, navigate to preferred channel if in full interface mode
                    if (interfaceMode === "full" && preferredChannel && preferredChannel !== "general") {
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
            }
        });
        
        // Fallback: Send login token after iframe loads
        document.getElementById(iframeId).addEventListener('load', function() {
            console.log("Iframe loaded, sending login token as fallback");
            setTimeout(sendLoginToken, 1000);
        });
    </script>
</div>
HTML,
    $iframe_id,      // container id
    esc_attr($initial_display), // initial display style
    $width,          // width
    $height,         // height 1
    $height,         // height 2 (min-height)
    $iframe_id,      // iframe id
    $iframe_url,     // iframe URL
    $height,         // height 3
    $height,         // height 4
    $height,         // height 5
    $iframe_id,      // iframe id for script
    esc_url($host),  // host URL for script
    esc_js($login_token), // token
    esc_js($channel), // preferred channel for script
    esc_js($interface_mode), // interface mode for script
    esc_js($room_type) // room type for script
);

        // Add stream integration if enabled
        if ($settings['check_stream_status'] === 'yes' && function_exists('should_display_ant_media_stream')) {
            $html .= sprintf('
            <script>
            (function() {
                const containerEl = document.getElementById("%s-container");
                
                function showChat(reason) {
                    const timestamp = new Date().toISOString();
                    
                    console.log("💬 Chat: SHOWING - " + reason);
                    
                    if (containerEl && containerEl.style.display === "none") {
                        containerEl.style.display = "block";
                    }
                }
                
                function hideChat(reason) {
                    const timestamp = new Date().toISOString();
                    
                    console.log("💬 Chat: HIDING - " + reason);
                    
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
                    id: "%s-elementor",
                    show: showChat,
                    hide: hideChat
                });
                
                console.log("💬 Logged-in Chat: Real-time updates initialized for " + "%s");
                console.log("💬 Logged-in Chat: Initial state - container display: " + (containerEl ? containerEl.style.display : "NOT_FOUND"));
                console.log("💬 Logged-in Chat: Initial state - server_streams_live: ' . ($server_streams_live ? 'true' : 'false') . '");
                
                // Initial status check - use WordPress Heartbeat data, NO iframe visibility checks
                setTimeout(function() {
                    console.log("💬 WordPress Heartbeat: Checking initial Elementor chat visibility from server data");
                    
                    // Use server-side determined status - no AJAX calls, no iframe visibility checks
                    // This relies on WordPress Heartbeat system that updates every 5 seconds
                    // and WebSocket for instant updates when available
                    var serverStreamStatus = ' . ($server_streams_live ? 'true' : 'false') . ';
                    
                    console.log("💬 WordPress Heartbeat: Elementor server detected streams live:", serverStreamStatus);
                    
                    if (serverStreamStatus) {
                        showChat("initial check: server confirmed streams live");
                    } else {
                        hideChat("initial check: server confirmed no streams");
                    }
                }, 1000);
                
                // Listen for the WordPress Heartbeat stream status events
                jQuery(document).on("amsa-stream-status-update", function(event, statusData) {
                    console.log("💬 WordPress Heartbeat: Elementor chat received status update", statusData);
                    if (statusData && typeof statusData.any_live !== "undefined") {
                        if (statusData.any_live) {
                            showChat("WordPress Heartbeat event: streams live");
                        } else {
                            hideChat("WordPress Heartbeat event: no streams");
                        }
                    }
                });
                
                // Listen for real-time WebSocket events (instant updates)
                document.addEventListener("amsaStreamStatusChanged", function(event) {
                    console.log("💬 WebSocket: Elementor chat received real-time status change", event.detail);
                    if (event.detail && typeof event.detail.isLive !== "undefined") {
                        if (event.detail.isLive) {
                            showChat("WebSocket event: stream started");
                        } else {
                            hideChat("WebSocket event: stream ended");
                        }
                    }
                });
            })();
            </script>',
            esc_js($iframe_id),  // for getElementById("%s-container") 
            esc_js($iframe_id),  // for showChat Container ID: "%s-container"
            esc_js($iframe_id),  // for hideChat Container ID: "%s-container"  
            esc_js($iframe_id),  // for id: "%s-elementor"
            esc_js($iframe_id),  // for initialized for "%s"
            esc_js($iframe_id)   // for any other %s placeholder
            );
        }

        if ($settings['show_debug'] === 'yes') {
            echo $debug_output;
        }
        echo $html;
        if ($settings['show_debug'] === 'yes') {
            echo '</div>';
        }
        rocket_chat_log("Widget: Successfully rendered chat for user: {$current_user->user_login}", 'info');

        // Add JavaScript debug output for login status
        echo '<script>';
        echo 'console.log("🔍 Rocket Chat Login Debug:");';
        echo 'console.log("WordPress Login Status:", ' . ($is_logged_in ? 'true' : 'false') . ');';
        if ($current_user && $current_user->ID) {
            echo 'console.log("User ID:", ' . $current_user->ID . ');';
            echo 'console.log("Username:", "' . esc_js($current_user->user_login) . '");';
            echo 'console.log("Email:", "' . esc_js($current_user->user_email ?: 'NO EMAIL') . '");';
            echo 'console.log("Display Name:", "' . esc_js($current_user->display_name) . '");';
            
            $rocket_chat_user = get_user_meta($current_user->ID, 'rocket_chat_user_id', true);
            $rocket_chat_password = get_user_meta($current_user->ID, 'rocket_chat_encrypted_password', true);
            echo 'console.log("Rocket Chat User ID:", "' . esc_js($rocket_chat_user ?: 'NOT SET') . '");';
            echo 'console.log("Rocket Chat Password:", "' . ($rocket_chat_password ? 'SET' : 'NOT SET') . '");';
        }
        echo 'console.log("Anonymous access path would be taken:", ' . (!$is_logged_in ? 'true' : 'false') . ');';
        echo '</script>';
    }
}
Plugin::instance()->widgets_manager->register_widget_type(new Rocket_Chat_Embed_Widget());
