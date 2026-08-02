<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend;

use Dizzy\Events\Frontend\ViewModels\OccurrenceViewData;
use Dizzy\Events\Frontend\ViewModels\SingleEventViewData;
use Dizzy\Events\Services\EventService;
use Throwable;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Handles single event display.
 *
 * @package Dizzy\Events\Frontend
 */
final class SingleEvent
{
    /**
     * Create the single event renderer.
     */
    public function __construct(
        private EventService $service
    ) {
    }

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_filter(
            'template_include',
            [
                $this,
                'template',
            ]
        );
    }

    /**
     * Load custom template.
     */
    public function template(string $template): string
    {
        if (! is_singular('event')) {
            return $template;
        }

        global $post;

        if (! $post instanceof WP_Post) {
            return $template;
        }

        $data = $this->service->getEvent((int) $post->ID);

        if ($data === null) {
            return $template;
        }

        $occurrenceGroups = $this->service->groupOccurrences(
            $data['occurrences']
        );

        $viewData = new SingleEventViewData(
            event: $data['event'],
            details: $data['details'],
            upcomingOccurrences: $this->occurrences(
                $occurrenceGroups['upcoming']
            ),
            pastOccurrences: $this->occurrences(
                $occurrenceGroups['past']
            ),
        );

        set_query_var('dizzy_event_data', $viewData);

        $customTemplate = DIZZY_EVENTS_PATH
            . 'includes/Frontend/Views/single-event.php';

        if (file_exists($customTemplate)) {
            return $customTemplate;
        }

        return $template;
    }

    /**
     * @param array<\Dizzy\Events\Models\Occurrence> $occurrences
     * @return array<OccurrenceViewData>
     */
    private function occurrences(array $occurrences): array
    {
        $views = [];

        foreach ($occurrences as $occurrence) {
            try {
                $views[] = OccurrenceViewData::from($occurrence);
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
            }
        }

        return $views;
    }
}
