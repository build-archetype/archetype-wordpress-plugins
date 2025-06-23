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
    // S3 Settings Group
    add_settings_section(
        'video_library_s3_section',
        'S3 Storage Configuration',
        'video_library_s3_section_callback',
        'video-library'
    );

    register_setting('video_library_settings', 'video_library_s3_bucket');
    register_setting('video_library_settings', 'video_library_s3_region');
    register_setting('video_library_settings', 'video_library_s3_access_key');
    register_setting('video_library_settings', 'video_library_s3_secret_key');
    register_setting('video_library_settings', 'video_library_s3_endpoint');
    register_setting('video_library_settings', 'video_library_presigned_expiry');

    add_settings_field(
        'video_library_s3_bucket',
        'S3 Bucket Name',
        'video_library_s3_bucket_callback',
        'video-library',
        'video_library_s3_section'
    );

    add_settings_field(
        'video_library_s3_region',
        'S3 Region',
        'video_library_s3_region_callback',
        'video-library',
        'video_library_s3_section'
    );

    add_settings_field(
        'video_library_s3_access_key',
        'Access Key',
        'video_library_s3_access_key_callback',
        'video-library',
        'video_library_s3_section'
    );

    add_settings_field(
        'video_library_s3_secret_key',
        'Secret Key',
        'video_library_s3_secret_key_callback',
        'video-library',
        'video_library_s3_section'
    );

    add_settings_field(
        'video_library_s3_endpoint',
        'Custom Endpoint (DigitalOcean Spaces)',
        'video_library_s3_endpoint_callback',
        'video-library',
        'video_library_s3_section'
    );

    add_settings_field(
        'video_library_presigned_expiry',
        'Video URL Expiry (seconds)',
        'video_library_presigned_expiry_callback',
        'video-library',
        'video_library_s3_section'
    );

    // Display Settings Group
    add_settings_section(
        'video_library_display_section',
        'Display Settings',
        'video_library_display_section_callback',
        'video-library'
    );

    register_setting('video_library_settings', 'video_library_videos_per_page');
    register_setting('video_library_settings', 'video_library_featured_video');
    register_setting('video_library_settings', 'video_library_enabled');

    add_settings_field(
        'video_library_videos_per_page',
        'Videos Per Page',
        'video_library_videos_per_page_callback',
        'video-library',
        'video_library_display_section'
    );

    add_settings_field(
        'video_library_featured_video',
        'Featured Video',
        'video_library_featured_video_callback',
        'video-library',
        'video_library_display_section'
    );

    add_settings_field(
        'video_library_enabled',
        'Enable Video Library',
        'video_library_enabled_callback',
        'video-library',
        'video_library_display_section'
    );

    // Path Organization Settings
    add_settings_section(
        'video_library_organization_section',
        'Video Organization',
        'video_library_organization_section_callback',
        'video-library'
    );

    register_setting('video_library_settings', 'video_library_suggested_paths');

    add_settings_field(
        'video_library_suggested_paths',
        'Suggested S3 Paths',
        'video_library_suggested_paths_callback',
        'video-library',
        'video_library_organization_section'
    );

    // Analytics Settings
    add_settings_section(
        'video_library_analytics_section',
        'Analytics Settings',
        'video_library_analytics_section_callback',
        'video-library'
    );

    register_setting('video_library_settings', 'video_library_analytics_enabled');
    register_setting('video_library_settings', 'video_library_debug_mode');

    add_settings_field(
        'video_library_analytics_enabled',
        'Enable Analytics',
        'video_library_analytics_enabled_callback',
        'video-library',
        'video_library_analytics_section'
    );

    add_settings_field(
        'video_library_debug_mode',
        'Debug Mode',
        'video_library_debug_mode_callback',
        'video-library',
        'video_library_analytics_section'
    );
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
        echo '<div class="notice notice-success"><p>Logs cleared successfully!</p></div>';
    }
    
    // Get debug mode setting
    $debug_mode = get_option('video_library_debug_mode', false);
    ?>
    <div class="wrap">
        <h1>Video Library Settings</h1>
        
        <?php if (isset($test_result)): ?>
        <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?>">
            <p><?php echo esc_html($test_result['message']); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (isset($bucket_result)): ?>
        <div class="notice <?php echo $bucket_result['success'] ? 'notice-success' : 'notice-error'; ?>">
            <p><?php echo esc_html($bucket_result['message']); ?></p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('video_library_settings');
            do_settings_sections('video-library');
            submit_button();
            ?>
        </form>

        <!-- S3 Testing Section -->
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
            <h2>S3 Connection Testing</h2>
            <p>Test your S3 connection and view bucket contents to ensure everything is working correctly.</p>
            
            <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                <?php submit_button('Test S3 Connection', 'secondary', 'test_s3_connection', false); ?>
            </form>
            
            <form method="post" action="" style="display: inline-block;">
                <?php submit_button('List Bucket Contents', 'secondary', 'list_bucket_contents', false); ?>
            </form>
        </div>

        <!-- Enhanced Logging Section - Always Visible -->
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
            <h2>Activity Logs 
                <span style="font-size: 12px; color: #666; font-weight: normal;">
                    (Auto-refresh: <span id="auto-refresh-status"><?php echo $debug_mode ? 'ON' : 'OFF'; ?></span>)
                </span>
            </h2>
            
            <!-- Log Controls -->
            <div style="margin-bottom: 15px;">
                <input type="text" id="log-search" placeholder="Search logs..." style="width: 300px; padding: 5px;">
                <button type="button" id="search-logs" class="button">Search</button>
                <button type="button" id="refresh-logs" class="button">Refresh</button>
                
                <form method="post" action="" style="display: inline-block; margin-left: 10px;">
                    <input type="hidden" name="clear_logs" value="1">
                    <?php wp_nonce_field('video_library_clear_logs', 'clear_logs_nonce'); ?>
                    <?php submit_button('Clear Logs', 'secondary', 'clear_logs_btn', false, ['style' => 'margin: 0;']); ?>
                </form>
                
                <label style="margin-left: 15px;">
                    <input type="checkbox" id="auto-refresh-toggle" <?php checked($debug_mode); ?>>
                    Auto-refresh (5s)
                </label>
            </div>
            
            <!-- Log Display -->
            <div id="log-container" style="max-height: 500px; overflow-y: auto; background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px;">
                <?php
                $logs = get_transient('video_library_logs');
                if ($logs && is_array($logs)) {
                    echo '<div id="log-content">';
                    echo '<pre style="margin: 0; font-family: Consolas, monospace; font-size: 12px; white-space: pre-wrap;">';
                    foreach (array_slice($logs, -50) as $log) {
                        // Color code log levels
                        $colored_log = $log;
                        if (strpos($log, '[error]') !== false) {
                            $colored_log = '<span style="color: #dc3545;">' . esc_html($log) . '</span>';
                        } elseif (strpos($log, '[warning]') !== false) {
                            $colored_log = '<span style="color: #ffc107;">' . esc_html($log) . '</span>';
                        } elseif (strpos($log, '[info]') !== false) {
                            $colored_log = '<span style="color: #28a745;">' . esc_html($log) . '</span>';
                        } elseif (strpos($log, '[debug]') !== false) {
                            $colored_log = '<span style="color: #6c757d;">' . esc_html($log) . '</span>';
                        } else {
                            $colored_log = esc_html($log);
                        }
                        echo $colored_log . "\n";
                    }
                    echo '</pre>';
                    echo '</div>';
                    echo '<p style="margin-top: 10px; font-size: 12px; color: #666;">Showing last 50 log entries. Total logs in memory: ' . count($logs) . '</p>';
                } else {
                    echo '<div id="log-content"><p style="color: #666; font-style: italic;">No logs available yet. Interact with the plugin to generate logs.</p></div>';
                }
                ?>
            </div>
        </div>

        <div class="video-library-usage-examples">
            <h2>Usage Examples</h2>
            <h3>Shortcode Examples:</h3>
            <pre><code>[video_library]</code></pre>
            <pre><code>[video_library category="tutorials"]</code></pre>
            <pre><code>[video_library path="premium/"]</code></pre>
            <pre><code>[video_library s3_prefix="webinars/" videos_per_page="8"]</code></pre>
            <pre><code>[video_library_debug]</code> - Test logging and debug mode</pre>

            <h3>Elementor Widget:</h3>
            <p>Add the "Video Library" widget to any page and configure the filtering options in the widget settings.</p>

            <h3>Path-Based Organization:</h3>
            <p>Organize your videos by using S3 paths like:</p>
            <ul>
                <li><code>premium/video1.mp4</code> - Premium content</li>
                <li><code>tutorials/beginner/intro.mp4</code> - Beginner tutorials</li>
                <li><code>webinars/2024/session1.mp4</code> - Webinar recordings</li>
                <li><code>live-streams/platinum/stream.mp4</code> - Recorded live streams</li>
            </ul>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        let autoRefreshInterval;
        let isAutoRefreshEnabled = <?php echo $debug_mode ? 'true' : 'false'; ?>;
        
        // Auto-refresh functionality
        function toggleAutoRefresh(enable) {
            if (enable && !autoRefreshInterval) {
                autoRefreshInterval = setInterval(function() {
                    refreshLogs();
                }, 5000);
                $('#auto-refresh-status').text('ON').css('color', '#28a745');
            } else if (!enable && autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                $('#auto-refresh-status').text('OFF').css('color', '#6c757d');
            }
        }
        
        // Initialize auto-refresh
        toggleAutoRefresh(isAutoRefreshEnabled);
        
        // Auto-refresh toggle
        $('#auto-refresh-toggle').change(function() {
            isAutoRefreshEnabled = this.checked;
            toggleAutoRefresh(isAutoRefreshEnabled);
        });
        
        // Manual refresh
        $('#refresh-logs').click(function() {
            refreshLogs();
        });
        
        // Search functionality
        $('#search-logs, #log-search').on('click keyup', function(e) {
            if (e.type === 'click' || e.keyCode === 13) {
                searchLogs();
            }
        });
        
        function refreshLogs() {
            $.post(ajaxurl, {
                action: 'video_library_get_logs',
                nonce: '<?php echo wp_create_nonce('video_library_logs'); ?>'
            }, function(response) {
                if (response.success) {
                    $('#log-content').html(response.data.html);
                }
            });
        }
        
        function searchLogs() {
            const searchTerm = $('#log-search').val().toLowerCase();
            const logLines = $('#log-content pre').html().split('\n');
            
            if (!searchTerm) {
                // Show all logs if no search term
                refreshLogs();
                return;
            }
            
            const filteredLines = logLines.filter(line => 
                line.toLowerCase().includes(searchTerm)
            );
            
            $('#log-content pre').html(filteredLines.join('\n'));
        }
    });
    </script>
    <?php
}

