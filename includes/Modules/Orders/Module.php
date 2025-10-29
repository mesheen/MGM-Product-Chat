<?php
declare(strict_types=1);

namespace AuraModular\Modules\Orders;

use AuraModular\Modules\BaseModule;

final class Module extends BaseModule
{
    protected string $slug = 'aura-orders';

    public function register(): void
    {
        add_action('wp_ajax_aura_send_order', [$this, 'send_order_ajax_handler']);
        add_action('wp_ajax_nopriv_aura_send_order', [$this, 'send_order_ajax_handler']);
    }

    public function send_order_ajax_handler(): void
    {
        if (empty($_POST['order'])) { wp_send_json_error(['message' => 'No order data.']); }
        $order_items = json_decode(stripslashes($_POST['order']), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($order_items) || empty($order_items)) { wp_send_json_error(['message' => 'Invalid order data.']); }

        $artwork_filename = isset($_POST['artwork_filename']) ? sanitize_file_name($_POST['artwork_filename']) : '';
        $artwork_url = isset($_POST['artwork_url']) ? esc_url_raw($_POST['artwork_url']) : '';
        $artwork_attachment_id = isset($_POST['artwork_attachment_id']) ? intval($_POST['artwork_attachment_id']) : 0;

        $admin_email = get_option('admin_email');
        $subject = 'New Custom Garment Order from Website Chatbot';
        $message = "A new custom garment order has been submitted:\n\n============================================\n\n";
        foreach ($order_items as $index => $item) {
            $message .= "--- ITEM " . ($index + 1) . " ---\n";
            $message .= "Product: " . sanitize_text_field($item['product'] ?? 'N/A') . "\n";
            $message .= "Colors: " . sanitize_text_field($item['colors'] ?? 'N/A') . "\n";
            $message .= "Sizes: " . sanitize_text_field($item['sizes'] ?? 'N/A') . "\n";
            $message .= "Quantities: " . sanitize_text_field($item['quantity'] ?? 'N/A') . "\n";
            $message .= "Artwork: " . sanitize_text_field($item['artwork'] ?? 'N/A') . "\n";
            $message .= "Print Placement: " . sanitize_text_field($item['placement'] ?? 'N/A') . "\n\n";
        }
        $message .= "============================================\n\nPlease follow up with the client to finalize details and provide a quote.\n";
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $attachments = [];
        $max_mb = 10; $max_bytes = $max_mb * 1024 * 1024;
        if ($artwork_attachment_id) {
            $path = get_attached_file($artwork_attachment_id);
            if ($path && file_exists($path) && filesize($path) <= $max_bytes) {
                $attachments[] = $path;
            }
        }
        if (wp_mail($admin_email, $subject, $message, $headers, $attachments)) {
            wp_send_json_success(['message' => 'Email sent successfully.']);
        }
        wp_send_json_error(['message' => 'Failed to send email.']);
    }
}