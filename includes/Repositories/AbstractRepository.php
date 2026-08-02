<?php

declare(strict_types=1);

namespace Dizzy\Events\Repositories;

use Dizzy\Events\Contracts\Hydrates;

defined('ABSPATH') || exit;

/**
 * Base repository.
 *
 * Provides common database operations and model hydration.
 *
 * @package Dizzy\Events\Repositories
 */
abstract class AbstractRepository
{
    /**
     * Returns the model class handled by this repository.
     *
     * @return class-string<Hydrates>
     */
    abstract protected function modelClass(): string;

    /**
     * Hydrate single model.
     */
    protected function hydrate(
        ?object $source
    ): ?Hydrates {

        if ($source === null) {
            return null;
        }

        $model = $this->modelClass();

        return $model::from($source);
    }

    /**
     * Hydrate multiple models.
     *
     * @param array<object> $sources
     *
     * @return array<Hydrates>
     */
    protected function hydrateMany(
        array $sources
    ): array {

        $items = [];

        foreach ($sources as $source) {
            $items[] = $this->hydrate($source);
        }

        return $items;
    }

}
