<?php
if (!function_exists('aura_chatbot_primary_color_render')) {
    function aura_chatbot_primary_color_render() {
        $opts = get_option('aura_chatbot_settings', array());
        $val  = isset($opts['primary_color']) ? $opts['primary_color'] : '#0ea5e9';
        echo '<input type="color" name="aura_chatbot_settings[primary_color]" value="' . esc_attr($val) . '" />';
        echo '<p class="description">' . esc_html__('Primary highlight color used across the UI.', 'aura-product-chatbot') . '</p>';
    }
}
if (!function_exists('aura_chatbot_user_bubble_color_render')) {
    function aura_chatbot_user_bubble_color_render() {
        $opts = get_option('aura_chatbot_settings', array());
        $val  = isset($opts['user_bubble_color']) ? $opts['user_bubble_color'] : '#1f2937';
        echo '<input type="color" name="aura_chatbot_settings[user_bubble_color]" value="' . esc_attr($val) . '" />';
        echo '<p class="description">' . esc_html__('Chat bubble background for user messages.', 'aura-product-chatbot') . '</p>';
    }
}
