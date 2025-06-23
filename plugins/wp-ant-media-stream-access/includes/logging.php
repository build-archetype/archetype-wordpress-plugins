<?php
if (!defined('ABSPATH')) exit;

class Ant_Media_Logger {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log($message, $level = 'info') {
        ant_media_log($message, $level);
    }
    
    public function get_logs() {
        $logs = get_transient('ant_media_logs');
        return is_array($logs) ? $logs : [];
    }
    
    public function clear_logs() {
        delete_transient('ant_media_logs');
        ant_media_log('Logs cleared by admin', 'info');
    }
} 