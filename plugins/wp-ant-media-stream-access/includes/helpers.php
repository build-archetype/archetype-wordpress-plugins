<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple JWT Implementation for Ant Media
 * Based on the JWT standard but simplified for our use case
 */
class SimpleJWT {
    
    public static function encode($payload, $key, $alg = 'HS256') {
        $header = json_encode(['typ' => 'JWT', 'alg' => $alg]);
        $payload = json_encode($payload);
        
        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $key, true);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    public static function decode($jwt, $key) {
        $parts = explode('.', $jwt);
        if (count($parts) != 3) {
            return false;
        }
        
        $header = json_decode(self::base64UrlDecode($parts[0]), true);
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        $signature = self::base64UrlDecode($parts[2]);
        
        $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], $key, true);
        
        if (hash_equals($signature, $expectedSignature)) {
            return $payload;
        }
        
        return false;
    }
    
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

/**
 * User Tier Detection Logic
 */
function amsa_amsa_get_user_tier() {
    if (!is_user_logged_in()) {
        return null;
    }
    
    $user = wp_get_current_user();
    
    // Check user roles first
    if (in_array('platinum', $user->roles)) {
        return 'platinum';
    }
    if (in_array('gold', $user->roles)) {
        return 'gold';
    }
    if (in_array('silver', $user->roles)) {
        return 'silver';
    }
    
    // Check user meta as fallback
    $user_tier = get_user_meta($user->ID, 'stream_tier', true);
    if (in_array($user_tier, ['platinum', 'gold', 'silver'])) {
        return $user_tier;
    }
    
    // Default tier for logged-in users
    return 'silver';
}

/**
 * Stream Selection Logic
 */
function get_stream_id_for_user() {
    $tier = amsa_get_user_tier();
    
    if (!$tier) {
        return null; // No access for non-logged-in users
    }
    
    $streams_config = json_decode(get_option('ant_media_streams_config', '{}'), true);
    
    if (isset($streams_config[$tier])) {
        return $streams_config[$tier];
    }
    
    // Fallback to default stream configuration
    $default_streams = [
        'platinum' => 'stream_platinum',
        'gold' => 'stream_gold',
        'silver' => 'stream_silver'
    ];
    
    return isset($default_streams[$tier]) ? $default_streams[$tier] : null;
}

/**
 * JWT Token Generation
 */
function generate_stream_token($stream_id, $user_id = null) {
    $secret = get_option('ant_media_jwt_secret');
    $expiry = intval(get_option('ant_media_token_expiry', 3600));
    
    if (empty($secret)) {
        ant_media_log('JWT secret not configured', 'error');
        return false;
    }
    
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $current_user = get_userdata($user_id);
    $tier = amsa_get_user_tier();
    
    $payload = [
        'streamName' => $stream_id,
        'exp' => time() + $expiry,
        'iat' => time(),
        'userId' => $user_id,
        'username' => $current_user ? $current_user->user_login : 'guest',
        'tier' => $tier,
        'type' => 'play' // or 'publish' for streaming
    ];
    
    // Allow filtering of JWT payload
    $payload = apply_filters('ant_media_jwt_payload', $payload, $stream_id, $user_id);
    
    $token = SimpleJWT::encode($payload, $secret);
    
    ant_media_log("Generated JWT token for stream: {$stream_id}, user: {$user_id}, tier: {$tier}", 'info');
    
    return $token;
}

/**
 * Validate if user should have access to stream
 */
function user_can_access_stream($stream_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false; // No access for non-logged-in users
    }
    
    $user_stream_id = get_stream_id_for_user();
    $has_access = ($user_stream_id === $stream_id);
    
    // Allow filtering of access control
    return apply_filters('ant_media_user_can_access_stream', $has_access, $stream_id, $user_id);
}

/**
 * Get stream URL with token
 */
