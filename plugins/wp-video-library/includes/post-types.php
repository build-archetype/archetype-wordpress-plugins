<?php
if (!defined('ABSPATH')) exit;

/**
 * Register video library custom post type and taxonomies
 */
function video_library_register_post_types() {
    // Register video library item post type
    $args = [
        'labels' => [
            'name' => __('Video Library', 'video-library'),
            'singular_name' => __('Video', 'video-library'),
            'menu_name' => __('Video Library', 'video-library'),
            'add_new' => __('Add New Video', 'video-library'),
            'add_new_item' => __('Add New Video', 'video-library'),
            'edit_item' => __('Edit Video', 'video-library'),
            'new_item' => __('New Video', 'video-library'),
            'view_item' => __('View Video', 'video-library'),
            'search_items' => __('Search Videos', 'video-library'),
            'not_found' => __('No videos found', 'video-library'),
            'not_found_in_trash' => __('No videos found in trash', 'video-library'),
            'all_items' => __('All Videos', 'video-library'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-video-alt3',
        'menu_position' => 25,
        'capability_type' => 'post',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'video-library'],
        'has_archive' => false,
    ];
    
    register_post_type('video_library_item', $args);
    
    // Register video categories taxonomy
    register_taxonomy('video_category', 'video_library_item', [
        'labels' => [
            'name' => __('Video Categories', 'video-library'),
            'singular_name' => __('Video Category', 'video-library'),
            'menu_name' => __('Categories', 'video-library'),
            'all_items' => __('All Categories', 'video-library'),
            'edit_item' => __('Edit Category', 'video-library'),
            'view_item' => __('View Category', 'video-library'),
            'update_item' => __('Update Category', 'video-library'),
            'add_new_item' => __('Add New Category', 'video-library'),
            'new_item_name' => __('New Category Name', 'video-library'),
            'search_items' => __('Search Categories', 'video-library'),
            'not_found' => __('No categories found', 'video-library'),
        ],
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'video-category'],
    ]);
    
    // Register video tags taxonomy
    register_taxonomy('video_tag', 'video_library_item', [
        'labels' => [
            'name' => __('Video Tags', 'video-library'),
            'singular_name' => __('Video Tag', 'video-library'),
            'menu_name' => __('Tags', 'video-library'),
            'all_items' => __('All Tags', 'video-library'),
            'edit_item' => __('Edit Tag', 'video-library'),
            'view_item' => __('View Tag', 'video-library'),
            'update_item' => __('Update Tag', 'video-library'),
            'add_new_item' => __('Add New Tag', 'video-library'),
            'new_item_name' => __('New Tag Name', 'video-library'),
            'search_items' => __('Search Tags', 'video-library'),
            'not_found' => __('No tags found', 'video-library'),
        ],
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'video-tag'],
    ]);
}

/**
 * Add custom meta boxes for video library items
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'video_library_details',
        __('Video Details', 'video-library'),
        'video_library_details_meta_box',
        'video_library_item',
        'normal',
        'high'
    );
    
    add_meta_box(
        'video_library_s3_settings',
        __('S3 Storage Settings', 'video-library'),
        'video_library_s3_meta_box',
        'video_library_item',
        'normal',
        'high'
    );
    
    add_meta_box(
        'video_library_access_control',
        __('Access Control', 'video-library'),
        'video_library_access_control_meta_box',
        'video_library_item',
        'side',
        'high'
    );
    
    add_meta_box(
        'video_library_analytics',
        __('Video Analytics', 'video-library'),
        'video_library_analytics_meta_box',
        'video_library_item',
        'side',
        'default'
    );
});

/**
 * Video details meta box
 */
