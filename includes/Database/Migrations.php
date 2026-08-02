<?php

declare(strict_types=1);

namespace Dizzy\Events\Database;

defined('ABSPATH') || exit;

/**
 * Handles database migrations.
 *
 * @package Dizzy\Events\Database
 */
final class Migrations
{
    /**
     * Current database version.
     */
    private const VERSION = '1.0.7';

    /**
     * Option key.
     */
    private const OPTION = 'dizzy_events_db_version';

    /**
     * Run migrations.
     */
    public static function run(): void
    {
        $installed = (string) get_option(
            self::OPTION,
            '0.0.0'
        );

        if (version_compare($installed, self::VERSION, '>=')) {
            return;
        }

        self::createOccurrencesTable();
        self::createReservationsTable();
        self::createPostersTable();

        update_option(
            self::OPTION,
            self::VERSION
        );
    }

    private static function createOccurrencesTable(): void
    {
        global $wpdb;

        self::createTable(
            $wpdb->prefix . 'dizzy_event_occurrences',
            Schema::occurrences()
        );
    }

    private static function createReservationsTable(): void
    {
        global $wpdb;

        self::createTable(
            $wpdb->prefix . 'dizzy_event_reservations',
            Schema::reservations()
        );
    }

    private static function createPostersTable(): void
    {
        global $wpdb;

        self::createTable(
            $wpdb->prefix . 'dizzy_event_posters',
            Schema::posters()
        );
    }

    private static function createTable(string $table, string $schema): void
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $sql = sprintf(
            "CREATE TABLE %s (%s) %s;",
            $table,
            $schema,
            $charset
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}

