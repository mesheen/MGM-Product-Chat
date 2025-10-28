<?php
namespace AuraModular\Modules\OrderSummary;
use AuraModular\Interfaces\ModuleInterface;
class Module implements ModuleInterface {
    public function register() {
        add_action('wp_ajax_aura_send_order', 'aura_send_order_ajax_handler');
        add_action('wp_ajax_nopriv_aura_send_order', 'aura_send_order_ajax_handler');
    }
}
