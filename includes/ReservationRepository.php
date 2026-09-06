<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

use RuntimeException;

defined('ABSPATH') || exit;

final class ReservationRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dizzy_event_reservations';
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table, [
            'event_id' => 0,
            'occurrence_id' => 0,
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => (string) $data['phone'],
            'reservation_date' => (string) $data['reservation_date'],
            'reservation_time' => (string) $data['reservation_time'],
            'guests' => (int) $data['guests'],
            'table_id' => (int) ($data['table_id'] ?? 0),
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 120),
            'status' => (string) $data['status'],
            'notes' => (string) $data['message'],
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
        $tables = $wpdb->prefix . 'dizzy_reservation_tables';
        return $wpdb->get_results("SELECT r.*,t.code table_code,t.label table_label FROM {$this->table} r LEFT JOIN {$tables} t ON t.id=r.table_id ORDER BY r.reservation_date DESC,r.reservation_time DESC,r.created_at DESC", ARRAY_A) ?: [];
    }

    public function updateStatus(int $id, string $status): bool
    {
        global $wpdb;
        return $wpdb->update($this->table, ['status' => $status, 'updated_at' => current_time('mysql', true)], ['id' => $id]) !== false;
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
        return $wpdb->get_results(
            "SELECT reservation_date,reservation_time,
            COUNT(id) reservations,
            COALESCE(SUM(guests),0) guests,
            COALESCE(SUM(CASE WHEN status='confirmed' THEN guests ELSE 0 END),0) confirmed_guests,
            COUNT(CASE WHEN status='waitlisted' THEN 1 END) waitlisted
            FROM {$this->table}
            GROUP BY reservation_date,reservation_time
            ORDER BY reservation_date DESC,reservation_time DESC",
            ARRAY_A
        ) ?: [];
    }
}
