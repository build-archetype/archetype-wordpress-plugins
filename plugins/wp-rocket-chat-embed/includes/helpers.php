<?php
if (!defined('ABSPATH')) exit;

class RocketChatAPI {
    private $host_url;
    private $admin_user;
    private $admin_pass;
    private $auth_token;
    private $user_id;
    private $log_file;
    private $last_request;
    private $last_response;

    public function __construct() {
        $this->host_url = rtrim(get_option('rocket_chat_host_url'), '/');
        $this->admin_user = get_option('rocket_chat_admin_user');
        $this->admin_pass = get_option('rocket_chat_admin_pass');
        
        // Set up direct file logging
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/rocket-chat-api.log';
        
        // Log initialization
        $this->log("API Initialized");
        $this->log("Host URL: " . $this->host_url);
        $this->log("Admin User: " . $this->admin_user);
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";
        // File log
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
        // Plugin debug logger (transient)
        $logs = get_transient('rocket_chat_logs');
        if (!is_array($logs)) $logs = [];
        $logs[] = $log_message;
        if (count($logs) > 100) array_shift($logs);
        set_transient('rocket_chat_logs', $logs, 60 * 60 * 24);
    }

    public function get_last_request() {
        return $this->last_request;
    }
    public function get_last_response() {
        return $this->last_response;
    }

    private function make_request($endpoint, $method = 'GET', $data = null) {
        $url = $this->host_url . '/api/v1/' . ltrim($endpoint, '/');
        $this->last_request = [
            'url' => $url,
            'method' => $method,
            'data' => $data
        ];
        $this->log("Making request to: $url");
        $this->log("Method: $method");
        $json_data = $data ? json_encode($data) : null;
        if ($json_data) {
            $this->log("Raw JSON Body: $json_data");
            $this->log("Data: " . print_r($data, true));
        }
        $headers = [
            'Content-Type: application/json',
            'User-Agent: curl/7.68.0'
        ];
        if ($this->auth_token && $this->user_id) {
            $headers[] = 'X-Auth-Token: ' . $this->auth_token;
            $headers[] = 'X-User-Id: ' . $this->user_id;
        }
        $this->log("Request Headers: " . print_r($headers, true));
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($json_data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        $this->last_response = [
            'response' => $response,
            'info' => $info,
            'error' => $error
        ];
        $this->log("Raw cURL Response: " . print_r($this->last_response, true));
        if ($error) {
            $this->log("cURL Error: $error");
            return [
                'success' => false,
                'error' => $error,
                'curl_error' => true
            ];
        }
        $body = json_decode($response, true);
        $status = isset($info['http_code']) ? $info['http_code'] : 0;
        $this->log("Response status: $status");
        $this->log("Response body: " . print_r($body, true));
        if ($status !== 200) {
            $this->log("API Error: Status $status, Response: " . print_r($body, true));
            return [
                'success' => false,
                'status' => $status,
                'response' => $body
            ];
        }
        return $body;
    }

    public function login() {
        $this->log("Attempting login");
        $data = [
            'user' => $this->admin_user,
            'password' => $this->admin_pass
        ];
        $response = $this->make_request('login', 'POST', $data);
        if ($response && isset($response['data']['authToken']) && isset($response['data']['userId'])) {
            $this->auth_token = $response['data']['authToken'];
            $this->user_id = $response['data']['userId'];
            $this->log("Login successful. userId: " . $this->user_id);
            return true;
        }
        $this->log("Login failed");
        return false;
    }

    // --- Encryption helpers ---
    private function encrypt_password($password) {
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'default_key';
        $ivlen = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $ciphertext);
    }
    private function decrypt_password($encrypted) {
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'default_key';
        $data = base64_decode($encrypted);
        $ivlen = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivlen);
        $ciphertext = substr($data, $ivlen);
        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, 0, $iv);
    }

    public function create_user($username, $email, $name, $wp_user_id = null) {
        $this->log("Creating user: $username");
        if (!$this->auth_token && !$this->login()) {
            $this->log("Failed to login before creating user");
            return false;
        }
        $generated_password = wp_generate_password();
        $this->log("Generated password for new user: $username");
        $data = [
            'email' => $email,
            'name' => $name,
            'password' => $generated_password,
            'username' => $username,
            'requirePasswordChange' => false,
            'sendWelcomeEmail' => true,
            'verified' => true
        ];
        $result = $this->make_request('users.create', 'POST', $data);
        if ($result && $wp_user_id) {
            $this->log("Storing encrypted password for WordPress user ID: $wp_user_id");
            $encrypted = $this->encrypt_password($generated_password);
            update_user_meta($wp_user_id, 'rocket_chat_password', $encrypted);
            $this->log("Successfully stored encrypted password");
        }
        if ($result) {
            $this->log("User created successfully");
        } else {
            $this->log("Failed to create user");
        }
        return $result;
    }

    public function create_token($username) {
        $this->log("Creating token for user: $username");
        if (!$this->auth_token && !$this->login()) {
            $this->log("Failed to login before creating token");
            return false;
        }

        $data = ['username' => $username];
        $result = $this->make_request('users.createToken', 'POST', $data);
        if ($result) {
            $this->log("Token created successfully");
            $this->log("Token details: " . print_r($result['data'], true));
        } else {
            $this->log("Failed to create token");
        }
        return $result;
    }

    public function get_user($username) {
        $this->log("Getting user info for: $username");
        if (!$this->auth_token && !$this->login()) {
            $this->log("Failed to login before getting user");
            return false;
        }

        $result = $this->make_request('users.info?username=' . urlencode($username));
        if ($result) {
            $this->log("User info retrieved successfully");
        } else {
            $this->log("Failed to get user info");
        }
        return $result;
    }

    public function login_as_user($username, $encrypted_password) {
        $this->log("Attempting to decrypt password for user: $username");
        $password = $this->decrypt_password($encrypted_password);
        if (!$password) {
            $this->log("Failed to decrypt password");
            return false;
        }
        $this->log("Successfully decrypted password");
        
        $this->log("Attempting login as user: $username");
        $data = [
            'user' => $username,
            'password' => $password
        ];
        $response = $this->make_request('login', 'POST', $data);
        if ($response && isset($response['data']['authToken']) && isset($response['data']['userId'])) {
            $this->auth_token = $response['data']['authToken'];
            $this->user_id = $response['data']['userId'];
            $this->log("User login successful. userId: " . $this->user_id);
            return true;
        }
        $this->log("User login failed");
        return false;
    }

    // Add a getter for the last user auth token
    public function get_user_auth_token() {
        return $this->auth_token;
    }
}

