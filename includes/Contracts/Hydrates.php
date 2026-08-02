<?php

declare(strict_types=1);

namespace Dizzy\Events\Contracts;

defined('ABSPATH') || exit;

/**
 * Defines objects that can be created from external data.
 *
 * @package Dizzy\Events\Contracts
 */
interface Hydrates
{
    /**
     * Create an instance from a source object.
     *
     * The source may be:
     * - database row
     * - WordPress object
     * - API response object
     *
     * @param object $source Source object.
     */
    public static function from(object $source): static;

    /**
     * Convert object to array representation.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array;
}