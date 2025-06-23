<?php
if (!defined('ABSPATH')) exit;

/**
 * Database Management Class for Ant Media Stream Access
 * Handles stream analytics, user activity tracking, and stream metrics
 */
class AMSA_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create database tables for stream analytics
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        try {
            ant_media_log('Creating/updating database tables...', 'info');
            
            // Table for stream events (play, pause, stop, error)
            $table_stream_events = $wpdb->prefix . 'amsa_stream_events';
            
            $sql_events = "CREATE TABLE $table_stream_events (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                stream_id varchar(255) NOT NULL,
                event_type varchar(50) NOT NULL,
                event_data longtext DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                session_id varchar(255) DEFAULT NULL,
                tier varchar(50) DEFAULT NULL,
                duration_seconds int(11) DEFAULT 0,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY stream_id (stream_id),
                KEY event_type (event_type),
                KEY timestamp (timestamp),
                KEY session_id (session_id)
            ) $charset_collate;";
            
            // Table for stream sessions (complete viewing sessions)
            $table_stream_sessions = $wpdb->prefix . 'amsa_stream_sessions';
            
            $sql_sessions = "CREATE TABLE $table_stream_sessions (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id varchar(255) NOT NULL,
                user_id bigint(20) NOT NULL,
                stream_id varchar(255) NOT NULL,
                start_time datetime DEFAULT CURRENT_TIMESTAMP,
                end_time datetime DEFAULT NULL,
                total_duration int(11) DEFAULT 0,
                peak_viewers int(11) DEFAULT 0,
                quality_changes int(11) DEFAULT 0,
                buffering_events int(11) DEFAULT 0,
                error_count int(11) DEFAULT 0,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                tier varchar(50) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY session_id (session_id),
                KEY user_id (user_id),
                KEY stream_id (stream_id),
                KEY start_time (start_time)
            ) $charset_collate;";
            
            // Table for stream metrics (daily aggregates)
            $table_stream_metrics = $wpdb->prefix . 'amsa_stream_metrics';
            
            $sql_metrics = "CREATE TABLE $table_stream_metrics (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                stream_id varchar(255) NOT NULL,
                metric_date date NOT NULL,
                total_viewers int(11) DEFAULT 0,
                unique_viewers int(11) DEFAULT 0,
                total_view_time int(11) DEFAULT 0,
                average_view_time decimal(10,2) DEFAULT 0,
                peak_concurrent int(11) DEFAULT 0,
                total_starts int(11) DEFAULT 0,
                total_completions int(11) DEFAULT 0,
                bounce_rate decimal(5,2) DEFAULT 0,
                error_rate decimal(5,2) DEFAULT 0,
                tier_breakdown longtext DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY stream_date (stream_id, metric_date),
                KEY metric_date (metric_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            dbDelta($sql_events);
            dbDelta($sql_sessions);
            dbDelta($sql_metrics);
            
            // Check if tables were created successfully
            $tables_created = [];
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_stream_events'") == $table_stream_events) {
                $tables_created[] = 'stream_events';
            }
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_stream_sessions'") == $table_stream_sessions) {
                $tables_created[] = 'stream_sessions';
            }
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_stream_metrics'") == $table_stream_metrics) {
                $tables_created[] = 'stream_metrics';
            }
            
            ant_media_log('Database tables created/updated: ' . implode(', ', $tables_created), 'info');
            
            // Update database version
            update_option('amsa_db_version', AMSA_VERSION);
            
        } catch (Exception $e) {
            ant_media_log('Database table creation error: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Record a stream event
     */
    public static function record_event($user_id, $stream_id, $event_type, $event_data = null, $session_id = null) {
        global $wpdb;
        
        try {
            $table = $wpdb->prefix . 'amsa_stream_events';
            
            $user_tier = get_user_tier();
            $ip_address = self::get_client_ip();
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            
            if (!$session_id) {
                $session_id = self::generate_session_id($user_id, $stream_id);
            }
            
            $result = $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'stream_id' => $stream_id,
                    'event_type' => $event_type,
                    'event_data' => is_array($event_data) ? json_encode($event_data) : $event_data,
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent,
                    'session_id' => $session_id,
                    'tier' => $user_tier,
                    'timestamp' => current_time('mysql')
                ],
                [
                    '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
            
            if ($result !== false) {
                ant_media_log("Recorded stream event: {$event_type} for user {$user_id} on stream {$stream_id}", 'debug');
                
                // Update session if this is a session-related event
                if (in_array($event_type, ['play', 'pause', 'stop', 'ended'])) {
                    self::update_session($session_id, $event_type, $event_data);
                }
                
                return $wpdb->insert_id;
            } else {
                ant_media_log('Failed to record stream event: ' . $wpdb->last_error, 'error');
                return false;
            }
            
        } catch (Exception $e) {
            ant_media_log('Error recording stream event: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Update or create a stream session
     */
    public static function update_session($session_id, $event_type, $event_data = null) {
        global $wpdb;
        
        try {
            $table = $wpdb->prefix . 'amsa_stream_sessions';
            
            // Check if session exists
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE session_id = %s",
                $session_id
            ));
            
            if (!$session && $event_type === 'play') {
                // Create new session
                $user_id = get_current_user_id();
                $stream_id = isset($event_data['stream_id']) ? $event_data['stream_id'] : '';
                $user_tier = get_user_tier();
                $ip_address = self::get_client_ip();
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                
                $result = $wpdb->insert(
                    $table,
                    [
                        'session_id' => $session_id,
                        'user_id' => $user_id,
                        'stream_id' => $stream_id,
                        'start_time' => current_time('mysql'),
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent,
                        'tier' => $user_tier
                    ],
                    ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
                );
                
                ant_media_log("Created new stream session: {$session_id}", 'debug');
                
            } elseif ($session) {
                // Update existing session
                $updates = [];
                $formats = [];
                
                if (in_array($event_type, ['stop', 'ended', 'pause'])) {
                    $updates['end_time'] = current_time('mysql');
                    $formats[] = '%s';
                    
                    // Calculate duration if we have start and end times
                    if ($session->start_time) {
                        $start = strtotime($session->start_time);
                        $end = time();
                        $duration = max(0, $end - $start);
                        $updates['total_duration'] = $duration;
                        $formats[] = '%d';
                    }
                }
                
                // Update error count
                if ($event_type === 'error') {
                    $updates['error_count'] = $session->error_count + 1;
                    $formats[] = '%d';
                }
                
                // Update quality changes
                if ($event_type === 'quality_change') {
                    $updates['quality_changes'] = $session->quality_changes + 1;
                    $formats[] = '%d';
                }
                
                // Update buffering events
                if ($event_type === 'buffering') {
                    $updates['buffering_events'] = $session->buffering_events + 1;
                    $formats[] = '%d';
                }
                
                if (!empty($updates)) {
                    $wpdb->update(
                        $table,
                        $updates,
                        ['session_id' => $session_id],
                        $formats,
                        ['%s']
                    );
                    
                    ant_media_log("Updated stream session: {$session_id} with event: {$event_type}", 'debug');
                }
            }
            
        } catch (Exception $e) {
            ant_media_log('Error updating stream session: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Get stream analytics for a specific stream
     */
    public static function get_stream_analytics($stream_id, $days = 30) {
        global $wpdb;
        
        try {
            $events_table = $wpdb->prefix . 'amsa_stream_events';
            $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
            
            $date_from = date('Y-m-d', strtotime("-{$days} days"));
            
            // Get basic metrics
            $total_views = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM $events_table 
                WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) >= %s",
                $stream_id, $date_from
            ));
            
            $unique_viewers = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM $events_table 
                WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) >= %s",
                $stream_id, $date_from
            ));
            
            $total_watch_time = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_duration) FROM $sessions_table 
                WHERE stream_id = %s AND DATE(start_time) >= %s",
                $stream_id, $date_from
            ));
            
            $average_watch_time = $total_views > 0 ? ($total_watch_time / $total_views) : 0;
            
            // Get tier breakdown
            $tier_breakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT tier, COUNT(DISTINCT session_id) as views 
                FROM $events_table 
                WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) >= %s 
                GROUP BY tier",
                $stream_id, $date_from
            ));
            
            return [
                'stream_id' => $stream_id,
                'period_days' => $days,
                'total_views' => intval($total_views),
                'unique_viewers' => intval($unique_viewers),
                'total_watch_time' => intval($total_watch_time),
                'average_watch_time' => round($average_watch_time, 2),
                'tier_breakdown' => $tier_breakdown
            ];
            
        } catch (Exception $e) {
            ant_media_log('Error getting stream analytics: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get daily metrics for all streams
     */
    public static function get_daily_metrics($days = 7) {
        global $wpdb;
        
        try {
            $metrics_table = $wpdb->prefix . 'amsa_stream_metrics';
            
            $date_from = date('Y-m-d', strtotime("-{$days} days"));
            
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $metrics_table 
                WHERE metric_date >= %s 
                ORDER BY metric_date DESC, stream_id",
                $date_from
            ));
            
        } catch (Exception $e) {
            ant_media_log('Error getting daily metrics: ' . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Generate aggregated daily metrics
     */
    public static function generate_daily_metrics($date = null) {
        global $wpdb;
        
        if (!$date) {
            $date = date('Y-m-d', strtotime('yesterday'));
        }
        
        try {
            $events_table = $wpdb->prefix . 'amsa_stream_events';
            $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
            $metrics_table = $wpdb->prefix . 'amsa_stream_metrics';
            
            // Get all streams that had activity on this date
            $streams = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT stream_id FROM $events_table WHERE DATE(timestamp) = %s",
                $date
            ));
            
            foreach ($streams as $stream_id) {
                $metrics = self::calculate_stream_metrics($stream_id, $date);
                
                // Insert or update metrics
                $wpdb->replace(
                    $metrics_table,
                    array_merge(['stream_id' => $stream_id, 'metric_date' => $date], $metrics),
                    ['%s', '%s', '%d', '%d', '%d', '%f', '%d', '%d', '%d', '%f', '%f', '%s']
                );
            }
            
            ant_media_log("Generated daily metrics for {$date}: " . count($streams) . " streams", 'info');
            
        } catch (Exception $e) {
            ant_media_log('Error generating daily metrics: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Calculate metrics for a specific stream and date
     */
    private static function calculate_stream_metrics($stream_id, $date) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
        
        // Basic counts
        $total_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $events_table 
            WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) = %s",
            $stream_id, $date
        ));
        
        $unique_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $events_table 
            WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) = %s",
            $stream_id, $date
        ));
        
        $total_view_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_duration) FROM $sessions_table 
            WHERE stream_id = %s AND DATE(start_time) = %s",
            $stream_id, $date
        ));
        
        $total_starts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $events_table 
            WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) = %s",
            $stream_id, $date
        ));
        
        $total_completions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $events_table 
            WHERE stream_id = %s AND event_type = 'ended' AND DATE(timestamp) = %s",
            $stream_id, $date
        ));
        
        $error_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $events_table 
            WHERE stream_id = %s AND event_type = 'error' AND DATE(timestamp) = %s",
            $stream_id, $date
        ));
        
        // Tier breakdown
        $tier_data = $wpdb->get_results($wpdb->prepare(
            "SELECT tier, COUNT(DISTINCT session_id) as count 
            FROM $events_table 
            WHERE stream_id = %s AND event_type = 'play' AND DATE(timestamp) = %s 
            GROUP BY tier",
            $stream_id, $date
        ));
        
        // Calculate rates
        $average_view_time = $total_viewers > 0 ? ($total_view_time / $total_viewers) : 0;
        $bounce_rate = $total_starts > 0 ? (($total_starts - $total_completions) / $total_starts * 100) : 0;
        $error_rate = $total_starts > 0 ? ($error_count / $total_starts * 100) : 0;
        
        return [
            'total_viewers' => intval($total_viewers),
            'unique_viewers' => intval($unique_viewers),
            'total_view_time' => intval($total_view_time),
            'average_view_time' => round($average_view_time, 2),
            'peak_concurrent' => 0, // Would need real-time tracking
            'total_starts' => intval($total_starts),
            'total_completions' => intval($total_completions),
            'bounce_rate' => round($bounce_rate, 2),
            'error_rate' => round($error_rate, 2),
            'tier_breakdown' => json_encode($tier_data)
        ];
    }
    
    /**
     * Clean old data
     */
    public static function cleanup_old_data($days = 90) {
        global $wpdb;
        
        try {
            $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
            
            $events_table = $wpdb->prefix . 'amsa_stream_events';
            $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
            $metrics_table = $wpdb->prefix . 'amsa_stream_metrics';
            
            // Delete old events
            $deleted_events = $wpdb->query($wpdb->prepare(
                "DELETE FROM $events_table WHERE DATE(timestamp) < %s",
                $cutoff_date
            ));
            
            // Delete old sessions
            $deleted_sessions = $wpdb->query($wpdb->prepare(
                "DELETE FROM $sessions_table WHERE DATE(start_time) < %s",
                $cutoff_date
            ));
            
            // Keep metrics longer (1 year)
            $metrics_cutoff = date('Y-m-d', strtotime('-365 days'));
            $deleted_metrics = $wpdb->query($wpdb->prepare(
                "DELETE FROM $metrics_table WHERE metric_date < %s",
                $metrics_cutoff
            ));
            
            ant_media_log("Cleaned up old data: {$deleted_events} events, {$deleted_sessions} sessions, {$deleted_metrics} metrics", 'info');
            
        } catch (Exception $e) {
            ant_media_log('Error cleaning up old data: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Generate a session ID
     */
    private static function generate_session_id($user_id, $stream_id) {
        return 'amsa_' . $user_id . '_' . md5($stream_id . time() . wp_rand());
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
}

/**
 * Helper function to record stream events
 */
function amsa_record_stream_event($stream_id, $user_id, $event_type, $event_data = null, $session_id = null) {
    return AMSA_Database::record_event($user_id, $stream_id, $event_type, $event_data, $session_id);
}

/**
 * Schedule daily metrics generation
 */
add_action('init', function() {
    if (!wp_next_scheduled('amsa_generate_daily_metrics')) {
        wp_schedule_event(strtotime('02:00:00'), 'daily', 'amsa_generate_daily_metrics');
    }
});

add_action('amsa_generate_daily_metrics', function() {
    AMSA_Database::generate_daily_metrics();
});

/**
 * Schedule weekly cleanup
 */
add_action('init', function() {
    if (!wp_next_scheduled('amsa_cleanup_old_data')) {
        wp_schedule_event(strtotime('Sunday 03:00:00'), 'weekly', 'amsa_cleanup_old_data');
    }
});

add_action('amsa_cleanup_old_data', function() {
    AMSA_Database::cleanup_old_data();
}); 