// Section callbacks
function video_library_s3_section_callback() {
    echo '<p>Configure your S3 or DigitalOcean Spaces connection. This is where your video files are stored.</p>';
}

function video_library_display_section_callback() {
    echo '<p>Control how the video library appears on your website.</p>';
}

function video_library_organization_section_callback() {
    echo '<p>Set up path-based organization for easy filtering and content management.</p>';
}

function video_library_analytics_section_callback() {
    echo '<p>Configure video viewing analytics and debug options.</p>';
}

// Field callbacks
function video_library_s3_bucket_callback() {
    $value = get_option('video_library_s3_bucket', '');
    echo '<input type="text" name="video_library_s3_bucket" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">Your S3 bucket name or DigitalOcean Space name</p>';
}

function video_library_s3_region_callback() {
    $value = get_option('video_library_s3_region', 'us-east-1');
    echo '<input type="text" name="video_library_s3_region" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">S3 region (e.g., us-east-1, nyc3 for DigitalOcean)</p>';
}

function video_library_s3_access_key_callback() {
    $value = get_option('video_library_s3_access_key', '');
    echo '<input type="text" name="video_library_s3_access_key" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">S3 Access Key ID</p>';
}

function video_library_s3_secret_key_callback() {
    $value = get_option('video_library_s3_secret_key', '');
    echo '<input type="password" name="video_library_s3_secret_key" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">S3 Secret Access Key</p>';
}

