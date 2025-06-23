<?php
if (!defined('ABSPATH')) exit;

/**
 * Stream Analytics and Reporting System
 * Provides detailed insights into stream performance and user engagement
 */
class AMSA_Analytics {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get comprehensive stream analytics dashboard data
     */
    public static function get_dashboard_analytics($days = 30) {
        try {
            $analytics = [
                'overview' => self::get_overview_metrics($days),
                'streams' => self::get_stream_performance($days),
                'users' => self::get_user_analytics($days),
                'tiers' => self::get_tier_analytics($days),
                'timeline' => self::get_timeline_data($days),
                'errors' => self::get_error_analytics($days)
            ];
            
            return $analytics;
            
        } catch (Exception $e) {
            ant_media_log('Error generating analytics dashboard: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get overview metrics (KPIs)
     */
    private static function get_overview_metrics($days) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Total sessions
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $events_table 
            WHERE event_type = 'play' AND DATE(timestamp) >= %s",
            $date_from
        ));
        
        // Unique viewers
        $unique_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $events_table 
            WHERE event_type = 'play' AND DATE(timestamp) >= %s",
            $date_from
        ));
        
        // Total watch time (in seconds)
        $total_watch_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_duration) FROM $sessions_table 
            WHERE DATE(start_time) >= %s",
            $date_from
        ));
        
        // Average session duration
        $avg_session_duration = $total_sessions > 0 ? ($total_watch_time / $total_sessions) : 0;
        
        // Current live viewers (last 5 minutes)
        $current_live = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $events_table 
            WHERE event_type = 'play' AND timestamp >= %s",
            date('Y-m-d H:i:s', strtotime('-5 minutes'))
        ));
        
        // Peak concurrent viewers
        $peak_concurrent = self::calculate_peak_concurrent($date_from);
        
        // Compare with previous period
        $prev_date_from = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
        $prev_date_to = date('Y-m-d', strtotime("-{$days} days"));
        
        $prev_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM $events_table 
            WHERE event_type = 'play' AND DATE(timestamp) >= %s AND DATE(timestamp) < %s",
            $prev_date_from, $prev_date_to
        ));
        
        $sessions_change = $prev_sessions > 0 ? (($total_sessions - $prev_sessions) / $prev_sessions * 100) : 0;
        
        return [
            'total_sessions' => intval($total_sessions),
            'unique_viewers' => intval($unique_viewers),
            'total_watch_time' => intval($total_watch_time),
            'total_watch_time_formatted' => self::format_duration($total_watch_time),
            'avg_session_duration' => round($avg_session_duration, 2),
            'avg_session_duration_formatted' => self::format_duration($avg_session_duration),
            'current_live_viewers' => intval($current_live),
            'peak_concurrent_viewers' => intval($peak_concurrent),
            'sessions_change_percent' => round($sessions_change, 1)
        ];
    }
    
    /**
     * Get individual stream performance metrics
     */
    private static function get_stream_performance($days) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $streams = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                stream_id,
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(DISTINCT user_id) as unique_viewers,
                SUM(total_duration) as total_watch_time,
                AVG(total_duration) as avg_session_duration,
                COUNT(CASE WHEN error_count > 0 THEN 1 END) as error_sessions,
                MAX(total_duration) as longest_session
            FROM $sessions_table 
            WHERE DATE(start_time) >= %s 
            GROUP BY stream_id 
            ORDER BY total_sessions DESC",
            $date_from
        ));
        
        // Enhance with additional metrics
        foreach ($streams as &$stream) {
            $stream->total_watch_time = intval($stream->total_watch_time);
            $stream->total_watch_time_formatted = self::format_duration($stream->total_watch_time);
            $stream->avg_session_duration = round($stream->avg_session_duration, 2);
            $stream->avg_session_duration_formatted = self::format_duration($stream->avg_session_duration);
            $stream->longest_session_formatted = self::format_duration($stream->longest_session);
            $stream->error_rate = $stream->total_sessions > 0 ? round(($stream->error_sessions / $stream->total_sessions) * 100, 2) : 0;
            
            // Get recent activity (last 24 hours)
            $recent_activity = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM $events_table 
                WHERE stream_id = %s AND event_type = 'play' AND timestamp >= %s",
                $stream->stream_id, date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));
            $stream->recent_activity = intval($recent_activity);
        }
        
        return $streams;
    }
    
    /**
     * Get user engagement analytics
     */
    private static function get_user_analytics($days) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Top viewers by watch time
        $top_viewers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.user_id,
                u.user_login as username,
                u.display_name,
                COUNT(DISTINCT s.session_id) as total_sessions,
                SUM(s.total_duration) as total_watch_time,
                AVG(s.total_duration) as avg_session_duration,
                s.tier
            FROM $sessions_table s
            LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
            WHERE DATE(s.start_time) >= %s 
            GROUP BY s.user_id 
            ORDER BY total_watch_time DESC 
            LIMIT 20",
            $date_from
        ));
        
        // User retention (returning viewers)
        $returning_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM (
                SELECT user_id, COUNT(DISTINCT DATE(timestamp)) as days_active
                FROM $events_table 
                WHERE event_type = 'play' AND DATE(timestamp) >= %s
                GROUP BY user_id
                HAVING days_active > 1
            ) as returning_users",
            $date_from
        ));
        
        $total_unique_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $events_table 
            WHERE event_type = 'play' AND DATE(timestamp) >= %s",
            $date_from
        ));
        
        $retention_rate = $total_unique_viewers > 0 ? ($returning_viewers / $total_unique_viewers * 100) : 0;
        
        // Format top viewers data
        foreach ($top_viewers as &$viewer) {
            $viewer->total_watch_time_formatted = self::format_duration($viewer->total_watch_time);
            $viewer->avg_session_duration_formatted = self::format_duration($viewer->avg_session_duration);
        }
        
        return [
            'top_viewers' => $top_viewers,
            'total_unique_viewers' => intval($total_unique_viewers),
            'returning_viewers' => intval($returning_viewers),
            'retention_rate' => round($retention_rate, 2)
        ];
    }
    
    /**
     * Get tier-based analytics
     */
    private static function get_tier_analytics($days) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        $sessions_table = $wpdb->prefix . 'amsa_stream_sessions';
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $tier_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                tier,
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(DISTINCT user_id) as unique_viewers,
                SUM(total_duration) as total_watch_time,
                AVG(total_duration) as avg_session_duration
            FROM $sessions_table 
            WHERE DATE(start_time) >= %s AND tier IS NOT NULL
            GROUP BY tier 
            ORDER BY total_sessions DESC",
            $date_from
        ));
        
        // Calculate percentages and format data
        $total_sessions = array_sum(array_column($tier_stats, 'total_sessions'));
        
        foreach ($tier_stats as &$tier) {
            $tier->percentage = $total_sessions > 0 ? round(($tier->total_sessions / $total_sessions) * 100, 1) : 0;
            $tier->total_watch_time_formatted = self::format_duration($tier->total_watch_time);
            $tier->avg_session_duration_formatted = self::format_duration($tier->avg_session_duration);
        }
        
        return $tier_stats;
    }
    
    /**
     * Get timeline data for charts
     */
    private static function get_timeline_data($days) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Daily session counts
        $daily_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(timestamp) as date,
                COUNT(DISTINCT session_id) as sessions,
                COUNT(DISTINCT user_id) as unique_viewers
            FROM $events_table 
            WHERE event_type = 'play' AND DATE(timestamp) >= %s
            GROUP BY DATE(timestamp) 
            ORDER BY date",
            $date_from
        ));
        
        // Hourly activity for today
        $hourly_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                HOUR(timestamp) as hour,
                COUNT(DISTINCT session_id) as sessions
            FROM $events_table 
            WHERE event_type = 'play' AND DATE(timestamp) = %s
            GROUP BY HOUR(timestamp) 
            ORDER BY hour",
            date('Y-m-d')
        ));
        
        return [
            'daily_sessions' => $daily_sessions,
            'hourly_activity' => $hourly_activity
        ];
    }
    
    /**
     * Get error analytics
     */
    private static function get_error_analytics($days) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        // Error counts by type
        $error_types = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_data,
                COUNT(*) as error_count,
                COUNT(DISTINCT session_id) as affected_sessions
            FROM $events_table 
            WHERE event_type = 'error' AND DATE(timestamp) >= %s
            GROUP BY event_data 
            ORDER BY error_count DESC",
            $date_from
        ));
        
        // Error rate by stream
        $stream_errors = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                stream_id,
                COUNT(*) as total_errors,
                COUNT(DISTINCT session_id) as error_sessions
            FROM $events_table 
            WHERE event_type = 'error' AND DATE(timestamp) >= %s
            GROUP BY stream_id 
            ORDER BY total_errors DESC",
            $date_from
        ));
        
        // Daily error trend
        $daily_errors = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(timestamp) as date,
                COUNT(*) as error_count
            FROM $events_table 
            WHERE event_type = 'error' AND DATE(timestamp) >= %s
            GROUP BY DATE(timestamp) 
            ORDER BY date",
            $date_from
        ));
        
        return [
            'error_types' => $error_types,
            'stream_errors' => $stream_errors,
            'daily_errors' => $daily_errors
        ];
    }
    
    /**
     * Calculate peak concurrent viewers
     */
    private static function calculate_peak_concurrent($date_from) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'amsa_stream_events';
        
        // This is a simplified calculation - in production you'd want more sophisticated real-time tracking
        $peak = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(concurrent_count) FROM (
                SELECT 
                    DATE_FORMAT(timestamp, '%%Y-%%m-%%d %%H:%%i') as minute_bucket,
                    COUNT(DISTINCT session_id) as concurrent_count
                FROM $events_table 
                WHERE event_type = 'play' AND DATE(timestamp) >= %s
                GROUP BY minute_bucket
            ) as minute_counts",
            $date_from
        ));
        
        return intval($peak);
    }
    
    /**
     * Export analytics data to CSV
     */
    public static function export_analytics_csv($type = 'overview', $days = 30) {
        try {
            $filename = "amsa-analytics-{$type}-" . date('Y-m-d') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            switch ($type) {
                case 'streams':
                    $data = self::get_stream_performance($days);
                    fputcsv($output, ['Stream ID', 'Total Sessions', 'Unique Viewers', 'Total Watch Time', 'Avg Session Duration', 'Error Rate']);
                    
                    foreach ($data as $stream) {
                        fputcsv($output, [
                            $stream->stream_id,
                            $stream->total_sessions,
                            $stream->unique_viewers,
                            $stream->total_watch_time_formatted,
                            $stream->avg_session_duration_formatted,
                            $stream->error_rate . '%'
                        ]);
                    }
                    break;
                    
                case 'users':
                    $data = self::get_user_analytics($days);
                    fputcsv($output, ['User ID', 'Username', 'Display Name', 'Total Sessions', 'Total Watch Time', 'Avg Session Duration', 'Tier']);
                    
                    foreach ($data['top_viewers'] as $viewer) {
                        fputcsv($output, [
                            $viewer->user_id,
                            $viewer->username,
                            $viewer->display_name,
                            $viewer->total_sessions,
                            $viewer->total_watch_time_formatted,
                            $viewer->avg_session_duration_formatted,
                            $viewer->tier
                        ]);
                    }
                    break;
                    
                default:
                    $data = self::get_overview_metrics($days);
                    fputcsv($output, ['Metric', 'Value']);
                    
                    foreach ($data as $key => $value) {
                        fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
                    }
                    break;
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            ant_media_log('Error exporting analytics CSV: ' . $e->getMessage(), 'error');
            wp_die('Export failed. Please try again.');
        }
    }
    
    /**
     * Get real-time analytics (for AJAX updates)
     */
    public static function get_realtime_analytics() {
        global $wpdb;
        
        try {
            $events_table = $wpdb->prefix . 'amsa_stream_events';
            
            // Current live viewers (last 5 minutes)
            $current_live = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM $events_table 
                WHERE event_type = 'play' AND timestamp >= %s",
                date('Y-m-d H:i:s', strtotime('-5 minutes'))
            ));
            
            // Active streams
            $active_streams = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    stream_id,
                    COUNT(DISTINCT session_id) as current_viewers
                FROM $events_table 
                WHERE event_type = 'play' AND timestamp >= %s
                GROUP BY stream_id
                ORDER BY current_viewers DESC",
                date('Y-m-d H:i:s', strtotime('-5 minutes'))
            ));
            
            // Recent events (last 10)
            $recent_events = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    timestamp,
                    stream_id,
                    event_type,
                    tier
                FROM $events_table 
                WHERE timestamp >= %s
                ORDER BY timestamp DESC
                LIMIT 10",
                date('Y-m-d H:i:s', strtotime('-1 hour'))
            ));
            
            return [
                'current_live_viewers' => intval($current_live),
                'active_streams' => $active_streams,
                'recent_events' => $recent_events,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            ant_media_log('Error getting real-time analytics: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Format duration in seconds to human readable format
     */
    private static function format_duration($seconds) {
        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . 'm';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = round(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }
}

/**
 * AJAX handler for real-time analytics
 */
add_action('wp_ajax_amsa_get_realtime_analytics', function() {
    check_ajax_referer('amsa_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $analytics = AMSA_Analytics::get_realtime_analytics();
    
    if ($analytics) {
        wp_send_json_success($analytics);
    } else {
        wp_send_json_error('Failed to get analytics data');
    }
});

/**
 * AJAX handler for analytics export
 */
add_action('wp_ajax_amsa_export_analytics', function() {
    check_ajax_referer('amsa_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $type = sanitize_text_field($_GET['type'] ?? 'overview');
    $days = intval($_GET['days'] ?? 30);
    
    AMSA_Analytics::export_analytics_csv($type, $days);
}); 