function get_stream_url($stream_id, $format = 'hls') {
    $server_url = rtrim(get_option('ant_media_server_url'), '/');
    $token = generate_stream_token($stream_id);
    
    if (!$token) {
        return false;
    }
    
    // Different URL formats based on stream type
    switch ($format) {
        case 'hls':
            return "{$server_url}/live/{$stream_id}/playlist.m3u8?token={$token}";
        case 'webrtc':
            return "{$server_url}/live/{$stream_id}/webrtc?token={$token}";
        case 'embed':
            return "{$server_url}/live/{$stream_id}/embed?token={$token}";
        default:
            return "{$server_url}/live/{$stream_id}/playlist.m3u8?token={$token}";
    }
}

/**
 * External Control Functions
 */
function set_ant_media_stream_state($state) {
    $old_state = get_option('ant_media_enabled', 'true');
    $new_state = $state ? 'true' : 'false';
    
    update_option('ant_media_enabled', $new_state);
    
    // Trigger action hook when state changes
    if ($old_state !== $new_state) {
        do_action('ant_media_state_changed', $new_state === 'true');
        ant_media_log("Ant Media stream state changed from {$old_state} to {$new_state}", 'info');
    }
}

function should_display_ant_media_stream() {
    $stream_enabled = get_option('ant_media_enabled', 'true') === 'true';
    $has_server_url = !empty(get_option('ant_media_server_url'));
    
    // Default display logic - just need server URL
    $should_display = $stream_enabled && $has_server_url;
    
    // Allow filtering of display logic
    return apply_filters('ant_media_should_display_stream', $should_display);
}

function get_ant_media_stream_state() {
    return get_option('ant_media_enabled', 'true') === 'true';
}

/**
 * Stream URL Builder
 */
function build_ant_media_iframe_url($stream_id, $options = []) {
    $server_url = $options['server_url'] ?? get_option('ant_media_server_url');
    $app_name = $options['app_name'] ?? get_option('ant_media_app_name', 'live');
    
    if (empty($server_url) || empty($stream_id)) {
        return false;
    }
    
    // Clean up server URL - remove trailing slashes and fix any colon issues
    $server_url = rtrim($server_url, '/');
    // Fix common copy/paste error where URLs have extra colon like "domain.com:/path"
    // Split on protocol to avoid affecting it
    if (preg_match('/^(https?:\/\/)(.+)/', $server_url, $matches)) {
        $protocol = $matches[1];
        $rest = $matches[2];
        // Fix any colon-slash that's not part of protocol
        $rest = str_replace(':/', '/', $rest);
        $server_url = $protocol . $rest;
    }
    
    // Build iframe URL parameters - use 'id' to match Ant Media's standard format
    $params = [
        'id' => $stream_id,
        'autoplay' => $options['autoplay'] ?? 'true',
        'mute' => $options['muted'] ?? 'true',
        'playOrder' => $options['play_order'] ?? 'webrtc,hls'
    ];
    
    // Add token if provided
    if (!empty($options['token'])) {
        $params['token'] = $options['token'];
    }
    
    $iframe_url = $server_url . '/' . $app_name . '/play.html?' . http_build_query($params);
    
    ant_media_log("Built iframe URL for stream {$stream_id}: {$iframe_url}", 'debug');
    
    return $iframe_url;
}

/**
 * Stream Status Check
 */
