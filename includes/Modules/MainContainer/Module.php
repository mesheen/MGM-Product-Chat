<?php
namespace AuraModular\Modules\MainContainer;
use AuraModular\Interfaces\ModuleInterface;
class Module implements ModuleInterface {
    public function register() {
        add_shortcode('aura_chatbot_interface', 'aura_chatbot_shortcode');
        add_action('wp_enqueue_scripts', 'aura_chatbot_enqueue_scripts');
    }
}