function video_library_details_meta_box($post) {
    wp_nonce_field('video_library_meta_box', 'video_library_meta_nonce');
    
    $duration = get_post_meta($post->ID, '_video_duration', true);
    $file_size = get_post_meta($post->ID, '_video_file_size', true);
    $video_quality = get_post_meta($post->ID, '_video_quality', true);
    $video_format = get_post_meta($post->ID, '_video_format', true);
    $thumbnail = get_post_meta($post->ID, '_video_thumbnail', true);
    ?>
    <table class="form-table">
        <tr>
            <th><label for="video_duration"><?php _e('Duration (seconds)', 'video-library'); ?></label></th>
            <td><input type="number" id="video_duration" name="video_duration" value="<?php echo esc_attr($duration); ?>" min="0" /></td>
        </tr>
        <tr>
            <th><label for="video_file_size"><?php _e('File Size (bytes)', 'video-library'); ?></label></th>
            <td><input type="number" id="video_file_size" name="video_file_size" value="<?php echo esc_attr($file_size); ?>" min="0" /></td>
        </tr>
        <tr>
            <th><label for="video_quality"><?php _e('Video Quality', 'video-library'); ?></label></th>
            <td>
                <select id="video_quality" name="video_quality">
                    <option value="240p" <?php selected($video_quality, '240p'); ?>>240p</option>
                    <option value="360p" <?php selected($video_quality, '360p'); ?>>360p</option>
                    <option value="480p" <?php selected($video_quality, '480p'); ?>>480p</option>
                    <option value="720p" <?php selected($video_quality, '720p'); ?>>720p (HD)</option>
                    <option value="1080p" <?php selected($video_quality, '1080p'); ?>>1080p (Full HD)</option>
                    <option value="1440p" <?php selected($video_quality, '1440p'); ?>>1440p (2K)</option>
                    <option value="2160p" <?php selected($video_quality, '2160p'); ?>>2160p (4K)</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="video_format"><?php _e('Video Format', 'video-library'); ?></label></th>
            <td>
                <select id="video_format" name="video_format">
                    <option value="mp4" <?php selected($video_format, 'mp4'); ?>>MP4</option>
                    <option value="webm" <?php selected($video_format, 'webm'); ?>>WebM</option>
                    <option value="avi" <?php selected($video_format, 'avi'); ?>>AVI</option>
                    <option value="mov" <?php selected($video_format, 'mov'); ?>>MOV</option>
                    <option value="mkv" <?php selected($video_format, 'mkv'); ?>>MKV</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="video_thumbnail"><?php _e('Thumbnail URL', 'video-library'); ?></label></th>
            <td>
                <input type="url" id="video_thumbnail" name="video_thumbnail" value="<?php echo esc_attr($thumbnail); ?>" class="regular-text" />
                <p class="description"><?php _e('Custom thumbnail URL (optional - will use featured image if not set)', 'video-library'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * S3 settings meta box
 */
function video_library_s3_meta_box($post) {
    $s3_key = get_post_meta($post->ID, '_video_s3_key', true);
    $s3_bucket = get_post_meta($post->ID, '_video_s3_bucket', true);
    $s3_region = get_post_meta($post->ID, '_video_s3_region', true);
    ?>
    <table class="form-table">
        <tr>
            <th><label for="video_s3_key"><?php _e('S3 Object Key', 'video-library'); ?></label></th>
            <td>
                <input type="text" id="video_s3_key" name="video_s3_key" value="<?php echo esc_attr($s3_key); ?>" class="regular-text" required />
                <p class="description"><?php _e('The S3 object key/path to the video file', 'video-library'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="video_s3_bucket"><?php _e('S3 Bucket (optional)', 'video-library'); ?></label></th>
            <td>
                <input type="text" id="video_s3_bucket" name="video_s3_bucket" value="<?php echo esc_attr($s3_bucket); ?>" class="regular-text" />
                <p class="description"><?php _e('Override default bucket for this video', 'video-library'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="video_s3_region"><?php _e('S3 Region (optional)', 'video-library'); ?></label></th>
            <td>
                <input type="text" id="video_s3_region" name="video_s3_region" value="<?php echo esc_attr($s3_region); ?>" class="regular-text" />
                <p class="description"><?php _e('Override default region for this video', 'video-library'); ?></p>
            </td>
        </tr>
    </table>
    
    <?php if ($s3_key): ?>
    <div style="margin-top: 15px;">
        <button type="button" id="test_s3_connection" class="button">
            <?php _e('Test S3 Connection', 'video-library'); ?>
        </button>
        <span id="s3_test_result" style="margin-left: 10px;"></span>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test_s3_connection').click(function() {
            var button = $(this);
            var result = $('#s3_test_result');
            
            button.prop('disabled', true).text('Testing...');
            result.html('');
            
            $.post(ajaxurl, {
                action: 'test_video_s3_connection',
                video_id: <?php echo $post->ID; ?>,
                nonce: '<?php echo wp_create_nonce('test_s3_connection'); ?>'
            }, function(response) {
                if (response.success) {
                    result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
            }).always(function() {
                button.prop('disabled', false).text('Test S3 Connection');
            });
        });
    });
    </script>
    <?php endif; ?>
    <?php
}

/**
 * Access control meta box
 */
function video_library_access_control_meta_box($post) {
    $required_tier = get_post_meta($post->ID, '_video_required_tier', true);
    $is_featured = get_option('video_library_featured_video') == $post->ID;
    ?>
    <p>
        <label for="video_required_tier"><strong><?php _e('Required User Tier', 'video-library'); ?></strong></label><br>
        <select id="video_required_tier" name="video_required_tier" style="width: 100%;">
            <option value="silver" <?php selected($required_tier, 'silver'); ?>><?php _e('Silver (Default)', 'video-library'); ?></option>
            <option value="gold" <?php selected($required_tier, 'gold'); ?>><?php _e('Gold', 'video-library'); ?></option>
            <option value="platinum" <?php selected($required_tier, 'platinum'); ?>><?php _e('Platinum', 'video-library'); ?></option>
        </select>
    </p>
    
    <p>
        <label>
            <input type="checkbox" name="set_as_featured" value="1" <?php checked($is_featured); ?> />
            <?php _e('Set as Featured Video', 'video-library'); ?>
        </label>
    </p>
    
    <?php if ($is_featured): ?>
    <p><em><?php _e('This video is currently featured on the library homepage.', 'video-library'); ?></em></p>
    <?php endif; ?>
    <?php
}

/**
 * Analytics meta box
 */
function video_library_analytics_meta_box($post) {
    $view_count = get_post_meta($post->ID, '_video_view_count', true);
    $favorite_count = get_post_meta($post->ID, '_video_favorite_count', true);
    
    if (get_option('video_library_analytics_enabled', true)) {
        $analytics = Video_Library_Analytics::get_video_analytics($post->ID, 30);
    }
    ?>
    <div style="text-align: center;">
        <p><strong><?php echo number_format($view_count ?: 0); ?></strong><br><?php _e('Total Views', 'video-library'); ?></p>
        <p><strong><?php echo number_format($favorite_count ?: 0); ?></strong><br><?php _e('Favorites', 'video-library'); ?></p>
        
        <?php if (isset($analytics)): ?>
        <p><strong><?php echo number_format($analytics['unique_viewers']); ?></strong><br><?php _e('Unique Viewers (30 days)', 'video-library'); ?></p>
        <p><strong><?php echo gmdate('H:i:s', $analytics['average_duration']); ?></strong><br><?php _e('Average Watch Time', 'video-library'); ?></p>
        <?php endif; ?>
    </div>
    
    <p style="text-align: center; margin-top: 15px;">
        <a href="<?php echo admin_url('admin.php?page=video-library-analytics&video_id=' . $post->ID); ?>" class="button">
            <?php _e('View Detailed Analytics', 'video-library'); ?>
        </a>
    </p>
    <?php
}

/**
 * Save meta box data
 */
add_action('save_post', function($post_id) {
    if (!isset($_POST['video_library_meta_nonce']) || !wp_verify_nonce($_POST['video_library_meta_nonce'], 'video_library_meta_box')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (get_post_type($post_id) !== 'video_library_item') {
        return;
    }
    
    // Save video details
    $fields = [
        'video_duration' => '_video_duration',
        'video_file_size' => '_video_file_size',
        'video_quality' => '_video_quality',
        'video_format' => '_video_format',
        'video_thumbnail' => '_video_thumbnail',
        'video_s3_key' => '_video_s3_key',
        'video_s3_bucket' => '_video_s3_bucket',
        'video_s3_region' => '_video_s3_region',
        'video_required_tier' => '_video_required_tier',
    ];
    
    foreach ($fields as $field => $meta_key) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
        }
    }
    
    // Handle featured video
    if (isset($_POST['set_as_featured']) && $_POST['set_as_featured'] == '1') {
        update_option('video_library_featured_video', $post_id);
    } elseif (get_option('video_library_featured_video') == $post_id) {
        // Remove featured status if unchecked
        if (!isset($_POST['set_as_featured'])) {
            delete_option('video_library_featured_video');
        }
    }
});

