<?php
if (!defined('ABSPATH')) exit;

class RocketChatLogger {
    private static $instance = null;
    private $log_file;
    private $debug_mode;

    private function __construct() {
        $this->debug_mode = true; // Force debug mode on
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/rocket-chat-debug.log';
        
        // Ensure log file exists and is writable
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
            chmod($this->log_file, 0666);
        }
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            // Only log initialization once
            self::$instance->log('RocketChat Logger initialized', 'info');
        }
        return self::$instance;
    }

    public function log($message, $type = 'info') {
        // Always log errors
        if ($type !== 'error' && !$this->debug_mode) {
            return;
        }

        $timestamp = current_time('mysql');
        $formatted_message = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            strtoupper($type),
            is_array($message) || is_object($message) ? print_r($message, true) : $message
        );

        // Log to WordPress debug log
        error_log('[RocketChat] ' . $formatted_message);

        // Log to custom file
        file_put_contents($this->log_file, $formatted_message, FILE_APPEND);

        // Store in transient for admin display
        $logs = get_transient('rocket_chat_logs') ?: [];
        array_unshift($logs, $formatted_message);
        $logs = array_slice($logs, 0, 100); // Keep last 100 logs
        set_transient('rocket_chat_logs', $logs, DAY_IN_SECONDS);

        // Also log to PHP error log as backup
        error_log('[RocketChat Debug] ' . $formatted_message);
    }

    public function get_logs() {
        return get_transient('rocket_chat_logs') ?: [];
    }

    public function clear_logs() {
        delete_transient('rocket_chat_logs');
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
            touch($this->log_file);
            chmod($this->log_file, 0666);
        }
    }
}

function rocket_chat_log($message, $type = 'info') {
    return RocketChatLogger::get_instance()->log($message, $type);
}
