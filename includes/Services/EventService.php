<?php

declare(strict_types=1);

namespace Dizzy\Events\Services;

use Dizzy\Events\Core\Config;
use Dizzy\Events\Enums\EventStatus;
use Dizzy\Events\Models\Event;
use Dizzy\Events\Models\EventDetails;
use Dizzy\Events\Models\Occurrence;
use Dizzy\Events\Repositories\EventRepository;
use Dizzy\Events\Repositories\OccurrenceRepository;

defined('ABSPATH') || exit;

/**
 * Event application service.
 *
 * Handles event-related business operations.
 *
 * @package Dizzy\Events\Services
 */
final class EventService
{
    /**
     * Create the event service.
     */
    public function __construct(
        private EventRepository $eventRepository,
        private OccurrenceRepository $occurrenceRepository,
    ) {
    }

    /**
     * Get a published event with occurrences and details.
     *
     * @return array{
     *     event: Event,
     *     occurrences: array<Occurrence>,
     *     details: EventDetails
     * }|null
     */
    public function getEvent(int $eventId): ?array
    {
        $event = $this->eventRepository->findById($eventId);

        if (
            $event === null
            || $event->status !== EventStatus::PUBLISHED
        ) {
            return null;
        }

        return [
            'event'       => $event,
            'occurrences' => $this->occurrenceRepository->findByEventId($eventId),
            'details'     => $this->getEventDetails($eventId),
        ];
    }

    /**
     * Get event details.
     */
    public function getEventDetails(int $eventId): EventDetails
    {
        $artistNames = wp_get_post_terms($eventId, Config::TAX_ARTIST, ['fields' => 'names']);
        $artist = is_wp_error($artistNames)
            ? null
            : implode(', ', array_map('strval', $artistNames));
        $venueTerms = wp_get_post_terms($eventId, Config::TAX_VENUE);
        $venueTerm = ! is_wp_error($venueTerms) ? ($venueTerms[0] ?? null) : null;
        $venue = $venueTerm instanceof \WP_Term ? $venueTerm->name : null;
        $address = $venueTerm instanceof \WP_Term
            ? trim((string) get_term_meta($venueTerm->term_id, '_dizzy_address', true))
            : null;
        $mapsUrl = $venueTerm instanceof \WP_Term
            ? esc_url_raw((string) get_term_meta($venueTerm->term_id, '_dizzy_maps_url', true))
            : null;

        return EventDetails::fromMeta(
            get_post_meta($eventId),
            null,
            $artist !== '' ? $artist : null,
            $venue,
            $address !== '' ? $address : null,
            $mapsUrl !== '' ? $mapsUrl : null,
        );
    }

    /**
     * Get upcoming event presentation data.
     *
     * @return array<int, array{
     *     event: Event,
     *     details: EventDetails,
     *     occurrences: array<Occurrence>
     * }>
     */
    public function getUpcomingEventData(int $limit = 20): array
    {
        $events = $this->getUpcomingEvents($limit);

        if ($events === []) {
            return [];
        }

        $eventIds = array_map(
            static function (Event $event): int {
                return $event->id;
            },
            $events
        );

        $occurrencesByEvent = $this->occurrenceRepository
            ->findUpcomingByEventIds($eventIds);
        $data = [];

        foreach ($events as $event) {
            $data[] = [
                'event'       => $event,
                'details'     => $this->getEventDetails($event->id),
                'occurrences' => $occurrencesByEvent[$event->id] ?? [],
            ];
        }

        return $data;
    }

    /**
     * Group loaded occurrences by temporal state.
     *
     * @param array<Occurrence> $occurrences Occurrence records.
     *
     * @return array{
     *     upcoming: array<Occurrence>,
     *     past: array<Occurrence>
     * }
     */
    public function groupOccurrences(array $occurrences): array
    {
        $grouped = [
            'upcoming' => [],
            'past'     => [],
        ];

        foreach ($occurrences as $occurrence) {
            if (! $occurrence instanceof Occurrence) {
                continue;
            }

            $group = $occurrence->isUpcoming()
                ? 'upcoming'
                : 'past';

            $grouped[$group][] = $occurrence;
        }

        $this->sortOccurrences($grouped['upcoming']);
        $this->sortOccurrences($grouped['past']);

        return $grouped;
    }

    /**
     * Get upcoming occurrences in chronological order.
     *
     * @return array<Occurrence>
     */
    public function getUpcomingOccurrences(int $eventId): array
    {
        $grouped = $this->groupOccurrences(
            $this->occurrenceRepository->findByEventId($eventId)
        );

        return $grouped['upcoming'];
    }

    /**
     * Get past occurrences in chronological order.
     *
     * @return array<Occurrence>
     */
    public function getPastOccurrences(int $eventId): array
    {
        $grouped = $this->groupOccurrences(
            $this->occurrenceRepository->findByEventId($eventId)
        );

        return $grouped['past'];
    }

    /**
     * Get upcoming published events ordered by next occurrence.
     *
     * @return array<Event>
     */
    public function getUpcomingEvents(int $limit = 20): array
    {
        $eventIds = $this->occurrenceRepository->findUpcomingEventIds($limit);

        return $this->eventRepository->findPublishedByIds($eventIds);
    }

    /**
     * Check whether event has a current or upcoming published occurrence.
     */
    public function hasUpcomingOccurrences(int $eventId): bool
    {
        return $this->occurrenceRepository->hasUpcomingForEvent($eventId);
    }

    /**
     * Sort occurrence records chronologically.
     *
     * @param array<Occurrence> $occurrences Occurrence records.
     */
    private function sortOccurrences(array &$occurrences): void
    {
        usort(
            $occurrences,
            static function (
                Occurrence $first,
                Occurrence $second
            ): int {
                $comparison = $first->startDateTime <=> $second->startDateTime;

                return $comparison !== 0
                    ? $comparison
                    : $first->id <=> $second->id;
            }
        );
    }
}