function video_library_s3_endpoint_callback() {
    $value = get_option('video_library_s3_endpoint', '');
    echo '<input type="text" name="video_library_s3_endpoint" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description"><strong>For DigitalOcean Spaces:</strong> https://nyc3.digitaloceanspaces.com (replace "nyc3" with your region)<br>';
    echo '<strong>Important:</strong> Do NOT include your bucket name in the endpoint URL<br>';
    echo '<strong>Examples:</strong><br>';
    echo '• DigitalOcean NYC3: <code>https://nyc3.digitaloceanspaces.com</code><br>';
    echo '• DigitalOcean SGP1: <code>https://sgp1.digitaloceanspaces.com</code><br>';
    echo '• AWS S3: Leave empty to use default AWS endpoints</p>';
}

function video_library_presigned_expiry_callback() {
    $value = get_option('video_library_presigned_expiry', 3600);
    echo '<input type="number" name="video_library_presigned_expiry" value="' . esc_attr($value) . '" min="300" max="86400" />';
    echo '<p class="description">How long video URLs remain valid (300 = 5 minutes, 3600 = 1 hour, 86400 = 24 hours)</p>';
}

function video_library_videos_per_page_callback() {
    $value = get_option('video_library_videos_per_page', 12);
    echo '<input type="number" name="video_library_videos_per_page" value="' . esc_attr($value) . '" min="1" max="50" />';
    echo '<p class="description">Default number of videos to show per page</p>';
}

