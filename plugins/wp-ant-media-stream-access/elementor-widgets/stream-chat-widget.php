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

        ?>
        <div class="stream-chat-widget">
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
            .stream-chat-widget {
                display: flex;
                gap: 15px;
                width: 100%;
            }
            
            .stream-section {
                flex: 2;
            }
            
            .chat-section {
                flex: 1;
                min-width: 300px;
            }
            
            @media (max-width: 768px) {
                .stream-chat-widget {
                    flex-direction: column;
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
