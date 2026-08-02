<?php

declare(strict_types=1);

namespace Dizzy\Events\Providers;

use Dizzy\Events\Core\Container;
use Dizzy\Events\Mail\Services\MailService;
use Dizzy\Events\Repositories\OccurrenceRepository;
use Dizzy\Events\Reservations\ReservationRepository;
use Dizzy\Events\Reservations\ReservationService;
use Dizzy\Events\Reservations\TicketService;

defined('ABSPATH') || exit;

final class ReservationServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            ReservationRepository::class,
            static function (): ReservationRepository {
                global $wpdb;

                return new ReservationRepository($wpdb);
            }
        );

        $container->singleton(
            TicketService::class,
            static function () use ($container): TicketService {
                return new TicketService(
                    $container->get(ReservationRepository::class),
                    $container->get(OccurrenceRepository::class)
                );
            }
        );

        $container->singleton(
            ReservationService::class,
            static function () use ($container): ReservationService {
                return new ReservationService(
                    $container->get(ReservationRepository::class),
                    $container->get(MailService::class),
                    $container->get(OccurrenceRepository::class),
                    $container->get(TicketService::class)
                );
            }
        );

        $container->get(TicketService::class)->register();
    }
}

