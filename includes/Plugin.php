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
        $events = new EventGateway();

        if (! $events->available()) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Dizzy Reservations Manager requires Dizzy Events Manager to be active.', 'dizzy-reservations-manager') .
                    '</p></div>';
            });
            return;
        }

        $repository = new ReservationRepository();
        $service = new ReservationService($events, $repository, new Mailer());

        (new FrontendController($events, $service))->register();

        if (is_admin()) {
            (new AdminController($repository, $service))->register();
        }
    }
}
