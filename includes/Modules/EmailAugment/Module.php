<?php
namespace AuraModular\Modules\EmailAugment;
use AuraModular\Interfaces\ModuleInterface;
class Module implements ModuleInterface {
    public function register() {
        if ( ! function_exists('WC') ) { return; }
        // Show artwork meta in WooCommerce emails (safe, scoped)
        add_filter('woocommerce_email_order_meta_fields', array($this, 'add_order_meta_fields'), 10, 3);
        // Optionally attach the file on customer_processing_order only
        add_filter('woocommerce_email_attachments', array($this, 'maybe_attach_artwork'), 10, 3);
    }

    public function add_order_meta_fields($fields, $sent_to_admin, $order) {
        if (!is_a($order, '\WC_Order')) { return $fields; }
        $aid = (int) get_post_meta($order->get_id(), '_aura_artwork_attachment_id', true);
        if (!$aid) { return $fields; }
        $url  = wp_get_attachment_url($aid);
        $name = get_the_title($aid);
        $fields['aura_artwork'] = array(
            'label' => __('Artwork', 'aura-chatbot'),
            'value' => esc_html($name) . ($url ? ' — ' . esc_url($url) : '')
        );
        return $fields;
    }

    public function maybe_attach_artwork($attachments, $email_id, $order) {
        if (!is_a($order, '\WC_Order')) { return $attachments; }
        if ($email_id !== 'customer_processing_order') { return $attachments; }
        $aid = (int) get_post_meta($order->get_id(), '_aura_artwork_attachment_id', true);
        if ($aid) {
            $path = get_attached_file($aid);
            if (is_readable($path)) { $attachments[] = $path; }
        }
        return $attachments;
    }
}
