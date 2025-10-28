<?php
declare(strict_types=1);

namespace AuraModular\Modules\Chat;

use AuraModular\Modules\BaseModule;

/**
 * Chat module skeleton following modern standards:
 * - Small, single-responsibility class that registers only required hooks.
 * - Enqueues assets and exposes REST endpoints via a dedicated controller if needed.
 */
final class Module extends BaseModule
{
    protected string $slug = 'aura-chat';

    public function register(): void
    {
        $this->add_action('wp_enqueue_scripts', 'enqueue_assets');
        $this->add_action('rest_api_init', 'register_rest_routes');
        // Other initialization as needed
    }

    public function enqueue_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $handle = 'aura-chatbot-frontend';
        $asset_url = plugin_dir_url(AURA_PLUGIN_FILE) . 'assets/js/aura-chatbot.js';
        $asset_path = plugin_dir_path(AURA_PLUGIN_FILE) . 'assets/js/aura-chatbot.js';

        wp_register_script(
            $handle,
            $asset_url,
            [], // add dependencies array for your built bundle (e.g., ['wp-element'] if React)
            file_exists($asset_path) ? filemtime($asset_path) : null,
            true
        );

        // Provide localized config safely
        $config = [
            'restUrl' => esc_url_raw(rest_url('aura-chat/v1/query')),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'sending' => __('Send', 'aura-product-chatbot'),
                'error' => __('An error occurred. Please try again.', 'aura-product-chatbot'),
                'placeholder' => __('Ask about this product...', 'aura-product-chatbot'),
            ],
        ];
        wp_localize_script($handle, 'AURA_CHATBOT_CONFIG', $config);
        wp_enqueue_script($handle);
        // Enqueue CSS similarly, prefer a small stylesheet if present
    }

    public function register_rest_routes(): void
    {
        register_rest_route('aura-chat/v1', '/query', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_query'],
            'permission_callback' => function () {
                return true; // or capability checks as needed
            },
        ]);
    }

    public function handle_query(\WP_REST_Request $request)
    {
        $body = json_decode((string) $request->get_body(), true) ?? [];
        $prompt = trim((string) ($body['prompt'] ?? ''));

        if ($prompt === '') {
            return new \WP_Error('empty_prompt', 'Prompt is empty', ['status' => 400]);
        }

        // Example: delegate to a service class (not implemented here) that returns the reply
        $reply = sprintf("You asked: %s", wp_strip_all_tags($prompt));

        return rest_ensure_response(['reply' => $reply]);
    }
}