<?php
if (!defined('ABSPATH')) exit;

/**
 * Video Library Database Management
 * Handles custom table creation and schema management
 */
class Video_Library_Database {
    
    /**
     * Create custom database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Video views table
        $views_table = $wpdb->prefix . 'video_library_views';
        $views_sql = "CREATE TABLE {$views_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            video_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            duration_watched int(11) unsigned DEFAULT 0,
            view_count int(11) unsigned DEFAULT 1,
            view_date datetime NOT NULL,
            last_viewed datetime NOT NULL,
            user_ip varchar(45) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            PRIMARY KEY (id),
            KEY video_id (video_id),
            KEY user_id (user_id),
            KEY view_date (view_date),
            KEY user_video_date (user_id, video_id, view_date)
        ) {$charset_collate};";
        
        // Video favorites table
        $favorites_table = $wpdb->prefix . 'video_library_favorites';
        $favorites_sql = "CREATE TABLE {$favorites_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            video_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            favorited_date datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_video (user_id, video_id),
            KEY video_id (video_id),
            KEY favorited_date (favorited_date)
        ) {$charset_collate};";
        
        // Video watch sessions table (for detailed analytics)
        $sessions_table = $wpdb->prefix . 'video_library_sessions';
        $sessions_sql = "CREATE TABLE {$sessions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            video_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            session_start datetime NOT NULL,
            session_end datetime DEFAULT NULL,
            duration_watched int(11) unsigned DEFAULT 0,
            percentage_watched decimal(5,2) DEFAULT 0.00,
            playback_quality varchar(20) DEFAULT '',
            device_type varchar(50) DEFAULT '',
            browser varchar(100) DEFAULT '',
            referrer_url text DEFAULT '',
            exit_point int(11) unsigned DEFAULT 0,
            completed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY video_id (video_id),
            KEY user_id (user_id),
            KEY session_start (session_start),
            KEY completed (completed)
        ) {$charset_collate};";
        
        // Video search queries table (for analytics)
        $searches_table = $wpdb->prefix . 'video_library_searches';
        $searches_sql = "CREATE TABLE {$searches_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            search_query varchar(255) NOT NULL,
            results_count int(11) unsigned DEFAULT 0,
            clicked_video_id bigint(20) unsigned DEFAULT NULL,
            search_date datetime NOT NULL,
            user_ip varchar(45) DEFAULT '',
            PRIMARY KEY (id),
            KEY search_query (search_query),
            KEY search_date (search_date),
            KEY user_id (user_id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($views_sql);
        dbDelta($favorites_sql);
        dbDelta($sessions_sql);
        dbDelta($searches_sql);
        
        // Update database version
        update_option('video_library_db_version', '1.0');
        
        video_library_log('Database tables created successfully', 'info');
    }
    
    /**
     * Check if database needs updating
     */
    public static function maybe_update_database() {
        $current_version = get_option('video_library_db_version', '0');
        $target_version = '1.0';
        
        if (version_compare($current_version, $target_version, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Drop custom tables (used during uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'video_library_views',
            $wpdb->prefix . 'video_library_favorites',
            $wpdb->prefix . 'video_library_sessions',
            $wpdb->prefix . 'video_library_searches'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option('video_library_db_version');
        
        video_library_log('Database tables dropped', 'info');
    }
    
    /**
     * Get table names
     */
    public static function get_table_names() {
        global $wpdb;
        
        return [
            'views' => $wpdb->prefix . 'video_library_views',
            'favorites' => $wpdb->prefix . 'video_library_favorites',
            'sessions' => $wpdb->prefix . 'video_library_sessions',
            'searches' => $wpdb->prefix . 'video_library_searches'
        ];
    }
    
    /**
     * Get database statistics
     */
    public static function get_database_stats() {
        global $wpdb;
        
        $tables = self::get_table_names();
        $stats = [];
        
        foreach ($tables as $name => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='{$wpdb->dbname}' AND table_name='{$table}'");
            
            $stats[$name] = [
                'table' => $table,
                'rows' => intval($count),
                'size_mb' => floatval($size)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Optimize database tables
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = self::get_table_names();
        $results = [];
        
        foreach ($tables as $name => $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            $results[$name] = $result !== false;
        }
        
        video_library_log('Database tables optimized', 'info');
        
        return $results;
    }
    
    /**
     * Backup database tables to SQL file
     */
    public static function backup_tables($file_path = null) {
        global $wpdb;
        
        if (!$file_path) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/video-library-backup-' . date('Y-m-d-H-i-s') . '.sql';
        }
        
        $tables = self::get_table_names();
        $sql_dump = '';
        
        foreach ($tables as $name => $table) {
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
            if ($create_table) {
                $sql_dump .= "\n\n-- Table structure for {$table}\n";
                $sql_dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql_dump .= $create_table[1] . ";\n\n";
                
                // Get table data
                $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
                if ($rows) {
                    $sql_dump .= "-- Data for table {$table}\n";
                    foreach ($rows as $row) {
                        $values = array_map(function($value) use ($wpdb) {
                            return $value === null ? 'NULL' : "'" . $wpdb->_real_escape($value) . "'";
                        }, array_values($row));
                        
                        $sql_dump .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql_dump .= "\n";
                }
            }
        }
        
        $result = file_put_contents($file_path, $sql_dump);
        
        if ($result !== false) {
            video_library_log("Database backup created: {$file_path}", 'info');
            return $file_path;
        } else {
            video_library_log("Failed to create database backup", 'error');
            return false;
        }
    }
    
    /**
     * Clean up old data based on retention settings
     */
    public static function cleanup_old_data() {
        global $wpdb;
        
        $retention_days = get_option('video_library_data_retention_days', 365);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $tables = self::get_table_names();
        $total_deleted = 0;
        
        // Clean up views table
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['views']} WHERE view_date < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        // Clean up sessions table
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['sessions']} WHERE session_start < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        // Clean up searches table
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tables['searches']} WHERE search_date < %s",
            $cutoff_date
        ));
        $total_deleted += $deleted;
        
        // Note: We don't clean up favorites as they don't have a time-based expiry
        
        if ($total_deleted > 0) {
            video_library_log("Cleaned up {$total_deleted} old records", 'info');
            
            // Optimize tables after cleanup
            self::optimize_tables();
        }
        
        return $total_deleted;
    }
    
