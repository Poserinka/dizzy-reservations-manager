<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

use RuntimeException;

defined('ABSPATH') || exit;

final class ReservationRepository
{
    private string $table;
    private string $capacities;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dizzy_event_reservations';
        $this->capacities = $wpdb->prefix . 'dizzy_reservation_capacities';
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table, [
            'event_id' => (int) $data['event_id'],
            'occurrence_id' => (int) $data['occurrence_id'],
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => (string) ($data['phone'] ?? ''),
            'guests' => (int) $data['guests'],
            'status' => (string) $data['status'],
            'notes' => (string) ($data['notes'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($ok === false) {
            throw new RuntimeException('Reservation could not be saved: ' . $wpdb->last_error);
        }

        return (int) $wpdb->insert_id;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function all(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at DESC", ARRAY_A) ?: [];
    }

    public function reservedGuests(int $occurrenceId, int $excludeId = 0): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(guests),0) FROM {$this->table} WHERE occurrence_id=%d AND id<>%d AND status IN (%s,%s)",
            $occurrenceId,
            $excludeId,
            'pending',
            'confirmed'
        ));
    }

    public function capacity(int $occurrenceId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT capacity FROM {$this->capacities} WHERE occurrence_id=%d", $occurrenceId));
    }

    public function setCapacity(int $occurrenceId, int $capacity): bool
    {
        global $wpdb;
        return $wpdb->replace($this->capacities, [
            'occurrence_id' => $occurrenceId,
            'capacity' => $capacity > 0 ? $capacity : null,
            'updated_at' => current_time('mysql', true),
        ]) !== false;
    }

    public function updateStatus(int $id, string $status): bool
    {
        global $wpdb;
        return $wpdb->update(
            $this->table,
            ['status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $id]
        ) !== false;
    }

    public function reportSummary(): array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            "SELECT COUNT(*) reservations,
            COALESCE(SUM(CASE WHEN status='confirmed' THEN guests ELSE 0 END),0) confirmed_guests,
            COUNT(CASE WHEN status='waitlisted' THEN 1 END) waitlisted
            FROM {$this->table}",
            ARRAY_A
        ) ?: [];

        return array_map('intval', [
            'reservations' => $row['reservations'] ?? 0,
            'confirmed_guests' => $row['confirmed_guests'] ?? 0,
            'waitlisted' => $row['waitlisted'] ?? 0,
        ]);
    }

    public function reportRows(): array
    {
        global $wpdb;
        $occurrences = $wpdb->prefix . 'dizzy_event_occurrences';
        $rows = $wpdb->get_results(
            "SELECT r.event_id,r.occurrence_id,p.post_title,o.start_datetime,
                COUNT(r.id) reservations,
                COALESCE(SUM(r.guests),0) guests
            FROM {$this->table} r
            LEFT JOIN {$wpdb->posts} p ON p.ID=r.event_id
            LEFT JOIN {$occurrences} o ON o.id=r.occurrence_id
            GROUP BY r.event_id,r.occurrence_id,p.post_title,o.start_datetime
            ORDER BY o.start_datetime DESC",
            ARRAY_A
        ) ?: [];

        foreach ($rows as &$row) {
            $row['capacity'] = absint(get_post_meta((int) $row['event_id'], '_dizzy_capacity', true));
        }
        unset($row);

        return $rows;
    }
}
