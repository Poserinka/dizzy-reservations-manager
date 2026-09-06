<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class TableRepository
{
    private string $tables;
    private string $holds;
    private string $reservations;

    public function __construct()
    {
        global $wpdb;
        $this->tables = $wpdb->prefix . 'dizzy_reservation_tables';
        $this->holds = $wpdb->prefix . 'dizzy_reservation_table_holds';
        $this->reservations = $wpdb->prefix . 'dizzy_event_reservations';
    }

    public function all(bool $activeOnly = false): array
    {
        global $wpdb;
        $where = $activeOnly ? ' WHERE active=1' : '';
        return $wpdb->get_results("SELECT * FROM {$this->tables}{$where} ORDER BY sort_order,code", ARRAY_A) ?: [];
    }

    public function hasActiveTables(): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tables} WHERE active=1") > 0;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tables} WHERE id=%d AND active=1", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function saveLayout(array $items): void
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $seen = [];
        foreach ($items as $index => $item) {
            $id = absint($item['id'] ?? 0);
            $data = [
                'code' => strtoupper(sanitize_key((string) ($item['code'] ?? ''))),
                'label' => sanitize_text_field((string) ($item['label'] ?? '')),
                'capacity' => max(1, min(100, absint($item['capacity'] ?? 2))),
                'shape' => in_array(($item['shape'] ?? ''), ['round','square','rectangle'], true) ? $item['shape'] : 'square',
                'pos_x' => $this->percent($item['x'] ?? 10), 'pos_y' => $this->percent($item['y'] ?? 10),
                'width' => max(2, $this->percent($item['width'] ?? 7)), 'height' => max(2, $this->percent($item['height'] ?? 7)),
                'rotation' => max(-180, min(180, (float) ($item['rotation'] ?? 0))),
                'active' => ! empty($item['active']) ? 1 : 0, 'sort_order' => $index, 'updated_at' => $now,
            ];
            if ($data['code'] === '') continue;
            if ($data['label'] === '') $data['label'] = $data['code'];
            if ($id > 0) {
                $wpdb->update($this->tables, $data, ['id' => $id]);
                $seen[] = $id;
            } else {
                $data['created_at'] = $now;
                $wpdb->insert($this->tables, $data);
                $seen[] = (int) $wpdb->insert_id;
            }
        }
        if ($seen) {
            $ids = implode(',', array_map('absint', $seen));
            $wpdb->query("UPDATE {$this->tables} SET active=0 WHERE id NOT IN ({$ids})");
        } else {
            $wpdb->query("UPDATE {$this->tables} SET active=0");
        }
    }

    public function availability(string $date, string $time, int $guests, string $session = ''): array
    {
        $start = $date . ' ' . $time . ':00';
        $end = (new \DateTimeImmutable($start, wp_timezone()))->modify('+120 minutes')->format('Y-m-d H:i:s');
        return array_map(function (array $table) use ($start, $end, $guests, $session): array {
            $state = (int) $table['capacity'] < $guests ? 'too_small' : ($this->isAvailable((int) $table['id'], $start, $end, $session) ? 'available' : 'booked');
            return $table + ['state' => $state];
        }, $this->all(true));
    }

    public function hold(int $tableId, string $date, string $time, int $guests, string $session): bool
    {
        global $wpdb;
        if (! $this->lock($tableId)) return false;
        try {
            $this->cleanupHolds();
            $table = $this->find($tableId);
            $start = $date . ' ' . $time . ':00';
            $end = (new \DateTimeImmutable($start, wp_timezone()))->modify('+120 minutes')->format('Y-m-d H:i:s');
            if ($table === null || (int) $table['capacity'] < $guests || ! $this->isAvailable($tableId, $start, $end, $session)) return false;
            $wpdb->delete($this->holds, ['session_token' => $session]);
            return $wpdb->insert($this->holds, [
                'table_id'=>$tableId,'session_token'=>$session,'start_at'=>$start,'end_at'=>$end,
                'expires_at'=>gmdate('Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS),'created_at'=>current_time('mysql', true),
            ]) !== false;
        } finally { $this->unlock($tableId); }
    }

    public function release(string $session): void
    {
        global $wpdb;
        $wpdb->delete($this->holds, ['session_token' => $session]);
    }

    public function validateAndLock(int $tableId, string $date, string $time, int $guests, string $session): bool
    {
        if (! $this->lock($tableId)) return false;
        $table = $this->find($tableId);
        $start = $date . ' ' . $time . ':00';
        $end = (new \DateTimeImmutable($start, wp_timezone()))->modify('+120 minutes')->format('Y-m-d H:i:s');
        $valid = $table !== null && (int) $table['capacity'] >= $guests && $this->isAvailable($tableId, $start, $end, $session);
        if (! $valid) $this->unlock($tableId);
        return $valid;
    }

    public function unlock(int $tableId): void
    {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'dizzy_reservation_table_' . $tableId));
    }

    private function isAvailable(int $tableId, string $start, string $end, string $session): bool
    {
        global $wpdb;
        $this->cleanupHolds();
        $booked = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->reservations} WHERE table_id=%d AND status!='cancelled' AND TIMESTAMP(reservation_date,reservation_time)<%s AND DATE_ADD(TIMESTAMP(reservation_date,reservation_time), INTERVAL duration_minutes MINUTE)>%s",
            $tableId, $end, $start
        ));
        if ($booked > 0) return false;
        $held = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->holds} WHERE table_id=%d AND session_token!=%s AND start_at<%s AND end_at>%s AND expires_at>%s",
            $tableId, $session, $end, $start, current_time('mysql', true)
        ));
        return $held === 0;
    }

    private function cleanupHolds(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$this->holds} WHERE expires_at<%s", current_time('mysql', true)));
    }

    private function lock(int $tableId): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', 'dizzy_reservation_table_' . $tableId)) === 1;
    }

    private function percent(mixed $value): float { return max(0, min(100, round((float) $value, 3))); }
}
