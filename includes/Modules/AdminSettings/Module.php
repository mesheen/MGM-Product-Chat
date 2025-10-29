<?php
declare(strict_types=1);

namespace AuraModular\Modules\AdminSettings;

use AuraModular\Modules\BaseModule;

final class Module extends BaseModule
{
    protected string $slug = 'aura-admin-settings';

    public function register(): void
    {
        $this->add_action('admin_menu', 'add_admin_menu');
        $this->add_action('admin_init', 'settings_init');
        $this->add_action('admin_enqueue_scripts', 'admin_enqueue_scripts');

        // AJAX handlers for admin testers
        add_action('wp_ajax_aura_test_api_key', [$this, 'ajax_test_api_key']);
    }

    public function add_admin_menu(): void
    {
        add_options_page(
            __('Aura Product Chatbot Settings', 'aura-product-chatbot'),
            __('Aura Chatbot', 'aura-product-chatbot'),
            'manage_options',
            'aura-chatbot',
            [$this, 'render_settings_page']
        );
    }

    public function settings_init(): void
    {
        register_setting(
            'auraChatbotPage',
            'aura_chatbot_settings',
            [ 'sanitize_callback' => [$this, 'sanitize_settings'] ]
        );

        add_settings_section('aura_chatbot_flows_section', __('Chatbot Flow Settings', 'aura-product-chatbot'), null, 'auraChatbotPage');

        add_settings_field('aura_chatbot_api_key', __('Gemini API Key', 'aura-product-chatbot'), [$this, 'render_api_key'], 'auraChatbotPage', 'aura_chatbot_flows_section');
        add_settings_field('aura_chatbot_welcome_message', __('Welcome Message', 'aura-product-chatbot'), [$this, 'render_welcome_message'], 'auraChatbotPage', 'aura_chatbot_flows_section');
        add_settings_field('aura_chatbot_print_placements', __('Print Placements', 'aura-product-chatbot'), [$this, 'render_print_placements'], 'auraChatbotPage', 'aura_chatbot_flows_section');
        add_settings_field('aura_chatbot_canned_responses', __('General Canned Responses', 'aura-product-chatbot'), [$this, 'render_canned_responses'], 'auraChatbotPage', 'aura_chatbot_flows_section');
    }

    public function sanitize_settings($input): array
    {
        $out = [];
        if (!is_array($input)) {
            return $out;
        }

        $out['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $out['welcome_message'] = isset($input['welcome_message']) ? wp_kses_post($input['welcome_message']) : '';
        $out['print_placements'] = isset($input['print_placements']) ? sanitize_textarea_field($input['print_placements']) : '';
        $out['canned_responses'] = isset($input['canned_responses']) ? sanitize_textarea_field($input['canned_responses']) : '';

        // Keep color/matrix settings if present, sanitize as hex color
        $colors = [
            'primary_color', 'user_bubble_color',
            'matrix_header_bg','matrix_header_text','matrix_cell_bg','matrix_cell_border','matrix_cell_text','matrix_cell_focus'
        ];
        foreach ($colors as $c) {
            if (isset($input[$c])) {
                $out[$c] = $this->sanitize_color($input[$c]);
            }
        }

        return $out;
    }

    private function sanitize_color($val): string
    {
        $val = trim((string)$val);
        if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $val)) {
            return $val;
        }
        return '';
    }

    public function render_api_key(): void
    {
        $options = get_option('aura_chatbot_settings', []);
        $api_key = $options['api_key'] ?? '';
        printf('<input type="password" id="aura_api_key_field" name="aura_chatbot_settings[api_key]" value="%s" size="50" class="regular-text">', esc_attr($api_key));
        echo ' ' . sprintf('<button type="button" id="aura_test_api_key" class="button">%s</button>', esc_html__('Test Connection', 'aura-product-chatbot'));
        echo '<span id="aura_api_test_result" style="margin-left: 10px;"></span>';
        echo '<p class="description">' . esc_html__('Enter your Google Gemini API key. AI assist is optional—search still works without it.', 'aura-product-chatbot') . '</p>';
    }

    public function render_welcome_message(): void
    {
        $options = get_option('aura_chatbot_settings', []);
        $welcome_message = $options['welcome_message'] ?? "Hello! I can help you find products.";
        printf('<textarea cols="50" rows="3" name="aura_chatbot_settings[welcome_message]">%s</textarea>', esc_textarea($welcome_message));
    }

    public function render_print_placements(): void
    {
        $options = get_option('aura_chatbot_settings', []);
        $placements = $options['print_placements'] ?? "Front Center\nBack Center\nLeft Chest\nRight Sleeve";
        printf('<textarea cols="50" rows="4" name="aura_chatbot_settings[print_placements]">%s</textarea>', esc_textarea($placements));
        echo '<p class="description">' . esc_html__('Enter one print placement option per line.', 'aura-product-chatbot') . '</p>';
    }

    public function render_canned_responses(): void
    {
        $options = get_option('aura_chatbot_settings', []);
        $responses = $options['canned_responses'] ?? "Business hours|Our hours are 9 AM to 5 PM, Monday to Friday.";
        printf('<textarea cols="50" rows="5" name="aura_chatbot_settings[canned_responses]">%s</textarea>', esc_textarea($responses));
        echo '<p class="description">' . esc_html__('Format: <code>Question|Answer</code>', 'aura-product-chatbot') . '</p>';
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Aura Product Chatbot Settings', 'aura-product-chatbot') . '</h1><form action="options.php" method="post">';
        settings_fields('auraChatbotPage');
        do_settings_sections('auraChatbotPage');
        submit_button();
        echo '</form></div>';
    }

    public function admin_enqueue_scripts(string $hook): void
    {
        if ($hook !== 'settings_page_aura-chatbot') {
            return;
        }

        wp_enqueue_script('jquery');
        $nonce = wp_create_nonce('aura_test_api_key');
        $ajax_url = admin_url('admin-ajax.php');
        $inline = "jQuery(function($){$('#aura_test_api_key').on('click',function(){var btn=$(this),res=$('#aura_api_test_result');var apiKey=$('#aura_api_key_field').val();res.text('Testing...').css('color','');btn.prop('disabled',true);$.post('" . esc_js($ajax_url) . "', {action:'aura_test_api_key', api_key: apiKey, _wpnonce: '" . esc_js($nonce) . "'}, function(res){ if(res && res.success){ $('#aura_api_test_result').text('Success!').css('color','green'); } else { $('#aura_api_test_result').text('Failed. ' + (res && res.data && res.data.message ? res.data.message : '')).css('color','red'); } }).fail(function(){ $('#aura_api_test_result').text('AJAX error.').css('color','red'); }).always(function(){ btn.prop('disabled',false); });});});";
        wp_add_inline_script('jquery', $inline);
    }

    public function ajax_test_api_key(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }
        check_ajax_referer('aura_test_api_key');

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API Key is empty.']);
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=' . rawurlencode($api_key);
        $args = [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['contents' => [['parts' => [['text' => 'hello']]]]]),
            'timeout' => 15,
        ];

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'WordPress HTTP Error: ' . $response->get_error_message()]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code === 200 && !empty($body['candidates'])) {
            wp_send_json_success();
        }

        $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error.';
        wp_send_json_error(['message' => 'API Error (' . $response_code . '): ' . $error_message]);
    }
}