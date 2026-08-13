<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class Plugin
{
    private static bool $booted = false;

    public function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        (new ControllerRole())->register();
        $repository = new ReservationRepository();
        $service = new ReservationService($repository, new Mailer());

        (new FrontendController($service))->register();
        (new MobileApiController($repository, $service))->register();

        if (is_admin()) {
            (new AdminController($repository, $service))->register();
        }
    }
}
