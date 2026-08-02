<?php

declare(strict_types=1);

namespace Dizzy\Events\PostTypes;

use Dizzy\Events\Core\Config;

defined('ABSPATH') || exit;

/**
 * Registers Event custom post type.
 *
 * @package Dizzy\Events\PostTypes
 */
final class EventPostType
{
    private const RELATION_MIGRATION_OPTION = 'dizzy_events_artist_venue_taxonomy_migrated';

    /**
     * Register post type.
     */
    public function register(): void
    {
        $this->registerStatuses();

        register_post_type(
            Config::POST_TYPE_EVENT,
            [
                'labels' => [
                    'name' =>
                        __('Events', 'dizzy-events-manager'),

                    'singular_name' =>
                        __('Event', 'dizzy-events-manager'),

                    'add_new' =>
                        __('Add New Event', 'dizzy-events-manager'),

                    'edit_item' =>
                        __('Edit Event', 'dizzy-events-manager'),

                    'view_item' =>
                        __('View Event', 'dizzy-events-manager'),
                ],

                'public' => true,

                'show_ui' => true,

                'menu_icon' => 'dashicons-calendar-alt',

                'supports' => [
                    'title',
                    'editor',
                    'thumbnail',
                    'excerpt',
                ],

                'has_archive' => true,

                'rewrite' => [
                    'slug' => 'events',
                ],

                'show_in_rest' => true,
            ]
        );

        $this->registerTaxonomies();
        $this->migrateLegacyRelations();
    }

    private function registerStatuses(): void
    {
        register_post_status(
            'cancelled',
            [
                'label' => __('Cancelled', 'dizzy-events-manager'),
                'public' => false,
                'internal' => false,
                'protected' => true,
                'exclude_from_search' => true,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop(
                    'Cancelled <span class="count">(%s)</span>',
                    'Cancelled <span class="count">(%s)</span>',
                    'dizzy-events-manager'
                ),
            ]
        );

        register_post_status(
            'archived',
            [
                'label' => __('Archived', 'dizzy-events-manager'),
                'public' => false,
                'internal' => false,
                'protected' => true,
                'exclude_from_search' => true,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop(
                    'Archived <span class="count">(%s)</span>',
                    'Archived <span class="count">(%s)</span>',
                    'dizzy-events-manager'
                ),
            ]
        );
    }

    private function registerTaxonomies(): void
    {
        register_taxonomy(
            Config::TAX_CATEGORY,
            [Config::POST_TYPE_EVENT],
            [
                'labels' => [
                    'name' => __('Event Categories', 'dizzy-events-manager'),
                    'singular_name' => __('Event Category', 'dizzy-events-manager'),
                    'search_items' => __('Search Event Categories', 'dizzy-events-manager'),
                    'all_items' => __('All Event Categories', 'dizzy-events-manager'),
                    'edit_item' => __('Edit Event Category', 'dizzy-events-manager'),
                    'add_new_item' => __('Add Event Category', 'dizzy-events-manager'),
                ],
                'public' => true,
                'hierarchical' => true,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'event-category'],
            ]
        );

        register_taxonomy(
            Config::TAX_ARTIST,
            [Config::POST_TYPE_EVENT],
            [
                'labels' => [
                    'name' => __('Artists', 'dizzy-events-manager'),
                    'singular_name' => __('Artist', 'dizzy-events-manager'),
                    'search_items' => __('Search Artists', 'dizzy-events-manager'),
                    'all_items' => __('All Artists', 'dizzy-events-manager'),
                    'edit_item' => __('Edit Artist', 'dizzy-events-manager'),
                    'add_new_item' => __('Add Artist', 'dizzy-events-manager'),
                ],
                'public' => true,
                'hierarchical' => false,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'event-artist'],
            ]
        );

        register_taxonomy(
            Config::TAX_VENUE,
            [Config::POST_TYPE_EVENT],
            [
                'labels' => [
                    'name' => __('Venues', 'dizzy-events-manager'),
                    'singular_name' => __('Venue', 'dizzy-events-manager'),
                    'search_items' => __('Search Venues', 'dizzy-events-manager'),
                    'all_items' => __('All Venues', 'dizzy-events-manager'),
                    'edit_item' => __('Edit Venue', 'dizzy-events-manager'),
                    'add_new_item' => __('Add Venue', 'dizzy-events-manager'),
                ],
                'public' => true,
                'hierarchical' => true,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'rewrite' => ['slug' => 'event-venue'],
            ]
        );
    }

    private function migrateLegacyRelations(): void
    {
        if (get_option(self::RELATION_MIGRATION_OPTION, '') === '1') {
            return;
        }

        $eventIds = get_posts([
            'post_type' => Config::POST_TYPE_EVENT,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        $succeeded = true;

        foreach ($eventIds as $eventId) {
            $eventId = (int) $eventId;
            $artist = trim((string) get_post_meta($eventId, '_dizzy_artist', true));
            $venue = trim((string) get_post_meta($eventId, '_dizzy_venue', true));

            if ($artist !== '' && ! has_term('', Config::TAX_ARTIST, $eventId)) {
                $artists = array_values(array_filter(array_map('trim', explode(',', $artist))));
                $result = wp_set_object_terms($eventId, $artists, Config::TAX_ARTIST);
                $succeeded = $succeeded && ! is_wp_error($result);
            }

            if ($venue !== '' && ! has_term('', Config::TAX_VENUE, $eventId)) {
                $result = wp_set_object_terms($eventId, [$venue], Config::TAX_VENUE);

                if (is_wp_error($result)) {
                    $succeeded = false;
                    continue;
                }

                $termId = (int) ($result[0] ?? 0);

                if ($termId > 0) {
                    $address = trim((string) get_post_meta($eventId, '_dizzy_address', true));
                    $mapsUrl = trim((string) get_post_meta($eventId, '_dizzy_maps_url', true));

                    if ($address !== '' && get_term_meta($termId, '_dizzy_address', true) === '') {
                        update_term_meta($termId, '_dizzy_address', $address);
                    }

                    if ($mapsUrl !== '' && get_term_meta($termId, '_dizzy_maps_url', true) === '') {
                        update_term_meta($termId, '_dizzy_maps_url', $mapsUrl);
                    }
                }
            }
        }

        if ($succeeded) {
            flush_rewrite_rules(false);
            update_option(self::RELATION_MIGRATION_OPTION, '1', false);
        }
    }
}
