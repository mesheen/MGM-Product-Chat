<?php
declare(strict_types=1);

namespace AuraModular;

use AuraModular\Interfaces\ModuleInterface;

/**
 * Lightweight plugin manager that holds and registers modules.
 */
final class Plugin
{
    /** @var ModuleInterface[] */
    private array $modules = [];

    public function add_module(ModuleInterface $module): self
    {
        $this->modules[] = $module;
        return $this;
    }

    public function register(): void
    {
        foreach ($this->modules as $module) {
            // defensive: ensure register exists
            if (method_exists($module, 'register')) {
                $module->register();
            }
        }
    }

    /**
     * Return registered modules for diagnostics / testing.
     *
     * @return ModuleInterface[]
     */
    public function get_modules(): array
    {
        return $this->modules;
    }
}