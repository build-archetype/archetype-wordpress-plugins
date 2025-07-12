<?php
if (!defined('ABSPATH')) exit;

// Add settings page to admin menu
add_action('admin_menu', 'video_library_add_settings_page');

function video_library_add_settings_page() {
    add_options_page(
        'Video Library Settings',
        'Video Library',
        'manage_options',
        'video-library',
        'video_library_settings_page'
    );
}

// Register settings
add_action('admin_init', 'video_library_register_settings');

function video_library_register_settings() {
    // Main settings group
    register_setting('video_library_settings', 'video_library_s3_bucket');
    register_setting('video_library_settings', 'video_library_s3_endpoint');
    register_setting('video_library_settings', 'video_library_s3_access_key');
    register_setting('video_library_settings', 'video_library_s3_secret_key');
    register_setting('video_library_settings', 'video_library_presigned_expiry');
    register_setting('video_library_settings', 'video_library_videos_per_page');
    register_setting('video_library_settings', 'video_library_analytics_enabled');
    register_setting('video_library_settings', 'video_library_debug_mode');
}

// Settings page HTML
function video_library_settings_page() {
    // Safety checks to prevent fatal errors
    if (!function_exists('video_library_log')) {
        function video_library_log($message, $level = 'info') {
            error_log("Video Library [$level]: $message");
        }
    }
    
    if (isset($_POST['test_s3_connection'])) {
        $test_result = video_library_test_s3_connection();
    }
    
    if (isset($_POST['list_bucket_contents'])) {
        $bucket_result = video_library_list_bucket_contents();
    }
    
    // Handle clear logs request
    if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['clear_logs_nonce'], 'video_library_clear_logs')) {
        delete_transient('video_library_logs');
        echo '<div class="notice notice-success"><p>✅ Logs cleared successfully!</p></div>';
    }
    
    // Get current settings for status dashboard
    $s3_bucket = get_option('video_library_s3_bucket', '');
    $s3_endpoint = get_option('video_library_s3_endpoint', 'https://nyc3.digitaloceanspaces.com');
    $s3_access_key = get_option('video_library_s3_access_key', '');
    $s3_secret_key = get_option('video_library_s3_secret_key', '');
    $debug_mode = get_option('video_library_debug_mode', false);
    $analytics_enabled = get_option('video_library_analytics_enabled', true);
    
    // Calculate storage status
    $s3_configured = !empty($s3_bucket) && !empty($s3_access_key) && !empty($s3_secret_key) && !empty($s3_endpoint);
    ?>
    <div class="wrap">
        <h1>Video Library Settings</h1>
        
        <div class="notice notice-info">
            <p><strong>📺 Basic Usage:</strong> <code>[video_library]</code> displays all videos from your S3 bucket</p>
            <p><strong>🎯 YouTube Layout:</strong> <code>[video_library layout="youtube"]</code> for main player + sidebar</p>
        </div>
        
        <?php if (isset($test_result)): ?>
        <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?>">
            <p><?php echo esc_html($test_result['message']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (isset($bucket_result)): ?>
        <div class="notice <?php echo $bucket_result['success'] ? 'notice-success' : 'notice-error'; ?>">
            <p><?php echo esc_html($bucket_result['message']); ?></p>
            <?php if ($bucket_result['success'] && !empty($bucket_result['files'])): ?>
                <details style="margin-top: 10px;">
                    <summary>📁 Found <?php echo count($bucket_result['files']); ?> video files</summary>
                    <ul style="max-height: 200px; overflow-y: auto; margin: 10px 0;">
                        <?php foreach (array_slice($bucket_result['files'], 0, 20) as $file): ?>
                            <li><code><?php echo esc_html($file); ?></code></li>
                        <?php endforeach; ?>
                        <?php if (count($bucket_result['files']) > 20): ?>
                            <li><em>... and <?php echo count($bucket_result['files']) - 20; ?> more files</em></li>
                        <?php endif; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
            <!-- Configuration Form -->
            <div class="postbox" style="padding: 20px;">
                <h2 style="margin-top: 0;">🔧 Storage Configuration</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('video_library_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">S3 Bucket Name</th>
                            <td>
                                <input type="text" name="video_library_s3_bucket" value="<?php echo esc_attr($s3_bucket); ?>" class="regular-text" placeholder="my-video-bucket" />
                                <p class="description">Your S3 bucket or DigitalOcean Space name</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Endpoint URL</th>
                            <td>
                                <input type="text" name="video_library_s3_endpoint" value="<?php echo esc_attr($s3_endpoint); ?>" class="regular-text" placeholder="https://nyc3.digitaloceanspaces.com" />
                                <p class="description">For DigitalOcean Spaces: <code>https://nyc3.digitaloceanspaces.com</code><br>For AWS S3: <code>https://s3.us-east-1.amazonaws.com</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Access Key</th>
                            <td>
                                <input type="text" name="video_library_s3_access_key" value="<?php echo esc_attr($s3_access_key); ?>" class="regular-text" />
                                <p class="description">S3/Spaces Access Key ID</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Secret Key</th>
                            <td>
                                <input type="password" name="video_library_s3_secret_key" value="<?php echo esc_attr($s3_secret_key); ?>" class="regular-text" />
                                <p class="description">S3/Spaces Secret Access Key</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">URL Expiry</th>
                            <td>
                                <input type="number" name="video_library_presigned_expiry" value="<?php echo esc_attr(get_option('video_library_presigned_expiry', 3600)); ?>" min="300" max="86400" style="width: 80px;" /> seconds
                                <p class="description">How long video URLs remain valid (3600 = 1 hour)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Videos Per Page</th>
                            <td>
                                <input type="number" name="video_library_videos_per_page" value="<?php echo esc_attr(get_option('video_library_videos_per_page', 12)); ?>" min="1" max="50" style="width: 80px;" />
                                <p class="description">Default number of videos to show</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Analytics</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="video_library_analytics_enabled" value="1" <?php checked($analytics_enabled, 1); ?> />
                                    Track video views and user interactions
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Debug Mode</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="video_library_debug_mode" value="1" <?php checked($debug_mode, 1); ?> />
                                    Enable detailed logging for troubleshooting
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('💾 Save Settings', 'primary'); ?>
                </form>
            </div>
            
            <!-- System Status -->
            <div class="postbox" style="padding: 20px;">
                <h2 style="margin-top: 0;">📊 System Status</h2>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <td><strong>S3 Bucket:</strong></td>
                        <td><?php echo $s3_bucket ? '✅ ' . esc_html($s3_bucket) : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Endpoint:</strong></td>
                        <td><?php echo $s3_endpoint ? '✅ ' . esc_html(parse_url($s3_endpoint, PHP_URL_HOST)) : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Access Keys:</strong></td>
                        <td><?php echo ($s3_access_key && $s3_secret_key) ? '✅ Configured' : '❌ Missing'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Full Config:</strong></td>
                        <td><?php echo $s3_configured ? '✅ Ready' : '❌ Incomplete'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Analytics:</strong></td>
                        <td><?php echo $analytics_enabled ? '📈 Enabled' : '⚪ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Debug Mode:</strong></td>
                        <td><?php echo $debug_mode ? '🐛 Enabled' : '⚪ Disabled'; ?></td>
                    </tr>
                </table>
                
                <!-- Quick Testing -->
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <h4 style="margin: 0 0 10px 0;">🔧 Quick Tests</h4>
                    <form method="post" style="margin-bottom: 10px;">
                        <button type="submit" name="test_s3_connection" class="button button-secondary" style="width: 100%;">🔗 Test Connection</button>
                    </form>
                    <form method="post">
                        <button type="submit" name="list_bucket_contents" class="button button-secondary" style="width: 100%;">📁 List Videos</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Usage Examples -->
        <div class="postbox" style="padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-top: 0;">📝 Usage Examples</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h3>🎬 Shortcode Examples</h3>
                    
                    <h4>📺 Basic Video Gallery</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0; border-radius: 4px;">[video_library]</code>
                    <p><em>Displays all videos from your S3 bucket</em></p>
                    
                    <h4>🎯 YouTube-Style Layout</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0; border-radius: 4px;">[video_library layout="youtube"]</code>
                    <p><em>Main video player with scrollable sidebar</em></p>
                    
                    <h4>📁 Filter by Path</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0; border-radius: 4px;">[video_library path="premium/" videos_per_page="8"]</code>
                    <p><em>Only shows videos from the "premium/" folder</em></p>
                    
                    <h4>🔍 With Search</h4>
                    <code style="display: block; padding: 10px; background: #f9f9f9; margin: 10px 0; border-radius: 4px;">[video_library show_search="true" show_categories="true"]</code>
                    <p><em>Includes search and category filtering</em></p>
                </div>
                
                <div>
                    <h3>⚙️ Available Parameters</h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Default</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>layout</code></td><td>grid</td><td>Layout: grid or youtube</td></tr>
                            <tr><td><code>path</code></td><td>""</td><td>Filter by S3 folder path</td></tr>
                            <tr><td><code>videos_per_page</code></td><td>12</td><td>Number of videos to show</td></tr>
                            <tr><td><code>show_search</code></td><td>true</td><td>Include search box</td></tr>
                            <tr><td><code>show_categories</code></td><td>true</td><td>Include category filter</td></tr>
                            <tr><td><code>orderby</code></td><td>date</td><td>Sort order: date, title</td></tr>
                            <tr><td><code>order</code></td><td>DESC</td><td>ASC or DESC</td></tr>
                        </tbody>
                    </table>
                    
                    <h3 style="margin-top: 20px;">📁 S3 Organization Tips</h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px;">
                        <strong>Organize your videos like this:</strong><br><br>
                        <code>premium/advanced-course.mp4</code><br>
                        <code>tutorials/getting-started.mp4</code><br>
                        <code>webinars/2024/january-session.mp4</code><br>
                        <code>live-streams/recording-1.mp4</code><br><br>
                        
                        <strong>Then filter with:</strong><br>
                        <code>[video_library path="premium/"]</code><br>
                        <code>[video_library path="tutorials/"]</code>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($debug_mode): ?>
        <!-- Debug Logs -->
        <div class="postbox" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">🐛 Debug Logs</h2>
                <div>
                    <input type="text" id="logSearch" placeholder="Search logs..." style="margin-right: 10px; padding: 5px;">
                    <button type="button" class="button" onclick="refreshLogs()">🔄 Refresh</button>
                    <button type="button" class="button" onclick="copyLogs()">📋 Copy All</button>
                    <button type="button" class="button" onclick="clearVideoLibraryLogs()">🗑️ Clear</button>
                    <button type="button" class="button" onclick="toggleAutoRefresh()">⏰ Auto-refresh</button>
                </div>
            </div>
            
            <div id="logContainer" style="background: #1e1e1e; color: #f8f8f2; padding: 20px; border-radius: 6px; max-height: 600px; overflow-y: auto; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.5;">
                <?php
                $logs = get_transient('video_library_logs');
                
                if (empty($logs)) {
                    echo '<div style="color: #888; font-style: italic;">No logs available. Logs will appear here when debug mode is enabled and videos are accessed.</div>';
                } else {
                    foreach (array_reverse(array_slice($logs, -50)) as $log) {
                        // Handle both old string format and new structured format
                        if (is_array($log)) {
                            $log_text = "[{$log['timestamp']}] [{$log['level']}] {$log['message']}";
                            $level = $log['level'];
                        } else {
                            $log_text = $log;
                            // Extract level from old format
                            if (strpos($log, '[error]') !== false) $level = 'error';
                            elseif (strpos($log, '[warning]') !== false) $level = 'warning';
                            elseif (strpos($log, '[info]') !== false) $level = 'info';
                            elseif (strpos($log, '[debug]') !== false) $level = 'debug';
                            else $level = 'info';
                        }
                        
                        $escaped_log = esc_html($log_text);
                        
                        // Add syntax highlighting for different log levels
                        if ($level === 'error') {
                            echo '<div style="color: #ff6b6b;">' . $escaped_log . '</div>';
                        } elseif ($level === 'warning') {
                            echo '<div style="color: #ffa500;">' . $escaped_log . '</div>';
                        } elseif ($level === 'info') {
                            echo '<div style="color: #74c0fc;">' . $escaped_log . '</div>';
                        } elseif ($level === 'debug') {
                            echo '<div style="color: #51cf66;">' . $escaped_log . '</div>';
                        } else {
                            echo '<div>' . $escaped_log . '</div>';
                        }
                    }
                    echo '<p style="margin-top: 15px; font-size: 12px; color: #888; border-top: 1px solid #333; padding-top: 10px;">Showing last 50 log entries. Total logs: ' . count($logs) . '</p>';
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
                    action: 'video_library_get_logs',
                    nonce: '<?php echo wp_create_nonce('video_library_admin'); ?>'
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
        function clearVideoLibraryLogs() {
            if (confirm('Clear all debug logs?')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'video_library_clear_logs',
                        nonce: '<?php echo wp_create_nonce('video_library_admin'); ?>'
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

// Test S3 connection
function video_library_test_s3_connection() {
    try {
        video_library_log('🔗 Testing S3 connection from settings page', 'info');
        
        $bucket = get_option('video_library_s3_bucket');
        $endpoint = get_option('video_library_s3_endpoint');
        $access_key = get_option('video_library_s3_access_key');
        $secret_key = get_option('video_library_s3_secret_key');

        if (empty($bucket) || empty($endpoint) || empty($access_key) || empty($secret_key)) {
            video_library_log('❌ S3 credentials not fully configured for connection test', 'error');
            return [
                'success' => false,
                'message' => 'Please fill in all required S3 settings first.'
            ];
        }

        // Check if S3 class exists
        if (!class_exists('Video_Library_S3')) {
            video_library_log('❌ Video_Library_S3 class not found', 'error');
            return [
                'success' => false,
                'message' => 'S3 integration class not loaded. Please check plugin installation.'
            ];
        }

        // Test authentication
        $auth_test = Video_Library_S3::test_connection();
        
        if ($auth_test) {
            video_library_log('✅ S3 connection test successful', 'info');
            return [
                'success' => true,
                'message' => '✅ S3 connection successful! Your credentials are working correctly.'
            ];
        } else {
            video_library_log('❌ S3 connection test failed', 'error');
            return [
                'success' => false,
                'message' => '❌ S3 connection failed. Please check your credentials and endpoint URL.'
            ];
        }
    } catch (Exception $e) {
        video_library_log('❌ Exception during S3 connection test: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'message' => 'Connection test failed: ' . $e->getMessage()
        ];
    }
}

// List bucket contents with detailed logging
function video_library_list_bucket_contents() {
    try {
        video_library_log('📁 Listing S3 bucket contents from settings page', 'info');
        
        $bucket = get_option('video_library_s3_bucket');
        $access_key = get_option('video_library_s3_access_key');
        $secret_key = get_option('video_library_s3_secret_key');

        if (empty($bucket) || empty($access_key) || empty($secret_key)) {
            video_library_log('❌ S3 credentials not configured for bucket listing', 'error');
            return [
                'success' => false,
                'message' => 'Please fill in all required S3 settings first.'
            ];
        }

        // Check if S3 class exists
        if (!class_exists('Video_Library_S3')) {
            video_library_log('❌ Video_Library_S3 class not found', 'error');
            return [
                'success' => false,
                'message' => 'S3 integration class not loaded. Please check plugin installation.'
            ];
        }

        // Test authentication first
        $auth_test = Video_Library_S3::test_connection();
        
        if (!$auth_test) {
            video_library_log('❌ S3 authentication failed during bucket listing', 'error');
            return [
                'success' => false,
                'message' => 'S3 authentication failed. Please check your credentials.'
            ];
        }
        
        video_library_log('✅ S3 authentication successful, listing bucket contents', 'info');
        
        // Create S3 instance and list files
        $s3 = new Video_Library_S3();
        $files = $s3->list_bucket_files();
        
        if ($files === false) {
            video_library_log('❌ Failed to list bucket contents', 'error');
            return [
                'success' => false,
                'message' => 'Failed to list bucket contents. Check your bucket name and permissions.'
            ];
        }
        
        $video_extensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'flv', 'wmv'];
        $video_files = [];
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $video_extensions)) {
                $video_files[] = $file;
            }
        }
        
        video_library_log("📹 Found " . count($video_files) . " video files out of " . count($files) . " total files", 'info');
        
        return [
            'success' => true,
            'message' => "✅ Successfully listed bucket contents! Found " . count($video_files) . " video files.",
            'files' => $video_files
        ];
        
    } catch (Exception $e) {
        video_library_log('❌ Exception during bucket listing: ' . $e->getMessage(), 'error');
        return [
            'success' => false,
            'message' => 'Failed to list bucket: ' . $e->getMessage()
        ];
    }
}

// Add debug log AJAX handlers
/**
 * AJAX handler for getting logs
 */
add_action('wp_ajax_video_library_get_logs', 'handle_video_library_get_logs');

function handle_video_library_get_logs() {
    check_ajax_referer('video_library_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $logs = get_transient('video_library_logs');
    $html = '';
    
    if (empty($logs)) {
        $html = '<div style="color: #888; font-style: italic;">No logs available. Logs will appear here when debug mode is enabled and videos are accessed.</div>';
    } else {
        foreach (array_reverse(array_slice($logs, -50)) as $log) {
            // Handle both old string format and new structured format
            if (is_array($log)) {
                $log_text = "[{$log['timestamp']}] [{$log['level']}] {$log['message']}";
                $level = $log['level'];
            } else {
                $log_text = $log;
                // Extract level from old format
                if (strpos($log, '[error]') !== false) $level = 'error';
                elseif (strpos($log, '[warning]') !== false) $level = 'warning';
                elseif (strpos($log, '[info]') !== false) $level = 'info';
                elseif (strpos($log, '[debug]') !== false) $level = 'debug';
                else $level = 'info';
            }
            
            $escaped_log = esc_html($log_text);
            
            // Add syntax highlighting for different log levels
            if ($level === 'error') {
                $html .= '<div style="color: #ff6b6b;">' . $escaped_log . '</div>';
            } elseif ($level === 'warning') {
                $html .= '<div style="color: #ffa500;">' . $escaped_log . '</div>';
            } elseif ($level === 'info') {
                $html .= '<div style="color: #74c0fc;">' . $escaped_log . '</div>';
            } elseif ($level === 'debug') {
                $html .= '<div style="color: #51cf66;">' . $escaped_log . '</div>';
            } else {
                $html .= '<div>' . $escaped_log . '</div>';
            }
        }
        $html .= '<p style="margin-top: 15px; font-size: 12px; color: #888; border-top: 1px solid #333; padding-top: 10px;">Showing last 50 log entries. Total logs: ' . count($logs) . '</p>';
    }
    
    wp_send_json_success(['logs' => $html]);
}

/**
 * AJAX handler for clearing logs
 */
add_action('wp_ajax_video_library_clear_logs', 'handle_video_library_clear_logs');

function handle_video_library_clear_logs() {
    check_ajax_referer('video_library_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    delete_transient('video_library_logs');
    video_library_log('🗑️ Debug logs cleared from admin panel', 'info');
    
    wp_send_json_success('Logs cleared');
} 