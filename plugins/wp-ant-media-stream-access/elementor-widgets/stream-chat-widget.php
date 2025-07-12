<?php
if (!defined('ABSPATH')) exit;

class Stream_Chat_Combined_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'stream-chat-combined';
    }

    public function get_title() {
        return __('Stream + Chat Integration', 'ant-media-stream-access');
    }

    public function get_icon() {
        return 'eicon-video-camera';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['stream', 'chat', 'ant media', 'rocket chat', 'live'];
    }

    protected function _register_controls() {
        // Layout Settings Section
        $this->start_controls_section(
            'layout_section',
            [
                'label' => __('Layout Settings', 'ant-media-stream-access'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'layout_direction',
            [
                'label' => __('Layout Direction', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'row',
                'options' => [
                    'row' => __('Side by Side (Row)', 'ant-media-stream-access'),
                    'column' => __('Stacked (Column)', 'ant-media-stream-access'),
                ],
                'description' => __('Choose how to arrange stream and chat', 'ant-media-stream-access'),
            ]
        );

        $this->add_control(
            'stream_width',
            [
                'label' => __('Stream Width', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['%'],
                'range' => [
                    '%' => [
                        'min' => 30,
                        'max' => 80,
                        'step' => 5,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 65,
                ],
                'condition' => [
                    'layout_direction' => 'row',
                ],
                'description' => __('Width of stream section when side by side', 'ant-media-stream-access'),
            ]
        );

        $this->end_controls_section();

        // Stream Settings Section
        $this->start_controls_section(
            'stream_section',
            [
                'label' => __('Stream Settings', 'ant-media-stream-access'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'stream_id',
            [
                'label' => __('Stream ID', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('Enter stream ID', 'ant-media-stream-access'),
                'description' => __('The unique identifier for your stream', 'ant-media-stream-access'),
            ]
        );

        $this->add_control(
            'server_url',
            [
                'label' => __('Server URL (Optional)', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('https://your-server.com', 'ant-media-stream-access'),
                'description' => __('Leave empty to use plugin default settings', 'ant-media-stream-access'),
            ]
        );

        $this->end_controls_section();

        // Chat Settings Section
        $this->start_controls_section(
            'chat_section',
            [
                'label' => __('Chat Settings', 'ant-media-stream-access'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'chat_channel',
            [
                'label' => __('Chat Channel', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'general',
                'description' => __('Rocket Chat channel to display', 'ant-media-stream-access'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Check if required plugins are active
        $rocket_chat_active = function_exists('should_display_rocket_chat');
        
        if (!$rocket_chat_active) {
            echo '<div class="elementor-alert elementor-alert-warning">Rocket Chat plugin is not active. Please activate it to use chat integration.</div>';
            return;
        }

        if (empty($settings['stream_id'])) {
            echo '<div class="elementor-alert elementor-alert-info">Please configure a Stream ID in the widget settings.</div>';
            return;
        }

        // Get layout settings
        $layout_direction = $settings['layout_direction'] ?? 'row';
        $stream_width = $settings['stream_width']['size'] ?? 65;
        $chat_width = 100 - $stream_width;
        
        $widget_id = 'stream-chat-' . uniqid();

        ?>
        <div class="stream-chat-widget" id="<?php echo esc_attr($widget_id); ?>">
            <div class="stream-section">
                <?php
                // Render the stream using existing shortcode
                $stream_atts = [
                    'stream_id' => $settings['stream_id'],
                ];
                
                if (!empty($settings['server_url'])) {
                    $stream_atts['server_url'] = $settings['server_url'];
                }
                
                echo do_shortcode('[antmedia_simple ' . $this->build_shortcode_attributes($stream_atts) . ']');
                ?>
            </div>

            <div class="chat-section">
                <?php
                echo do_shortcode('[rocketchat_iframe channel="' . esc_attr($settings['chat_channel']) . '"]');
                ?>
            </div>
        </div>

        <style>
            #<?php echo esc_attr($widget_id); ?> {
                display: flex;
                gap: 15px;
                width: 100%;
                <?php if ($layout_direction === 'column'): ?>
                flex-direction: column;
                <?php else: ?>
                flex-direction: row;
                <?php endif; ?>
            }
            
            <?php if ($layout_direction === 'row'): ?>
            #<?php echo esc_attr($widget_id); ?> .stream-section {
                flex: 0 0 <?php echo esc_attr($stream_width); ?>%;
                max-width: <?php echo esc_attr($stream_width); ?>%;
            }
            
            #<?php echo esc_attr($widget_id); ?> .chat-section {
                flex: 0 0 <?php echo esc_attr($chat_width); ?>%;
                max-width: <?php echo esc_attr($chat_width); ?>%;
                min-width: 280px;
            }
            <?php else: ?>
            #<?php echo esc_attr($widget_id); ?> .stream-section,
            #<?php echo esc_attr($widget_id); ?> .chat-section {
                flex: 1;
                width: 100%;
            }
            <?php endif; ?>
            
            /* Mobile responsiveness - always stack on small screens */
            @media (max-width: 768px) {
                #<?php echo esc_attr($widget_id); ?> {
                    flex-direction: column !important;
                }
                
                #<?php echo esc_attr($widget_id); ?> .stream-section,
                #<?php echo esc_attr($widget_id); ?> .chat-section {
                    flex: 1 !important;
                    max-width: 100% !important;
                    min-width: auto !important;
                }
            }
        </style>
        <?php
    }

    /**
     * Helper function to build shortcode attributes
     */
    private function build_shortcode_attributes($atts) {
        $attribute_string = '';
        foreach ($atts as $key => $value) {
            $attribute_string .= $key . '="' . esc_attr($value) . '" ';
        }
        return trim($attribute_string);
    }
}
