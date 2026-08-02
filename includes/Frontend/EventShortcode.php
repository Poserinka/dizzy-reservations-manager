<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend;

use Dizzy\Events\Frontend\Builders\EventPresentationBuilder;
use Dizzy\Events\Services\EventService;

defined('ABSPATH') || exit;

/**
 * Frontend event shortcode.
 *
 * @package Dizzy\Events\Frontend
 */
final class EventShortcode
{
    /**
     * Create the event shortcode.
     */
    public function __construct(
        private EventService $service,
        private EventPresentationBuilder $builder
    ) {
    }

    /**
     * Register shortcode.
     */
    public function register(): void
    {
        add_shortcode(
            'dizzy_events',
            [
                $this,
                'render',
            ]
        );
    }

    /**
     * Render events.
     *
     * @param array<string, mixed> $atts Shortcode attributes.
     */
    public function render(array $atts = []): string
    {
        $atts = shortcode_atts(
            [
                'limit' => 10,
            ],
            $atts,
            'dizzy_events'
        );

        $limit = absint($atts['limit']);

        if ($limit <= 0) {
            $limit = 10;
        }

        $limit = min($limit, 100);
        $eventData = $this->service->getUpcomingEventData($limit);

        if ($eventData === []) {
            return sprintf(
                '<p>%s</p>',
                esc_html__(
                    'No upcoming events.',
                    'dizzy-events-manager'
                )
            );
        }

        ob_start();
        ?>
        <div class="dizzy-events">
            <?php foreach ($eventData as $data) : ?>
                <?php
                $viewData = $this->builder->build(
                    $data['event'],
                    $data['details'],
                    $data['occurrences']
                );

                include DIZZY_EVENTS_PATH . 'includes/Frontend/Views/event-card.php';
                ?>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
