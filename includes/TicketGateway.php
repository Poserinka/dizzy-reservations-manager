<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class TicketGateway
{
    public function validTicketCount(int $eventId, int $occurrenceId, string $email): int
    {
        if ($eventId < 1 || $occurrenceId < 1 || ! is_email($email)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dizzy_tm_tickets';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ((string) $exists !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE event_id=%d
                AND occurrence_id=%d
                AND status='valid'
                AND LOWER(holder_email)=LOWER(%s)",
            $eventId,
            $occurrenceId,
            sanitize_email($email)
        ));
    }
}
