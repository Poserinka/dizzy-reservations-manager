<?php

declare(strict_types=1);

namespace Dizzy\Events\Models;

use DateTimeImmutable;
use DateTimeZone;
use Dizzy\Events\Contracts\Hydrates;
use Dizzy\Events\Enums\EventStatus;

defined('ABSPATH') || exit;

/**
 * Immutable event model.
 *
 * Represents a Dizzy event entity.
 *
 * @package Dizzy\Events\Models
 */
readonly class Event implements Hydrates
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $content,
        public EventStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Create event from source object.
     */
    public static function from(object $source): static
    {
        $status = EventStatus::tryFrom(
            (string) ($source->status ?? EventStatus::DRAFT->value)
        ) ?? EventStatus::DRAFT;

        $timezone = wp_timezone();
        $fallback = new DateTimeImmutable('now', $timezone);

        return new self(
            id: (int) ($source->id ?? 0),
            title: (string) ($source->title ?? ''),
            slug: (string) ($source->slug ?? ''),
            content: (string) ($source->content ?? ''),
            status: $status,
            createdAt: self::hydrateDateTime(
                $source->created_at ?? null,
                $timezone,
                $fallback
            ),
            updatedAt: self::hydrateDateTime(
                $source->updated_at ?? null,
                $timezone,
                $fallback
            ),
        );
    }

    /**
     * Convert event to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'slug'       => $this->slug,
            'content'    => $this->content,
            'status'     => $this->status->value,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Hydrate a strict WordPress local date-time value.
     */
    private static function hydrateDateTime(
        mixed $value,
        DateTimeZone $timezone,
        DateTimeImmutable $fallback
    ): DateTimeImmutable {
        if (! is_scalar($value)) {
            return $fallback;
        }

        $date = trim((string) $value);

        if ($date === '' || $date === '0000-00-00 00:00:00') {
            return $fallback;
        }

        $dateTime = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $date,
            $timezone
        );

        if ($dateTime === false) {
            return $fallback;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if (
            is_array($errors)
            && (
                $errors['warning_count'] > 0
                || $errors['error_count'] > 0
            )
        ) {
            return $fallback;
        }

        if ($dateTime->format('Y-m-d H:i:s') !== $date) {
            return $fallback;
        }

        return $dateTime;
    }
}
