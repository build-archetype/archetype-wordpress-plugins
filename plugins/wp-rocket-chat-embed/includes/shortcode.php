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
    ], $atts);

    $host_url = get_option('rocket_chat_host_url');
    if (empty($host_url)) {
        return '<p>Rocket.Chat configuration is incomplete. Please configure in Settings → Rocket Chat Widget.</p>';
    }

    // Check license status
    $is_premium = rce_is_premium_active();
    
    // For free version, require login
    if (!$is_premium && !is_user_logged_in()) {
        return '<div class="rce-upgrade-notice" style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 4px;">
            <strong>Free Version:</strong> Please log in to access chat. Premium version supports guest access.
            <a href="' . wp_login_url(get_permalink()) . '">Log in here</a> or <a href="https://your-site.com/upgrade" target="_blank">upgrade to premium</a>.
        </div>';
    }

    $channel = sanitize_text_field($atts['channel']);
    $width = sanitize_text_field($atts['width']);
    $height = sanitize_text_field($atts['height']);
    $style = sanitize_text_field($atts['style']);

    // Premium features
    $iframe_params = [];
    
    if ($is_premium) {
        // Premium: Add SSO and auto-login if enabled and user is logged in
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
    } else {
        // Free version: Basic user info if logged in
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $iframe_params['username'] = $current_user->user_login;
        }
    }

    // Build iframe URL
    $iframe_url = rtrim($host_url, '/') . '/channel/' . $channel;
    if (!empty($iframe_params)) {
        $iframe_url .= '?' . http_build_query($iframe_params);
    }

    // Premium custom CSS
    $custom_css = '';
    if ($is_premium) {
        $custom_css_option = get_option('rocket_chat_custom_css', '');
        if (!empty($custom_css_option)) {
            $custom_css = '<style>' . wp_strip_all_tags($custom_css_option) . '</style>';
        }
    }

    // Free version upgrade notice
    $upgrade_notice = '';
    if (!$is_premium) {
        $upgrade_notice = '<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 8px 12px; margin-bottom: 10px; font-size: 13px;">
            <strong>Free Version Active</strong> - Upgrade to unlock SSO, custom styling, auto-login, and guest access.
            <a href="https://your-site.com/upgrade" target="_blank" style="color: #0073aa; text-decoration: none; font-weight: bold;">Upgrade Now →</a>
        </div>';
    }

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
        '%s%s%s
        <div class="rce-container" style="width: %s; height: %s; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; %s">
            <div class="rce-loading">
                <div class="rce-spinner"></div>
            </div>
            <iframe id="%s" class="rce-iframe" src="%s" width="100%%" height="100%%" frameborder="0" title="Rocket.Chat" onload="this.classList.add(\'loaded\'); this.parentNode.querySelector(\'.rce-loading\').style.display=\'none\'"></iframe>
        </div>',
        $loading_css,
        $custom_css,
        $upgrade_notice,
        esc_attr($width),
        esc_attr($height),
        esc_attr($style),
        esc_attr($iframe_id),
        esc_url($iframe_url)
    );

    // Fire action hook for tracking
    do_action('rocket_chat_iframe_displayed', [
        'channel' => $channel,
        'user_logged_in' => is_user_logged_in(),
        'is_premium' => $is_premium
    ]);

    return $iframe_html;
});
