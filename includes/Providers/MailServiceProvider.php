<?php

declare(strict_types=1);

namespace Dizzy\Events\Providers;

use Dizzy\Events\Core\Container;
use Dizzy\Events\Mail\Services\MailService;

defined('ABSPATH') || exit;

final class MailServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            MailService::class,
            static function (): MailService {
                return new MailService();
            }
        );
    }
}
