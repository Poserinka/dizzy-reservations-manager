<?php

declare(strict_types=1);

namespace Dizzy\Events\Admin;

use Dizzy\Events\Core\Config;
defined('ABSPATH') || exit;

/**
 * Loads admin assets.
 *
 * @package Dizzy\Events\Admin
 */
final class AdminAssets
{
    private const MAX_OCCURRENCES = 100;

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action(
            'admin_enqueue_scripts',
            [
                $this,
                'enqueue',
            ]
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();

        if (! $screen || $screen->post_type !== Config::POST_TYPE_EVENT) {
            return;
        }

        wp_enqueue_script(
            'dizzy-events-admin',
            DIZZY_EVENTS_URL . 'assets/js/occurrence-admin.js',
            [
                'jquery',
                'jquery-ui-sortable',
            ],
            DIZZY_EVENTS_VERSION,
            true
        );

        wp_localize_script(
            'dizzy-events-admin',
            'DizzyEventsAdmin',
            [
                'removeLabel' => esc_html__(
                    'Remove',
                    'dizzy-events-manager'
                ),
                'selectTimeLabel' => esc_html__(
                    'Select time',
                    'dizzy-events-manager'
                ),
                'dragLabel' => esc_html__(
                    'Drag to reorder',
                    'dizzy-events-manager'
                ),
                'limitLabel' => sprintf(
                    /* translators: %d: maximum number of event dates. */
                    esc_html__(
                        'An event can contain no more than %d dates.',
                        'dizzy-events-manager'
                    ),
                    self::MAX_OCCURRENCES
                ),
                'maxOccurrences' => self::MAX_OCCURRENCES,
                'timeOptions'    => OccurrenceMetaBox::timeOptions(),
            ]
        );

        wp_enqueue_style(
            'dizzy-events-admin',
            DIZZY_EVENTS_URL . 'assets/css/admin.css',
            [],
            DIZZY_EVENTS_VERSION
        );
    }
}
