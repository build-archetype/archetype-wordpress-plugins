<?php
/**
 * Stream Integration for Rocket Chat
 * 
 * This file handles the clean integration between stream status and chat visibility.
 * It uses the simplified stream sync system for reliable communication.
 */

if (!defined('ABSPATH')) exit;

class RocketChat_StreamIntegration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Only initialize if ant-media plugin is active
        if (!function_exists('amsa_is_stream_live')) {
            return;
        }
        
        // Hook into the clean stream status system
        add_action('amsa_stream_status_changed', [$this, 'handle_stream_status_change'], 10, 2);
        
        // Add JavaScript for real-time chat updates
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Handle stream status changes from the sync system
     */
    public function handle_stream_status_change($is_live, $previous_status) {
        rocket_chat_log("Stream status changed: " . ($is_live ? 'LIVE' : 'OFFLINE'), 'info');
        
        // The JavaScript will handle the actual showing/hiding
        // We just need to ensure the data is available
    }
    
    /**
     * Enqueue JavaScript for chat visibility control
     */
    public function enqueue_scripts() {
        if (!$this->page_has_chat()) {
            return;
        }
        
        wp_add_inline_script('jquery', $this->get_chat_script());
    }
    
    /**
     * Check if current page has chat
     */
    private function page_has_chat() {
        global $post;
        return $post && has_shortcode($post->post_content, 'rocketchat_iframe');
    }
    
    /**
     * Get the chat control JavaScript
     */
    private function get_chat_script() {
        return '
        jQuery(document).ready(function($) {
            // Initialize chat container registry
            window.rocketChatContainers = window.rocketChatContainers || [];
            
            // Listen for stream status changes
            document.addEventListener("amsaStreamStatusChanged", function(event) {
                var isLive = event.detail.isLive;
                console.log("Rocket Chat: Stream status changed to", isLive ? "LIVE" : "OFFLINE");
                
                // Update all chat containers
                window.rocketChatContainers.forEach(function(container) {
                    if (isLive) {
                        container.show();
                    } else {
                        container.hide();
                    }
                });
            });
            
            console.log("Rocket Chat: Stream integration initialized");
        });
        ';
    }
    
    /**
     * Get current stream status for server-side rendering
     */
    public function is_stream_live() {
        return function_exists('amsa_is_stream_live') ? amsa_is_stream_live() : false;
    }
}

// Initialize the integration
RocketChat_StreamIntegration::get_instance();

/**
 * Helper function for templates
 */
function rocket_chat_stream_is_live() {
    return RocketChat_StreamIntegration::get_instance()->is_stream_live();
} 