/**
 * Add custom columns to video library admin list
 */
add_filter('manage_video_library_item_posts_columns', function($columns) {
    $new_columns = [];
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = $columns['title'];
    $new_columns['thumbnail'] = __('Thumbnail', 'video-library');
    $new_columns['duration'] = __('Duration', 'video-library');
    $new_columns['quality'] = __('Quality', 'video-library');
    $new_columns['tier'] = __('Required Tier', 'video-library');
    $new_columns['views'] = __('Views', 'video-library');
    $new_columns['favorites'] = __('Favorites', 'video-library');
    $new_columns['video_category'] = __('Categories', 'video-library');
    $new_columns['date'] = $columns['date'];
    
    return $new_columns;
});

/**
 * Display custom column content
 */
add_action('manage_video_library_item_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'thumbnail':
            $thumbnail_url = get_video_thumbnail_url($post_id);
            if ($thumbnail_url) {
                echo '<img src="' . esc_url($thumbnail_url) . '" style="width: 60px; height: auto;" />';
            } else {
                echo '<span style="color: #ccc;">No thumbnail</span>';
            }
            break;
            
        case 'duration':
            echo get_video_duration_formatted($post_id);
            break;
            
        case 'quality':
            $quality = get_post_meta($post_id, '_video_quality', true);
            echo $quality ? esc_html($quality) : '—';
            break;
            
        case 'tier':
            $tier = get_post_meta($post_id, '_video_required_tier', true);
            $tier_colors = ['silver' => '#c0c0c0', 'gold' => '#ffd700', 'platinum' => '#e5e4e2'];
            $color = isset($tier_colors[$tier]) ? $tier_colors[$tier] : '#c0c0c0';
            echo '<span style="color: ' . $color . '; font-weight: bold;">' . ucfirst($tier ?: 'silver') . '</span>';
            break;
            
        case 'views':
            $views = get_post_meta($post_id, '_video_view_count', true);
            echo number_format($views ?: 0);
            break;
            
        case 'favorites':
            $favorites = get_post_meta($post_id, '_video_favorite_count', true);
            echo number_format($favorites ?: 0);
            break;
    }
}, 10, 2);

