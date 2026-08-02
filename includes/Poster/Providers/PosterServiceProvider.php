<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Providers;

use Dizzy\Events\Core\Container;
use Dizzy\Events\Poster\Contracts\PosterGenerator;
use Dizzy\Events\Poster\Generators\OpenAIImageGenerator;
use Dizzy\Events\Poster\Generators\PlaceholderGenerator;
use Dizzy\Events\Poster\Repositories\PosterRepository;
use Dizzy\Events\Poster\Renderers\PosterRenderer;
use Dizzy\Events\Poster\Services\PosterService;

defined('ABSPATH') || exit;

final class PosterServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            PosterRepository::class,
            static function (): PosterRepository {
                return new PosterRepository();
            }
        );

        $container->singleton(
            PosterGenerator::class,
            static function (): PosterGenerator {
                $apiKey = (string) get_option('dizzy_events_openai_api_key', '');

                if ($apiKey !== '') {
                    return new OpenAIImageGenerator($apiKey);
                }

                return new PlaceholderGenerator();
            }
        );

        $container->singleton(
            PosterRenderer::class,
            static fn (): PosterRenderer => new PosterRenderer()
        );

        $container->singleton(
            PosterService::class,
            static function () use ($container): PosterService {
                return new PosterService(
                    $container->get(PosterRepository::class),
                    $container->get(PosterGenerator::class),
                    $container->get(PosterRenderer::class)
                );
            }
        );
    }
}
