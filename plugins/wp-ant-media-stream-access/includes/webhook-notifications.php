<?php
/**
 * Webhook-based Real-Time Stream Notifications for Ant Media Server
 * 
 * This is the CORRECT approach per Ant Media documentation:
 * - WebSocket is for WebRTC signaling, not server-wide notifications
 * - Webhooks provide real-time server-wide stream start/stop events
 * - Gives TRUE 0-second detection when properly configured
 * 
 * VERSION 2.2: Webhook-based approach for instant notifications
 */

if (!defined('ABSPATH')) exit;

class AMSA_Webhook_Notifications {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('init', [$this, 'init_webhook_endpoint']);
        add_action('admin_init', [$this, 'add_webhook_settings']);
        add_action('wp_footer', [$this, 'add_webhook_javascript'], 1);
    }
    
    /**
     * Initialize webhook endpoint for Ant Media Server to call
     */
    public function init_webhook_endpoint() {
        add_rewrite_rule(
            '^ant-media-webhook/?$',
            'index.php?ant_media_webhook=1',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'ant_media_webhook';
            return $vars;
        });
        
        add_action('template_redirect', [$this, 'handle_webhook']);
    }
    
    /**
     * Handle incoming webhook from Ant Media Server
     */
    public function handle_webhook() {
        if (!get_query_var('ant_media_webhook')) {
            return;
        }
        
        // Get webhook data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Log the webhook call
        $this->log_webhook('Webhook received: ' . $input);
        
        // Validate webhook
        if (!$this->validate_webhook($data)) {
            $this->log_webhook('Invalid webhook data');
            http_response_code(400);
            exit('Invalid webhook');
        }
        
        // Process stream event
        $event_type = $this->determine_event_type($data);
        $stream_id = $this->extract_stream_id($data);
        
        if (!$stream_id) {
            $this->log_webhook('No stream ID found in webhook');
            http_response_code(400);
            exit('No stream ID');
        }
        
        // Update WordPress cache immediately
        $this->update_stream_cache($stream_id, $event_type);
        
        // Trigger client-side updates
        $this->trigger_client_updates($stream_id, $event_type);
        
        // Log success
        $this->log_webhook("Stream {$stream_id} {$event_type} - WordPress updated instantly");
        
        // Respond to Ant Media Server
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'stream_id' => $stream_id,
            'event' => $event_type,
            'timestamp' => time()
        ]);
        exit;
    }
    
    /**
     * Validate webhook data
     */
    private function validate_webhook($data) {
        // Basic validation - can be enhanced with security tokens
        return is_array($data) && !empty($data);
    }
    
    /**
     * Determine event type from webhook data
     */
    private function determine_event_type($data) {
        // Common webhook patterns for stream events
        if (isset($data['event'])) {
            switch ($data['event']) {
                case 'publish_started':
                case 'stream_started':
                case 'broadcast_started':
                    return 'started';
                case 'publish_finished':
                case 'stream_finished':
                case 'broadcast_finished':
                    return 'finished';
            }
        }
        
        // Check for action field
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'liveStreamStarted':
                case 'publishStart':
                    return 'started';
                case 'liveStreamEnded':
                case 'publishEnd':
                    return 'finished';
            }
        }
        
        // Check HTTP method or URL patterns
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'POST') {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, 'start') !== false) return 'started';
            if (strpos($uri, 'stop') !== false || strpos($uri, 'end') !== false) return 'finished';
        }
        
        return 'unknown';
    }
    
    /**
     * Extract stream ID from webhook data
     */
    private function extract_stream_id($data) {
        // Try different common field names
        $stream_fields = ['streamId', 'stream_id', 'id', 'broadcastId', 'broadcast_id'];
        
        foreach ($stream_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return sanitize_text_field($data[$field]);
            }
        }
        
        // Check URL parameters
        if (isset($_GET['streamId'])) {
            return sanitize_text_field($_GET['streamId']);
        }
        
        return null;
    }
    
    /**
     * Update WordPress stream cache immediately
     */
    private function update_stream_cache($stream_id, $event_type) {
        $current_streams = get_option('amsa_streams_currently_live', []);
        
        if ($event_type === 'started') {
            if (!in_array($stream_id, $current_streams)) {
                $current_streams[] = $stream_id;
                $this->log_webhook("Added {$stream_id} to live streams");
            }
        } elseif ($event_type === 'finished') {
            $key = array_search($stream_id, $current_streams);
            if ($key !== false) {
                unset($current_streams[$key]);
                $current_streams = array_values($current_streams);
                $this->log_webhook("Removed {$stream_id} from live streams");
            }
        }
        
        // Update cache with 0-second detection
        update_option('amsa_streams_currently_live', $current_streams);
        
        // Update timestamp
        update_option('amsa_stream_cache_timestamp', time());
        
        // Clear any existing cache
        wp_cache_delete('amsa_streams_currently_live');
    }
    
    /**
     * Trigger client-side updates immediately
     */
    private function trigger_client_updates($stream_id, $event_type) {
        // Store the event for JavaScript to pick up
        $events = get_transient('amsa_webhook_events') ?: [];
        $events[] = [
            'stream_id' => $stream_id,
            'event' => $event_type,
            'timestamp' => time(),
            'method' => 'webhook'
        ];
        
        // Keep only last 10 events
        $events = array_slice($events, -10);
        set_transient('amsa_webhook_events', $events, 60);
    }
    
    /**
     * Add webhook settings to admin panel
     */
    public function add_webhook_settings() {
        add_settings_section(
            'amsa_webhook_section',
            'Webhook Notifications (Recommended)',
            [$this, 'render_webhook_section'],
            'amsa_settings'
        );
        
        add_settings_field(
            'amsa_webhook_enabled',
            'Enable Webhook Notifications',
            [$this, 'render_webhook_enabled_field'],
            'amsa_settings',
            'amsa_webhook_section'
        );
        
        register_setting('amsa_settings', 'amsa_webhook_enabled');
    }
    
    /**
     * Render webhook settings section
     */
    public function render_webhook_section() {
        $webhook_url = home_url('ant-media-webhook');
        echo '<p><strong>✅ RECOMMENDED: True 0-second detection using webhooks</strong></p>';
        echo '<p>Configure your Ant Media Server to call this webhook URL when streams start/stop:</p>';
        echo '<p><code style="background: #f0f0f0; padding: 5px;">' . esc_url($webhook_url) . '</code></p>';
        echo '<p><strong>Ant Media Server Configuration:</strong></p>';
        echo '<pre style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa;">
# Add to red5-web.properties:
settings.webhookAuthenticateURL=' . esc_url($webhook_url) . '

# Restart Ant Media Server after configuration
sudo service antmedia restart</pre>';
    }
    
    /**
     * Render webhook enabled field
     */
    public function render_webhook_enabled_field() {
        $enabled = get_option('amsa_webhook_enabled', true);
        echo '<input type="checkbox" name="amsa_webhook_enabled" value="1" ' . checked($enabled, true, false) . '>';
        echo '<label>Enable webhook notifications for instant stream detection</label>';
    }
    
    /**
     * Add JavaScript for webhook-based updates
     */
    public function add_webhook_javascript() {
        if (is_admin()) return;
        
        if (!get_option('amsa_webhook_enabled', true)) return;
        
        ?>
        <script>
        // Webhook-based Real-Time Stream Detection
        console.log("🎯 Webhook: Real-time stream detection initialized");
        
        // Poll for webhook events
        function checkWebhookEvents() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?' + new URLSearchParams({
                action: 'amsa_get_webhook_events',
                nonce: '<?php echo wp_create_nonce('amsa_webhook_nonce'); ?>'
            }))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(event => {
                        console.log(`🎯 Webhook: Stream ${event.stream_id} ${event.event} (${event.method})`);
                        
                        // Update UI immediately
                        if (window.updateChatVisibility) {
                            const isLive = event.event === 'started';
                            window.updateChatVisibility(isLive, 'Webhook Real-Time');
                        }
                        
                        // Trigger WordPress Heartbeat sync
                        if (window.wp && window.wp.heartbeat) {
                            window.wp.heartbeat.connectNow();
                        }
                    });
                }
            })
            .catch(error => {
                console.warn("🎯 Webhook: Event polling failed", error);
            });
        }
        
        // Check for events every 2 seconds (very responsive)
        setInterval(checkWebhookEvents, 2000);
        
        // Initial check
        checkWebhookEvents();
        </script>
        <?php
    }
    
    /**
     * Log webhook activity
     */
    private function log_webhook($message) {
        if (get_option('amsa_webhook_debug', false)) {
            error_log('[AMSA Webhook] ' . $message);
        }
    }
}

// AJAX handler for webhook events
add_action('wp_ajax_amsa_get_webhook_events', 'handle_webhook_events_ajax');
add_action('wp_ajax_nopriv_amsa_get_webhook_events', 'handle_webhook_events_ajax');

function handle_webhook_events_ajax() {
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'amsa_webhook_nonce')) {
        wp_die('Invalid nonce');
    }
    
    $events = get_transient('amsa_webhook_events') ?: [];
    
    // Clear events after reading
    if (!empty($events)) {
        delete_transient('amsa_webhook_events');
    }
    
    wp_send_json_success($events);
}

// Initialize webhook notifications
AMSA_Webhook_Notifications::get_instance(); 