function check_ant_media_stream_status($stream_id, $server_url = null, $app_name = null) {
    if (!$server_url) {
        $server_url = get_option('ant_media_server_url');
    }
    
    if (!$app_name) {
        $app_name = get_option('ant_media_app_name', 'live');
    }
    
    if (empty($server_url) || empty($stream_id)) {
        ant_media_log("❌ API: Stream status check failed: missing server_url or stream_id", 'error');
        return false;
    }
    
    $server_url = rtrim($server_url, '/');
    $api_url = $server_url . '/' . $app_name . '/rest/v2/broadcasts/' . $stream_id;
    
    ant_media_log("🌐 API: Calling {$api_url}", 'debug');
    
    // FORCE DEBUG - Show API URL being called
    error_log("🚨 ANT MEDIA DEBUG - API URL: {$api_url}");
    
    $start_time = microtime(true);
    $response = wp_remote_get($api_url, [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json'
        ]
    ]);
    $end_time = microtime(true);
    $api_duration = round(($end_time - $start_time) * 1000, 2);
    
    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        ant_media_log("❌ API: Request failed for {$stream_id} after {$api_duration}ms: {$error_msg}", 'error');
        // FALLBACK FOR LIVE STREAMING: If API fails, assume offline to avoid false positives
        ant_media_log("⚠️  API: Defaulting to FALSE (offline) due to API error - better than false positive", 'warning');
        return false;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $response_size = strlen($body);
    
    ant_media_log("📡 API: Response in {$api_duration}ms - HTTP {$http_code}, {$response_size} bytes", 'info');
    
    // Log raw response for debugging
    ant_media_log("📄 API: Raw response body: " . substr($body, 0, 500) . ($response_size > 500 ? '...' : ''), 'debug');
    
    $data = json_decode($body, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        ant_media_log("❌ API: Invalid JSON response for {$stream_id}: " . json_last_error_msg(), 'error');
        ant_media_log("📄 API: Raw non-JSON body: " . $body, 'error');
        return false; // Failsafe: assume offline for invalid responses
    }
    
    // Log the full parsed API response for debugging
    ant_media_log("📊 API: Parsed response for {$stream_id}: " . json_encode($data), 'debug');
    
    // FORCE LOG FOR DEBUGGING - always show this even in production
    error_log("🚨 ANT MEDIA DEBUG - Stream {$stream_id} API Response: " . json_encode($data));
    
    if (isset($data['status'])) {
        $status = $data['status'];
        ant_media_log("🎯 API: Stream {$stream_id} status: '{$status}'", 'info');
        
        // CLEAN LOGIC: Only trust the status field from Ant Media Server
        // The status field is the authoritative source of truth
        $live_statuses = [
            'broadcasting', 'live', 'playing', 'active', 'started', 
            'publish_started', 'stream_started', 'online', 'ready',
            'created', 'publishing'
        ];
        $is_live = in_array(strtolower($status), $live_statuses);
        
        // Log the decision
        ant_media_log("✅ API: Stream {$stream_id} status '{$status}' = " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
        
        // REMOVED: Old complex logic that checked bitrate/speed/viewers
        // That logic caused false positives when streams had finished 
        // but still had leftover metrics data
        
        return $is_live;
    } else {
        ant_media_log("⚠️  API: No 'status' field in response for {$stream_id}", 'warning');
        ant_media_log("📄 API: Available fields: " . implode(', ', array_keys($data ?: [])), 'warning');
        
        // Check for error messages
        if (isset($data['message'])) {
            ant_media_log("📢 API: Message from server: {$data['message']}", 'warning');
        }
        if (isset($data['success'])) {
            ant_media_log("🎯 API: Success flag: " . ($data['success'] ? 'true' : 'false'), 'warning');
        }
        
        // Default to false if no status found
        ant_media_log("❌ API: Defaulting to OFFLINE due to missing status field", 'warning');
        return false;
    }
}

/**
 * Logging function
 */
function ant_media_log($message, $level = 'info') {
    if (!get_option('ant_media_debug_mode', false)) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message";
    
    // Store in transient for admin display
    $logs = get_transient('ant_media_logs');
    if (!is_array($logs)) $logs = [];
    $logs[] = $log_message;
    if (count($logs) > 100) array_shift($logs);
    set_transient('ant_media_logs', $logs, 60 * 60 * 24);
    
    // Also log to WordPress debug log if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Ant Media Stream: $log_message");
    }
}

/**
 * Get time ago string
 */
function amsa_get_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

/**
 * License Management Functions
 */

