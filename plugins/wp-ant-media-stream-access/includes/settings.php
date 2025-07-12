<?php
if (!defined('ABSPATH')) exit;

/**
 * Add admin menu
 */
function ant_media_add_admin_menu() {
    add_options_page(
        'Ant Media Stream Settings',
        'Ant Media Stream Access',
        'manage_options',
        'ant-media-stream-access',
        'ant_media_admin_page'
    );
}
add_action('admin_menu', 'ant_media_add_admin_menu');

/**
 * Initialize settings
 */
function ant_media_admin_init() {
    register_setting('ant_media_settings', 'ant_media_server_url');
    register_setting('ant_media_settings', 'ant_media_jwt_secret');
    register_setting('ant_media_settings', 'ant_media_app_name');
    register_setting('ant_media_settings', 'ant_media_enabled');
    register_setting('ant_media_settings', 'ant_media_debug_mode');
    
    add_settings_section(
        'ant_media_main',
        'Ant Media Server Configuration',
        'ant_media_settings_section_callback',
        'ant-media-stream-access'
    );
    
    add_settings_field(
        'ant_media_server_url',
        'Server URL',
        'ant_media_server_url_callback',
        'ant-media-stream-access',
        'ant_media_main'
    );
    
    add_settings_field(
        'ant_media_app_name',
        'Application Name',
        'ant_media_app_name_callback',
        'ant-media-stream-access',
        'ant_media_main'
    );
    
    add_settings_field(
        'ant_media_jwt_secret',
        'JWT Secret Key',
        'ant_media_jwt_secret_callback',
        'ant-media-stream-access',
        'ant_media_main'
    );
    
    add_settings_field(
        'ant_media_enabled',
        'Enable Streams',
        'ant_media_enabled_callback',
        'ant-media-stream-access',
        'ant_media_main'
    );
    
    add_settings_field(
        'ant_media_debug_mode',
        'Debug Mode',
        'ant_media_debug_mode_callback',
        'ant-media-stream-access',
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

function ant_media_app_name_callback() {
    $app_name = get_option('ant_media_app_name', 'live');
    echo '<input type="text" id="ant_media_app_name" name="ant_media_app_name" value="' . esc_attr($app_name) . '" class="regular-text" placeholder="live" />';
    echo '<p class="description">Application name on your Ant Media Server (e.g., "live", "TriplePointTradingStreaming")</p>';
}

function ant_media_jwt_secret_callback() {
    $jwt_secret = get_option('ant_media_jwt_secret', '');
    echo '<input type="text" id="ant_media_jwt_secret" name="ant_media_jwt_secret" value="' . esc_attr($jwt_secret) . '" class="regular-text" placeholder="Enter your JWT secret" />';
    echo '<p class="description">Enter your JWT secret key</p>';
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
    <div class="wrap" style="max-width: none;">
        <h1>Ant Media Stream Access Settings</h1>
        
        <div class="notice notice-info">
            <p><strong>Basic Shortcode:</strong> Use <code>[antmedia_stream]</code> for automatic user tier-based streaming.</p>
            <p><strong>Direct Access:</strong> Use <code>[antmedia_stream_direct stream_id="your_stream_id"]</code> for specific streams.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <!-- Settings Form -->
            <div class="postbox" style="padding: 20px;">
                <h2 style="margin-top: 0;">Server Configuration</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('ant_media_settings');
                    do_settings_sections('ant-media-stream-access');
                    submit_button('Save Settings', 'primary', 'submit', false);
                    ?>
                </form>
            </div>
            
            <!-- Quick Status -->
            <div class="postbox" style="padding: 20px;">
                <h2 style="margin-top: 0;">System Status</h2>
                <?php
                $server_url = get_option('ant_media_server_url', '');
                $app_name = get_option('ant_media_app_name', 'live');
                $enabled = get_option('ant_media_enabled', 'true');
                $debug_mode = get_option('ant_media_debug_mode', false);
                $jwt_secret = get_option('ant_media_jwt_secret', '');
                ?>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <td><strong>Server URL:</strong></td>
                        <td><?php echo $server_url ? '✅ ' . esc_html($server_url) : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>App Name:</strong></td>
                        <td><?php echo $app_name ? '✅ ' . esc_html($app_name) : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>JWT Secret:</strong></td>
                        <td><?php echo $jwt_secret ? '✅ Configured' : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Streams:</strong></td>
                        <td><?php echo $enabled === 'true' ? '✅ Enabled' : '❌ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Debug Mode:</strong></td>
                        <td><?php echo $debug_mode ? '✅ Enabled' : '❌ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Integration:</strong></td>
                        <td><?php echo function_exists('should_display_rocket_chat') ? '✅ Rocket Chat Active' : '⚪ Rocket Chat Not Active'; ?></td>
                    </tr>
                </table>
                
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <button type="button" class="button" onclick="testConnection()" id="testConnectionBtn">
                        🔍 Test API Connection & Get Server IP
                    </button>
                    <div id="connectionTestResult" style="margin-top: 10px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Full Width Usage Examples -->
        <div class="postbox" style="padding: 20px;">
            <h2 style="margin-top: 0;">Usage Examples & Parameters</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h3>Shortcode Examples</h3>
                    
                    <h4>🎯 Automatic Tier-Based Streaming</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[antmedia_stream]</code>
                    <p><em>Routes users to appropriate streams based on their tier (platinum/gold/silver)</em></p>
                    
                    <h4>🎨 Customized Player</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[antmedia_stream width="800px" height="450px" format="hls" autoplay="false"]</code>
                    
                    <h4>🔒 Direct Stream Access</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;">[antmedia_stream_direct stream_id="specific_stream_123"]</code>
                    
                    <h4>💬 Combined Stream + Chat (Elementor)</h4>
                    <p>Use the "Stream + Chat Combined" widget in Elementor for integrated layouts with automatic chat visibility.</p>
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
                            <tr><td><strong>width</strong></td><td>100%</td><td>Player width (px or %)</td></tr>
                            <tr><td><strong>height</strong></td><td>500px</td><td>Player height</td></tr>
                            <tr><td><strong>format</strong></td><td>hls</td><td>Stream format: hls, webrtc, embed</td></tr>
                            <tr><td><strong>controls</strong></td><td>true</td><td>Show player controls</td></tr>
                            <tr><td><strong>autoplay</strong></td><td>false</td><td>Auto-start playback</td></tr>
                            <tr><td><strong>muted</strong></td><td>true</td><td>Start muted (required for autoplay)</td></tr>
                            <tr><td><strong>style</strong></td><td>""</td><td>Custom CSS styles</td></tr>
                            <tr><td><strong>stream_id</strong></td><td>-</td><td>Specific stream (for direct access)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php 
        $debug_mode = get_option('ant_media_debug_mode', false);
        if ($debug_mode): 
        ?>
        <!-- Full Width Debug Logs -->
        <div class="postbox" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Debug Logs</h2>
                <div>
                    <input type="text" id="logSearch" placeholder="Search logs..." style="margin-right: 10px; padding: 5px;">
                    <button type="button" class="button" onclick="refreshLogs()">🔄 Refresh</button>
                    <button type="button" class="button" onclick="copyLogs()">📋 Copy All</button>
                    <button type="button" class="button" onclick="clearAntMediaLogs()">🗑️ Clear</button>
                    <button type="button" class="button" onclick="toggleAutoRefresh()">⏰ Auto-refresh</button>
                </div>
            </div>
            
            <div id="logContainer" style="background: #1e1e1e; color: #f8f8f2; padding: 20px; border-radius: 6px; max-height: 600px; overflow-y: auto; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.5;">
                <?php
                $logs = get_transient('ant_media_logs');
                
                if (empty($logs)) {
                    echo '<div style="color: #888; font-style: italic;">No logs available. Logs will appear here when debug mode is enabled and streams are used.</div>';
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
        </style>
        
        <script>
        let autoRefreshInterval = null;
        let isAutoRefreshing = false;
        
        // Test API connection and get server IP
        function testConnection() {
            const button = document.getElementById('testConnectionBtn');
            const result = document.getElementById('connectionTestResult');
            
            button.disabled = true;
            button.textContent = '🔄 Testing...';
            result.innerHTML = '<div style="padding: 10px; background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px; color: #0969da;">Testing connection and detecting server IP...</div>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ant_media_test_connection',
                    nonce: '<?php echo wp_create_nonce('ant_media_admin'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                button.disabled = false;
                button.textContent = '🔍 Test API Connection & Get Server IP';
                
                if (data.success) {
                    const testData = data.data;
                    let html = '<div style="padding: 15px; background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px; font-family: monospace; font-size: 13px;">';
                    
                    html += '<h4 style="margin: 0 0 10px 0; color: #0969da;">🖥️ Server Information</h4>';
                    html += '<div><strong>WordPress Server IP:</strong> <code style="background: #fff; padding: 2px 4px; border-radius: 3px;">' + testData.server_ip + '</code></div>';
                    html += '<div style="margin-top: 5px;"><strong>User Agent:</strong> <code style="background: #fff; padding: 2px 4px; border-radius: 3px;">' + testData.user_agent + '</code></div>';
                    
                    html += '<h4 style="margin: 15px 0 10px 0; color: #0969da;">🔗 API Test Results</h4>';
                    
                    if (testData.api_test.success) {
                        html += '<div style="color: #0f5132; background: #d1e7dd; padding: 8px; border-radius: 4px; margin: 5px 0;">';
                        html += '✅ <strong>API Connection Successful!</strong><br>';
                        html += 'Response: ' + JSON.stringify(testData.api_test.response);
                        html += '</div>';
                    } else {
                        html += '<div style="color: #842029; background: #f8d7da; padding: 8px; border-radius: 4px; margin: 5px 0;">';
                        html += '❌ <strong>API Connection Failed</strong><br>';
                        html += '<strong>Error:</strong> ' + testData.api_test.error + '<br>';
                        if (testData.api_test.http_code) {
                            html += '<strong>HTTP Code:</strong> ' + testData.api_test.http_code + '<br>';
                        }
                        if (testData.api_test.response) {
                            html += '<strong>Response:</strong> ' + testData.api_test.response.substring(0, 200) + '...';
                        }
                        html += '</div>';
                    }
                    
                    html += '<h4 style="margin: 15px 0 10px 0; color: #0969da;">💡 IP Whitelist Instructions</h4>';
                    html += '<div style="background: #fff3cd; border: 1px solid #ffecb5; padding: 10px; border-radius: 4px; color: #664d03;">';
                    html += '<strong>Add this IP to your Ant Media Server:</strong><br>';
                    html += '<code style="background: #fff; padding: 4px 8px; border-radius: 3px; font-size: 14px; font-weight: bold;">' + testData.server_ip + '</code><br>';
                    html += '<small>Go to Ant Media dashboard → Settings → Security → Enable IP Filter for RESTful API</small>';
                    html += '</div>';
                    
                    html += '</div>';
                    result.innerHTML = html;
                } else {
                    result.innerHTML = '<div style="color: #842029; background: #f8d7da; padding: 10px; border: 1px solid #f5c2c7; border-radius: 4px;">❌ Test failed: ' + data.data + '</div>';
                }
            })
            .catch(error => {
                button.disabled = false;
                button.textContent = '🔍 Test API Connection & Get Server IP';
                result.innerHTML = '<div style="color: #842029; background: #f8d7da; padding: 10px; border: 1px solid #f5c2c7; border-radius: 4px;">❌ Request failed: ' + error.message + '</div>';
            });
        }
        
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
                    action: 'ant_media_get_logs',
                    nonce: '<?php echo wp_create_nonce('ant_media_admin'); ?>'
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
        function clearAntMediaLogs() {
            if (confirm('Clear all debug logs?')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'ant_media_clear_logs',
                        nonce: '<?php echo wp_create_nonce('ant_media_admin'); ?>'
                    })
                })
                .then(() => refreshLogs())
                .catch(error => console.error('Error:', error));
            }
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
    
    delete_transient('ant_media_logs');
    
    wp_send_json_success('Logs cleared');
}

