<?php

declare(strict_types=1);

namespace Dizzy\Events\Models;

defined('ABSPATH') || exit;

/**
 * Event additional information.
 *
 * Represents non-core event data.
 *
 * @package Dizzy\Events\Models
 */
readonly class EventDetails
{
    private const DEFAULT_VENUE = 'Jazzcafé Dizzy';

    private const DEFAULT_ADDRESS = "'s-Gravendijkwal 127, 3021 EK Rotterdam";

    private const DEFAULT_MAPS_URL = 'https://maps.app.goo.gl/t73PkgDRtb6RvKFMA';

    /**
     * Create event details.
     */
    public function __construct(
        public ?string $artist,
        public ?string $genre,
        public string $venue,
        public string $address,
        public string $mapsUrl,
        public ?string $ticketUrl,
        public ?float $ticketPrice,
        public bool $featured,
    ) {
    }

    /**
     * Create from WordPress metadata.
     *
     * Supports both normalized keys and raw get_post_meta() output.
     *
     * @param array<string, mixed> $meta Event metadata.
     */
    public static function fromMeta(
        array $meta,
        ?string $genre = null,
        ?string $artist = null,
        ?string $venue = null,
        ?string $address = null,
        ?string $mapsUrl = null,
    ): self
    {
        return new self(
            artist: $artist ?? self::stringValue(
                self::metaValue($meta, 'artist')
            ),
            genre: $genre ?? self::stringValue(
                self::metaValue($meta, 'genre')
            ),
            venue: $venue ?? self::stringValue(
                self::metaValue($meta, 'venue')
            ) ?? self::DEFAULT_VENUE,
            address: $address ?? self::stringValue(
                self::metaValue($meta, 'address')
            ) ?? self::DEFAULT_ADDRESS,
            mapsUrl: $mapsUrl ?? self::urlValue(
                self::metaValue($meta, 'maps_url')
            ) ?? self::DEFAULT_MAPS_URL,
            ticketUrl: self::urlValue(
                self::metaValue($meta, 'ticket_url')
            ),
            ticketPrice: self::priceValue(
                self::metaValue($meta, 'ticket_price')
            ),
            featured: self::boolValue(
                self::metaValue($meta, 'featured')
            ),
        );
    }

    /**
     * Get a normalized metadata value.
     *
     * @param array<string, mixed> $meta Metadata values.
     */
    private static function metaValue(array $meta, string $key): mixed
    {
        $value = $meta[$key] ?? $meta['_dizzy_' . $key] ?? null;

        while (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return $value;
    }

    /**
     * Normalize a string value.
     */
    private static function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalize a URL value.
     */
    private static function urlValue(mixed $value): ?string
    {
        $value = self::stringValue($value);

        if ($value === null) {
            return null;
        }

        $url = esc_url_raw($value, ['http', 'https']);

        return $url === '' ? null : $url;
    }

    /**
     * Normalize a non-negative ticket price.
     */
    private static function priceValue(mixed $value): ?float
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = str_replace(',', '.', trim((string) $value));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $price = (float) $value;

        return $price < 0 ? null : $price;
    }

    /**
     * Normalize a boolean value.
     */
    private static function boolValue(mixed $value): bool
    {
        while (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return in_array(
            $value,
            [true, 1, '1', 'true', 'yes', 'on'],
            true
        );
    }
}
