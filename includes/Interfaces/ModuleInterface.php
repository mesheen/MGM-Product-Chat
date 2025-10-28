<?php
declare(strict_types=1);

namespace AuraModular\Interfaces;

/**
 * Minimal Module contract used by the Plugin bootstrap.
 */
interface ModuleInterface
{
    /**
     * Called once when registering modules.
     */
    public function register(): void;

    /**
     * Optional: return the module slug or name for diagnostics.
     */
    public function get_slug(): string;
}