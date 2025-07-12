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
        // Properly construct the URL based on method
        if ($method === 'GET' && strpos($endpoint, '?') !== false) {
            // For GET requests with query parameters, endpoint already contains the full path
            $url = $this->host_url . '/api/v1/' . ltrim($endpoint, '/');
        } else {
            $url = $this->host_url . '/api/v1/' . ltrim($endpoint, '/');
        }
        
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
    public function encrypt_password($password) {
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

    public function create_user($username, $email, $name, $wp_user_id = null, $channels = null) {
        $this->log("Creating user: $username");
        if (!$this->auth_token && !$this->login()) {
            $this->log("Failed to login before creating user");
            return false;
        }

        // Get unique username if the preferred one is taken
        if ($wp_user_id) {
            $unique_username = $this->get_unique_username($username, $wp_user_id);
            if ($unique_username !== $username) {
                $this->log("Using unique username: $unique_username instead of: $username");
                $username = $unique_username;
            }
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
            $this->log("Storing encrypted password and username for WordPress user ID: $wp_user_id");
            $encrypted = $this->encrypt_password($generated_password);
            update_user_meta($wp_user_id, 'rocket_chat_password', $encrypted);
            update_user_meta($wp_user_id, 'rocket_chat_username', $username);
            $this->log("Successfully stored encrypted password and username: $username");
        }
        if ($result && isset($result['success']) && $result['success']) {
            $this->log("User created successfully");
            
            // Auto-join channels after user creation
            $default_channels = ['general']; // Always join general
            
            // Add specific channels if provided
            if ($channels) {
                if (is_string($channels)) {
                    $channels = [$channels];
                }
                $default_channels = array_merge($default_channels, $channels);
            }
            
            // Remove duplicates and empty values
            $default_channels = array_filter(array_unique($default_channels));
            
            if (!empty($default_channels)) {
                $this->log("Auto-joining user $username to channels: " . implode(', ', $default_channels));
                
                // Small delay to ensure user is fully created
                sleep(1);
                
                $join_results = $this->join_channels($username, $default_channels);
                foreach ($join_results as $channel => $success) {
                    if ($success) {
                        $this->log("✅ Successfully joined $username to #$channel");
                    } else {
                        $this->log("❌ Failed to join $username to #$channel");
                    }
                }
            }
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

    /**
     * Generate a unique username if the preferred one is taken
     */
    public function get_unique_username($preferred_username, $wp_user_id) {
        $this->log("Finding unique username for WordPress user ID: $wp_user_id, preferred: $preferred_username");
        
        // First check if we already have a stored username for this WordPress user
        $stored_username = get_user_meta($wp_user_id, 'rocket_chat_username', true);
        if ($stored_username) {
            $this->log("Found stored username for WP user $wp_user_id: $stored_username");
            // Verify this stored username still exists and belongs to us
            $user_check = $this->get_user($stored_username);
            if ($user_check && isset($user_check['user'])) {
                $this->log("Stored username $stored_username is valid, using it");
                return $stored_username;
            } else {
                $this->log("Stored username $stored_username no longer exists, will create new one");
                delete_user_meta($wp_user_id, 'rocket_chat_username');
            }
        }
        
        // Check if preferred username is available
        $user_check = $this->get_user($preferred_username);
        if (!$user_check || (isset($user_check['success']) && !$user_check['success'])) {
            $this->log("Preferred username $preferred_username is available");
            return $preferred_username;
        }
        
        $this->log("Username $preferred_username is taken, generating unique alternative");
        
        // Generate unique alternatives
        $base_username = $preferred_username;
        $suffix = 1;
        
        do {
            $test_username = $base_username . '_' . $suffix;
            $user_check = $this->get_user($test_username);
            $is_available = !$user_check || (isset($user_check['success']) && !$user_check['success']);
            
            if ($is_available) {
                $this->log("Found available username: $test_username");
                return $test_username;
            }
            
            $suffix++;
        } while ($suffix <= 100); // Prevent infinite loop
        
        // Fallback with timestamp if all numeric suffixes are taken
        $fallback_username = $base_username . '_' . time();
        $this->log("Using fallback username with timestamp: $fallback_username");
        return $fallback_username;
    }

    /**
     * Channel Management Methods
     */

    /**
     * Get information about a channel or group
     */
    public function get_channel_info($name) {
        if (!$this->auth_token && !$this->login()) {
            return false;
        }
        
        // First try as a channel
        $response = $this->make_request("channels.info?roomName={$name}", 'GET');
        if ($response && isset($response['channel'])) {
            return array_merge($response['channel'], ['room_type' => 'channel']);
        }
        
        // If not found as channel, try as a private group
        $response = $this->make_request("groups.info?roomName={$name}", 'GET');
        if ($response && isset($response['group'])) {
            return array_merge($response['group'], ['room_type' => 'group']);
        }
        
        return false;
    }
    
    /**
     * Get room type (channel or group) for a given room name
     */
    public function get_room_type($name) {
        $room_info = $this->get_channel_info($name);
        return $room_info ? $room_info['room_type'] : false;
    }

    /**
     * Join user to a channel or group
     */
    public function join_channel($username, $channel) {
        if (!$this->auth_token && !$this->login()) {
            $this->log("Cannot join channel - not authenticated");
            return false;
        }
        
        // Get channel info to determine room type
        $channel_info = $this->get_channel_info($channel);
        if (!$channel_info) {
            $this->log("Channel/group not found: $channel - skipping join");
            return false; // Don't treat as error - channel might not exist
        }
        
        $room_type = $channel_info['room_type'];
        $this->log("Joining user $username to $room_type: $channel");
        
        if ($room_type === 'group') {
            // Use groups.invite for private groups
            $response = $this->make_request('groups.invite', 'POST', [
                'roomName' => $channel,
                'username' => $username
            ]);
            
            if ($response && isset($response['success']) && $response['success']) {
                $this->log("✅ Successfully joined user $username to group: $channel");
                return true;
            } else {
                // Check if already a member
                if (isset($response['error']) && strpos($response['error'], 'already') !== false) {
                    $this->log("User $username is already a member of group: $channel");
                    return true;
                }
                $this->log("❌ Failed to join user $username to group: $channel - " . print_r($response, true));
                return false;
            }
        } else {
            // Use channels.join for public channels
            $response = $this->make_request('channels.join', 'POST', [
                'roomName' => $channel,
                'username' => $username
            ]);
            
            if ($response && isset($response['success']) && $response['success']) {
                $this->log("✅ Successfully joined user $username to channel: $channel");
                return true;
            } else {
                // Check if already a member
                if (isset($response['error']) && strpos($response['error'], 'already') !== false) {
                    $this->log("User $username is already a member of channel: $channel");
                    return true;
                }
                $this->log("❌ Failed to join user $username to channel: $channel - " . print_r($response, true));
                return false;
            }
        }
    }

    // Join user to multiple channels
    public function join_channels($username, $channels) {
        $this->log("Joining user $username to multiple channels: " . implode(', ', $channels));
        $results = [];
        
        foreach ($channels as $channel) {
            $channel = trim($channel);
            if (!empty($channel)) {
                $results[$channel] = $this->join_channel($username, $channel);
            }
        }
        
        return $results;
    }

    /**
     * Debug method to list all available channels and rooms
     */
    public function debug_list_all_channels() {
        $this->log("=== DEBUG: Listing all available channels and rooms ===");
        
        if (!$this->auth_token && !$this->login()) {
            $this->log("Failed to login for debug listing");
            return false;
        }

        // List public channels
        $this->log("--- Public Channels (channels.list) ---");
        $channels_result = $this->make_request('channels.list');
        if ($channels_result && isset($channels_result['channels'])) {
            foreach ($channels_result['channels'] as $channel) {
                $this->log("PUBLIC CHANNEL: " . ($channel['name'] ?? 'unnamed') . " (ID: " . ($channel['_id'] ?? 'no-id') . ")");
            }
        } else {
            $this->log("No public channels found or error in channels.list");
        }

        // List private groups
        $this->log("--- Private Groups (groups.list) ---");
        $groups_result = $this->make_request('groups.list');
        if ($groups_result && isset($groups_result['groups'])) {
            foreach ($groups_result['groups'] as $group) {
                $this->log("PRIVATE GROUP: " . ($group['name'] ?? 'unnamed') . " (ID: " . ($group['_id'] ?? 'no-id') . ")");
            }
        } else {
            $this->log("No private groups found or error in groups.list");
        }

        // Try rooms.get (subscribed rooms)
        $this->log("--- Subscribed Rooms (rooms.get) ---");
        $rooms_result = $this->make_request('rooms.get');
        if ($rooms_result && isset($rooms_result['update'])) {
            foreach ($rooms_result['update'] as $room) {
                $type_name = 'unknown';
                switch ($room['t'] ?? '') {
                    case 'c': $type_name = 'public channel'; break;
                    case 'p': $type_name = 'private group'; break;
                    case 'd': $type_name = 'direct message'; break;
                    case 'l': $type_name = 'livechat'; break;
                }
                $this->log("SUBSCRIBED ROOM: " . ($room['name'] ?? $room['fname'] ?? 'unnamed') . " (Type: $type_name, ID: " . ($room['_id'] ?? 'no-id') . ")");
            }
        } else {
            $this->log("No subscribed rooms found or error in rooms.get");
        }

        $this->log("=== END DEBUG LISTING ===");
        return true;
    }

    /**
     * Parse API errors and return user-friendly messages
     */
    public function get_friendly_error_message($api_response) {
        // If response is successful, no error
        if ($api_response && isset($api_response['success']) && $api_response['success']) {
            return null;
        }
        
        $error_code = null;
        $error_message = null;
        
        // Extract error information from different response formats
        if (isset($api_response['error'])) {
            $error_message = $api_response['error'];
        }
        if (isset($api_response['errorType'])) {
            $error_code = $api_response['errorType'];
        }
        if (isset($api_response['response']['error'])) {
            $error_message = $api_response['response']['error'];
        }
        if (isset($api_response['response']['errorType'])) {
            $error_code = $api_response['response']['errorType'];
        }
        
        // Map specific error codes to user-friendly messages
        $friendly_messages = [
            'error-field-unavailable' => [
                'type' => 'account_exists',
                'title' => 'Account Already Exists',
                'message' => 'There is already an account associated with your email address.',
                'action' => 'If you need assistance accessing your existing chat account, please contact Triple Point Trading support.'
            ],
            'error-username-field-unavailable' => [
                'type' => 'username_taken',
                'title' => 'Username Not Available',
                'message' => 'Your preferred username is already taken.',
                'action' => 'Our system will automatically assign you a unique username. If you need assistance, please contact Triple Point Trading support.'
            ],
            'error-invalid-user' => [
                'type' => 'user_not_found',
                'title' => 'User Account Issue',
                'message' => 'There was an issue with your chat account setup.',
                'action' => 'Please contact Triple Point Trading support for assistance with your chat access.'
            ],
            'Unauthorized' => [
                'type' => 'auth_error',
                'title' => 'Authentication Error',
                'message' => 'There was an issue authenticating your chat access.',
                'action' => 'This is usually temporary. Please refresh the page or contact Triple Point Trading support if the issue persists.'
            ]
        ];
        
        // Check for specific error patterns in the message
        if ($error_message) {
            // Check for email already in use
            if (strpos($error_message, 'already in use') !== false || strpos($error_message, 'email') !== false) {
                return $friendly_messages['error-field-unavailable'];
            }
            
            // Check for username taken
            if (strpos($error_message, 'username') !== false && strpos($error_message, 'unavailable') !== false) {
                return $friendly_messages['error-username-field-unavailable'];
            }
            
            // Check for unauthorized
            if (strpos($error_message, 'Unauthorized') !== false) {
                return $friendly_messages['Unauthorized'];
            }
        }
        
        // Check for specific error codes
        if ($error_code && isset($friendly_messages[$error_code])) {
            return $friendly_messages[$error_code];
        }
        
        // Default friendly message for unknown errors
        return [
            'type' => 'general_error',
            'title' => 'Chat Access Issue',
            'message' => 'We encountered an issue setting up your chat access.',
            'action' => 'Please refresh the page and try again. If the problem persists, contact Triple Point Trading support for assistance.'
        ];
    }

    /**
     * Debug method to list all available channels and rooms
     */
}

function get_rocket_chat_api() {
    static $api = null;
    if ($api === null) {
        $api = new RocketChatAPI();
    }
    return $api;
}

/**
 * Helper function to get current channel context
 */
function get_current_rocket_chat_channel() {
    // Try to get channel from current request context
    $channel = null;
    
    // Check if we're in a shortcode context
    if (isset($GLOBALS['current_rocket_chat_channel'])) {
        $channel = $GLOBALS['current_rocket_chat_channel'];
    }
    
    // Check if there's a default channel set
    if (!$channel) {
        $channel = get_option('rocket_chat_default_channel', 'general');
    }
    
    return $channel;
}

/**
 * Set channel context for current request
 */
function set_current_rocket_chat_channel($channel) {
    $GLOBALS['current_rocket_chat_channel'] = $channel;
}

// Add test function
function test_rocket_chat_flow($username, $channel = null) {
    $api = get_rocket_chat_api();
    $current_user = wp_get_current_user();
    $wp_user_id = get_current_user_id();
    
    rocket_chat_log("Starting test flow for user: $username", 'info');
    
    // Set channel context if provided
    if ($channel) {
        set_current_rocket_chat_channel($channel);
    }
    
    $channels_to_join = [];
    if ($channel && $channel !== 'general') {
        $channels_to_join[] = $channel;
    }
    
    // Step 1: Check if user exists
    rocket_chat_log("Step 1: Checking if user exists", 'info');
    $rc_user = $api->get_user($username);
    if (!$rc_user || (isset($rc_user['success']) && !$rc_user['success'])) {
        rocket_chat_log("User does not exist, creating new user", 'info');
        $create_result = $api->create_user(
            $current_user->user_login,
            $current_user->user_email,
            $current_user->display_name,
            $wp_user_id,
            $channels_to_join
        );
        if (!$create_result) {
            rocket_chat_log("Failed to create user", 'error');
            return false;
        }
        rocket_chat_log("User created successfully", 'info');
    } else {
        rocket_chat_log("User exists", 'info');
        
        // User exists, but still try to join them to the channel if specified
        if (!empty($channels_to_join)) {
            rocket_chat_log("Joining existing user to additional channels", 'info');
            $join_results = $api->join_channels($username, $channels_to_join);
            foreach ($join_results as $ch => $success) {
                if ($success) {
                    rocket_chat_log("✅ Successfully joined existing user to #$ch", 'info');
                } else {
                    rocket_chat_log("❌ Failed to join existing user to #$ch (might already be member)", 'info');
                }
            }
        }
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
    // ✅ UNLIMITED ACCESS: Always return true - you have unlimited license for this plugin
    return true;
    
    // The following code is kept for reference but not used:
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
        return ['status' => 'free', 'message' => '🎉 You have an unlimited license for this plugin - all premium features unlocked!'];
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

// Debug function to trigger channel listing
function debug_rocket_chat_channels() {
    $api = get_rocket_chat_api();
    return $api->debug_list_all_channels();
}

// AJAX handler for debugging channels
add_action('wp_ajax_rce_debug_channels', 'rce_handle_debug_channels');
function rce_handle_debug_channels() {
    check_ajax_referer('rce_debug_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    rocket_chat_log("=== MANUAL DEBUG TRIGGER ===", 'info');
    $result = debug_rocket_chat_channels();
    
    if ($result) {
        wp_send_json_success('Debug listing completed. Check the logs for channel details.');
    } else {
        wp_send_json_error('Debug listing failed. Check the logs for error details.');
    }
}

/**
 * Enhanced user management with tier-based auto-joining
 */
function ensure_user_in_rocket_chat_channels($username, $requested_channel = null) {
    rocket_chat_log("=== Enhanced channel management for user: $username ===", 'info');
    
    $api = get_rocket_chat_api();
    $current_user = wp_get_current_user();
    $wp_user_id = get_current_user_id();
    
    // Only join the specific channel requested, plus general
    $user_channels = ['general']; // Always include general
    
    // Add the requested channel if specified and not general
    if ($requested_channel && $requested_channel !== 'general') {
        $user_channels[] = $requested_channel;
    }
    
    // Remove duplicates
    $user_channels = array_unique($user_channels);
    
    rocket_chat_log("User will be added to channels: " . implode(', ', $user_channels), 'info');
    
    // Check if we have a stored Rocket.Chat username for this WordPress user
    $stored_username = get_user_meta($wp_user_id, 'rocket_chat_username', true);
    $stored_password = get_user_meta($wp_user_id, 'rocket_chat_password', true);
    
    if ($stored_username && $stored_password) {
        // We have a stored Rocket.Chat user for this WordPress user
        rocket_chat_log("Found stored Rocket.Chat username: $stored_username for WP user ID: $wp_user_id", 'info');
        
        // Verify the stored user still exists
        $rc_user = $api->get_user($stored_username);
        if ($rc_user && isset($rc_user['user'])) {
            rocket_chat_log("Stored user exists, ensuring membership in requested channels", 'info');
            $actual_username = $stored_username;
        } else {
            rocket_chat_log("Stored user no longer exists, will create new user", 'info');
            delete_user_meta($wp_user_id, 'rocket_chat_username');
            delete_user_meta($wp_user_id, 'rocket_chat_password');
            $actual_username = null;
        }
    } else {
        $actual_username = null;
    }
    
    if (!$actual_username) {
        // No stored user or stored user doesn't exist, create new user
        rocket_chat_log("Creating new Rocket.Chat user for WordPress user: $username", 'info');
        $result = $api->create_user(
            $username, // This will be made unique automatically
            $current_user->user_email,
            $current_user->display_name,
            $wp_user_id,
            $user_channels
        );
        
        if (!$result || (isset($result['success']) && !$result['success'])) {
            // User creation failed, get friendly error message
            $error_info = $api->get_friendly_error_message($result);
            rocket_chat_log("User creation failed with error: " . json_encode($error_info), 'error');
            return [
                'success' => false,
                'error' => $error_info
            ];
        }
        
        rocket_chat_log("User creation result: Success", 'info');
        return ['success' => true];
    } else {
        // User exists, ensure they're in the specific channels
        $success_count = 0;
        $total_count = count($user_channels);
        
        foreach ($user_channels as $channel) {
            rocket_chat_log("Checking membership in channel: $channel", 'info');
            $join_result = $api->join_channel($actual_username, $channel);
            if ($join_result) {
                rocket_chat_log("✅ Successfully ensured user is in channel: $channel", 'info');
                $success_count++;
            } else {
                rocket_chat_log("⚠️ Could not join user to channel: $channel (may not exist or already member)", 'warning');
            }
        }
        
        rocket_chat_log("Channel membership check complete: $success_count/$total_count successful", 'info');
        return ['success' => $success_count > 0]; // Return true if at least one channel join was successful
    }
}

/**
 * Determine appropriate channels for a user based on their WordPress roles
 */
function get_user_appropriate_channels($user) {
    $channels = ['general']; // Everyone gets general by default
    
    if (!$user || !$user->exists()) {
        return $channels;
    }
    
    $user_roles = $user->roles;
    rocket_chat_log("User roles: " . implode(', ', $user_roles), 'debug');
    
    // Map WordPress roles to Rocket.Chat channels
    $role_channel_map = [
        'platinum' => 'platinum',
        'gold' => 'gold', 
        'silver' => 'silver',
        'premium' => 'gold', // Map premium to gold if needed
        'subscriber' => 'silver', // Default subscribers to silver
        'administrator' => ['platinum', 'gold', 'silver'], // Admins get access to all
        'editor' => ['platinum', 'gold', 'silver'], // Editors get access to all
    ];
    
    // Allow filtering of the role mapping
    $role_channel_map = apply_filters('rocket_chat_role_channel_map', $role_channel_map);
    
    foreach ($user_roles as $role) {
        if (isset($role_channel_map[$role])) {
            $role_channels = $role_channel_map[$role];
            if (is_array($role_channels)) {
                $channels = array_merge($channels, $role_channels);
            } else {
                $channels[] = $role_channels;
            }
        }
    }
    
    // Remove duplicates and filter
    $channels = array_unique($channels);
    
    rocket_chat_log("Determined appropriate channels for user: " . implode(', ', $channels), 'info');
    
    return apply_filters('rocket_chat_user_channels', $channels, $user);
}

/**
 * Enhanced user management with tier-based auto-joining for auto-tier mode
 */
function ensure_user_in_rocket_chat_channels_auto_tier($username) {
    rocket_chat_log("=== Auto-tier channel management for user: $username ===", 'info');
    
    $api = get_rocket_chat_api();
    $current_user = wp_get_current_user();
    $wp_user_id = get_current_user_id();
    
    // Determine user's tier and appropriate channels based on roles
    $user_channels = get_user_appropriate_channels($current_user);
    
    rocket_chat_log("Auto-tier: User will be added to channels: " . implode(', ', $user_channels), 'info');
    
    // Check if user exists in Rocket.Chat
    $rc_user = $api->get_user($username);
    if (!$rc_user || (isset($rc_user['success']) && !$rc_user['success'])) {
        // User doesn't exist, create them and join to all appropriate channels
        rocket_chat_log("Creating new Rocket.Chat user with auto-tier: $username", 'info');
        $result = $api->create_user(
            $current_user->user_login,
            $current_user->user_email,
            $current_user->display_name,
            $wp_user_id,
            $user_channels
        );
        
        if (!$result || (isset($result['success']) && !$result['success'])) {
            // User creation failed, get friendly error message
            $error_info = $api->get_friendly_error_message($result);
            rocket_chat_log("Auto-tier user creation failed with error: " . json_encode($error_info), 'error');
            return [
                'success' => false,
                'error' => $error_info
            ];
        }
        
        rocket_chat_log("Auto-tier user creation result: Success", 'info');
        return ['success' => true];
    } else {
        // User exists, ensure they're in all appropriate channels
        rocket_chat_log("User exists, ensuring membership in auto-tier channels", 'info');
        
        // Check if we have a stored password for this user
        $encrypted_password = get_user_meta($wp_user_id, 'rocket_chat_password', true);
        if (!$encrypted_password) {
            rocket_chat_log("No encrypted password found for existing user, regenerating", 'info');
            // Generate a new password
            $new_password = wp_generate_password();
            // Update user in Rocket.Chat with new password
            $update_result = $api->make_request('users.update', 'POST', [
                'userId' => $rc_user['user']['_id'],
                'data' => [
                    'password' => $new_password
                ]
            ]);
            if ($update_result) {
                // Store the encrypted password
                $encrypted_password = $api->encrypt_password($new_password);
                update_user_meta($wp_user_id, 'rocket_chat_password', $encrypted_password);
                rocket_chat_log("New password generated and stored for existing user", 'info');
            } else {
                rocket_chat_log("Failed to update user password in Rocket.Chat", 'error');
            }
        }
        
        $success_count = 0;
        $total_count = count($user_channels);
        
        foreach ($user_channels as $channel) {
            rocket_chat_log("Auto-tier: Checking membership in channel: $channel", 'info');
            $join_result = $api->join_channel($username, $channel);
            if ($join_result) {
                rocket_chat_log("✅ Auto-tier: Successfully ensured user is in channel: $channel", 'info');
                $success_count++;
            } else {
                rocket_chat_log("⚠️ Auto-tier: Could not join user to channel: $channel (may not exist or already member)", 'warning');
            }
        }
        
        rocket_chat_log("Auto-tier channel membership check complete: $success_count/$total_count successful", 'info');
        return ['success' => $success_count > 0]; // Return true if at least one channel join was successful
    }
} 