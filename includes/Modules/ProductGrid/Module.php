<?php
namespace AuraModular\Modules\ProductGrid;
use AuraModular\Interfaces\ModuleInterface;
class Module implements ModuleInterface {
    public function register() {
        add_action('wp_ajax_aura_fetch_products', 'aura_fetch_products_ajax_handler');
        add_action('wp_ajax_nopriv_aura_fetch_products', 'aura_fetch_products_ajax_handler');
    }
}
