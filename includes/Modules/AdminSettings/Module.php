<?php
namespace AuraModular\Modules\AdminSettings;
use AuraModular\Interfaces\ModuleInterface;
class Module implements ModuleInterface {
    public function register() {
        add_action('admin_menu', 'aura_chatbot_add_admin_menu');
        add_action('admin_init', 'aura_chatbot_settings_init');
        add_action('admin_init', 'aura_chatbot_settings_init_matrix_colors');
    }
}