    /**
     * Get database health status
     */
    public static function get_health_status() {
        global $wpdb;
        
        $tables = self::get_table_names();
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];
        
        // Check if all tables exist
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if (!$exists) {
                $health['status'] = 'critical';
                $health['issues'][] = "Missing table: {$table}";
            }
        }
        
        // Check table sizes
        $stats = self::get_database_stats();
        foreach ($stats as $name => $stat) {
            if ($stat['size_mb'] > 100) { // 100MB threshold
                $health['recommendations'][] = "Consider archiving old data from {$name} table (current size: {$stat['size_mb']}MB)";
            }
            
            if ($stat['rows'] > 1000000) { // 1M rows threshold
                $health['recommendations'][] = "Large number of rows in {$name} table ({$stat['rows']} rows) - consider cleanup";
            }
        }
        
        // Check for orphaned records
        $orphaned_views = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$tables['views']} v 
            LEFT JOIN {$wpdb->posts} p ON v.video_id = p.ID 
            WHERE p.ID IS NULL
        ");
        
        if ($orphaned_views > 0) {
            $health['issues'][] = "{$orphaned_views} orphaned view records found";
            $health['recommendations'][] = "Clean up orphaned view records";
        }
        
        if (!empty($health['issues'])) {
            $health['status'] = count($health['issues']) > 3 ? 'critical' : 'warning';
        }
        
        return $health;
    }
    
    /**
     * Repair database issues
     */
    public static function repair_database() {
        global $wpdb;
        
        $tables = self::get_table_names();
        $repairs = [];
        
        // Recreate missing tables
        foreach ($tables as $name => $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if (!$exists) {
                self::create_tables();
                $repairs[] = "Recreated missing table: {$table}";
                break; // create_tables() creates all tables
            }
        }
        
        // Clean up orphaned records
        $deleted = $wpdb->query("
            DELETE v FROM {$tables['views']} v 
            LEFT JOIN {$wpdb->posts} p ON v.video_id = p.ID 
            WHERE p.ID IS NULL
        ");
        if ($deleted > 0) {
            $repairs[] = "Removed {$deleted} orphaned view records";
        }
        
        $deleted = $wpdb->query("
            DELETE f FROM {$tables['favorites']} f 
            LEFT JOIN {$wpdb->posts} p ON f.video_id = p.ID 
            WHERE p.ID IS NULL
        ");
        if ($deleted > 0) {
            $repairs[] = "Removed {$deleted} orphaned favorite records";
        }
        
        // Repair table structures
        foreach ($tables as $name => $table) {
            $wpdb->query("REPAIR TABLE {$table}");
            $repairs[] = "Repaired table: {$table}";
        }
        
        video_library_log('Database repair completed: ' . implode(', ', $repairs), 'info');
        
        return $repairs;
    }
} 