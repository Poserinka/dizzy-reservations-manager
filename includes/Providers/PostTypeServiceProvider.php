<?php

declare(strict_types=1);

namespace Dizzy\Events\Providers;

use Dizzy\Events\Core\Container;
use Dizzy\Events\PostTypes\EventPostType;

defined('ABSPATH') || exit;

/**
 * Registers post type services.
 *
 * @package Dizzy\Events\Providers
 */
final class PostTypeServiceProvider
{
    /**
     * Register post types.
     */
    public function register(
        Container $container
    ): void {

        $container->singleton(
            EventPostType::class,
            static function (): EventPostType {

                return new EventPostType();
            }
        );

        $container
            ->get(EventPostType::class)
            ->register();
    }
}
