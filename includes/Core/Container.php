<?php

declare(strict_types=1);

namespace Dizzy\Events\Core;

use Closure;
use RuntimeException;

defined('ABSPATH') || exit;

/**
 * Simple dependency injection container.
 *
 * Manages shared service instances.
 *
 * @package Dizzy\Events\Core
 */
final class Container
{
    /**
     * Registered bindings.
     *
     * @var array<string,Closure>
     */
    private array $bindings = [];

    /**
     * Created instances.
     *
     * @var array<string,mixed>
     */
    private array $instances = [];

    /**
     * Register a service.
     *
     * @param string $id Service identifier.
     * @param Closure $resolver Service factory.
     */
    public function bind(
        string $id,
        Closure $resolver
    ): void {

        $this->bindings[$id] = $resolver;
    }

    /**
     * Register a shared singleton service.
     *
     * @param string $id Service identifier.
     * @param Closure $resolver Service factory.
     */
    public function singleton(
        string $id,
        Closure $resolver
    ): void {

        $this->bindings[$id] = function () use ($id, $resolver) {

            if (! isset($this->instances[$id])) {
                $this->instances[$id] = $resolver();
            }

            return $this->instances[$id];
        };
    }

    /**
     * Resolve service.
     *
     * @throws RuntimeException
     */
    public function get(
        string $id
    ): mixed {

        if (! isset($this->bindings[$id])) {
            throw new RuntimeException(
                sprintf(
                    'Service [%s] is not registered.',
                    $id
                )
            );
        }

        return ($this->bindings[$id])();
    }

    /**
     * Check service registration.
     */
    public function has(
        string $id
    ): bool {

        return isset($this->bindings[$id]);
    }
}