function get_rocket_chat_api() {
    static $api = null;
    if ($api === null) {
        $api = new RocketChatAPI();
    }
    return $api;
}

// Add test function
function test_rocket_chat_flow($username) {
    $api = get_rocket_chat_api();
    $current_user = wp_get_current_user();
    $wp_user_id = get_current_user_id();
    
    rocket_chat_log("Starting test flow for user: $username", 'info');
    
    // Step 1: Check if user exists
    rocket_chat_log("Step 1: Checking if user exists", 'info');
    $rc_user = $api->get_user($username);
    if (!$rc_user) {
        rocket_chat_log("User does not exist, creating new user", 'info');
        $create_result = $api->create_user(
            $current_user->user_login,
            $current_user->user_email,
            $current_user->display_name,
            $wp_user_id
        );
        if (!$create_result) {
            rocket_chat_log("Failed to create user", 'error');
            return false;
        }
        rocket_chat_log("User created successfully", 'info');
    } else {
        rocket_chat_log("User exists", 'info');
    }
    
    // Step 2: Get encrypted password
    rocket_chat_log("Step 2: Getting encrypted password", 'info');
    $encrypted_password = get_user_meta($wp_user_id, 'rocket_chat_password', true);
    if (!$encrypted_password) {
        rocket_chat_log("No encrypted password found, creating new password", 'info');
        // Generate a new password
        $new_password = wp_generate_password();
        // Update user in Rocket.Chat with new password
        $update_result = $api->make_request('users.update', 'POST', [
            'userId' => $rc_user['user']['_id'],
            'data' => [
                'password' => $new_password
            ]
        ]);
        if (!$update_result) {
            rocket_chat_log("Failed to update user password", 'error');
            return false;
        }
        // Store the encrypted password
        $encrypted_password = $api->encrypt_password($new_password);
        update_user_meta($wp_user_id, 'rocket_chat_password', $encrypted_password);
        rocket_chat_log("New password created and stored", 'info');
    } else {
        rocket_chat_log("Found encrypted password", 'info');
    }
    
    // Step 3: Login as user
    rocket_chat_log("Step 3: Logging in as user", 'info');
    $login_success = $api->login_as_user($username, $encrypted_password);
    if (!$login_success) {
        rocket_chat_log("Failed to login as user", 'error');
        return false;
    }
    rocket_chat_log("Successfully logged in as user", 'info');
    
    // Step 4: Generate token
    rocket_chat_log("Step 4: Generating token", 'info');
    $token_result = $api->create_token($username);
    if (!$token_result || !isset($token_result['data']['authToken'])) {
        rocket_chat_log("Failed to generate token", 'error');
        return false;
    }
    rocket_chat_log("Successfully generated token", 'info');
    
    rocket_chat_log("Test flow completed successfully", 'info');
    return true;
} 

