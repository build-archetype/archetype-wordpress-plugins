<?php
add_action('elementor/widgets/widgets_registered', function($widgets_manager) {
    if (!class_exists('\Elementor\Widget_Base')) return;

    require_once __DIR__ . '/../elementor-widgets/rocket-chat-embed-widget.php';
});
