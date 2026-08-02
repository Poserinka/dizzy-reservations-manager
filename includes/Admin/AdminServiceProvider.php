<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Container;
use Dizzy\Events\Poster\Services\PosterService;
use Dizzy\Events\Poster\Repositories\PosterRepository;
use Dizzy\Events\Repositories\OccurrenceRepository;
use Dizzy\Events\Reservations\ReservationRepository;
use Dizzy\Events\Reservations\ReservationService;
use Dizzy\Events\Services\OccurrenceService;
use Dizzy\Events\Reports\ReportRepository;

defined('ABSPATH') || exit;

final class AdminServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(OccurrenceMetaBox::class, static function () use ($container): OccurrenceMetaBox {
            return new OccurrenceMetaBox($container->get(OccurrenceRepository::class), $container->get(OccurrenceService::class));
        });

        $container->singleton(EventDetailsMetaBox::class, static fn (): EventDetailsMetaBox => new EventDetailsMetaBox());
        $container->singleton(EventStatusMetaBox::class, static fn (): EventStatusMetaBox => new EventStatusMetaBox());
        $container->singleton(AdminAssets::class, static fn (): AdminAssets => new AdminAssets());
        $container->singleton(ReservationAdmin::class, static function () use ($container): ReservationAdmin {
            return new ReservationAdmin(
                $container->get(ReservationRepository::class),
                $container->get(ReservationService::class)
            );
        });
        $container->singleton(CheckinAdmin::class, static function () use ($container): CheckinAdmin {
            return new CheckinAdmin($container->get(ReservationRepository::class));
        });
        $container->singleton(ReportRepository::class, static function (): ReportRepository {
            global $wpdb;
            return new ReportRepository($wpdb);
        });
        $container->singleton(ReportsAdmin::class, static function () use ($container): ReportsAdmin {
            return new ReportsAdmin($container->get(ReportRepository::class));
        });
        $container->singleton(PosterAdmin::class, static function () use ($container): PosterAdmin {
            return new PosterAdmin(
                $container->get(PosterService::class),
                $container->get(PosterRepository::class)
            );
        });
        $container->singleton(PosterSettings::class, static fn (): PosterSettings => new PosterSettings());
        $container->singleton(VenueTaxonomyFields::class, static fn (): VenueTaxonomyFields => new VenueTaxonomyFields());

        $container->get(OccurrenceMetaBox::class)->register();
        $container->get(EventDetailsMetaBox::class)->register();
        $container->get(EventStatusMetaBox::class)->register();
        $container->get(AdminAssets::class)->register();
        $container->get(ReservationAdmin::class)->register();
        $container->get(CheckinAdmin::class)->register();
        $container->get(ReportsAdmin::class)->register();
        $container->get(PosterAdmin::class)->register();
        $container->get(PosterSettings::class)->register();
        $container->get(VenueTaxonomyFields::class)->register();
    }
}

