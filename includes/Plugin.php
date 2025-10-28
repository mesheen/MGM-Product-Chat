<?php
namespace AuraModular;
use AuraModular\Interfaces\ModuleInterface;

class Plugin {
    private $modules = array();

    public function __construct() {
        add_action('init', array($this, 'ensure_upload_dir'));
    }

    public function ensure_upload_dir() {
        $u = wp_upload_dir();
        if (!empty($u['basedir'])) {
            $dir = trailingslashit($u['basedir']) . 'aura-artwork';
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            // Harden: block PHP execution
            $ht = $dir . '/.htaccess';
            if (!file_exists($ht)) {
                file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|php\\.)\">\nDeny from all\n</FilesMatch>\n");
            }
            // Index file
            $idx = $dir . '/index.html';
            if (!file_exists($idx)) {
                file_put_contents($idx, "");
            }
        }
    }

    public function add_module(ModuleInterface $m) { $this->modules[] = $m; return $this; }
    public function register() { foreach ($this->modules as $m) { $m->register(); } }
}
