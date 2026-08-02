<?php

declare(strict_types=1);

namespace Dizzy\Events\Poster\Repositories;

use Dizzy\Events\Poster\Models\Poster;
use RuntimeException;

defined('ABSPATH') || exit;

final class PosterRepository
{
    public function create(array $data): Poster
    {
        global $wpdb;

        $table = $wpdb->prefix . 'dizzy_event_posters';
        $now = current_time('mysql');
        $eventId = (int) ($data['event_id'] ?? 0);

        if ($eventId <= 0) {
            throw new RuntimeException('A valid event ID is required to create a poster.');
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'event_id' => $eventId,
                'attachment_id' => $data['attachment_id'] ?? null,
                'prompt' => $data['prompt'] ?? '',
                'image_url' => $data['image_url'] ?? '',
                'provider' => $data['provider'] ?? 'ai',
                'status' => $data['status'] ?? 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            throw new RuntimeException(
                'Could not create poster: ' . $wpdb->last_error
            );
        }

        return $this->find((int) $wpdb->insert_id);
    }

    public function find(int $id): Poster
    {
        global $wpdb;

        $table = $wpdb->prefix . 'dizzy_event_posters';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (! is_array($row)) {
            throw new RuntimeException('Poster record could not be found.');
        }

        return $this->hydrate($row);
    }

    public function findByEvent(int $eventId): ?Poster
    {
        global $wpdb;

        if ($eventId <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'dizzy_event_posters';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE event_id = %d ORDER BY id DESC LIMIT 1",
                $eventId
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): Poster
    {
        return new Poster(
            (int) ($row['id'] ?? 0),
            isset($row['event_id']) ? (int) $row['event_id'] : null,
            isset($row['attachment_id']) ? (int) $row['attachment_id'] : null,
            (string) ($row['prompt'] ?? ''),
            (string) ($row['image_url'] ?? ''),
            (string) ($row['status'] ?? 'draft'),
        );
    }
}
