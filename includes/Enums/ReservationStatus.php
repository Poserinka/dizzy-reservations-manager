<?php

declare(strict_types=1);

namespace Dizzy\Events\Enums;

defined('ABSPATH') || exit;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Waitlisted = 'waitlisted';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}

