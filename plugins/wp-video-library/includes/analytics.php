<?php
if (!defined('ABSPATH')) exit;

/**
 * Video Library Analytics Class
 * Tracks video views, user interactions, and generates usage reports
 */
class Video_Library_Analytics {
    
    /**
     * Track a video view
     */
    public static function track_view($video_id, $user_id = null, $duration_watched = 0) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$video_id || !$user_id) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'video_library_views';
        
        // Check if this user has already viewed this video today
        $today = current_time('Y-m-d');
        $existing_view = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE video_id = %d AND user_id = %d AND DATE(view_date) = %s",
            $video_id, $user_id, $today
        ));
        
        if ($existing_view) {
            // Update existing view with new duration if it's longer
            if ($duration_watched > $existing_view->duration_watched) {
                $wpdb->update(
                    $table_name,
                    [
                        'duration_watched' => $duration_watched,
                        'view_count' => $existing_view->view_count + 1,
                        'last_viewed' => current_time('mysql')
                    ],
                    ['id' => $existing_view->id],
                    ['%d', '%d', '%s'],
                    ['%d']
                );
            } else {
                // Just increment view count
                $wpdb->update(
                    $table_name,
                    [
                        'view_count' => $existing_view->view_count + 1,
                        'last_viewed' => current_time('mysql')
                    ],
                    ['id' => $existing_view->id],
                    ['%d', '%s'],
                    ['%d']
                );
            }
        } else {
            // Insert new view record
            $wpdb->insert(
                $table_name,
                [
                    'video_id' => $video_id,
                    'user_id' => $user_id,
                    'duration_watched' => $duration_watched,
                    'view_count' => 1,
                    'view_date' => current_time('mysql'),
                    'last_viewed' => current_time('mysql'),
                    'user_ip' => self::get_user_ip(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
            );
        }
        
        // Update video meta with total view count
        self::update_video_view_count($video_id);
        
        video_library_log("Tracked view for video {$video_id} by user {$user_id}", 'info');
        
        return true;
    }
    
    /**
     * Toggle video favorite status
     */
    public static function toggle_favorite($video_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$video_id || !$user_id) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'video_library_favorites';
        
        // Check if already favorited
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE video_id = %d AND user_id = %d",
            $video_id, $user_id
        ));
        
        if ($existing) {
            // Remove from favorites
            $wpdb->delete(
                $table_name,
                ['video_id' => $video_id, 'user_id' => $user_id],
                ['%d', '%d']
            );
            $is_favorite = false;
        } else {
            // Add to favorites
            $wpdb->insert(
                $table_name,
                [
                    'video_id' => $video_id,
                    'user_id' => $user_id,
                    'favorited_date' => current_time('mysql')
                ],
                ['%d', '%d', '%s']
            );
            $is_favorite = true;
        }
        
        // Update favorite count meta
        self::update_video_favorite_count($video_id);
        
        video_library_log("Toggled favorite for video {$video_id} by user {$user_id}: " . ($is_favorite ? 'added' : 'removed'), 'info');
        
        return $is_favorite;
    }
    
    /**
     * Check if user has favorited a video
     */
    public static function is_favorited($video_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$video_id || !$user_id) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'video_library_favorites';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE video_id = %d AND user_id = %d",
            $video_id, $user_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Get user's favorite videos
     */
    public static function get_user_favorites($user_id = null, $limit = 12) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        $table_name = $wpdb->prefix . 'video_library_favorites';
        
        $video_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT video_id FROM {$table_name} WHERE user_id = %d ORDER BY favorited_date DESC LIMIT %d",
            $user_id, $limit
        ));
        
        if (empty($video_ids)) {
            return [];
        }
        
        // Get the actual video posts
        $args = [
            'post_type' => 'video_library_item',
            'post_status' => 'publish',
            'post__in' => $video_ids,
            'orderby' => 'post__in',
            'posts_per_page' => $limit
        ];
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    /**
     * Get video analytics data
     */
    public static function get_video_analytics($video_id, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'video_library_views';
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get total views
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(view_count) FROM {$table_name} WHERE video_id = %d AND view_date >= %s",
            $video_id, $start_date
        ));
        
        // Get unique viewers
        $unique_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE video_id = %d AND view_date >= %s",
            $video_id, $start_date
        ));
        
        // Get average watch duration
        $avg_duration = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(duration_watched) FROM {$table_name} WHERE video_id = %d AND view_date >= %s AND duration_watched > 0",
            $video_id, $start_date
        ));
        
        // Get daily views for chart
        $daily_views = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(view_date) as date, SUM(view_count) as views 
             FROM {$table_name} 
             WHERE video_id = %d AND view_date >= %s 
             GROUP BY DATE(view_date) 
             ORDER BY date DESC",
            $video_id, $start_date
        ));
        
        // Get favorite count
        $favorites_table = $wpdb->prefix . 'video_library_favorites';
        $favorite_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$favorites_table} WHERE video_id = %d",
            $video_id
        ));
        
        return [
            'total_views' => intval($total_views),
            'unique_viewers' => intval($unique_viewers),
            'average_duration' => round(floatval($avg_duration), 2),
            'favorite_count' => intval($favorite_count),
            'daily_views' => $daily_views
        ];
    }
    
    /**
     * Get overall analytics
     */
    public static function get_overall_analytics($days = 30) {
        global $wpdb;
        
        $views_table = $wpdb->prefix . 'video_library_views';
        $favorites_table = $wpdb->prefix . 'video_library_favorites';
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Total videos
        $total_videos = wp_count_posts('video_library_item')->publish;
        
        // Total views
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(view_count) FROM {$views_table} WHERE view_date >= %s",
            $start_date
        ));
        
        // Total unique viewers
        $unique_viewers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$views_table} WHERE view_date >= %s",
            $start_date
        ));
        
        // Total favorites
        $total_favorites = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$favorites_table} WHERE favorited_date >= %s",
            $start_date
        ));
        
        // Most viewed videos
        $most_viewed = $wpdb->get_results($wpdb->prepare(
            "SELECT video_id, SUM(view_count) as total_views 
             FROM {$views_table} 
             WHERE view_date >= %s 
             GROUP BY video_id 
             ORDER BY total_views DESC 
             LIMIT 10",
            $start_date
        ));
        
        // Top users by watch time
        $top_users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, SUM(duration_watched) as total_duration, SUM(view_count) as total_views
             FROM {$views_table} 
             WHERE view_date >= %s AND duration_watched > 0
             GROUP BY user_id 
             ORDER BY total_duration DESC 
             LIMIT 10",
            $start_date
        ));
        
        // Daily analytics
        $daily_analytics = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(view_date) as date, 
                    SUM(view_count) as views,
                    COUNT(DISTINCT user_id) as unique_viewers,
                    AVG(duration_watched) as avg_duration
             FROM {$views_table} 
             WHERE view_date >= %s 
             GROUP BY DATE(view_date) 
             ORDER BY date DESC",
            $start_date
        ));
        
        return [
            'total_videos' => intval($total_videos),
            'total_views' => intval($total_views),
            'unique_viewers' => intval($unique_viewers),
            'total_favorites' => intval($total_favorites),
            'most_viewed' => $most_viewed,
            'top_users' => $top_users,
            'daily_analytics' => $daily_analytics
        ];
    }
    
    /**
     * Get user analytics
     */
    public static function get_user_analytics($user_id = null, $days = 30) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        $table_name = $wpdb->prefix . 'video_library_views';
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Total views by user
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(view_count) FROM {$table_name} WHERE user_id = %d AND view_date >= %s",
            $user_id, $start_date
        ));
        
        // Total watch time
        $total_duration = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(duration_watched) FROM {$table_name} WHERE user_id = %d AND view_date >= %s",
            $user_id, $start_date
        ));
        
        // Unique videos watched
        $unique_videos = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT video_id) FROM {$table_name} WHERE user_id = %d AND view_date >= %s",
            $user_id, $start_date
        ));
        
        // Recently watched videos
        $recent_videos = $wpdb->get_results($wpdb->prepare(
            "SELECT video_id, last_viewed, duration_watched, view_count
             FROM {$table_name} 
             WHERE user_id = %d 
             ORDER BY last_viewed DESC 
             LIMIT 10",
            $user_id
        ));
        
        return [
            'total_views' => intval($total_views),
            'total_duration' => intval($total_duration),
            'unique_videos' => intval($unique_videos),
            'recent_videos' => $recent_videos
        ];
    }
    
    /**
     * Update video view count meta
     */
    private static function update_video_view_count($video_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'video_library_views';
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(view_count) FROM {$table_name} WHERE video_id = %d",
            $video_id
        ));
        
        update_post_meta($video_id, '_video_view_count', intval($total_views));
    }
    
    /**
     * Update video favorite count meta
     */
    private static function update_video_favorite_count($video_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'video_library_favorites';
        $favorite_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE video_id = %d",
            $video_id
        ));
        
        update_post_meta($video_id, '_video_favorite_count', intval($favorite_count));
    }
    
    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '0.0.0.0';
    }
    
    /**
     * Clean up old analytics data
     */
    public static function cleanup_old_data($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Clean up old view records
        $views_table = $wpdb->prefix . 'video_library_views';
        $deleted_views = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$views_table} WHERE view_date < %s",
            $cutoff_date
        ));
        
        if ($deleted_views > 0) {
            video_library_log("Cleaned up {$deleted_views} old view records", 'info');
        }
        
        return $deleted_views;
    }
    
    /**
     * Export analytics data to CSV
     */
    public static function export_analytics_csv($type = 'overall', $days = 30) {
        if ($type === 'overall') {
            $data = self::get_overall_analytics($days);
            $filename = 'video-library-analytics-' . date('Y-m-d') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($output, ['Metric', 'Value']);
            
            // Write data
            fputcsv($output, ['Total Videos', $data['total_videos']]);
            fputcsv($output, ['Total Views', $data['total_views']]);
            fputcsv($output, ['Unique Viewers', $data['unique_viewers']]);
            fputcsv($output, ['Total Favorites', $data['total_favorites']]);
            
            // Add daily data
            fputcsv($output, ['']);
            fputcsv($output, ['Date', 'Views', 'Unique Viewers', 'Avg Duration']);
            foreach ($data['daily_analytics'] as $day) {
                fputcsv($output, [
                    $day->date,
                    $day->views,
                    $day->unique_viewers,
                    round($day->avg_duration, 2)
                ]);
            }
            
            fclose($output);
            exit;
        }
    }
} 