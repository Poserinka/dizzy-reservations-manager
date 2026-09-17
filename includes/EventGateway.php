<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class EventGateway
{
    public const POST_TYPE = 'dizzy_event';

    public function available(): bool
    {
        return post_type_exists(self::POST_TYPE);
    }

    /**
     * Find the first paid, published concert occurrence on a local calendar day.
     */
    public function ticketedConcertOn(string $date): ?array
    {
        if (! $this->available() || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dizzy_event_occurrences';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.id,o.event_id,o.start_datetime,o.end_datetime,o.timezone,p.post_title
                FROM {$table} o
                INNER JOIN {$wpdb->posts} p ON p.ID=o.event_id
                WHERE o.status=%s
                    AND p.post_type=%s
                    AND p.post_status='publish'
                    AND DATE(o.start_datetime)=%s
                ORDER BY o.start_datetime ASC",
                'publish',
                self::POST_TYPE,
                $date
            ),
            ARRAY_A
        ) ?: [];

        $row = null;
        $price = '';
        foreach ($rows as $candidate) {
            $candidatePrice = (string) get_post_meta((int) $candidate['event_id'], '_dizzy_standard_ticket_price', true);
            if ($candidatePrice === '') {
                $candidatePrice = (string) get_post_meta((int) $candidate['event_id'], '_dizzy_ticket_price', true);
            }
            if (is_numeric($candidatePrice) && (float) $candidatePrice > 0) {
                $row = $candidate;
                $price = $candidatePrice;
                break;
            }
        }
        if (! is_array($row)) {
            return null;
        }
        $ticketUrl = (string) get_post_meta((int) $row['event_id'], '_dizzy_ticket_url', true);

        $start = new \DateTimeImmutable((string) $row['start_datetime'], wp_timezone());
        $end = ! empty($row['end_datetime'])
            ? new \DateTimeImmutable((string) $row['end_datetime'], wp_timezone())
            : $start->modify('+3 hours');
        $dinnerCutoff = $start->modify('-1 hour');

        return $row + [
            'price' => number_format((float) $price, 2, '.', ''),
            'ticket_url' => esc_url_raw($ticketUrl),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'dinner_cutoff' => $dinnerCutoff->format('H:i'),
        ];
    }

    public function occurrence(int $eventId, int $occurrenceId): ?array
    {
        if ($eventId <= 0 || $occurrenceId <= 0 || get_post_type($eventId) !== self::POST_TYPE) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dizzy_event_occurrences';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id,event_id,start_datetime,end_datetime,timezone
                FROM {$table}
                WHERE id=%d AND event_id=%d AND status=%s
                LIMIT 1",
                $occurrenceId,
                $eventId,
                'publish'
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function upcoming(int $eventId): array
    {
        if (get_post_type($eventId) !== self::POST_TYPE) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dizzy_event_occurrences';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    o.id,
                    o.event_id,
                    o.start_datetime,
                    o.end_datetime,
                    o.timezone,
                    p.post_title
                FROM {$table} o
                INNER JOIN {$wpdb->posts} p ON p.ID=o.event_id
                WHERE o.event_id=%d
                    AND o.status=%s
                    AND COALESCE(o.end_datetime,o.start_datetime)>=%s
                ORDER BY o.start_datetime",
                $eventId,
                'publish',
                current_time('mysql')
            ),
            ARRAY_A
        ) ?: [];
    }

    public function allUpcoming(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'dizzy_event_occurrences';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.id,o.event_id,o.start_datetime,o.end_datetime,o.timezone,p.post_title
                FROM {$table} o
                INNER JOIN {$wpdb->posts} p ON p.ID=o.event_id
                WHERE o.status=%s
                    AND p.post_type=%s
                    AND p.post_status IN ('publish','future','draft','pending','private')
                    AND COALESCE(o.end_datetime,o.start_datetime)>=%s
                ORDER BY o.start_datetime",
                'publish',
                self::POST_TYPE,
                current_time('mysql')
            ),
            ARRAY_A
        ) ?: [];
    }
}
