<?php

declare(strict_types=1);

namespace Dizzy\Events\Frontend\ViewModels;

use Dizzy\Events\Models\Occurrence;

defined('ABSPATH') || exit;

/**
 * Frontend occurrence data.
 *
 * @package Dizzy\Events\Frontend\ViewModels
 */
readonly class OccurrenceViewData
{
    /**
     * Create occurrence view data.
     */
    public function __construct(
        public string $date,
        public string $time,
    ) {
    }

    /**
     * Create from occurrence model.
     */
    public static function from(Occurrence $occurrence): self
    {
        $timestamp = $occurrence->startDateTime->getTimestamp();
        $timezone  = $occurrence->startDateTime->getTimezone();
        $dateFormat = self::formatOption('date_format', 'j F Y');
        $timeFormat = self::formatOption('time_format', 'H:i');

        return new self(
            date: wp_date($dateFormat, $timestamp, $timezone),
            time: wp_date($timeFormat, $timestamp, $timezone),
        );
    }

    /**
     * Get a usable WordPress date or time format option.
     */
    private static function formatOption(string $option, string $fallback): string
    {
        $format = get_option($option);

        if (! is_string($format)) {
            return $fallback;
        }

        $format = trim($format);

        return $format === '' ? $fallback : $format;
    }
}