function video_library_featured_video_callback() {
    $value = get_option('video_library_featured_video', '');
    $videos = get_posts([
        'post_type' => 'video_library_item',
        'post_status' => 'publish',
        'numberposts' => -1
    ]);
    
    echo '<select name="video_library_featured_video">';
    echo '<option value="">None</option>';
    foreach ($videos as $video) {
        $selected = ($value == $video->ID) ? 'selected' : '';
        echo '<option value="' . esc_attr($video->ID) . '" ' . $selected . '>' . esc_html($video->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Select a video to feature prominently</p>';
}

function video_library_enabled_callback() {
    $value = get_option('video_library_enabled', 'true');
    echo '<input type="checkbox" name="video_library_enabled" value="true" ' . checked($value, 'true', false) . ' />';
    echo '<p class="description">Uncheck to temporarily disable the video library</p>';
}

function video_library_suggested_paths_callback() {
    $value = get_option('video_library_suggested_paths', "premium/\ntutorials/\nwebinars/\nlive-streams/");
    echo '<textarea name="video_library_suggested_paths" rows="8" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">List common S3 paths for easy selection when adding videos (one per line)</p>';
}

function video_library_analytics_enabled_callback() {
    $value = get_option('video_library_analytics_enabled', true);
    echo '<input type="checkbox" name="video_library_analytics_enabled" value="1" ' . checked($value, 1, false) . ' />';
    echo '<p class="description">Track video views and user interactions</p>';
}

function video_library_debug_mode_callback() {
    $value = get_option('video_library_debug_mode', false);
    echo '<input type="checkbox" name="video_library_debug_mode" value="1" ' . checked($value, 1, false) . ' />';
    echo '<p class="description">Enable detailed logging for troubleshooting</p>';
}

// Test S3 connection
function video_library_test_s3_connection() {
    $bucket = get_option('video_library_s3_bucket');
    $region = get_option('video_library_s3_region');
    $access_key = get_option('video_library_s3_access_key');
    $secret_key = get_option('video_library_s3_secret_key');
    $endpoint = get_option('video_library_s3_endpoint');

    if (empty($bucket) || empty($access_key) || empty($secret_key)) {
        return [
            'success' => false,
            'message' => 'Please fill in all required S3 settings first.'
        ];
    }

    try {
        // Check if S3 class exists
        if (!class_exists('Video_Library_S3')) {
            return [
                'success' => false,
                'message' => 'S3 integration class not loaded. Please check plugin installation.'
            ];
        }
        
        // Test connection using the S3 integration class
        $result = Video_Library_S3::test_connection();
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'S3 connection successful! Your bucket is accessible.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'S3 connection failed. Please check your credentials and bucket settings.'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'S3 connection error: ' . $e->getMessage()
        ];
    } catch (Error $e) {
        return [
            'success' => false,
            'message' => 'S3 connection fatal error: ' . $e->getMessage()
        ];
    }
}

function video_library_list_bucket_contents() {
    try {
        if (!function_exists('video_library_log')) {
            function video_library_log($message, $level = 'info') {
                error_log("Video Library [$level]: $message");
            }
        }
        
        video_library_log('Starting bucket contents listing from settings page', 'info');
        
        $bucket = get_option('video_library_s3_bucket');
        $access_key = get_option('video_library_s3_access_key');
        $secret_key = get_option('video_library_s3_secret_key');

        if (empty($bucket) || empty($access_key) || empty($secret_key)) {
            video_library_log('S3 credentials not configured for bucket listing', 'error');
            return [
                'success' => false,
                'message' => 'Please fill in all required S3 settings first.'
            ];
        }

        // Check if S3 class exists
        if (!class_exists('Video_Library_S3')) {
            video_library_log('Video_Library_S3 class not found', 'error');
            return [
                'success' => false,
                'message' => 'S3 integration class not loaded. Please check plugin installation.'
            ];
        }

        // Test authentication first
        $auth_test = Video_Library_S3::test_connection();
        
        if (!$auth_test) {
            video_library_log('S3 authentication failed during bucket listing', 'error');
            return [
                'success' => false,
                'message' => 'S3 authentication failed. Please check your credentials.'
            ];
        }
        
        video_library_log('S3 authentication successful, listing bucket contents', 'info');
        
        // Create S3 instance and list files
        $s3 = new Video_Library_S3();
        $files = $s3->list_bucket_files();
        
        if ($files === false) {
            video_library_log('Failed to list bucket contents', 'error');
            return [
                'success' => false,
                'message' => 'Failed to list bucket contents. Check your bucket permissions.'
            ];
        }
        
        $video_extensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v'];
        $video_files = [];
        $other_files = [];
        
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $video_extensions)) {
                $video_files[] = $file;
            } else {
                $other_files[] = $file;
            }
        }
        
        $total_files = count($files);
        $total_videos = count($video_files);
        $total_other = count($other_files);
        
        video_library_log("Bucket listing complete: {$total_files} total files, {$total_videos} videos, {$total_other} other files", 'info');
        
        // Log first few video files as examples
        if ($video_files) {
            $sample_videos = array_slice($video_files, 0, 5);
            video_library_log('Sample video files found: ' . implode(', ', $sample_videos), 'info');
        }
        
        return [
            'success' => true,
            'message' => "✅ Bucket listing successful! Found {$total_files} total files ({$total_videos} videos, {$total_other} other files). Check logs for details."
        ];
        
    } catch (Exception $e) {
        if (function_exists('video_library_log')) {
            video_library_log('Exception during bucket listing: ' . $e->getMessage(), 'error');
        }
        return [
            'success' => false,
            'message' => 'Error listing bucket contents: ' . $e->getMessage()
        ];
    } catch (Error $e) {
        if (function_exists('video_library_log')) {
            video_library_log('Fatal error during bucket listing: ' . $e->getMessage(), 'error');
        }
        return [
            'success' => false,
            'message' => 'Fatal error listing bucket contents: ' . $e->getMessage()
        ];
    }
}

