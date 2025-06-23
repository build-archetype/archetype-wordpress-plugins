<?php
if (!defined('ABSPATH')) exit;

/**
 * Add admin menu
 */
function ant_media_add_admin_menu() {
    add_options_page(
        'Ant Media Stream Settings',
        'Ant Media Stream',
        'manage_options',
        'ant-media-stream',
        'ant_media_admin_page'
    );
}
add_action('admin_menu', 'ant_media_add_admin_menu');

/**
 * Initialize settings
 */
function ant_media_admin_init() {
    register_setting('ant_media_settings', 'ant_media_server_url');
    register_setting('ant_media_settings', 'ant_media_enabled');
    register_setting('ant_media_settings', 'ant_media_debug_mode');
    
    add_settings_section(
        'ant_media_main',
        'Ant Media Server Configuration',
        'ant_media_settings_section_callback',
        'ant-media-settings'
    );
    
    add_settings_field(
        'ant_media_server_url',
        'Server URL',
        'ant_media_server_url_callback',
        'ant-media-settings',
        'ant_media_main'
    );
    
    add_settings_field(
        'ant_media_enabled',
        'Enable Streams',
        'ant_media_enabled_callback',
        'ant-media-settings',
        'ant_media_main'
    );
    
    add_settings_field(
        'ant_media_debug_mode',
        'Debug Mode',
        'ant_media_debug_mode_callback',
        'ant-media-settings',
        'ant_media_main'
    );
}
add_action('admin_init', 'ant_media_admin_init');

function ant_media_settings_section_callback() {
    echo '<p>Configure your Ant Media Server settings below:</p>';
}

function ant_media_server_url_callback() {
    $server_url = get_option('ant_media_server_url', '');
    echo '<input type="url" id="ant_media_server_url" name="ant_media_server_url" value="' . esc_attr($server_url) . '" class="regular-text" placeholder="https://your-server.com:5443" />';
    echo '<p class="description">Enter your Ant Media Server URL (without trailing slash)</p>';
}

function ant_media_enabled_callback() {
    $enabled = get_option('ant_media_enabled', 'true');
    echo '<input type="checkbox" id="ant_media_enabled" name="ant_media_enabled" value="true" ' . checked($enabled, 'true', false) . ' />';
    echo '<label for="ant_media_enabled">Enable stream embedding</label>';
    echo '<p class="description">Uncheck to disable all stream embeds site-wide</p>';
}

function ant_media_debug_mode_callback() {
    $debug_mode = get_option('ant_media_debug_mode', false);
    echo '<input type="checkbox" id="ant_media_debug_mode" name="ant_media_debug_mode" value="1" ' . checked($debug_mode, 1, false) . ' />';
    echo '<label for="ant_media_debug_mode">Enable debug logging</label>';
    echo '<p class="description">Enable detailed logging for troubleshooting</p>';
}

/**
 * Admin page
 */
function ant_media_admin_page() {
    ?>
    <div class="wrap">
        <h1>Ant Media Stream Settings</h1>
        
        <div class="notice notice-info">
            <p><strong>Simple Shortcode:</strong> Use <code>[antmedia_simple stream_id="your_stream_id"]</code> to embed streams.</p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('ant_media_settings');
            do_settings_sections('ant-media-settings');
            submit_button();
            ?>
        </form>
        
        <div class="card">
            <h2>Usage Examples</h2>
            
            <h3>Basic Stream Embed</h3>
            <p>Simple stream with default settings:</p>
            <code>[antmedia_simple stream_id="test_stream"]</code>
            
            <h3>Customized Player</h3>
            <p>With custom size and offline message:</p>
            <code>[antmedia_simple stream_id="my_stream" width="800px" height="450px" offline_message="We'll be right back!"]</code>
            
            <h3>With Token Authentication</h3>
            <p>For secure streams:</p>
            <code>[antmedia_simple stream_id="secure_stream" token="your_jwt_token"]</code>
        </div>
        
        <div class="card">
            <h2>Available Parameters</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Default</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>stream_id</strong></td>
                        <td><em>required</em></td>
                        <td>The stream ID to play</td>
                    </tr>
                    <tr>
                        <td>server_url</td>
                        <td>From settings</td>
                        <td>Override default server URL</td>
                    </tr>
                    <tr>
                        <td>app_name</td>
                        <td>live</td>
                        <td>Application name on Ant Media Server</td>
                    </tr>
                    <tr>
                        <td>width</td>
                        <td>100%</td>
                        <td>Player width</td>
                    </tr>
                    <tr>
                        <td>height</td>
                        <td>500px</td>
                        <td>Player height</td>
                    </tr>
                    <tr>
                        <td>offline_message</td>
                        <td>Stream is currently offline...</td>
                        <td>Message shown when stream is offline</td>
                    </tr>
                    <tr>
                        <td>autoplay</td>
                        <td>true</td>
                        <td>Auto-play when stream is available</td>
                    </tr>
                    <tr>
                        <td>muted</td>
                        <td>true</td>
                        <td>Start muted (required for autoplay)</td>
                    </tr>
                    <tr>
                        <td>play_order</td>
                        <td>webrtc,hls</td>
                        <td>Playback technology preference</td>
                    </tr>
                    <tr>
                        <td>token</td>
                        <td><em>none</em></td>
                        <td>JWT token for secure streams</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php 
        $debug_mode = get_option('ant_media_debug_mode', false);
        if ($debug_mode): 
        ?>
        <div class="card">
            <h2>Debug Logs</h2>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                <?php
                $logger = Ant_Media_Logger::get_instance();
                $logs = $logger->get_logs();
                
                if (empty($logs)) {
                    echo '<p><em>No logs available. Logs will appear here when debug mode is enabled and streams are used.</em></p>';
                } else {
                    echo '<pre style="font-size: 12px; line-height: 1.4; margin: 0;">';
                    foreach (array_reverse($logs) as $log) {
                        echo esc_html($log) . "\n";
                    }
                    echo '</pre>';
                }
                ?>
            </div>
            <p style="margin-top: 10px;">
                <button type="button" class="button" onclick="clearAntMediaLogs()">Clear Logs</button>
                <button type="button" class="button" onclick="location.reload()">Refresh</button>
            </p>
        </div>
        
        <script>
        function clearAntMediaLogs() {
            if (confirm('Clear all debug logs?')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'ant_media_clear_logs',
                        nonce: '<?php echo wp_create_nonce('ant_media_admin'); ?>'
                    })
                })
                .then(() => location.reload())
                .catch(error => console.error('Error:', error));
            }
        }
        </script>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * AJAX handler for clearing logs
 */
add_action('wp_ajax_ant_media_clear_logs', 'handle_ant_media_clear_logs');

function handle_ant_media_clear_logs() {
    check_ajax_referer('ant_media_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $logger = Ant_Media_Logger::get_instance();
    $logger->clear_logs();
    
    wp_send_json_success('Logs cleared');
} 