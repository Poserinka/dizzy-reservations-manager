<?php

declare(strict_types=1);

namespace Dizzy\Events\Core;

use Dizzy\Events\Admin\AdminServiceProvider;
use Dizzy\Events\Frontend\FrontendServiceProvider;
use Dizzy\Events\Poster\Providers\PosterServiceProvider;
use Dizzy\Events\Providers\EventServiceProvider;
use Dizzy\Events\Providers\MailServiceProvider;
use Dizzy\Events\Providers\PostTypeServiceProvider;
use Dizzy\Events\Providers\ReservationServiceProvider;

defined('ABSPATH') || exit;

/**
 * Application bootstrap.
 *
 * Creates and manages application services.
 *
 * @package Dizzy\Events\Core
 */
final class Application
{
    private Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): void
    {
        $this->registerProviders();
    }

    public function container(): Container
    {
        return $this->container;
    }

    private function registerProviders(): void
    {
        $providers = [
            EventServiceProvider::class,
            ReservationServiceProvider::class,
            PosterServiceProvider::class,
            MailServiceProvider::class,
            AdminServiceProvider::class,
            PostTypeServiceProvider::class,
            FrontendServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            (new $provider())->register($this->container);
        }
    }
}
