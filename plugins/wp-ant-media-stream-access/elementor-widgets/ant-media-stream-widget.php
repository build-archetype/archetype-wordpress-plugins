<?php
if (!defined('ABSPATH')) exit;
namespace Elementor;

class Ant_Media_Stream_Widget extends Widget_Base {

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
                'default' => 'hls',
                'options' => [
                    'hls' => __('HLS (HTTP Live Streaming)', 'ant-media-stream-access'),
                    'embed' => __('Embedded Player', 'ant-media-stream-access'),
                    'webrtc' => __('WebRTC', 'ant-media-stream-access'),
                ],
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
                'default' => 'false',
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

        $atts = [
            'width' => $settings['width']['size'] . $settings['width']['unit'],
            'height' => $settings['height']['size'] . $settings['height']['unit'],
            'format' => $settings['format'],
            'controls' => $settings['controls'],
            'autoplay' => $settings['autoplay'],
        ];

        echo render_stream_player($atts);
    }

    protected function _content_template() {
        ?>
        <#
        var width = settings.width.size + settings.width.unit;
        var height = settings.height.size + settings.height.unit;
        #>
        <div class="ant-media-player-container" style="width: {{{ width }}}; height: {{{ height }}}; background: #000; display: flex; align-items: center; justify-content: center; color: white;">
            <div style="text-align: center;">
                <i class="eicon-play" style="font-size: 48px; margin-bottom: 10px;"></i>
                <p>Ant Media Stream Player</p>
                <p style="font-size: 12px; opacity: 0.7;">Format: {{{ settings.format }}}</p>
            </div>
        </div>
        <?php
    }
} 