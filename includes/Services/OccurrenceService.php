<?php

declare(strict_types=1);

namespace Dizzy\Events\Services;

use DateTimeImmutable;
use DateTimeZone;
use Dizzy\Events\Repositories\OccurrenceRepository;

defined('ABSPATH') || exit;

/**
 * Handles event occurrence business operations.
 *
 * @package Dizzy\Events\Services
 */
final class OccurrenceService
{
    private const MAX_OCCURRENCES_PER_EVENT = 100;

    /**
     * Occurrence service constructor.
     */
    public function __construct(
        private OccurrenceRepository $repository
    ) {
    }

    /**
     * Replace all occurrences belonging to an event.
     *
     * Existing records remain unchanged when submitted rows contain errors.
     *
     * @param array<string, mixed> $data Submitted occurrence data.
     *
     * @return array<int, array{row:int, code:string}>
     */
    public function replaceForEvent(
        int $eventId,
        array $data
    ): array {
        if ($eventId <= 0) {
            return [
                [
                    'row'  => 0,
                    'code' => 'invalid_event',
                ],
            ];
        }

        $normalized = $this->normalizeOccurrences($data);

        if ($normalized['errors'] !== []) {
            return $normalized['errors'];
        }

        $this->repository->replaceForEvent(
            $eventId,
            $normalized['occurrences']
        );

        return [];
    }

    /**
     * Normalize submitted occurrence rows.
     *
     * @param array<string, mixed> $data Submitted occurrence data.
     *
     * @return array{
     *     occurrences:array<int, array{
     *         start_datetime:string,
     *         id:int,
     *         end_datetime:string|null,
     *         capacity:int|null,
     *         all_day:int,
     *         timezone:string,
     *         sort_order:int,
     *         status:string
     *     }>,
     *     errors:array<int, array{row:int, code:string}>
     * }
     */
    private function normalizeOccurrences(array $data): array
    {
        $startDates = $this->getArrayValue($data, 'start_date');
        $startTimes = $this->getArrayValue($data, 'start_time');
        $endDates   = $this->getArrayValue($data, 'end_date');
        $endTimes   = $this->getArrayValue($data, 'end_time');
        $sortOrders = $this->getArrayValue($data, 'sort_order');
        $capacities = $this->getArrayValue($data, 'capacity');
        $ids = $this->getArrayValue($data, 'id');
        $rowCount   = max(
            count($startDates),
            count($startTimes),
            count($endDates),
            count($endTimes),
            count($sortOrders),
            count($capacities),
            count($ids)
        );

        if ($rowCount > self::MAX_OCCURRENCES_PER_EVENT) {
            return [
                'occurrences' => [],
                'errors'      => [
                    $this->error(0, 'too_many_occurrences'),
                ],
            ];
        }

        $timezone     = wp_timezone();
        $timezoneName = $timezone->getName();
        $occurrences  = [];
        $errors       = [];

        for ($index = 0; $index < $rowCount; $index++) {
            $rowNumber = $index + 1;
            $startDate = $this->sanitizeValue($startDates[$index] ?? '');
            $startTime = $this->sanitizeValue($startTimes[$index] ?? '');
            $endDate   = $this->sanitizeValue($endDates[$index] ?? '');
            $endTime   = $this->sanitizeValue($endTimes[$index] ?? '');

            if (
                $startDate === ''
                && $startTime === ''
                && $endDate === ''
                && $endTime === ''
            ) {
                continue;
            }

            if ($startDate === '') {
                $errors[] = $this->error($rowNumber, 'start_date_required');
                continue;
            }

            if ($startTime === '') {
                $errors[] = $this->error($rowNumber, 'start_time_required');
                continue;
            }

            $startDateTime = $this->createDateTime(
                $startDate,
                $startTime,
                $timezone
            );

            if ($startDateTime === null) {
                $errors[] = $this->error($rowNumber, 'invalid_start');
                continue;
            }

            if (($endDate === '') !== ($endTime === '')) {
                $errors[] = $this->error($rowNumber, 'incomplete_end');
                continue;
            }

            $endDateTime = null;

            if ($endDate !== '' && $endTime !== '') {
                $endDateTime = $this->createDateTime(
                    $endDate,
                    $endTime,
                    $timezone
                );

                if ($endDateTime === null) {
                    $errors[] = $this->error($rowNumber, 'invalid_end');
                    continue;
                }
            }

            if (
                $endDateTime !== null
                && $endDateTime < $startDateTime
            ) {
                $errors[] = $this->error($rowNumber, 'end_before_start');
                continue;
            }

            $sortOrder = isset($sortOrders[$index])
                ? absint($sortOrders[$index])
                : $index;
            $capacity = absint($capacities[$index] ?? 0);
            $id = absint($ids[$index] ?? 0);

            $occurrences[] = [
                'id'             => $id,
                'start_datetime' => $startDateTime->format('Y-m-d H:i:s'),
                'end_datetime'   => $endDateTime?->format('Y-m-d H:i:s'),
                'capacity'       => $capacity > 0 ? $capacity : null,
                'all_day'        => 0,
                'timezone'       => $timezoneName,
                'sort_order'     => $sortOrder,
                'status'         => 'publish',
            ];
        }

        return [
            'occurrences' => $occurrences,
            'errors'      => $errors,
        ];
    }

    /**
     * Build a validation error entry.
     *
     * @return array{row:int, code:string}
     */
    private function error(int $row, string $code): array
    {
        return [
            'row'  => $row,
            'code' => $code,
        ];
    }

    /**
     * Create a validated date and time value.
     */
    private function createDateTime(
        string $date,
        string $time,
        DateTimeZone $timezone
    ): ?DateTimeImmutable {
        $dateTime = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $date . ' ' . $time,
            $timezone
        );

        if ($dateTime === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if (
            is_array($errors)
            && (
                $errors['warning_count'] > 0
                || $errors['error_count'] > 0
            )
        ) {
            return null;
        }

        if (
            $dateTime->format('Y-m-d') !== $date
            || $dateTime->format('H:i') !== $time
        ) {
            return null;
        }

        return $dateTime;
    }

    /**
     * Sanitize one submitted scalar value.
     */
    private function sanitizeValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Get an array value from submitted data.
     *
     * @param array<string, mixed> $data Submitted occurrence data.
     *
     * @return array<int|string, mixed>
     */
    private function getArrayValue(
        array $data,
        string $key
    ): array {
        if (
            ! isset($data[$key])
            || ! is_array($data[$key])
        ) {
            return [];
        }

        return array_values($data[$key]);
    }
}