// Check if premium features are active
function amsa_is_premium_active() {
    // Check for free access override first
    if (amsa_has_free_access()) {
        return true;
    }
    
    $license_key = get_option('amsa_license_key');
    $license_status = get_option('amsa_license_status');
    $last_check = get_option('amsa_license_last_check', 0);
    
    // If no license key, return false
    if (empty($license_key)) {
        return false;
    }
    
    // Recheck license every 24 hours
    if (time() - $last_check > 86400) {
        amsa_validate_license($license_key);
    }
    
    return $license_status === 'active';
}

// Check for free access override
function amsa_has_free_access() {
    // Option 1: Check for special option (you can set this for specific users)
    if (get_option('amsa_free_access_enabled')) {
        return true;
    }
    
    // Option 2: Check for specific user meta or capability
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->has_cap('amsa_free_access')) {
        return true;
    }
    
    // Option 3: Check for magic constant (you can define this in wp-config.php)
    if (defined('AMSA_FREE_ACCESS') && AMSA_FREE_ACCESS === true) {
        return true;
    }
    
    return false;
}

// Validate license with Lemon Squeezy
function amsa_validate_license($license_key) {
    if (empty($license_key)) {
        update_option('amsa_license_status', 'invalid');
        return false;
    }
    
    // Lemon Squeezy API endpoint for license validation
    $api_url = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    
    $response = wp_remote_post($api_url, [
        'headers' => [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ],
        'body' => json_encode([
            'data' => [
                'type' => 'license-key-instances',
                'attributes' => [
                    'license_key' => $license_key
                ]
            ]
        ]),
        'timeout' => 30
    ]);
    
    update_option('amsa_license_last_check', time());
    
    if (is_wp_error($response)) {
        // On API error, keep existing status if it was active
        $current_status = get_option('amsa_license_status');
        if ($current_status !== 'active') {
            update_option('amsa_license_status', 'error');
        }
        return $current_status === 'active';
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['data']['attributes']['status']) && $data['data']['attributes']['status'] === 'active') {
        update_option('amsa_license_status', 'active');
        update_option('amsa_license_data', $data['data']['attributes']);
        return true;
    } else {
        update_option('amsa_license_status', 'invalid');
        delete_option('amsa_license_data');
        return false;
    }
}

// Get license status message
function amsa_get_license_status_message() {
    if (amsa_has_free_access()) {
        return ['status' => 'free', 'message' => 'Free access enabled - all premium features unlocked!'];
    }
    
    $license_key = get_option('amsa_license_key');
    $license_status = get_option('amsa_license_status');
    
    if (empty($license_key)) {
        return ['status' => 'none', 'message' => 'No license key entered. Limited to single tier streaming.'];
    }
    
    switch ($license_status) {
        case 'active':
            return ['status' => 'active', 'message' => 'License active - premium features unlocked!'];
        case 'invalid':
            return ['status' => 'invalid', 'message' => 'Invalid license key. Please check your key.'];
        case 'error':
            return ['status' => 'error', 'message' => 'License validation error. Trying again later.'];
        default:
            return ['status' => 'unknown', 'message' => 'License status unknown. Validating...'];
    }
}

// AJAX handler for license activation
add_action('wp_ajax_amsa_activate_license', 'amsa_handle_license_activation');
function amsa_handle_license_activation() {
    check_ajax_referer('amsa_license_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $license_key = sanitize_text_field($_POST['license_key']);
    
    if (empty($license_key)) {
        wp_send_json_error('License key is required');
    }
    
    update_option('amsa_license_key', $license_key);
    $is_valid = amsa_validate_license($license_key);
    
    if ($is_valid) {
        wp_send_json_success(amsa_get_license_status_message());
    } else {
        wp_send_json_error(amsa_get_license_status_message()['message']);
    }
}

// Premium feature wrapper
function amsa_premium_feature($callback, $fallback_message = null) {
    if (amsa_is_premium_active()) {
        return call_user_func($callback);
    } else {
        if ($fallback_message) {
            return '<div class="amsa-upgrade-notice">' . $fallback_message . ' <a href="https://your-site.com/upgrade" target="_blank">Upgrade to Premium</a></div>';
        }
        return false;
    }
} 