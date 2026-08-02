<?php

declare(strict_types=1);

namespace Dizzy\Events\Enums;

defined('ABSPATH') || exit;

/**
 * Event status values.
 *
 * @package Dizzy\Events\Enums
 */
enum EventStatus: string
{
    /**
     * Event is a draft.
     */
    case DRAFT = 'draft';

    /**
     * Event is awaiting editorial review.
     */
    case PENDING = 'pending';

    /**
     * Event is scheduled for future publication.
     */
    case SCHEDULED = 'future';

    /**
     * Event is privately visible.
     */
    case PRIVATE = 'private';

    /**
     * Event is publicly visible.
     */
    case PUBLISHED = 'publish';

    /**
     * Event is in the WordPress trash.
     */
    case TRASHED = 'trash';

    /**
     * Event has been cancelled.
     */
    case CANCELLED = 'cancelled';

    /**
     * Event is archived.
     */
    case ARCHIVED = 'archived';
}
