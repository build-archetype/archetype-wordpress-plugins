<?php
/**
 * Video Library Elementor Widget
 * Version 2.1.0 - Professional responsive design with Tailwind CSS and Video.js
 */
namespace Elementor;

if (!defined('ABSPATH')) exit;

class Video_Library_Widget extends Widget_Base {

    public function get_name() {
        return 'simple_video_library';
    }

    public function get_title() {
        return __('Simple Video Library', 'video-library');
    }

    public function get_icon() {
        return 'eicon-video-camera';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['video', 'library', 'simple', 'media', 'streaming', 'gallery', 'tube', 'tile'];
    }

    public function get_script_depends() {
        return ['video-library-js'];
    }

    public function get_style_depends() {
        return ['video-library-css'];
    }

    public function is_reload_preview_required() {
        // Force preview reload on major setting changes
        return true;
    }

    protected function get_default_render_method() {
        // Always use server-side rendering to show real videos in editor
        return 'server';
    }



    protected function register_controls() {
        
        // Layout Section
        $this->start_controls_section(
            'layout_section',
            [
                'label' => __('Layout', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Display Layout', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'tile' => __('Tile Layout (Card Grid)', 'video-library'),
                    'tube' => __('Tube Style (Featured + Sidebar)', 'video-library'),
                    'gallery' => __('Gallery Style (Fullscreen + Carousel)', 'video-library'),
                ],
                'default' => 'tile',
                'description' => __('Choose how videos are displayed. Tube style features a main player with sidebar. Gallery shows fullscreen video with thumbnail carousel.', 'video-library'),
            ]
        );

        $this->add_control(
            'layout_description',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; font-size: 12px;">
                    <strong>🎬 Tile Layout:</strong> Traditional responsive card grid layout.<br>
                    <strong>📺 Tube Style:</strong> Main featured video player with scrollable sidebar.<br>
                    <strong>🖼️ Gallery Style:</strong> Fullscreen video with thumbnail carousel navigation.
                </div>', 'video-library'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $this->end_controls_section();

        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content Settings', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'videos_per_page',
            [
                'label' => __('Videos Per Page', 'video-library'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'step' => 1,
                'default' => 12,
                'description' => __('Number of videos to display initially', 'video-library'),
            ]
        );

        $this->add_control(
            'show_search',
            [
                'label' => __('Show Search Bar', 'video-library'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'video-library'),
                'label_off' => __('Hide', 'video-library'),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'show_categories',
            [
                'label' => __('Show Category Filter', 'video-library'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'video-library'),
                'label_off' => __('Hide', 'video-library'),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __('Order By', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'date' => __('Date', 'video-library'),
                    'title' => __('Title', 'video-library'),
                    'filename' => __('Filename', 'video-library'),
                ],
                'default' => 'date',
                'description' => __('How to sort the videos', 'video-library'),
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Order Direction', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'DESC' => __('Descending (Z-A, newest first)', 'video-library'),
                    'ASC' => __('Ascending (A-Z, oldest first)', 'video-library'),
                ],
                'default' => 'DESC',
                'description' => __('Sort direction', 'video-library'),
            ]
        );

        $this->end_controls_section();

        // Filtering Section
        $this->start_controls_section(
            'filtering_section',
            [
                'label' => __('Content Filtering', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Get available categories for dropdown
        $categories_options = ['' => __('All Categories', 'video-library')];
        $categories = get_terms(['taxonomy' => 'video_category', 'hide_empty' => false]);
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $categories_options[$category->slug] = $category->name;
            }
        }

        // Get available tags for dropdown  
        $tags_options = ['' => __('All Tags', 'video-library')];
        $tags = get_terms(['taxonomy' => 'video_tag', 'hide_empty' => false]);
        if ($tags && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $tags_options[$tag->slug] = $tag->name;
            }
        }

        $this->add_control(
            'filter_category',
            [
                'label' => __('Filter by Category', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $categories_options,
                'default' => '',
                'description' => __('Show only videos from this category', 'video-library'),
            ]
        );

        $this->add_control(
            'filter_tag',
            [
                'label' => __('Filter by Tag', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $tags_options,
                'default' => '',
                'description' => __('Show only videos with this tag', 'video-library'),
            ]
        );

        $this->add_control(
            'filter_paths',
            [
                'label' => __('Filter by S3 Paths', 'video-library'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'rows' => 3,
                'default' => '',
                'placeholder' => __('premium/,tutorials/advanced/,webinars/', 'video-library'),
                'description' => __('Comma-separated list of S3 paths. Only videos from these paths will be shown. Leave empty to show all videos.', 'video-library'),
            ]
        );

        $this->add_control(
            'paths_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 3px solid #ffc107; font-size: 12px;">
                    <strong>📁 Path Filtering Examples:</strong><br>
                    • <code>premium/</code> - Shows only premium videos<br>
                    • <code>tutorials/,webinars/</code> - Shows tutorials and webinars<br>
                    • <code>2024/</code> - Shows videos from 2024 folder<br>
                    <br><em>Note: Paths are hidden from users and only used for filtering behind the scenes.</em>
                </div>', 'video-library'),
            ]
        );

        $this->add_control(
            'pre_search',
            [
                'label' => __('Pre-filter Search', 'video-library'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Search term', 'video-library'),
                'description' => __('Pre-filter videos by search term', 'video-library'),
            ]
        );

        $this->end_controls_section();

        // Display Options Section (removed duplicate orderby/order controls)
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Options', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'display_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; font-size: 12px;">
                    <strong>📋 Order Settings:</strong> Order controls are available in the "Content Settings" section above.
                </div>', 'video-library'),
            ]
        );

        $this->end_controls_section();

        // Tube Layout Specific Settings
        $this->start_controls_section(
            'tube_layout_section',
            [
                'label' => __('Tube Style Settings', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'layout' => 'tube',
                ],
            ]
        );

        $this->add_control(
            'tube_layout_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #f0f8ff; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa;">
                    <h4 style="margin: 0 0 10px 0;">📺 Tube Style Features:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Main Player:</strong> Featured video with inline playback</li>
                        <li><strong>Sidebar:</strong> Scrollable list of other videos</li>
                        <li><strong>Click to Switch:</strong> Click sidebar videos to change main player</li>
                        <li><strong>Auto-Play Next:</strong> Automatically plays next video when current ends</li>
                        <li><strong>Responsive:</strong> Adapts to mobile and tablet layouts</li>
                    </ul>
                </div>', 'video-library'),
            ]
        );

        $this->add_control(
            'sidebar_height',
            [
                'label' => __('Sidebar Height', 'video-library'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 1000,
                        'step' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 600,
                ],
                'description' => __('Maximum height of the scrollable sidebar', 'video-library'),
                'selectors' => [
                    '{{WRAPPER}} .video-sidebar-list' => 'max-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'main_player_ratio',
            [
                'label' => __('Main Player Aspect Ratio', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '16/9' => __('16:9 (YouTube Standard)', 'video-library'),
                    '21/9' => __('21:9 (Ultra-wide)', 'video-library'),
                    '4/3' => __('4:3 (Traditional)', 'video-library'),
                    '1/1' => __('1:1 (Square)', 'video-library'),
                ],
                'default' => '16/9',
                'description' => __('Aspect ratio for the main video player', 'video-library'),
                'selectors' => [
                    '{{WRAPPER}} .video-main-player' => 'aspect-ratio: {{VALUE}};',
                    '{{WRAPPER}} .gallery-main-player' => 'aspect-ratio: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Gallery Layout Specific Settings
        $this->start_controls_section(
            'gallery_layout_section',
            [
                'label' => __('Gallery Style Settings', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'layout' => 'gallery',
                ],
            ]
        );

        $this->add_control(
            'gallery_layout_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                    <h4 style="margin: 0 0 10px 0;">🖼️ Gallery Style Features:</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Fullscreen Player:</strong> Main video takes full width with cinematic aspect ratio</li>
                        <li><strong>Thumbnail Carousel:</strong> Horizontal scrolling thumbnail navigation</li>
                        <li><strong>Left/Right Navigation:</strong> Arrow buttons to navigate through videos</li>
                        <li><strong>Keyboard Support:</strong> Arrow keys for navigation</li>
                        <li><strong>Auto-Focus:</strong> Active thumbnail highlighted with border</li>
                        <li><strong>Touch Friendly:</strong> Mobile optimized with touch gestures</li>
                    </ul>
                </div>', 'video-library'),
            ]
        );

        $this->add_control(
            'gallery_thumbnails_visible',
            [
                'label' => __('Visible Thumbnails', 'video-library'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 3,
                        'max' => 10,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'size' => 5,
                ],
                'description' => __('Number of thumbnails visible in the carousel at once', 'video-library'),
            ]
        );

        $this->add_control(
            'gallery_thumbnail_size',
            [
                'label' => __('Thumbnail Size', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'small' => __('Small (80x45)', 'video-library'),
                    'medium' => __('Medium (120x68)', 'video-library'),
                    'large' => __('Large (160x90)', 'video-library'),
                ],
                'default' => 'medium',
                'description' => __('Size of thumbnails in the carousel', 'video-library'),
                'selectors' => [
                    '{{WRAPPER}} .gallery-thumbnail.small' => 'width: 80px; height: 45px;',
                    '{{WRAPPER}} .gallery-thumbnail.medium' => 'width: 120px; height: 68px;',
                    '{{WRAPPER}} .gallery-thumbnail.large' => 'width: 160px; height: 90px;',
                ],
            ]
        );

        $this->end_controls_section();

        // Tile Layout Specific Settings
        $this->start_controls_section(
            'tile_layout_section',
            [
                'label' => __('Tile Layout Settings', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'layout' => 'tile',
                ],
            ]
        );

        $this->add_control(
            'tile_columns',
            [
                'label' => __('Columns', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '1' => '1',
                    '2' => '2', 
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'default' => '3',
                'selectors' => [
                    '{{WRAPPER}} .video-tile-layout' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'tile_gap',
            [
                'label' => __('Tile Gap', 'video-library'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 24,
                ],
                'selectors' => [
                    '{{WRAPPER}} .video-tile-layout' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('General Styling', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Container Background', 'video-library'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .video-library-modern' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Container Padding', 'video-library'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .video-library-modern' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'video-library'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .video-tile-item' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .video-main-player' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .video-sidebar' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gallery-main-player' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .gallery-thumbnail' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Advanced Section
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Advanced', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Custom CSS Class', 'video-library'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'description' => __('Add custom CSS class for additional styling', 'video-library'),
            ]
        );

        $this->add_control(
            'debug_mode',
            [
                'label' => __('Debug Mode', 'video-library'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('On', 'video-library'),
                'label_off' => __('Off', 'video-library'),
                'return_value' => 'true',
                'default' => '',
                'description' => __('Enable to see console logs for troubleshooting', 'video-library'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Ensure shortcode functions are loaded
        if (!function_exists('video_library_modern_shortcode')) {
            if (file_exists(VL_PLUGIN_DIR . 'includes/shortcode.php')) {
                require_once VL_PLUGIN_DIR . 'includes/shortcode.php';
            }
        }

        // Build shortcode attributes from widget settings
        $shortcode_atts = [
            'layout' => $settings['layout'] ?? 'tube',
            'videos_per_page' => $settings['videos_per_page'] ?? 12,
            'show_search' => $settings['show_search'] ?? 'true',
            'show_categories' => $settings['show_categories'] ?? 'true',
            'orderby' => $settings['orderby'] ?? 'date',
            'order' => $settings['order'] ?? 'DESC',
            'show_info' => 'true',
            'theme' => 'modern',
            'autoplay' => 'false'
        ];

        // Add filtering options if set
        if (!empty($settings['filter_category'])) {
            $shortcode_atts['category'] = $settings['filter_category'];
        }

        if (!empty($settings['filter_tag'])) {
            $shortcode_atts['tag'] = $settings['filter_tag'];
        }

        if (!empty($settings['filter_paths'])) {
            $shortcode_atts['paths'] = $settings['filter_paths'];
        }

        if (!empty($settings['pre_search'])) {
            $shortcode_atts['search'] = $settings['pre_search'];
        }

        // Add custom CSS class if set
        $custom_class = '';
        if (!empty($settings['custom_css_class'])) {
            $custom_class = ' ' . esc_attr($settings['custom_css_class']);
        }

        // Add debug attributes
        if ($settings['debug_mode'] === 'true') {
            $shortcode_atts['debug'] = 'true';
        }

        // Generate unique ID for this widget instance
        $widget_id = 'elementor-video-library-' . $this->get_id();

        // Wrap in container with custom class and unique ID
        echo '<div id="' . esc_attr($widget_id) . '" class="elementor-video-library-widget elementor-widget-video-library video-library-v190' . $custom_class . '" data-settings="' . esc_attr(json_encode($settings)) . '" data-layout="' . esc_attr($shortcode_atts['layout']) . '">';
        
        // Add critical CSS for modern styling
        echo '<style>
        .elementor-widget-video-library .video-library-modern {
            width: 100% !important;
            max-width: 100% !important;
        }
        .elementor-widget-video-library .video-tube-layout {
            display: grid !important;
            grid-template-columns: 1fr 380px !important;
            gap: 2.5rem !important;
            align-items: start !important;
        }
        .elementor-widget-video-library .video-main-info {
            display: block !important;
            background: white !important;
            border-radius: 12px !important;
            padding: 1.5rem !important;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06) !important;
        }
        @media (max-width: 768px) {
            .elementor-widget-video-library .video-tube-layout {
                grid-template-columns: 1fr !important;
                gap: 1.5rem !important;
            }
        }
        </style>';
        
        // Add debugging info if debug mode is on
        if ($settings['debug_mode'] === 'true') {
            echo '<!-- Elementor Video Library Widget Debug -->';
            echo '<!-- Layout: ' . esc_html($shortcode_atts['layout']) . ' -->';
            echo '<!-- Show Info: ' . esc_html($shortcode_atts['show_info']) . ' -->';
            echo '<!-- Function exists: ' . (function_exists('video_library_modern_shortcode') ? 'YES' : 'NO') . ' -->';
        }
        
        // Render the video library using the modern shortcode function
        if (function_exists('video_library_modern_shortcode')) {
            $output = video_library_modern_shortcode($shortcode_atts);
            
            // If output is empty or too short, force a basic display
            if (empty($output) || strlen($output) < 500) {
                echo '<div class="video-library-fallback">';
                echo '<p><strong>Loading video library...</strong></p>';
                echo '<p>Layout: ' . esc_html($shortcode_atts['layout']) . '</p>';
                echo '<p>If this persists, try refreshing the page or check your S3 configuration.</p>';
                echo '</div>';
            } else {
                echo $output;
            }
        } else {
            echo '<div class="video-library-error">';
            echo '<h3>Video Library Widget Error</h3>';
            echo '<p>The video library shortcode function is not available.</p>';
            echo '<p>Please ensure the Video Library plugin is properly activated.</p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Force Elementor to refresh in editor mode
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            echo '<script>
                if (window.elementor) {
                    setTimeout(function() {
                        if (window.elementorFrontend && window.elementorFrontend.hooks) {
                            window.elementorFrontend.hooks.doAction("frontend/element_ready/widget", jQuery(".elementor-widget-video-library"));
                        }
                    }, 100);
                }
            </script>';
        }
    }

    protected function content_template() {
        // Return empty to force Elementor to always use the render() method
        // This ensures real videos are shown in both editor and frontend
        return '';
    }
}

// Register the widget
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Video_Library_Widget());
} 