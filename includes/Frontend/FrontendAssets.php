<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend;

defined('ABSPATH') || exit;

/**
 * Loads frontend event assets.
 *
 * @package Dizzy\Events\Frontend
 */
final class FrontendAssets
{
    /**
     * Register frontend asset hooks.
     */
    public function register(): void
    {
        add_action(
            'wp_enqueue_scripts',
            [
                $this,
                'enqueue',
            ]
        );
    }

    /**
     * Enqueue event frontend styles.
     */
    public function enqueue(): void
    {
        wp_enqueue_style(
            'dizzy-events-frontend',
            DIZZY_EVENTS_URL . 'assets/css/frontend.css',
            [],
            DIZZY_EVENTS_VERSION
        );
    }
}
