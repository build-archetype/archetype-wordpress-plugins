<?php
if (!defined('ABSPATH')) exit;

class Video_Library_Logger {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log($message, $level = 'info') {
        video_library_log($message, $level);
    }
    
    public function get_logs() {
        $logs = get_transient('video_library_logs');
        return is_array($logs) ? $logs : [];
    }
    
    public function clear_logs() {
        delete_transient('video_library_logs');
        video_library_log('Logs cleared by admin', 'info');
    }
} 