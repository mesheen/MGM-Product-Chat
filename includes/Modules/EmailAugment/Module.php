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
        
        // Check for multiple artwork files first
        $aids = get_post_meta($order->get_id(), '_aura_artwork_attachment_ids', true);
        
        // Fallback to single artwork file (backward compatibility)
        if (empty($aids)) {
            $aid = (int) get_post_meta($order->get_id(), '_aura_artwork_attachment_id', true);
            if ($aid) {
                $aids = array($aid);
            }
        }
        
        if (empty($aids) || !is_array($aids)) { return $fields; }
        
        $artwork_values = array();
        foreach ($aids as $aid) {
            $aid = (int) $aid;
            if (!$aid) { continue; }
            $url  = wp_get_attachment_url($aid);
            $name = get_the_title($aid);
            $artwork_values[] = esc_html($name) . ($url ? ' — ' . esc_url($url) : '');
        }
        
        if (!empty($artwork_values)) {
            $fields['aura_artwork'] = array(
                'label' => __('Artwork', 'aura-chatbot'),
                'value' => implode("\n", $artwork_values)
            );
        }
        
        return $fields;
    }

    public function maybe_attach_artwork($attachments, $email_id, $order) {
        if (!is_a($order, '\WC_Order')) { return $attachments; }
        if ($email_id !== 'customer_processing_order') { return $attachments; }
        
        // Check for multiple artwork files first
        $aids = get_post_meta($order->get_id(), '_aura_artwork_attachment_ids', true);
        
        // Fallback to single artwork file (backward compatibility)
        if (empty($aids)) {
            $aid = (int) get_post_meta($order->get_id(), '_aura_artwork_attachment_id', true);
            if ($aid) {
                $aids = array($aid);
            }
        }
        
        if (!empty($aids) && is_array($aids)) {
            foreach ($aids as $aid) {
                $aid = (int) $aid;
                if (!$aid) { continue; }
                $path = get_attached_file($aid);
                if (is_readable($path)) {
                    $attachments[] = $path;
                }
            }
        }
        
        return $attachments;
    }
}
