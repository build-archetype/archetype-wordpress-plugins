<?php
if (!defined('ABSPATH')) exit;

class Ant_Media_Stream_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ant-media-stream';
    }

    public function get_title() {
        return __('Ant Media Stream', 'ant-media-stream-access');
    }

    public function get_icon() {
        return 'eicon-play';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
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
                'description' => __('The unique identifier for your stream (e.g., "live_stream_1", "asx6N6h2KfK0jmE42665303919153337")', 'ant-media-stream-access'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'server_url',
            [
                'label' => __('Server URL (Optional)', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('https://your-server.com:5443', 'ant-media-stream-access'),
                'description' => __('Leave empty to use plugin default settings', 'ant-media-stream-access'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'app_name',
            [
                'label' => __('App Name (Optional)', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('live', 'ant-media-stream-access'),
                'description' => __('Leave empty to use plugin default app name', 'ant-media-stream-access'),
            ]
        );

        $this->add_control(
            'width',
            [
                'label' => __('Width', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 200,
                        'max' => 1200,
                        'step' => 10,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ant-media-player-container' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'height',
            [
                'label' => __('Height', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 200,
                        'max' => 800,
                        'step' => 10,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 500,
                ],
            ]
        );

        $this->add_control(
            'format',
            [
                'label' => __('Stream Format', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'iframe',
                'options' => [
                    'iframe' => __('Iframe Embed (Recommended)', 'ant-media-stream-access'),
                    'hls' => __('HLS (HTTP Live Streaming)', 'ant-media-stream-access'),
                    'webrtc' => __('WebRTC', 'ant-media-stream-access'),
                ],
                'description' => __('Iframe embed works with all configurations and provides the best compatibility', 'ant-media-stream-access'),
            ]
        );

        $this->add_control(
            'controls',
            [
                'label' => __('Show Controls', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'ant-media-stream-access'),
                'label_off' => __('Hide', 'ant-media-stream-access'),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label' => __('Autoplay', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'ant-media-stream-access'),
                'label_off' => __('No', 'ant-media-stream-access'),
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        $this->add_control(
            'muted',
            [
                'label' => __('Start Muted', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'ant-media-stream-access'),
                'label_off' => __('No', 'ant-media-stream-access'),
                'return_value' => 'true',
                'default' => 'true',
                'description' => __('Start playback muted (recommended for autoplay)', 'ant-media-stream-access'),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'offline_message_section',
            [
                'label' => __('Offline Message Controls', 'ant-media-stream-access'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'offline_title',
            [
                'label' => __('Offline Title', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Nothing is streaming live now.',
                'placeholder' => __('Enter offline title', 'ant-media-stream-access'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'offline_message',
            [
                'label' => __('Offline Description (Optional)', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => __('Enter additional description text', 'ant-media-stream-access'),
                'description' => __('Optional additional text to show under the title', 'ant-media-stream-access'),
            ]
        );

        $this->add_control(
            'cta_text',
            [
                'label' => __('Call-to-Action Button Text', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('e.g., "Visit Video Library"', 'ant-media-stream-access'),
                'description' => __('Text for the call-to-action button (leave empty to hide button)', 'ant-media-stream-access'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'cta_link',
            [
                'label' => __('Call-to-Action Link', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::URL,
                'placeholder' => __('https://yoursite.com/videos', 'ant-media-stream-access'),
                'description' => __('URL for the call-to-action button', 'ant-media-stream-access'),
                'show_external' => true,
                'default' => [
                    'url' => '',
                    'is_external' => true,
                    'nofollow' => true,
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'ant-media-stream-access'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'label' => __('Border', 'ant-media-stream-access'),
                'selector' => '{{WRAPPER}} .ant-media-player-container',
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'ant-media-stream-access'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ant-media-player-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'label' => __('Box Shadow', 'ant-media-stream-access'),
                'selector' => '{{WRAPPER}} .ant-media-player-container',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Validate required stream ID
        if (empty($settings['stream_id'])) {
            echo '<div class="elementor-alert elementor-alert-info">Please configure a Stream ID in the widget settings.</div>';
            return;
        }

        // Build shortcode attributes with proper null checking
        $width_setting = $settings['width'] ?? ['size' => 100, 'unit' => '%'];
        $height_setting = $settings['height'] ?? ['size' => 500, 'unit' => 'px'];
        
        $atts = [
            'stream_id' => $settings['stream_id'],
            'width' => ($width_setting['size'] ?? 100) . ($width_setting['unit'] ?? '%'),
            'height' => ($height_setting['size'] ?? 500) . ($height_setting['unit'] ?? 'px'),
            'format' => $settings['format'] ?? 'iframe',
            'controls' => $settings['controls'] ?? 'true',
            'autoplay' => $settings['autoplay'] ?? 'true',
            'muted' => $settings['muted'] ?? 'true',
        ];

        // Add optional server URL if provided
        if (!empty($settings['server_url'])) {
            $atts['server_url'] = $settings['server_url'];
        }

        // Add optional app name if provided
        if (!empty($settings['app_name'])) {
            $atts['app_name'] = $settings['app_name'];
        }

        // Add offline message parameters (always include offline_title to ensure offline message displays)
        $atts['offline_title'] = !empty($settings['offline_title']) ? $settings['offline_title'] : 'Nothing is streaming live now.';
        
        if (!empty($settings['offline_message'])) {
            $atts['offline_message'] = $settings['offline_message'];
        }
        
        if (!empty($settings['cta_text'])) {
            $atts['cta_text'] = $settings['cta_text'];
        }
        
        if (!empty($settings['cta_link']['url'])) {
            $atts['cta_link'] = $settings['cta_link']['url'];
        }

        // Use the iframe format for best compatibility
        if ($settings['format'] === 'iframe') {
            // Convert to the simple shortcode format
            $shortcode_atts = [];
            foreach ($atts as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $shortcode_atts[] = $key . '="' . esc_attr($value) . '"';
                }
            }
            echo do_shortcode('[antmedia_simple ' . implode(' ', $shortcode_atts) . ']');
        } else {
            // For HLS/WebRTC, use the original render function if it exists
            if (function_exists('render_stream_player')) {
                echo render_stream_player($atts);
            } else {
                // Fallback to iframe if render_stream_player doesn't exist
                echo do_shortcode('[antmedia_simple ' . implode(' ', array_map(function($k, $v) {
                    return $k . '="' . esc_attr($v) . '"';
                }, array_keys($atts), $atts)) . ']');
            }
        }
    }

    protected function _content_template() {
        ?>
        <#
        var width = settings.width && settings.width.size ? settings.width.size + settings.width.unit : '100%';
        var height = settings.height && settings.height.size ? settings.height.size + settings.height.unit : '500px';
        var streamId = settings.stream_id || 'No Stream ID';
        var format = settings.format || 'iframe';
        var offlineTitle = settings.offline_title || 'Nothing is streaming live now.';
        var offlineMessage = settings.offline_message || '';
        var ctaText = settings.cta_text || '';
        var ctaLink = settings.cta_link ? settings.cta_link.url : '';
        
        // Show offline message preview if any offline settings are configured
        var showOfflinePreview = offlineTitle || offlineMessage || ctaText;
        #>
        
        <# if (showOfflinePreview) { #>
            <!-- Offline Message Preview -->
            <div class="ant-media-player-container" style="width: {{{ width }}}; height: {{{ height }}}; background: linear-gradient(135deg, #1f2937 0%, #111827 100%); display: flex; align-items: center; justify-content: center; color: white; border-radius: 4px; position: relative;">
                <div style="text-align: center; padding: 20px; max-width: 300px;">
                    <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.7;">📺</div>
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: #d1d5db;">{{{ offlineTitle }}}</h3>
                    <# if (offlineMessage) { #>
                        <p style="font-size: 16px; line-height: 1.5; margin: 0 0 20px 0; color: #d1d5db;">{{{ offlineMessage }}}</p>
                    <# } #>
                    <# if (ctaText && ctaLink) { #>
                        <div style="display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; border-radius: 6px; font-weight: 600; margin: 0; border: 2px solid #3b82f6;">{{{ ctaText }}}</div>
                    <# } #>
                    <p style="margin-top: 15px; font-size: 12px; opacity: 0.6;">Preview: Offline Message</p>
                </div>
            </div>
        <# } else { #>
            <!-- Default Stream Player Preview -->
            <div class="ant-media-player-container" style="width: {{{ width }}}; height: {{{ height }}}; background: #000; display: flex; align-items: center; justify-content: center; color: white; border-radius: 4px;">
                <div style="text-align: center; padding: 20px;">
                    <i class="eicon-play" style="font-size: 48px; margin-bottom: 15px; color: #0073aa;"></i>
                    <p style="margin: 0 0 10px 0; font-size: 16px; font-weight: bold;">Ant Media Stream Player</p>
                    <p style="margin: 0 0 5px 0; font-size: 14px; opacity: 0.8;">Stream ID: <strong>{{{ streamId }}}</strong></p>
                    <p style="margin: 0 0 5px 0; font-size: 12px; opacity: 0.7;">Format: {{{ format }}}</p>
                    <# if (settings.server_url) { #>
                    <p style="margin: 0 0 5px 0; font-size: 12px; opacity: 0.7;">Custom Server: {{{ settings.server_url }}}</p>
                    <# } #>
                    <# if (settings.app_name) { #>
                    <p style="margin: 0 0 5px 0; font-size: 12px; opacity: 0.7;">App: {{{ settings.app_name }}}</p>
                    <# } #>
                    <# if (!settings.stream_id) { #>
                    <p style="margin-top: 15px; color: #ff6b6b; font-size: 14px;">⚠️ Please set a Stream ID</p>
                    <# } #>
                </div>
            </div>
        <# } #>
        <?php
    }
} 