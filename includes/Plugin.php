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
        $tables = new TableRepository();
        $events = new EventGateway();
        $service = new ReservationService($repository, new Mailer(), $tables, $events, new TicketGateway());
        add_action('dizzy_ticket_order_status_changed', [$service, 'ticketOrderStatusChanged'], 10, 2);

        (new FrontendController($service, $tables))->register();
        (new MobileApiController($repository, $service))->register();

        if (is_admin()) {
            (new AdminController($repository, $service))->register();
            (new TablesAdminController($tables))->register();
        }
    }
}
