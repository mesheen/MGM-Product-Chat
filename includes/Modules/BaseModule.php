<?php
declare(strict_types=1);

namespace AuraModular\Modules;

use AuraModular\Interfaces\ModuleInterface;

/**
 * Base class that modules can extend to get common helper methods.
 * - Keeps modules focused on registration (hooks) and avoids global state.
 */
abstract class BaseModule implements ModuleInterface
{
    protected string $slug = 'base-module';

    public function get_slug(): string
    {
        return $this->slug;
    }

    abstract public function register(): void;

    /** Helper for adding WP actions with an object context */
    protected function add_action(string $hook, string $method, int $priority = 10, int $args = 1): void
    {
        add_action($hook, [$this, $method], $priority, $args);
    }

    /** Helper for adding WP filters with an object context */
    protected function add_filter(string $hook, string $method, int $priority = 10, int $args = 1): void
    {
        add_filter($hook, [$this, $method], $priority, $args);
    }
}