<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend\ViewModels;

use Dizzy\Events\Models\Event;
use Dizzy\Events\Models\EventDetails;

defined('ABSPATH') || exit;

/**
 * Frontend single event page data.
 *
 * @package Dizzy\Events\Frontend\ViewModels
 */
readonly class SingleEventViewData
{
    /**
     * @param array<OccurrenceViewData> $upcomingOccurrences
     * @param array<OccurrenceViewData> $pastOccurrences
     */
    public function __construct(
        public Event $event,
        public EventDetails $details,
        public array $upcomingOccurrences,
        public array $pastOccurrences,
    ) {
    }
}