/**
 * AJAX handler for getting logs
 */
add_action('wp_ajax_ant_media_get_logs', 'handle_ant_media_get_logs');

function handle_ant_media_get_logs() {
    check_ajax_referer('ant_media_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $logs = get_transient('ant_media_logs');
    $html = '';
    
    if (empty($logs)) {
        $html = '<div style="color: #888; font-style: italic;">No logs available. Logs will appear here when debug mode is enabled and streams are used.</div>';
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
 * AJAX handler for testing API connection and getting server IP
 */
add_action('wp_ajax_ant_media_test_connection', 'handle_ant_media_test_connection');

function handle_ant_media_test_connection() {
    check_ajax_referer('ant_media_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $server_url = get_option('ant_media_server_url', '');
    $app_name = get_option('ant_media_app_name', 'live');
    
    if (empty($server_url)) {
        wp_send_json_error('Server URL not configured');
        return;
    }
    
    // Get server's outbound IP using multiple methods
    $server_ip = 'Unknown';
    $user_agent = 'WordPress/' . get_bloginfo('version') . ' AntMediaStreamAccess/' . AMSA_VERSION;
    
    // Method 1: Use httpbin.org to detect our IP
    $ip_detection_response = wp_remote_get('https://httpbin.org/ip', [
        'timeout' => 10,
        'user-agent' => $user_agent
    ]);
    
    if (!is_wp_error($ip_detection_response)) {
        $ip_body = wp_remote_retrieve_body($ip_detection_response);
        $ip_data = json_decode($ip_body, true);
        if ($ip_data && isset($ip_data['origin'])) {
            $server_ip = $ip_data['origin'];
        }
    }
    
    // Method 2: Fallback to ipify.org
    if ($server_ip === 'Unknown') {
        $ip_detection_response = wp_remote_get('https://api.ipify.org?format=json', [
            'timeout' => 10,
            'user-agent' => $user_agent
        ]);
        
        if (!is_wp_error($ip_detection_response)) {
            $ip_body = wp_remote_retrieve_body($ip_detection_response);
            $ip_data = json_decode($ip_body, true);
            if ($ip_data && isset($ip_data['ip'])) {
                $server_ip = $ip_data['ip'];
            }
        }
    }
    
    // Test the actual Ant Media API
    $test_stream_id = 'test_connection_probe'; // Use a test stream ID
    $api_url = rtrim($server_url, '/') . '/' . $app_name . '/rest/v2/broadcasts/' . $test_stream_id;
    
    $api_test_result = [
        'success' => false,
        'error' => '',
        'http_code' => null,
        'response' => ''
    ];
    
    $response = wp_remote_get($api_url, [
        'timeout' => 15,
        'user-agent' => $user_agent,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);
    
    if (is_wp_error($response)) {
        $api_test_result['error'] = $response->get_error_message();
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $api_test_result['http_code'] = $http_code;
        $api_test_result['response'] = $body;
        
        if ($http_code === 200) {
            // Success - we got a response (even if stream doesn't exist)
            $api_test_result['success'] = true;
            $api_test_result['error'] = 'API accessible';
            
            $data = json_decode($body, true);
            if ($data) {
                $api_test_result['response'] = $data;
            }
        } elseif ($http_code === 404) {
            // Stream not found is actually a good sign - API is accessible
            $api_test_result['success'] = true;
            $api_test_result['error'] = 'API accessible (test stream not found - this is expected)';
        } elseif ($http_code === 403) {
            $api_test_result['error'] = 'IP not whitelisted (403 Forbidden)';
        } else {
            $api_test_result['error'] = 'HTTP ' . $http_code . ' - ' . wp_remote_retrieve_response_message($response);
        }
    }
    
    wp_send_json_success([
        'server_ip' => $server_ip,
        'user_agent' => $user_agent,
        'api_test' => $api_test_result,
        'test_url' => $api_url
    ]);
} 