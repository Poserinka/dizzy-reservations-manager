<?php

declare(strict_types=1);

namespace Dizzy\Events\Providers;

use Dizzy\Events\Core\Config;
use Dizzy\Events\Core\Container;
use Dizzy\Events\Repositories\EventRepository;
use Dizzy\Events\Repositories\OccurrenceRepository;
use Dizzy\Events\Services\EventService;
use Dizzy\Events\Services\OccurrenceService;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Registers event manager services.
 *
 * @package Dizzy\Events\Providers
 */
final class EventServiceProvider
{
    /**
     * Register services.
     */
    public function register(Container $container): void
    {
        $container->singleton(
            EventRepository::class,
            static function (): EventRepository {
                return new EventRepository();
            }
        );

        $container->singleton(
            OccurrenceRepository::class,
            static function (): OccurrenceRepository {
                global $wpdb;

                return new OccurrenceRepository(
                    $wpdb->prefix . 'dizzy_event_occurrences',
                    $wpdb->posts
                );
            }
        );

        $container->singleton(
            EventService::class,
            static function () use ($container): EventService {
                return new EventService(
                    $container->get(EventRepository::class),
                    $container->get(OccurrenceRepository::class)
                );
            }
        );

        $container->singleton(
            OccurrenceService::class,
            static function () use ($container): OccurrenceService {
                return new OccurrenceService(
                    $container->get(OccurrenceRepository::class)
                );
            }
        );

        add_action(
            'before_delete_post',
            static function (int $postId) use ($container): void {
                if (get_post_type($postId) !== Config::POST_TYPE_EVENT) {
                    return;
                }

                try {
                    $container
                        ->get(OccurrenceRepository::class)
                        ->deleteForEvent($postId);
                } catch (Throwable $exception) {
                    error_log(
                        sprintf(
                            'Dizzy Events could not delete occurrences for event %d: %s',
                            $postId,
                            $exception->getMessage()
                        )
                    );
                }
            }
        );
    }
}
