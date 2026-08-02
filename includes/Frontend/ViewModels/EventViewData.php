<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend\ViewModels;

defined('ABSPATH') || exit;

/**
 * Frontend event presentation data.
 *
 * @package Dizzy\Events\Frontend\ViewModels
 */
readonly class EventViewData
{
    private const DEFAULT_CARD_DATE_LIMIT = 3;

    private const MAX_CARD_DATE_LIMIT = 10;

    /**
     * @param array<OccurrenceViewData> $dates
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
        public string $image,
        public string $excerpt,
        public ?string $artist,
        public ?string $genre,
        public ?string $venue,
        public ?string $address,
        public ?string $mapsUrl,
        public ?string $ticketUrl,
        public ?float $ticketPrice,
        public bool $featured,
        public array $dates,
    ) {
    }

    /**
     * @return array{visible: array<OccurrenceViewData>, remaining: int}
     */
    public function cardDatePresentation(): array
    {
        $visible = array_slice($this->dates, 0, $this->cardDateLimit());

        return [
            'visible' => $visible,
            'remaining' => max(0, count($this->dates) - count($visible)),
        ];
    }

    /**
     * @return array<OccurrenceViewData>
     */
    public function cardDates(): array
    {
        return $this->cardDatePresentation()['visible'];
    }

    public function remainingCardDateCount(): int
    {
        return $this->cardDatePresentation()['remaining'];
    }

    private function cardDateLimit(): int
    {
        $limit = apply_filters(
            'dizzy_events_card_date_limit',
            self::DEFAULT_CARD_DATE_LIMIT,
            $this->id
        );

        return min(max(1, absint($limit)), self::MAX_CARD_DATE_LIMIT);
    }
}