/**
 * Make custom columns sortable
 */
add_filter('manage_edit-video_library_item_sortable_columns', function($columns) {
    $columns['duration'] = 'duration';
    $columns['quality'] = 'quality';
    $columns['tier'] = 'tier';
    $columns['views'] = 'views';
    $columns['favorites'] = 'favorites';
    
    return $columns;
});

/**
 * Handle sorting for custom columns
 */
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ($orderby === 'duration') {
        $query->set('meta_key', '_video_duration');
        $query->set('orderby', 'meta_value_num');
    } elseif ($orderby === 'views') {
        $query->set('meta_key', '_video_view_count');
        $query->set('orderby', 'meta_value_num');
    } elseif ($orderby === 'favorites') {
        $query->set('meta_key', '_video_favorite_count');
        $query->set('orderby', 'meta_value_num');
    } elseif ($orderby === 'tier') {
        $query->set('meta_key', '_video_required_tier');
        $query->set('orderby', 'meta_value');
    } elseif ($orderby === 'quality') {
        $query->set('meta_key', '_video_quality');
        $query->set('orderby', 'meta_value');
    }
});

/**
 * AJAX handler for S3 connection test
 */
add_action('wp_ajax_test_video_s3_connection', function() {
    check_ajax_referer('test_s3_connection', 'nonce');
    
    $video_id = intval($_POST['video_id']);
    
    if (!$video_id || !current_user_can('edit_post', $video_id)) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $s3_key = get_post_meta($video_id, '_video_s3_key', true);
    
    if (!$s3_key) {
        wp_send_json_error(['message' => 'No S3 key specified']);
    }
    
    $file_info = Video_Library_S3::get_file_info($s3_key);
    
    if ($file_info['exists']) {
        wp_send_json_success([
            'message' => 'File found in S3',
            'file_info' => $file_info
        ]);
    } else {
        wp_send_json_error(['message' => 'File not found in S3']);
    }
}); 