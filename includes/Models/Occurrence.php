<?php

declare(strict_types=1);

namespace Dizzy\Events\Models;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

defined('ABSPATH') || exit;

/**
 * Event occurrence model.
 *
 * @package Dizzy\Events\Models
 */
readonly class Occurrence
{
    /**
     * Create an occurrence.
     */
    public function __construct(
        public int $id,
        public int $eventId,
        public DateTimeImmutable $startDateTime,
        public ?DateTimeImmutable $endDateTime,
        public ?int $capacity = null,
    ) {
        if (
            $this->endDateTime !== null
            && $this->endDateTime < $this->startDateTime
        ) {
            throw new InvalidArgumentException(
                'Occurrence end date cannot be before start date.'
            );
        }
    }

    /**
     * Hydrate from database row.
     */
    public static function hydrateFromRow(object $row): self
    {
        $timezone = self::resolveTimezone(
            isset($row->timezone) ? (string) $row->timezone : ''
        );

        $start = self::parseDateTime(
            isset($row->start_datetime) ? (string) $row->start_datetime : '',
            $timezone,
            'start_datetime'
        );

        $endValue = isset($row->end_datetime)
            ? trim((string) $row->end_datetime)
            : '';

        $end = $endValue !== ''
            ? self::parseDateTime($endValue, $timezone, 'end_datetime')
            : null;

        return new self(
            id: isset($row->id) ? (int) $row->id : 0,
            eventId: isset($row->event_id) ? (int) $row->event_id : 0,
            startDateTime: $start,
            endDateTime: $end,
            capacity: isset($row->capacity) && (int) $row->capacity > 0
                ? (int) $row->capacity
                : null
        );
    }

    /**
     * Check whether the occurrence is current or upcoming.
     */
    public function isUpcoming(): bool
    {
        $now = new DateTimeImmutable(
            'now',
            $this->startDateTime->getTimezone()
        );
        $activeUntil = $this->endDateTime ?? $this->startDateTime;

        return $activeUntil >= $now;
    }

    /**
     * Parse a stored database date strictly.
     */
    private static function parseDateTime(
        string $value,
        DateTimeZone $timezone,
        string $field
    ): DateTimeImmutable {
        $dateTime = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            $timezone
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $dateTime === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $dateTime->format('Y-m-d H:i:s') !== $value
        ) {
            throw new UnexpectedValueException(
                sprintf('Invalid occurrence %s value: %s', $field, $value)
            );
        }

        return $dateTime;
    }

    /**
     * Resolve a stored timezone safely.
     */
    private static function resolveTimezone(string $timezone): DateTimeZone
    {
        if ($timezone !== '') {
            try {
                return new DateTimeZone($timezone);
            } catch (Exception) {
                // Fall back to the current WordPress timezone.
            }
        }

        return wp_timezone();
    }
}

