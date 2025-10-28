<?php
declare(strict_types=1);

namespace AuraModular;

/**
 * Lightweight PSR-4 compatible autoloader for the plugin namespace.
 *
 * - Registers multiple base directories for the AuraModular namespace.
 * - Avoids requiring files unnecessarily and is defensive about file existence.
 */
final class Autoloader
{
    /** @var array<string,string> namespace prefix => base dir */
    private static array $prefixes = [];

    private function __construct() {}

    public static function register(array $map = []): void
    {
        // default map if none provided: current directory maps to AuraModular\
        if (empty($map)) {
            $map = [
                __NAMESPACE__ . '\\' => __DIR__ . DIRECTORY_SEPARATOR,
            ];
        }

        foreach ($map as $prefix => $baseDir) {
            // normalize
            $prefix = trim($prefix, '\\') . '\\';
            $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            self::$prefixes[$prefix] = $baseDir;
        }

        spl_autoload_register([self::class, 'autoload'], true, true);
    }

    public static function autoload(string $class): void
    {
        // only attempt to load classes from registered namespaces
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            // get relative class path
            $relative = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            $file = $baseDir . $relativePath;

            if (is_readable($file)) {
                require $file;
            }
            // intentionally silent failure so other autoloaders can try
            return;
        }
    }
}