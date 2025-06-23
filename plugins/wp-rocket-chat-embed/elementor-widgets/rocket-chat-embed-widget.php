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
        $this->end_controls_section();
    }
    protected function render() {
        if (!is_user_logged_in()) {
            rocket_chat_log("Widget render attempted by non-logged-in user", 'info');
            echo '<p>Please log in to access the chat.</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
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

        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<p>Rocket.Chat Host: ' . esc_html($host) . '</p>';
            $debug_output .= '<p>Channel: ' . esc_html($channel) . '</p>';
        }

        // Get or create Rocket.Chat user
        $api = get_rocket_chat_api();
        $wp_user_id = get_current_user_id();
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<h4>Checking User in Rocket.Chat</h4>';
        }
        rocket_chat_log("Widget: Checking Rocket.Chat user for WordPress user: {$current_user->user_login}", 'info');
        $rc_user = $api->get_user($current_user->user_login);
        if ($settings['show_debug'] === 'yes') {
            $debug_output .= '<pre>' . esc_html(print_r($rc_user, true)) . '</pre>';
        }

        // Robust: If user does not exist, create them
        if (!$rc_user || (isset($rc_user['success']) && !$rc_user['success'])) {
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<h4>Creating User in Rocket.Chat</h4>';
            }
            rocket_chat_log("Widget: Creating Rocket.Chat user for WordPress user: {$current_user->user_login}", 'info');
            $create_result = $api->create_user(
                $current_user->user_login,
                $current_user->user_email,
                $current_user->display_name,
                $wp_user_id
            );
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<h4>Rocket.Chat API Create User Response</h4>';
                $debug_output .= '<pre>' . esc_html(print_r($create_result, true)) . '</pre>';
            }
            if (!$create_result || (isset($create_result['success']) && !$create_result['success'])) {
                rocket_chat_log("Widget: Failed to create Rocket.Chat user for WordPress user: {$current_user->user_login}", 'error');
                if ($settings['show_debug'] === 'yes') {
                    echo $debug_output;
                }
                echo '<p style="color: red;">Error: Unable to create chat account. See debug info above.</p>';
                return;
            }
            rocket_chat_log("Widget: Successfully created Rocket.Chat user for WordPress user: {$current_user->user_login}", 'info');
        }

        // Always get the encrypted password after user creation
        $encrypted_password = get_user_meta($wp_user_id, 'rocket_chat_password', true);
        if (!$encrypted_password) {
            rocket_chat_log("Widget: No encrypted password found for user: {$current_user->user_login} after creation", 'error');
            if ($settings['show_debug'] === 'yes') {
                $debug_output .= '<p style="color: red;">Error: No password found after user creation.</p>';
                echo $debug_output;
            }
            echo '<p style="color: red;">Error: Unable to log in as chat user. No password found. See debug info above.</p>';
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
            echo '<p style="color: red;">Error: Unable to log in as chat user. See debug info above.</p>';
            return;
        }
        rocket_chat_log("Widget: Successfully logged in as user: {$current_user->user_login}", 'info');

        // Use the login token from login_as_user
        $iframe_id = 'rocket-chat-' . uniqid();

        // Build the iframe HTML with authentication script and loading state
        $html = sprintf(<<<HTML
<div class="rocket-chat-container" id="%s-container" style="width: %s; height: %s !important; min-height: %s !important;">
    <iframe id="%s" class="rocket-chat-iframe loaded" src="%s/channel/%s?layout=embedded" width="100%%" height="%s" style="height: %s !important; min-height: %s !important; width: 100%% !important;" frameborder="0"></iframe>
    <script>
        window.addEventListener("message", function(e) {
            console.log("Widget received message:", e.origin, e.data);
            if (e.origin === "%s") {
                if (e.data.eventName === "startup") {
                    console.log("Sending login-with-token to iframe (externalCommand)", "%s", "%s");
                    document.getElementById("%s").contentWindow.postMessage({
                        externalCommand: "login-with-token",
                        token: "%s"
                    }, "%s");
                }
            }
        });
    </script>
</div>
HTML,
    $iframe_id,
    $width,
    $height,
    $height,
    $iframe_id,
    esc_url($host),
    $channel,
    $height,
    $height,
    $height,
    esc_url($host),
    $iframe_id,
    esc_js($login_token),
    $iframe_id,
    esc_js($login_token),
    esc_url($host)
);

        if ($settings['show_debug'] === 'yes') {
            echo $debug_output;
        }
        echo $html;
        if ($settings['show_debug'] === 'yes') {
            echo '</div>';
        }
        rocket_chat_log("Widget: Successfully rendered chat for user: {$current_user->user_login}", 'info');
    }
}
Plugin::instance()->widgets_manager->register_widget_type(new Rocket_Chat_Embed_Widget());
