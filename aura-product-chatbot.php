<?php
/**
 * Plugin Name:       Aura Product Chatbot (Modular)
 * Description:       Modularized build. Same UI/behavior, cleaner architecture.
 * Version:           2.3.5
 * Author:            Kevin + ChatGPT
 * Text Domain:       aura-product-chatbot
 */

// Aura Chat – bootstrap communication layer
require_once plugin_dir_path( __FILE__ ) . 'includes/compat/communication-layer.php';
if (!defined('ABSPATH')) exit;
if (!defined('AURA_PLUGIN_FILE')) define('AURA_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/includes/Autoloader.php';
if (class_exists('AuraModular\Autoloader')) AuraModular\Autoloader::register();
require_once __DIR__ . '/includes/Compat/EnsureSettingsCallbacks.php';
require_once __DIR__ . '/includes/Modules/Legacy/Original.php';

add_action('plugins_loaded', function () {
    $plugin = new \AuraModular\Plugin();
    $plugin
        ->add_module(new \AuraModular\Modules\AdminSettings\Module())
        ->add_module(new \AuraModular\Modules\MainContainer\Module())
        ->add_module(new \AuraModular\Modules\Chat\Module())
        ->add_module(new \AuraModular\Modules\ProductGrid\Module())
        ->add_module(new \AuraModular\Modules\ProductDetail\Module())
        ->add_module(new \AuraModular\Modules\OrderSummary\Module())
        ->add_module(new \AuraModular\Modules\EmailAugment\Module());
    $plugin->register();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    $settings_url = admin_url('options-general.php?page=aura-chatbot');
    $links[] = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'aura-product-chatbot') . '</a>';
    return $links;
});