// External Control Functions
function set_chat_state($state) {
    $old_state = get_option('chat_open', 'true');
    $new_state = $state ? 'true' : 'false';
    
    update_option('chat_open', $new_state);
    
    // Trigger action hook when state changes
    if ($old_state !== $new_state) {
        do_action('rocket_chat_state_changed', $new_state === 'true');
        rocket_chat_log("Chat state changed from {$old_state} to {$new_state}", 'info');
    }
}

function should_display_rocket_chat() {
    $chat_open = get_option('chat_open', 'true') === 'true';
    $user_logged_in = is_user_logged_in();
    $has_required_settings = !empty(get_option('rocket_chat_host_url'));
    
    // Default display logic
    $should_display = $chat_open && $user_logged_in && $has_required_settings;
    
    // Allow filtering of display logic
    return apply_filters('rocket_chat_should_display', $should_display);
}

function get_chat_state() {
    return get_option('chat_open', 'true') === 'true';
}

/**
 * License Management Functions
 */

// Check if premium features are active
function rce_is_premium_active() {
    // Check for free access override first
    if (rce_has_free_access()) {
        return true;
    }
    
    $license_key = get_option('rce_license_key');
    $license_status = get_option('rce_license_status');
    $last_check = get_option('rce_license_last_check', 0);
    
    // If no license key, return false
    if (empty($license_key)) {
        return false;
    }
    
    // Recheck license every 24 hours
    if (time() - $last_check > 86400) {
        rce_validate_license($license_key);
    }
    
    return $license_status === 'active';
}

// Check for free access override
function rce_has_free_access() {
    // Option 1: Check for special option (you can set this for specific users)
    if (get_option('rce_free_access_enabled')) {
        return true;
    }
    
    // Option 2: Check for specific user meta or capability
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->has_cap('rce_free_access')) {
        return true;
    }
    
    // Option 3: Check for magic constant (you can define this in wp-config.php)
    if (defined('RCE_FREE_ACCESS') && RCE_FREE_ACCESS === true) {
        return true;
    }
    
    return false;
}

// Validate license with Lemon Squeezy
function rce_validate_license($license_key) {
    if (empty($license_key)) {
        update_option('rce_license_status', 'invalid');
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
    
    update_option('rce_license_last_check', time());
    
    if (is_wp_error($response)) {
        // On API error, keep existing status if it was active
        $current_status = get_option('rce_license_status');
        if ($current_status !== 'active') {
            update_option('rce_license_status', 'error');
        }
        return $current_status === 'active';
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['data']['attributes']['status']) && $data['data']['attributes']['status'] === 'active') {
        update_option('rce_license_status', 'active');
        update_option('rce_license_data', $data['data']['attributes']);
        return true;
    } else {
        update_option('rce_license_status', 'invalid');
        delete_option('rce_license_data');
        return false;
    }
}

// Get license status message
function rce_get_license_status_message() {
    if (rce_has_free_access()) {
        return ['status' => 'free', 'message' => 'Free access enabled - all premium features unlocked!'];
    }
    
    $license_key = get_option('rce_license_key');
    $license_status = get_option('rce_license_status');
    
    if (empty($license_key)) {
        return ['status' => 'none', 'message' => 'No license key entered. Premium features are disabled.'];
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
add_action('wp_ajax_rce_activate_license', 'rce_handle_license_activation');
function rce_handle_license_activation() {
    check_ajax_referer('rce_license_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $license_key = sanitize_text_field($_POST['license_key']);
    
    if (empty($license_key)) {
        wp_send_json_error('License key is required');
    }
    
    update_option('rce_license_key', $license_key);
    $is_valid = rce_validate_license($license_key);
    
    if ($is_valid) {
        wp_send_json_success(rce_get_license_status_message());
    } else {
        wp_send_json_error(rce_get_license_status_message()['message']);
    }
}

// Premium feature wrapper
function rce_premium_feature($callback, $fallback_message = null) {
    if (rce_is_premium_active()) {
        return call_user_func($callback);
    } else {
        if ($fallback_message) {
            return '<div class="rce-upgrade-notice">' . $fallback_message . ' <a href="https://your-site.com/upgrade" target="_blank">Upgrade to Premium</a></div>';
        }
        return false;
    }
} 