// AJAX handler for getting logs
if (!function_exists('video_library_ajax_get_logs')) {
    add_action('wp_ajax_video_library_get_logs', 'video_library_ajax_get_logs');

    function video_library_ajax_get_logs() {
        try {
            check_ajax_referer('video_library_logs', 'nonce');
            
            $logs = get_transient('video_library_logs');
            $html = '';
            
            if ($logs && is_array($logs)) {
                $html .= '<pre style="margin: 0; font-family: Consolas, monospace; font-size: 12px; white-space: pre-wrap;">';
                foreach (array_slice($logs, -50) as $log) {
                    // Color code log levels
                    $colored_log = $log;
                    if (strpos($log, '[error]') !== false) {
                        $colored_log = '<span style="color: #dc3545;">' . esc_html($log) . '</span>';
                    } elseif (strpos($log, '[warning]') !== false) {
                        $colored_log = '<span style="color: #ffc107;">' . esc_html($log) . '</span>';
                    } elseif (strpos($log, '[info]') !== false) {
                        $colored_log = '<span style="color: #28a745;">' . esc_html($log) . '</span>';
                    } elseif (strpos($log, '[debug]') !== false) {
                        $colored_log = '<span style="color: #6c757d;">' . esc_html($log) . '</span>';
                    } else {
                        $colored_log = esc_html($log);
                    }
                    $html .= $colored_log . "\n";
                }
                $html .= '</pre>';
                $html .= '<p style="margin-top: 10px; font-size: 12px; color: #666;">Showing last 50 log entries. Total logs in memory: ' . count($logs) . '</p>';
            } else {
                $html = '<p style="color: #666; font-style: italic;">No logs available yet. Interact with the plugin to generate logs.</p>';
            }
            
            wp_send_json_success(['html' => $html]);
        } catch (Exception $e) {
            wp_send_json_error('Error getting logs: ' . $e->getMessage());
        }
    }
} 