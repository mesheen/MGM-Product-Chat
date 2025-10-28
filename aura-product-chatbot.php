<?php
/**
 * Plugin Name:       Aura Product Chatbot (Modular)
 * Description:       Modularized build. Same UI/behavior, cleaner architecture.
 * Version:           2.3.5
 * Author:            Kevin + ChatGPT
 * Text Domain:       aura-product-chatbot
 */

declare(strict_types=1);

// Protect from direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin core constants.
if (!defined('AURA_PLUGIN_FILE')) {
    define('AURA_PLUGIN_FILE', __FILE__);
}
if (!defined('AURA_PLUGIN_DIR')) {
    define('AURA_PLUGIN_DIR', plugin_dir_path(AURA_PLUGIN_FILE));
}
if (!defined('AURA_PLUGIN_URL')) {
    define('AURA_PLUGIN_URL', plugin_dir_url(AURA_PLUGIN_FILE));
}

/**
 * Load translations.
 */
add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain(
        'aura-product-chatbot',
        false,
        dirname(plugin_basename(AURA_PLUGIN_FILE)) . '/languages'
    );
}, 5);

/**
 * Autoload: prefer composer if available, otherwise fall back to included autoloader.
 */
$composer_autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} else {
    $legacy_autoloader = AURA_PLUGIN_DIR . 'includes/Autoloader.php';
    if (file_exists($legacy_autoloader)) {
        require_once $legacy_autoloader;
        if (class_exists(\AuraModular\Autoloader::class) && is_callable([\AuraModular\Autoloader::class, 'register'])) {
            \AuraModular\Autoloader::register();
        }
    }
}

/**
 * Include optional compatibility / legacy files if present.
 */
$compat_file = AURA_PLUGIN_DIR . 'includes/compat/communication-layer.php';
if (file_exists($compat_file)) {
    require_once $compat_file;
}

$ensure_settings_callbacks = AURA_PLUGIN_DIR . 'includes/Compat/EnsureSettingsCallbacks.php';
if (file_exists($ensure_settings_callbacks)) {
    require_once $ensure_settings_callbacks;
}

$legacy_original = AURA_PLUGIN_DIR . 'includes/Modules/Legacy/Original.php';
if (file_exists($legacy_original)) {
    require_once $legacy_original;
}

/**
 * Register plugin and modules once plugins are loaded.
 * Uses defensive checks to avoid fatal errors if classes are missing.
 */
add_action('plugins_loaded', static function (): void {
    if (!class_exists(\AuraModular\Plugin::class)) {
        return;
    }

    $plugin = new \AuraModular\Plugin();

    // Define module classes to load in a single place for clarity and extendability.
    $module_classes = [
        \AuraModular\Modules\AdminSettings\Module::class,
        \AuraModular\Modules\MainContainer\Module::class,
        \AuraModular\Modules\Chat\Module::class,
        \AuraModular\Modules\ProductGrid\Module::class,
        \AuraModular\Modules\ProductDetail\Module::class,
        \AuraModular\Modules\OrderSummary\Module::class,
        \AuraModular\Modules\EmailAugment\Module::class,
    ];

    foreach ($module_classes as $module_class) {
        if (class_exists($module_class)) {
            $plugin->add_module(new $module_class());
        }
    }

    $plugin->register();
}, 20);

/**
 * Activation / deactivation hooks (safe, minimal behaviour).
 */
register_activation_hook(AURA_PLUGIN_FILE, static function (): void {
    // Keep activation lightweight: prepare default options or transient-based migrations here.
    // Avoid heavy work on activation that could time out.
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
});

register_deactivation_hook(AURA_PLUGIN_FILE, static function (): void {
    // Clean up transient state if needed.
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
});

/**
 * Add settings link on the plugins page for easy access.
 */
add_filter('plugin_action_links_' . plugin_basename(AURA_PLUGIN_FILE), static function (array $links): array {
    $settings_page = admin_url('options-general.php?page=aura-chatbot');
    $links[] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($settings_page),
        esc_html__('Settings', 'aura-product-chatbot')
    );
    return $links;
}, 10, 1);