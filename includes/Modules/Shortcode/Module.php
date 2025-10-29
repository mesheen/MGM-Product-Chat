<?php
declare(strict_types=1);

namespace AuraModular\Modules\Shortcode;

use AuraModular\Modules\BaseModule;

final class Module extends BaseModule
{
    protected string $slug = 'aura-shortcode';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_shortcode('aura_chatbot_interface', [$this, 'shortcode_output']);
    }

    public function enqueue_frontend_assets(): void
    {
        // Styles
        $css_rel = 'assets/css/aura-chatbot.css';
        $css_abs = plugin_dir_path(AURA_PLUGIN_FILE) . $css_rel;
        if (file_exists($css_abs)) {
            wp_register_style('aura-chatbot-css', plugin_dir_url(AURA_PLUGIN_FILE) . $css_rel, [], filemtime($css_abs));
            wp_enqueue_style('aura-chatbot-css');
        }

        // The main JS is enqueued by Chat module (assets/dist preferred); ensure Tailwind CDN registered if needed
        wp_register_script('aura-tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);
    }

    public function shortcode_output($atts = []): string
    {
        $options = get_option('aura_chatbot_settings', []);
        $api_key = $options['api_key'] ?? '';
        $primary_color = $options['primary_color'] ?? '#42B8FF';
        $user_bubble_color = $options['user_bubble_color'] ?? '#FF69B4';
        $welcome_message = $options['welcome_message'] ?? "Hello! Let's find a product.";
        $canned_responses_raw = $options['canned_responses'] ?? "";
        $placements_raw = $options['print_placements'] ?? "Front Center\nBack Center";

        // Localize any front-end data — Chat module already localizes restUrl/nonce, add specific UI defaults
        $vars = [
            'welcome_message' => $welcome_message,
            'canned_responses_raw' => is_string($canned_responses_raw) ? $canned_responses_raw : '',
            'placements_raw' => is_string($placements_raw) ? $placements_raw : '',
        ];
        wp_add_inline_script('aura-chatbot-frontend', 'window.AURA_CHATBOT_UI = ' . wp_json_encode($vars) . ';', 'after');

        ob_start();
        ?>
        <div data-aura-chat data-ctx-product-id="<?php echo esc_attr(get_the_ID()); ?>"></div>
        <?php
        return (string) ob_get_clean();
    }
}