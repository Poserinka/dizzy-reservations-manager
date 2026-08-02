<?php

declare(strict_types=1);

namespace Dizzy\Events\Models;

use Dizzy\Events\Enums\ReservationStatus;

defined('ABSPATH') || exit;

readonly class Reservation
{
    public function __construct(
        public int $id,
        public int $eventId,
        public ?int $occurrenceId,
        public string $name,
        public string $email,
        public int $guests,
        public ReservationStatus $status
    ) {
    }
}
