<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_options_page(
        'Rocket Chat Widget Settings',
        'Rocket Chat Widget',
        'manage_options',
        'rocket-chat-embed',
        'rocket_chat_embed_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('rocket_chat_embed_options', 'rocket_chat_host_url', [
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => '',
    ]);
    
    register_setting('rocket_chat_embed_options', 'rocket_chat_admin_user', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);
    
    register_setting('rocket_chat_embed_options', 'rocket_chat_admin_pass', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);
    
    register_setting('rocket_chat_embed_options', 'rocket_chat_default_channel', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'general',
    ]);
    
    register_setting('rocket_chat_embed_options', 'rocket_chat_debug_mode', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false,
    ]);
    
    register_setting('rocket_chat_embed_options', 'chat_open', [
        'type' => 'string',
        'sanitize_callback' => function($value) {
            $old_value = get_option('chat_open', 'true');
            $new_value = $value === '1' ? 'true' : 'false';
            
            // Trigger the state change action if value changed
            if ($old_value !== $new_value) {
                do_action('rocket_chat_state_changed', $new_value === 'true');
            }
            
            return $new_value;
        },
        'default' => 'true',
    ]);
});

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'settings_page_rocket-chat-embed') {
        return;
    }
    
    wp_enqueue_script('rce-admin-js', RCE_PLUGIN_URL . 'assets/admin.js', ['jquery'], RCE_VERSION, true);
    wp_localize_script('rce-admin-js', 'rceAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'license_nonce' => wp_create_nonce('rce_license_nonce')
    ]);
    
    wp_add_inline_style('wp-admin', '
        .rce-license-section { border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; background: #fff; }
        .rce-license-status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .rce-license-status.active { background: #d1eddd; border-left: 4px solid #00a32a; }
        .rce-license-status.invalid { background: #fcf2f2; border-left: 4px solid #d63638; }
        .rce-license-status.free { background: #e5f5ff; border-left: 4px solid #0073aa; }
        .rce-upgrade-notice { background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .rce-premium-badge { background: #ff6b35; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px; }
    ');
});

// Main settings page function
function rocket_chat_embed_settings_page() {
    if (isset($_POST['submit'])) {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'rocket_chat_embed_settings')) {
            wp_die('Security check failed');
        }

        // Save basic settings
        update_option('rocket_chat_host_url', sanitize_url($_POST['rocket_chat_host_url']));
        update_option('rocket_chat_admin_user', sanitize_text_field($_POST['rocket_chat_admin_user']));
        update_option('rocket_chat_admin_pass', sanitize_text_field($_POST['rocket_chat_admin_pass']));
        update_option('rocket_chat_default_channel', sanitize_text_field($_POST['rocket_chat_default_channel']));
        update_option('rocket_chat_debug_mode', isset($_POST['rocket_chat_debug_mode']));
        update_option('chat_open', sanitize_text_field($_POST['chat_open']));
        
        // Premium settings (only save if premium is active)
        if (rce_is_premium_active()) {
            update_option('rocket_chat_sso_enabled', isset($_POST['rocket_chat_sso_enabled']));
            update_option('rocket_chat_auto_login', isset($_POST['rocket_chat_auto_login']));
            update_option('rocket_chat_custom_css', sanitize_textarea_field($_POST['rocket_chat_custom_css']));
        }

        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }

    // Get current values
    $host_url = get_option('rocket_chat_host_url', '');
    $admin_user = get_option('rocket_chat_admin_user', '');
    $admin_pass = get_option('rocket_chat_admin_pass', '');
    $default_channel = get_option('rocket_chat_default_channel', 'general');
    $debug_mode = get_option('rocket_chat_debug_mode', false);
    $chat_open = get_option('chat_open', 'true');
    $sso_enabled = get_option('rocket_chat_sso_enabled', false);
    $auto_login = get_option('rocket_chat_auto_login', false);
    $custom_css = get_option('rocket_chat_custom_css', '');
    
    $license_status = rce_get_license_status_message();
    $is_premium = rce_is_premium_active();
    ?>
    <div class="wrap">
        <h1>Rocket.Chat Embed Settings</h1>
        
        <!-- License Section -->
        <div class="rce-license-section">
            <h2>License Management</h2>
            <div class="rce-license-status <?php echo $license_status['status']; ?>">
                <strong>Status:</strong> <?php echo $license_status['message']; ?>
            </div>
            
            <?php if (!$is_premium): ?>
            <p>Enter your license key to unlock premium features:</p>
            <table class="form-table">
                <tr>
                    <th scope="row">License Key</th>
                    <td>
                        <input type="text" id="rce_license_key" class="regular-text" placeholder="Enter your license key..." />
                        <button type="button" id="rce_activate_license" class="button button-primary">Activate License</button>
                        <p class="description">Get your license key from <a href="https://your-store.lemonsqueezy.com" target="_blank">your purchase receipt</a></p>
                    </td>
                </tr>
            </table>
            
            <h3>Premium Features Include:</h3>
            <ul>
                <li>✨ SSO Integration with WordPress users</li>
                <li>✨ Advanced user synchronization</li>
                <li>✨ Custom CSS styling</li>
                <li>✨ Auto-login functionality</li>
                <li>✨ Priority support</li>
            </ul>
            <?php else: ?>
            <p><strong>Premium features are active!</strong> Thank you for your purchase.</p>
            <?php endif; ?>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('rocket_chat_embed_settings'); ?>
            
            <h2>Basic Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rocket_chat_host_url">Rocket.Chat Server URL</label>
                    </th>
                    <td>
                        <input name="rocket_chat_host_url" type="url" id="rocket_chat_host_url" 
                               value="<?php echo esc_attr($host_url); ?>" class="regular-text" required />
                        <p class="description">Full URL to your Rocket.Chat instance (e.g., https://chat.example.com)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rocket_chat_admin_user">Admin Username</label>
                    </th>
                    <td>
                        <input name="rocket_chat_admin_user" type="text" id="rocket_chat_admin_user" 
                               value="<?php echo esc_attr($admin_user); ?>" class="regular-text" required />
                        <p class="description">Admin username for API access</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rocket_chat_admin_pass">Admin Password</label>
                    </th>
                    <td>
                        <input name="rocket_chat_admin_pass" type="password" id="rocket_chat_admin_pass" 
                               value="<?php echo esc_attr($admin_pass); ?>" class="regular-text" required />
                        <p class="description">Admin password for API access</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rocket_chat_default_channel">Default Channel</label>
                    </th>
                    <td>
                        <input name="rocket_chat_default_channel" type="text" id="rocket_chat_default_channel" 
                               value="<?php echo esc_attr($default_channel); ?>" class="regular-text" />
                        <p class="description">Default channel name (without #)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Chat State</th>
                    <td>
                        <label>
                            <input name="chat_open" type="radio" value="true" <?php checked($chat_open, 'true'); ?> />
                            Open
                        </label><br />
                        <label>
                            <input name="chat_open" type="radio" value="false" <?php checked($chat_open, 'false'); ?> />
                            Closed
                        </label>
                        <p class="description">Control chat widget visibility</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Debug Mode</th>
                    <td>
                        <label>
                            <input name="rocket_chat_debug_mode" type="checkbox" value="1" <?php checked($debug_mode); ?> />
                            Enable debug logging
                        </label>
                    </td>
                </tr>
            </table>

            <?php if ($is_premium): ?>
            <h2>Premium Features <span class="rce-premium-badge">PREMIUM</span></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">SSO Integration</th>
                    <td>
                        <label>
                            <input name="rocket_chat_sso_enabled" type="checkbox" value="1" <?php checked($sso_enabled); ?> />
                            Enable Single Sign-On with WordPress users
                        </label>
                        <p class="description">Automatically log WordPress users into Rocket.Chat</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Auto Login</th>
                    <td>
                        <label>
                            <input name="rocket_chat_auto_login" type="checkbox" value="1" <?php checked($auto_login); ?> />
                            Auto-login WordPress users
                        </label>
                        <p class="description">Skip login prompt for logged-in WordPress users</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rocket_chat_custom_css">Custom CSS</label>
                    </th>
                    <td>
                        <textarea name="rocket_chat_custom_css" id="rocket_chat_custom_css" rows="10" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description">Add custom CSS to style the chat widget</p>
                    </td>
                </tr>
            </table>
            <?php else: ?>
            <div class="rce-upgrade-notice">
                <h3>🚀 Unlock Premium Features</h3>
                <p>Get SSO integration, custom styling, auto-login, and priority support!</p>
                <a href="https://your-site.com/upgrade" class="button button-primary" target="_blank">Upgrade Now</a>
            </div>
            <?php endif; ?>

            <?php submit_button(); ?>
        </form>

        <?php if ($debug_mode): ?>
        <h2>Debug Information</h2>
        <div style="background: #f1f1f1; padding: 15px; margin: 20px 0;">
            <h3>Recent Logs</h3>
            <?php
            $logs = get_transient('rocket_chat_logs');
            if ($logs && is_array($logs)) {
                echo '<pre>';
                foreach (array_slice($logs, -10) as $log) {
                    echo esc_html($log) . "\n";
                }
                echo '</pre>';
            } else {
                echo '<p>No logs available.</p>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#rce_activate_license').on('click', function() {
            var licenseKey = $('#rce_license_key').val();
            var button = $(this);
            
            if (!licenseKey) {
                alert('Please enter a license key');
                return;
            }
            
            button.prop('disabled', true).text('Activating...');
            
            $.post(rceAjax.ajax_url, {
                action: 'rce_activate_license',
                license_key: licenseKey,
                nonce: rceAjax.license_nonce
            }, function(response) {
                if (response.success) {
                    alert('License activated successfully!');
                    location.reload();
                } else {
                    alert('License activation failed: ' + response.data);
                }
            }).always(function() {
                button.prop('disabled', false).text('Activate License');
            });
        });
    });
    </script>
    <?php
}
