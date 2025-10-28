<?php
namespace AuraModular\Modules\ProductDetail;
use AuraModular\Interfaces\ModuleInterface;
class Module implements ModuleInterface {
    public function register() {
        add_action('wp_ajax_aura_fetch_product_details', 'aura_fetch_product_details_ajax_handler');
        add_action('wp_ajax_nopriv_aura_fetch_product_details', 'aura_fetch_product_details_ajax_handler');
        add_action('wp_ajax_aura_upload_artwork', 'aura_upload_artwork_ajax_handler');
        add_action('wp_ajax_nopriv_aura_upload_artwork', 'aura_upload_artwork_ajax_handler');
        add_action('wp_ajax_aura_upload_artwork_b64', 'aura_upload_artwork_b64_ajax_handler');
        add_action('wp_ajax_nopriv_aura_upload_artwork_b64', 'aura_upload_artwork_b64_ajax_handler');
    }
}
