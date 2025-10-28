<?php
namespace AuraModular;
class Autoloader {
    public static function register() { spl_autoload_register(array(__CLASS__, 'autoload')); }
    private static function autoload($class) {
        if (strpos($class, __NAMESPACE__ . '\\') !== 0) return;
        $path = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen(__NAMESPACE__) + 1));
        $file = __DIR__ . DIRECTORY_SEPARATOR . $path . '.php';
        if (is_readable($file)) require $file;
    }
}
