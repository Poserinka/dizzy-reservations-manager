<?php

declare(strict_types=1);

namespace Dizzy\Events\Reservations;

use RuntimeException;
use wpdb;

defined('ABSPATH') || exit;

final class ReservationRepository
{
    private wpdb $wpdb;

    private string $table;

    private string $occurrencesTable;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'dizzy_event_reservations';
        $this->occurrencesTable = $wpdb->prefix . 'dizzy_event_occurrences';
    }

    public function all(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY id DESC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function find(int $reservationId): ?array
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $reservationId
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    public function save(array $data): int
    {
        $occurrenceId = (int) ($data['occurrence_id'] ?? 0);

        if ($occurrenceId <= 0) {
            return $this->insert($data);
        }

        if ($this->wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Could not start reservation transaction.');
        }

        try {
            $occurrence = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT event_id, capacity FROM {$this->occurrencesTable} WHERE id = %d FOR UPDATE",
                    $occurrenceId
                ),
                ARRAY_A
            );

            if (! is_array($occurrence) || (int) $occurrence['event_id'] !== (int) ($data['event_id'] ?? 0)) {
                throw new RuntimeException('The selected event date is not available.');
            }

            $capacity = (int) ($occurrence['capacity'] ?? 0);

            if ($capacity > 0) {
                $reserved = (int) $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COALESCE(SUM(guests), 0) FROM {$this->table} WHERE occurrence_id = %d AND status IN (%s, %s)",
                        $occurrenceId,
                        'pending',
                        'confirmed'
                    )
                );

                if ($reserved + (int) ($data['guests'] ?? 1) > $capacity) {
                    $data['status'] = 'waitlisted';
                }
            }

            $reservationId = $this->insert($data);

            if ($this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Could not commit reservation transaction.');
            }

            return $reservationId;
        } catch (\Throwable $exception) {
            $this->wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    private function insert(array $data): int
    {
        $now = current_time('mysql', true);

        $record = [
            'event_id'      => (int) ($data['event_id'] ?? 0),
            'occurrence_id' => ! empty($data['occurrence_id'])
                ? (int) $data['occurrence_id']
                : null,
            'name'           => (string) ($data['name'] ?? ''),
            'email'          => (string) ($data['email'] ?? ''),
            'phone'          => isset($data['phone']) ? (string) $data['phone'] : null,
            'guests'         => max(1, (int) ($data['guests'] ?? 1)),
            'status'         => (string) ($data['status'] ?? 'pending'),
            'notes'          => isset($data['notes']) ? (string) $data['notes'] : null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        if ($this->wpdb->insert($this->table, $record) === false) {
            throw new RuntimeException(
                'Could not create reservation: ' . $this->wpdb->last_error
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $reservationId, array $data): bool
    {
        $data['updated_at'] = current_time('mysql', true);

        return false !== $this->wpdb->update($this->table, $data, ['id' => $reservationId]);
    }

    public function updateStatus(int $reservationId, string $status): bool
    {
        $reservation = $this->find($reservationId);

        if ($reservation === null) {
            return false;
        }

        $occurrenceId = (int) ($reservation['occurrence_id'] ?? 0);

        if ($occurrenceId <= 0 || ! in_array($status, ['pending', 'confirmed'], true)) {
            return $this->update($reservationId, ['status' => $status]);
        }

        if ($this->wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Could not start reservation status transaction.');
        }

        try {
            $occurrence = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT capacity FROM {$this->occurrencesTable} WHERE id = %d FOR UPDATE",
                    $occurrenceId
                ),
                ARRAY_A
            );

            if (! is_array($occurrence)) {
                $this->wpdb->query('ROLLBACK');
                return false;
            }

            $capacity = (int) ($occurrence['capacity'] ?? 0);

            if ($capacity > 0) {
                $reserved = (int) $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COALESCE(SUM(guests), 0) FROM {$this->table} WHERE occurrence_id = %d AND id <> %d AND status IN (%s, %s)",
                        $occurrenceId,
                        $reservationId,
                        'pending',
                        'confirmed'
                    )
                );

                if ($reserved + (int) ($reservation['guests'] ?? 1) > $capacity) {
                    $this->wpdb->query('ROLLBACK');
                    return false;
                }
            }

            $updated = $this->update($reservationId, ['status' => $status]);

            if (! $updated || $this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Could not commit reservation status transaction.');
            }

            return true;
        } catch (\Throwable $exception) {
            $this->wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    public function delete(int $reservationId): bool
    {
        return false !== $this->wpdb->delete($this->table, ['id' => $reservationId]);
    }

    public function checkIn(int $reservationId, int $userId): string
    {
        $reservation = $this->find($reservationId);

        if ($reservation === null || (string) ($reservation['status'] ?? '') !== 'confirmed') {
            return 'invalid';
        }

        if (! empty($reservation['checked_in_at'])) {
            return 'already_checked_in';
        }

        $updated = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET checked_in_at = %s, checked_in_by = %d, updated_at = %s WHERE id = %d AND status = %s AND checked_in_at IS NULL",
                current_time('mysql', true),
                $userId,
                current_time('mysql', true),
                $reservationId,
                'confirmed'
            )
        );

        return $updated === 1 ? 'checked_in' : 'already_checked_in';
    }

    public function undoCheckIn(int $reservationId): bool
    {
        return false !== $this->wpdb->update(
            $this->table,
            ['checked_in_at' => null, 'checked_in_by' => null, 'updated_at' => current_time('mysql', true)],
            ['id' => $reservationId]
        );
    }

    /** @return array<string, int> */
    public function attendanceTotals(): array
    {
        $row = $this->wpdb->get_row(
            "SELECT
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_reservations,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN guests ELSE 0 END), 0) AS confirmed_guests,
                COUNT(CASE WHEN checked_in_at IS NOT NULL THEN 1 END) AS checked_in_reservations,
                COALESCE(SUM(CASE WHEN checked_in_at IS NOT NULL THEN guests ELSE 0 END), 0) AS checked_in_guests
            FROM {$this->table}",
            ARRAY_A
        );

        return [
            'confirmed_reservations' => (int) ($row['confirmed_reservations'] ?? 0),
            'confirmed_guests' => (int) ($row['confirmed_guests'] ?? 0),
            'checked_in_reservations' => (int) ($row['checked_in_reservations'] ?? 0),
            'checked_in_guests' => (int) ($row['checked_in_guests'] ?? 0),
        ];
    }
}

