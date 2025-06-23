<?php
if (!defined('ABSPATH')) exit;

// Add logs page to admin menu
add_action('admin_menu', 'video_library_add_logs_page');

function video_library_add_logs_page() {
    add_options_page(
        'Video Library Logs',
        'Video Library Logs', 
        'manage_options',
        'video-library-logs',
        'video_library_logs_page'
    );
}

// Logs page HTML
function video_library_logs_page() {
    // Handle clear logs action
    if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['logs_nonce'], 'video_library_logs')) {
        $logger = Video_Library_Logger::get_instance();
        $logger->clear_logs();
        echo '<div class="notice notice-success"><p>Logs cleared successfully!</p></div>';
    }
    
    $logger = Video_Library_Logger::get_instance();
    $logs = $logger->get_logs();
    $debug_enabled = get_option('video_library_debug_mode', false);
    ?>
    <div class="wrap">
        <h1>Video Library Debug Logs</h1>
        
        <?php if (!$debug_enabled): ?>
        <div class="notice notice-warning">
            <p>
                <strong>Debug mode is disabled.</strong> 
                <a href="<?php echo admin_url('options-general.php?page=video-library'); ?>">Enable it in settings</a> to start logging.
            </p>
        </div>
        <?php endif; ?>

        <div class="video-library-logs-controls">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('video_library_logs', 'logs_nonce'); ?>
                <input type="submit" name="clear_logs" class="button" value="Clear Logs" 
                       onclick="return confirm('Are you sure you want to clear all logs?');">
            </form>
            
            <button type="button" id="refresh-logs" class="button">Refresh</button>
            
            <button type="button" id="auto-refresh-toggle" class="button">
                <span id="auto-refresh-text">Enable Auto-refresh</span>
            </button>
        </div>

        <div id="logs-container" class="video-library-logs-container">
            <?php if (empty($logs)): ?>
                <div class="no-logs-message">
                    <p>No logs available. <?php echo $debug_enabled ? 'Perform some actions to see logs here.' : 'Enable debug mode to start logging.'; ?></p>
                </div>
            <?php else: ?>
                <div class="logs-header">
                    <strong>Showing <?php echo count($logs); ?> recent log entries:</strong>
                </div>
                <div class="logs-content">
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <div class="log-entry <?php echo video_library_get_log_level_class($log); ?>">
                            <code><?php echo esc_html($log); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .video-library-logs-container {
        margin-top: 20px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    
    .logs-header {
        padding: 10px 15px;
        background: #f6f7f7;
        border-bottom: 1px solid #ccd0d4;
    }
    
    .logs-content {
        max-height: 600px;
        overflow-y: auto;
        padding: 10px 0;
    }
    
    .log-entry {
        padding: 8px 15px;
        border-bottom: 1px solid #f0f0f1;
        font-family: Consolas, Monaco, monospace;
        font-size: 12px;
    }
    
    .log-entry:last-child {
        border-bottom: none;
    }
    
    .log-entry.log-error {
        background-color: #fef7f7;
        color: #d63638;
    }
    
    .log-entry.log-warning {
        background-color: #fffbf0;
        color: #dba617;
    }
    
    .log-entry.log-info {
        background-color: #f6f7f7;
        color: #3c434a;
    }
    
    .log-entry.log-debug {
        background-color: #f0f6ff;
        color: #0073aa;
    }
    
    .no-logs-message {
        padding: 40px 20px;
        text-align: center;
        color: #646970;
    }
    
    .video-library-logs-controls {
        margin: 20px 0;
    }
    
    .video-library-logs-controls .button {
        margin-right: 10px;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let autoRefreshInterval = null;
        let autoRefreshEnabled = false;
        
        // Refresh logs
        $('#refresh-logs').on('click', function() {
            location.reload();
        });
        
        // Auto-refresh toggle
        $('#auto-refresh-toggle').on('click', function() {
            if (autoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                autoRefreshEnabled = false;
                $('#auto-refresh-text').text('Enable Auto-refresh');
                $(this).removeClass('button-primary');
            } else {
                autoRefreshInterval = setInterval(function() {
                    location.reload();
                }, 5000); // Refresh every 5 seconds
                autoRefreshEnabled = true;
                $('#auto-refresh-text').text('Disable Auto-refresh');
                $(this).addClass('button-primary');
            }
        });
    });
    </script>
    <?php
}

// Helper function to determine log level CSS class
function video_library_get_log_level_class($log_entry) {
    if (strpos($log_entry, '[error]') !== false) {
        return 'log-error';
    } elseif (strpos($log_entry, '[warning]') !== false) {
        return 'log-warning';
    } elseif (strpos($log_entry, '[debug]') !== false) {
        return 'log-debug';
    } else {
        return 'log-info';
    }
}

// Add AJAX handler for real-time log updates
add_action('wp_ajax_get_video_library_logs', 'video_library_ajax_get_logs');

function video_library_ajax_get_logs() {
    check_ajax_referer('video_library_logs_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $logger = Video_Library_Logger::get_instance();
    $logs = $logger->get_logs();
    
    wp_send_json_success([
        'logs' => $logs,
        'count' => count($logs)
    ]);
} 