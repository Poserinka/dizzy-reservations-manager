<?php

declare(strict_types=1);

namespace Dizzy\Events\Reports;

use wpdb;

defined('ABSPATH') || exit;

final class ReportRepository
{
    private string $reservations;
    private string $occurrences;

    public function __construct(private readonly wpdb $wpdb)
    {
        $this->reservations = $wpdb->prefix . 'dizzy_event_reservations';
        $this->occurrences = $wpdb->prefix . 'dizzy_event_occurrences';
    }

    /** @return array<string, int> */
    public function summary(int $eventId, string $from, string $to): array
    {
        [$where, $args] = $this->filters($eventId, $from, $to, 'o', 'r');
        $sql = "SELECT
            COUNT(r.id) AS reservations,
            COALESCE(SUM(r.guests), 0) AS requested_guests,
            COUNT(CASE WHEN r.status = 'pending' THEN 1 END) AS pending,
            COUNT(CASE WHEN r.status = 'waitlisted' THEN 1 END) AS waitlisted,
            COUNT(CASE WHEN r.status = 'confirmed' THEN 1 END) AS confirmed,
            COUNT(CASE WHEN r.status = 'cancelled' THEN 1 END) AS cancelled,
            COALESCE(SUM(CASE WHEN r.status = 'confirmed' THEN r.guests ELSE 0 END), 0) AS confirmed_guests,
            COALESCE(SUM(CASE WHEN r.checked_in_at IS NOT NULL THEN r.guests ELSE 0 END), 0) AS attended_guests
        FROM {$this->reservations} r
        LEFT JOIN {$this->occurrences} o ON o.id = r.occurrence_id
        WHERE {$where}";
        $row = $this->wpdb->get_row($this->prepare($sql, $args), ARRAY_A);
        $confirmedGuests = (int) ($row['confirmed_guests'] ?? 0);
        $attendedGuests = (int) ($row['attended_guests'] ?? 0);

        return [
            'reservations' => (int) ($row['reservations'] ?? 0),
            'requested_guests' => (int) ($row['requested_guests'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'waitlisted' => (int) ($row['waitlisted'] ?? 0),
            'confirmed' => (int) ($row['confirmed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'confirmed_guests' => $confirmedGuests,
            'attended_guests' => $attendedGuests,
            'no_show_guests' => max(0, $confirmedGuests - $attendedGuests),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function occurrences(int $eventId, string $from, string $to): array
    {
        [$where, $args] = $this->filters($eventId, $from, $to, 'o');
        $sql = "SELECT
            o.id, o.event_id, p.post_title AS event_title, o.start_datetime, o.capacity,
            COUNT(r.id) AS reservation_count,
            COALESCE(SUM(CASE WHEN r.status = 'confirmed' THEN r.guests ELSE 0 END), 0) AS confirmed_guests,
            COALESCE(SUM(CASE WHEN r.status = 'waitlisted' THEN r.guests ELSE 0 END), 0) AS waitlisted_guests,
            COALESCE(SUM(CASE WHEN r.checked_in_at IS NOT NULL THEN r.guests ELSE 0 END), 0) AS attended_guests
        FROM {$this->occurrences} o
        INNER JOIN {$this->wpdb->posts} p ON p.ID = o.event_id
        LEFT JOIN {$this->reservations} r ON r.occurrence_id = o.id
        WHERE {$where}
        GROUP BY o.id, o.event_id, p.post_title, o.start_datetime, o.capacity
        ORDER BY o.start_datetime DESC";
        $rows = $this->wpdb->get_results($this->prepare($sql, $args), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /** @return array{0:string, 1:array<int, int|string>} */
    private function filters(int $eventId, string $from, string $to, string $alias, string $eventAlias = ''): array
    {
        $conditions = ['1=1'];
        $args = [];

        if ($eventId > 0) {
            $conditions[] = ($eventAlias !== '' ? $eventAlias : $alias) . '.event_id = %d';
            $args[] = $eventId;
        }
        if ($from !== '') {
            $conditions[] = "{$alias}.start_datetime >= %s";
            $args[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $conditions[] = "{$alias}.start_datetime <= %s";
            $args[] = $to . ' 23:59:59';
        }

        return [implode(' AND ', $conditions), $args];
    }

    /** @param array<int, int|string> $args */
    private function prepare(string $sql, array $args): string
    {
        return $args === [] ? $sql : $this->wpdb->prepare($sql, ...$args);
    }
}

