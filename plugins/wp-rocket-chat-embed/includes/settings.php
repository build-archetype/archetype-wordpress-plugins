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
        'license_nonce' => wp_create_nonce('rce_license_nonce'),
        'debug_nonce' => wp_create_nonce('rce_debug_nonce')
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
        
        // Premium settings (always available with unlimited license)
        update_option('rocket_chat_sso_enabled', isset($_POST['rocket_chat_sso_enabled']));
        update_option('rocket_chat_auto_login', isset($_POST['rocket_chat_auto_login']));
        update_option('rocket_chat_custom_css', sanitize_textarea_field($_POST['rocket_chat_custom_css']));

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
    <div class="wrap" style="max-width: none;">
        <h1>Rocket.Chat Embed Settings</h1>
        
        <div class="notice notice-info">
            <p><strong>Basic Shortcode:</strong> Use <code>[rocket_chat]</code> to embed chat widgets.</p>
            <p><strong>Elementor Widget:</strong> Use the "Rocket Chat" widget for advanced layouts and combined stream+chat designs.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <!-- Configuration Form -->
            <div class="postbox" style="padding: 20px;">
                <h2 style="margin-top: 0;">Server Configuration</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('rocket_chat_embed_settings'); ?>
                    
                    <table class="form-table" style="margin: 0;">
                        <tr>
                            <th scope="row">
                                <label for="rocket_chat_host_url">Server URL</label>
                            </th>
                            <td>
                                <input name="rocket_chat_host_url" type="url" id="rocket_chat_host_url" 
                                       value="<?php echo esc_attr($host_url); ?>" class="regular-text" required />
                                <p class="description">Full URL to your Rocket.Chat instance</p>
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
                    
                    <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                </form>
            </div>
            
            <!-- System Status -->
            <div class="postbox" style="padding: 20px;">
                <h2 style="margin-top: 0;">System Status</h2>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <td><strong>Server URL:</strong></td>
                        <td><?php echo $host_url ? '✅ ' . esc_html($host_url) : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Admin User:</strong></td>
                        <td><?php echo $admin_user ? '✅ ' . esc_html($admin_user) : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Chat State:</strong></td>
                        <td><?php echo $chat_open === 'true' ? '✅ Open' : '⚪ Closed'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Debug Mode:</strong></td>
                        <td><?php echo $debug_mode ? '✅ Enabled' : '❌ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>License:</strong></td>
                        <td>
                            <span class="rce-license-status <?php echo $license_status['status']; ?>" style="padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                <?php 
                                echo $license_status['status'] === 'active' ? '✅ Premium Active' : 
                                    ($license_status['status'] === 'free' ? '🆓 Free Access' : '⚪ Basic');
                                ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Integration:</strong></td>
                        <td><?php echo function_exists('should_display_ant_media_stream') ? '✅ Ant Media Integration' : '⚪ Standalone Mode'; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- License Management Section -->
        <div class="postbox" style="padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-top: 0;">License Management</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <div class="rce-license-status <?php echo $license_status['status']; ?>" style="padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <strong>Current Status:</strong> <?php echo $license_status['message']; ?>
                    </div>
                    
                    <h3>🎉 Unlimited License Active</h3>
                    <p><strong>Congratulations!</strong> You have unlimited access to all premium features with this plugin. No license key required!</p>
                    <p>All premium features are permanently unlocked and ready to use.</p>
                </div>
                
                <div>
                    <h3>💎 Premium Features (All Unlocked)</h3>
                    <ul style="list-style-type: none; padding: 0;">
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            ✅ <strong>SSO Integration</strong> - WordPress user sync
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            ✅ <strong>Auto-login</strong> - Seamless authentication  
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            ✅ <strong>Custom CSS</strong> - Complete styling control
                        </li>
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                            ✅ <strong>Advanced Analytics</strong> - Usage tracking
                        </li>
                        <li style="padding: 8px 0;">
                            ✅ <strong>Priority Support</strong> - Direct assistance
                        </li>
                    </ul>
                    
                    <p style="margin-top: 20px; color: #0073aa;">
                        <strong>🚀 All features are active and ready to use!</strong>
                    </p>
                </div>
            </div>
        </div>

        <!-- Premium Settings -->
        <div class="postbox" style="padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-top: 0;">Premium Settings <span style="background: #ff6b35; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px;">UNLIMITED ACCESS</span></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('rocket_chat_embed_settings'); ?>
                
                <!-- Copy basic settings as hidden fields to preserve them -->
                <input type="hidden" name="rocket_chat_host_url" value="<?php echo esc_attr($host_url); ?>">
                <input type="hidden" name="rocket_chat_admin_user" value="<?php echo esc_attr($admin_user); ?>">
                <input type="hidden" name="rocket_chat_admin_pass" value="<?php echo esc_attr($admin_pass); ?>">
                <input type="hidden" name="rocket_chat_default_channel" value="<?php echo esc_attr($default_channel); ?>">
                <input type="hidden" name="chat_open" value="<?php echo esc_attr($chat_open); ?>">
                <?php if ($debug_mode): ?><input type="hidden" name="rocket_chat_debug_mode" value="1"><?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h3>Authentication Settings</h3>
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th scope="row">Default Channel</th>
                                <td>
                                    <input name="rocket_chat_default_channel" type="text" value="<?php echo esc_attr($default_channel); ?>" class="regular-text" />
                                    <p class="description">Default channel name (without #)</p>
                                </td>
                            </tr>
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
                        </table>
                    </div>
                    
                    <div>
                        <h3>Custom Styling</h3>
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th scope="row">
                                    <label for="rocket_chat_custom_css">Custom CSS</label>
                                </th>
                                <td>
                                    <textarea name="rocket_chat_custom_css" id="rocket_chat_custom_css" rows="8" class="large-text code" style="font-family: 'Consolas', 'Monaco', monospace;"><?php echo esc_textarea($custom_css); ?></textarea>
                                    <p class="description">Add custom CSS to style the chat widget</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button('Save Premium Settings', 'primary', 'submit', false); ?>
            </form>
        </div>

        <!-- Usage Examples & Integration -->
        <div class="postbox" style="padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-top: 0;">Usage Examples & Integration</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h3>Shortcode Examples</h3>
                    
                    <h4>🗨️ Basic Chat Widget</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[rocket_chat]</code>
                    <p><em>Embeds chat widget with default settings</em></p>
                    
                    <h4>🎨 Customized Widget</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[rocket_chat width="100%" height="600px" channel="support"]</code>
                    
                    <h4>🔄 Auto-Tier Channel Assignment</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[rocket_chat auto_tier="true"]</code>
                    <p><em>Automatically assigns users to their highest tier channel (platinum > gold > silver)</em></p>
                    
                    <h4>👥 Full Interface (All Channels)</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[rocket_chat show_sidebar="true"]</code>
                    <p><em>Shows full Rocket.Chat interface with channel sidebar - users can see all their channels</em></p>
                    
                    <h4>🎯 Specific Channel Only</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[rocket_chat channel="silver" show_sidebar="false"]</code>
                    <p><em>Shows only the specified channel without sidebar</em></p>
                    
                    <h4>🔧 Debug Information</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[rocket_chat_debug]</code>
                    <p><em>Shows user's channel access, role mapping, and troubleshooting info</em></p>
                    
                    <h4>💬 Stream + Chat Integration</h4>
                    <p>When used with Ant Media Stream Access, chat automatically shows/hides based on stream status:</p>
                    <ul>
                        <li>✅ <strong>Stream Live:</strong> Chat widget appears</li>
                        <li>❌ <strong>Stream Offline:</strong> Chat widget hides</li>
                        <li>🔄 <strong>Real-time:</strong> Updates every 10 seconds</li>
                    </ul>
                    
                    <h4>🎯 Enhanced User Management</h4>
                    <p><strong>Automatic Features:</strong></p>
                    <ul>
                        <li>✅ <strong>New Users:</strong> Auto-created and joined to appropriate channels</li>
                        <li>✅ <strong>Existing Users:</strong> Auto-joined to new channels they access</li>
                        <li>✅ <strong>Role-Based Access:</strong> Users automatically joined based on WordPress roles</li>
                        <li>✅ <strong>Multi-Channel Support:</strong> Users can be in multiple channels simultaneously</li>
                        <li>✅ <strong>Interface Options:</strong> Choose between full interface (all channels) or specific channel view</li>
                        <li>✅ <strong>Smart Navigation:</strong> Auto-navigates to preferred channel in full interface mode</li>
                    </ul>
                    
                    <h4>🎯 Elementor Widgets</h4>
                    <p>Use the <strong>"Rocket Chat"</strong> or <strong>"Stream + Chat Combined"</strong> widgets for advanced layouts.</p>
                </div>
                
                <div>
                    <h3>Available Parameters</h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Parameter</th>
                                <th style="width: 25%;">Default</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>width</strong></td><td>100%</td><td>Widget width (px or %)</td></tr>
                            <tr><td><strong>height</strong></td><td>500px</td><td>Widget height</td></tr>
                            <tr><td><strong>channel</strong></td><td>general</td><td>Default channel to open</td></tr>
                            <tr><td><strong>auto_tier</strong></td><td>false</td><td>Auto-assign to user's highest tier channel</td></tr>
                            <tr><td><strong>check_stream_status</strong></td><td>true</td><td>Hide chat when no streams are live</td></tr>
                            <tr><td><strong>show_sidebar</strong></td><td>true</td><td>Show full interface with channel sidebar</td></tr>
                            <tr><td><strong>theme</strong></td><td>light</td><td>Chat theme (light/dark)</td></tr>
                            <tr><td><strong>layout</strong></td><td>embedded</td><td>Widget layout style</td></tr>
                            <tr><td><strong>show_header</strong></td><td>true</td><td>Show chat header</td></tr>
                            <tr><td><strong>auto_login</strong></td><td>false</td><td>Auto-login (Premium)</td></tr>
                        </tbody>
                    </table>
                    
                    <h3 style="margin-top: 20px;">🔧 WordPress Hooks</h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: 'Consolas', monospace; font-size: 12px;">
                        <strong>Filter chat display:</strong><br>
                        <code>rocket_chat_should_display</code><br><br>
                        <strong>Chat state changes:</strong><br>
                        <code>rocket_chat_state_changed</code>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($debug_mode): ?>
        <!-- Enhanced Debug Logs -->
        <div class="postbox" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Debug Logs</h2>
                <div>
                    <input type="text" id="logSearch" placeholder="Search logs..." style="margin-right: 10px; padding: 5px;">
                    <button type="button" class="button" onclick="refreshLogs()">🔄 Refresh</button>
                    <button type="button" class="button" onclick="copyLogs()">📋 Copy All</button>
                    <button type="button" class="button" onclick="clearRocketChatLogs()">🗑️ Clear</button>
                    <button type="button" class="button" onclick="debugChannels()">🔍 List Channels</button>
                    <button type="button" class="button" onclick="toggleAutoRefresh()">⏰ Auto-refresh</button>
                </div>
            </div>
            
            <div id="logContainer" style="background: #1e1e1e; color: #f8f8f2; padding: 20px; border-radius: 6px; max-height: 600px; overflow-y: auto; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.5;">
                <?php
                $logs = get_transient('rocket_chat_logs');
                
                if (empty($logs)) {
                    echo '<div style="color: #888; font-style: italic;">No logs available. Logs will appear here when debug mode is enabled and chat widgets are used.</div>';
                } else {
                    foreach (array_reverse($logs) as $log) {
                        $escaped_log = esc_html($log);
                        // Add syntax highlighting for different log levels
                        if (strpos($log, '[error]') !== false) {
                            echo '<div style="color: #ff6b6b;">' . $escaped_log . '</div>';
                        } elseif (strpos($log, '[warning]') !== false) {
                            echo '<div style="color: #ffa500;">' . $escaped_log . '</div>';
                        } elseif (strpos($log, '[info]') !== false) {
                            echo '<div style="color: #74c0fc;">' . $escaped_log . '</div>';
                        } elseif (strpos($log, '[debug]') !== false) {
                            echo '<div style="color: #51cf66;">' . $escaped_log . '</div>';
                        } else {
                            echo '<div>' . $escaped_log . '</div>';
                        }
                    }
                }
                ?>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 12px; color: #666;">
                <strong>💡 Log Tips:</strong>
                • Use search to filter logs 
                • Different colors indicate log levels (🔴 Error, 🟠 Warning, 🔵 Info, 🟢 Debug)
                • Auto-refresh updates every 10 seconds
                • Copy function includes timestamps for support
            </div>
        </div>
        
        <style>
        .highlighted {
            background-color: #fff3cd !important;
            color: #856404 !important;
        }
        
        #logContainer::-webkit-scrollbar {
            width: 8px;
        }
        
        #logContainer::-webkit-scrollbar-track {
            background: #2d2d2d;
        }
        
        #logContainer::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
        }
        
        #logContainer::-webkit-scrollbar-thumb:hover {
            background: #777;
        }
        
        .rce-license-status.active {
            background: #d1eddd;
            border-left: 4px solid #00a32a;
        }
        
        .rce-license-status.invalid {
            background: #fcf2f2;
            border-left: 4px solid #d63638;
        }
        
        .rce-license-status.free {
            background: #e5f5ff;
            border-left: 4px solid #0073aa;
        }
        </style>
        
        <script>
        let autoRefreshInterval = null;
        let isAutoRefreshing = false;
        
        // Search functionality
        document.getElementById('logSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const logContainer = document.getElementById('logContainer');
            const logLines = logContainer.querySelectorAll('div');
            
            logLines.forEach(line => {
                if (searchTerm === '') {
                    line.style.display = 'block';
                    line.classList.remove('highlighted');
                } else {
                    const text = line.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        line.style.display = 'block';
                        line.classList.add('highlighted');
                    } else {
                        line.style.display = 'none';
                        line.classList.remove('highlighted');
                    }
                }
            });
        });
        
        // Refresh logs
        function refreshLogs() {
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'rocket_chat_get_logs',
                    nonce: '<?php echo wp_create_nonce('rocket_chat_admin'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('logContainer').innerHTML = data.data.logs;
                    // Reapply search filter if active
                    const searchTerm = document.getElementById('logSearch').value;
                    if (searchTerm) {
                        document.getElementById('logSearch').dispatchEvent(new Event('input'));
                    }
                }
            })
            .catch(error => console.error('Error refreshing logs:', error));
        }
        
        // Copy logs to clipboard
        function copyLogs() {
            const logContainer = document.getElementById('logContainer');
            const visibleLogs = Array.from(logContainer.querySelectorAll('div:not([style*="display: none"])'))
                .map(div => div.textContent)
                .join('\n');
            
            navigator.clipboard.writeText(visibleLogs).then(() => {
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '✅ Copied!';
                button.style.background = '#46b450';
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy logs:', err);
                alert('Failed to copy logs to clipboard');
            });
        }
        
        // Clear logs
        function clearRocketChatLogs() {
            if (confirm('Clear all debug logs?')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'rocket_chat_clear_logs',
                        nonce: '<?php echo wp_create_nonce('rocket_chat_admin'); ?>'
                    })
                })
                .then(() => refreshLogs())
                .catch(error => console.error('Error:', error));
            }
        }
        
        // Debug channels listing
        function debugChannels() {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = '🔄 Listing...';
            button.disabled = true;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'rce_debug_channels',
                    nonce: '<?php echo wp_create_nonce('rce_debug_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = '✅ Listed!';
                    button.style.background = '#46b450';
                    // Refresh logs to show the new debug output
                    setTimeout(refreshLogs, 1000);
                } else {
                    button.textContent = '❌ Failed';
                    button.style.background = '#dc3232';
                    console.error('Debug channels failed:', data.data);
                }
            })
            .catch(error => {
                button.textContent = '❌ Error';
                button.style.background = '#dc3232';
                console.error('Error debugging channels:', error);
            })
            .finally(() => {
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                    button.disabled = false;
                }, 3000);
            });
        }
        
        // Toggle auto-refresh
        function toggleAutoRefresh() {
            const button = event.target;
            
            if (isAutoRefreshing) {
                clearInterval(autoRefreshInterval);
                isAutoRefreshing = false;
                button.textContent = '⏰ Auto-refresh';
                button.style.background = '';
            } else {
                autoRefreshInterval = setInterval(refreshLogs, 10000); // Every 10 seconds
                isAutoRefreshing = true;
                button.textContent = '⏸️ Stop Auto-refresh';
                button.style.background = '#00a32a';
                button.style.color = 'white';
            }
        }
        
        // License activation (existing functionality)
        jQuery(document).ready(function($) {
            $('#rce_activate_license').on('click', function() {
                var licenseKey = $('#rce_license_key').val();
                var button = $(this);
                
                if (!licenseKey) {
                    alert('Please enter a license key');
                    return;
                }
                
                button.prop('disabled', true).text('Activating...');
                
                $.post(ajaxurl, {
                    action: 'rce_activate_license',
                    license_key: licenseKey,
                    nonce: '<?php echo wp_create_nonce('rce_license_nonce'); ?>'
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
        <?php endif; ?>
    </div>
    <?php
}

/**
 * AJAX handler for getting logs
 */
add_action('wp_ajax_rocket_chat_get_logs', 'handle_rocket_chat_get_logs');

function handle_rocket_chat_get_logs() {
    check_ajax_referer('rocket_chat_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $logs = get_transient('rocket_chat_logs');
    $html = '';
    
    if (empty($logs)) {
        $html = '<div style="color: #888; font-style: italic;">No logs available. Logs will appear here when debug mode is enabled and chat widgets are used.</div>';
    } else {
        foreach (array_reverse($logs) as $log) {
            $escaped_log = esc_html($log);
            // Add syntax highlighting for different log levels
            if (strpos($log, '[error]') !== false) {
                $html .= '<div style="color: #ff6b6b;">' . $escaped_log . '</div>';
            } elseif (strpos($log, '[warning]') !== false) {
                $html .= '<div style="color: #ffa500;">' . $escaped_log . '</div>';
            } elseif (strpos($log, '[info]') !== false) {
                $html .= '<div style="color: #74c0fc;">' . $escaped_log . '</div>';
            } elseif (strpos($log, '[debug]') !== false) {
                $html .= '<div style="color: #51cf66;">' . $escaped_log . '</div>';
            } else {
                $html .= '<div>' . $escaped_log . '</div>';
            }
        }
    }
    
    wp_send_json_success(['logs' => $html]);
}

/**
 * AJAX handler for clearing logs
 */
add_action('wp_ajax_rocket_chat_clear_logs', 'handle_rocket_chat_clear_logs');

function handle_rocket_chat_clear_logs() {
    check_ajax_referer('rocket_chat_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    delete_transient('rocket_chat_logs');
    
    wp_send_json_success('Logs cleared');
}
