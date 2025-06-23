<?php
namespace Elementor;

if (!defined('ABSPATH')) exit;

class Video_Library_Widget extends Widget_Base {

    public function get_name() {
        return 'video_library';
    }

    public function get_title() {
        return __('Video Library', 'video-library');
    }

    public function get_icon() {
        return 'eicon-video-playlist';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['video', 'library', 'media', 'streaming', 'youtube'];
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
                    'youtube' => __('YouTube Style (Featured + Sidebar)', 'video-library'),
                    'grid' => __('Traditional Grid', 'video-library'),
                ],
                'default' => 'youtube',
                'description' => __('Choose how videos are displayed. YouTube style features a main player with sidebar list.', 'video-library'),
            ]
        );

        $this->add_control(
            'layout_description',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #e3f2fd; padding: 10px; border-radius: 5px; font-size: 12px;">
                    <strong>YouTube Layout:</strong> Main featured video player with scrollable sidebar of other videos.<br>
                    <strong>Grid Layout:</strong> Traditional card-based grid layout.
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
            'filter_path',
            [
                'label' => __('Filter by S3 Path', 'video-library'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('e.g., premium/, tutorials/', 'video-library'),
                'description' => __('Show only videos whose S3 key contains this path', 'video-library'),
            ]
        );

        $this->add_control(
            'filter_s3_prefix',
            [
                'label' => __('Filter by S3 Prefix', 'video-library'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('e.g., platinum/, webinars/', 'video-library'),
                'description' => __('Show only videos whose S3 key starts with this prefix', 'video-library'),
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

        // Display Options Section
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Options', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
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
                    'menu_order' => __('Menu Order', 'video-library'),
                    'rand' => __('Random', 'video-library'),
                ],
                'default' => 'date',
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Order Direction', 'video-library'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'DESC' => __('Descending (Newest First)', 'video-library'),
                    'ASC' => __('Ascending (Oldest First)', 'video-library'),
                ],
                'default' => 'DESC',
            ]
        );

        $this->end_controls_section();

        // YouTube Layout Specific Settings
        $this->start_controls_section(
            'youtube_layout_section',
            [
                'label' => __('YouTube Layout Settings', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'layout' => 'youtube',
                ],
            ]
        );

        $this->add_control(
            'youtube_layout_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => __('<div style="background: #f0f8ff; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa;">
                    <h4 style="margin: 0 0 10px 0;">🎬 YouTube Layout Features:</h4>
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
                    '{{WRAPPER}} .video-library-sidebar-list' => 'max-height: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .video-library-main-video' => 'aspect-ratio: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Grid Layout Specific Settings
        $this->start_controls_section(
            'grid_layout_section',
            [
                'label' => __('Grid Layout Settings', 'video-library'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'layout' => 'grid',
                ],
            ]
        );

        $this->add_control(
            'grid_columns',
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
                    '{{WRAPPER}} .video-library-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'grid_gap',
            [
                'label' => __('Grid Gap', 'video-library'),
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
                    '{{WRAPPER}} .video-library-grid' => 'gap: {{SIZE}}{{UNIT}};',
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
                    '{{WRAPPER}} .video-library-container' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .video-library-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} .video-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .video-library-main-video' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .video-library-sidebar-list' => 'border-radius: {{SIZE}}{{UNIT}};',
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

        // Build shortcode attributes from widget settings
        $shortcode_atts = [
            'layout' => $settings['layout'],
            'videos_per_page' => $settings['videos_per_page'],
            'show_search' => $settings['show_search'],
            'show_categories' => $settings['show_categories'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
        ];

        // Add filtering options if set
        if (!empty($settings['filter_category'])) {
            $shortcode_atts['category'] = $settings['filter_category'];
        }

        if (!empty($settings['filter_tag'])) {
            $shortcode_atts['tag'] = $settings['filter_tag'];
        }

        if (!empty($settings['filter_path'])) {
            $shortcode_atts['path'] = $settings['filter_path'];
        }

        if (!empty($settings['filter_s3_prefix'])) {
            $shortcode_atts['s3_prefix'] = $settings['filter_s3_prefix'];
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

        // Wrap in container with custom class
        echo '<div class="elementor-video-library-widget' . $custom_class . '">';
        
        // Render the video library using the shortcode function
        echo video_library_shortcode($shortcode_atts);
        
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="elementor-video-library-preview">
            <div style="text-align: center; padding: 40px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px;">
                <i class="eicon-video-playlist" style="font-size: 48px; margin-bottom: 15px; opacity: 0.8;"></i>
                <h3 style="margin: 0 0 10px 0; font-size: 24px;">Video Library Widget</h3>
                <p style="margin: 0; opacity: 0.9; font-size: 14px;">Configure your video library layout and settings in the left panel.</p>
                <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 5px; font-size: 12px;">
                    <strong>📺 YouTube Layout:</strong> Featured player + sidebar<br>
                    <strong>🎯 Grid Layout:</strong> Traditional card grid<br>
                    <strong>⚙️ Fully Customizable:</strong> All settings available in Elementor
                </div>
            </div>
        </div>
        <?php
    }
}

// Register the widget
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Video_Library_Widget());
} 