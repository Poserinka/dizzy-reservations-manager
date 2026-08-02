<?php

declare(strict_types=1);

namespace Dizzy\Events\Repositories;

use Dizzy\Events\Core\Config;
use Dizzy\Events\Models\Event;
use Throwable;
use WP_Post;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Repository for WordPress events.
 *
 * Handles retrieval of event posts.
 *
 * @package Dizzy\Events\Repositories
 */
final class EventRepository extends AbstractRepository
{
    /**
     * Event post type.
     */
    private const POST_TYPE = Config::POST_TYPE_EVENT;

    /**
     * Model handled by repository.
     *
     * @return class-string<Event>
     */
    protected function modelClass(): string
    {
        return Event::class;
    }

    /**
     * Find event by ID.
     */
    public function findById(int $id): ?Event
    {
        if ($id <= 0) {
            return null;
        }

        $post = get_post($id);

        if (! $post instanceof WP_Post) {
            return null;
        }

        if ($post->post_type !== self::POST_TYPE) {
            return null;
        }

        return $this->hydratePost($post);
    }

    /**
     * Find published events.
     *
     * @return array<Event>
     */
    public function findPublished(int $limit = 20): array
    {
        $limit = max(1, $limit);

        return $this->queryPublishedEvents(
            [
                'posts_per_page' => $limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );
    }

    /**
     * Find published events by IDs while preserving ID order.
     *
     * @param array<int> $eventIds Event post IDs.
     *
     * @return array<Event>
     */
    public function findPublishedByIds(array $eventIds): array
    {
        $eventIds = array_values(
            array_filter(
                array_map('absint', $eventIds)
            )
        );

        if ($eventIds === []) {
            return [];
        }

        return $this->queryPublishedEvents(
            [
                'post__in'       => $eventIds,
                'posts_per_page' => count($eventIds),
                'orderby'        => 'post__in',
            ]
        );
    }

    /**
     * Query published event posts.
     *
     * @param array<string, mixed> $arguments Additional query arguments.
     *
     * @return array<Event>
     */
    private function queryPublishedEvents(array $arguments): array
    {
        $query = new WP_Query(
            array_merge(
                [
                    'post_type'           => self::POST_TYPE,
                    'post_status'         => 'publish',
                    'no_found_rows'       => true,
                    'ignore_sticky_posts' => true,
                ],
                $arguments
            )
        );

        $events = [];

        foreach ($query->posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $event = $this->hydratePost($post);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Hydrate one WordPress event while isolating malformed records.
     */
    private function hydratePost(WP_Post $post): ?Event
    {
        try {
            return $this->hydrate(
                $this->convertPost($post)
            );
        } catch (Throwable $exception) {
            error_log(
                sprintf(
                    'Dizzy Events skipped malformed event %d: %s',
                    (int) $post->ID,
                    $exception->getMessage()
                )
            );

            return null;
        }
    }

    /**
     * Convert WP_Post to source object.
     */
    private function convertPost(WP_Post $post): object
    {
        return (object) [
            'id'         => (int) $post->ID,
            'title'      => (string) $post->post_title,
            'slug'       => (string) $post->post_name,
            'content'    => (string) $post->post_content,
            'status'     => (string) $post->post_status,
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
        ];
    }
}
