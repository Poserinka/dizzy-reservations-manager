<?php

declare(strict_types=1);

namespace Dizzy\Reservations\Database;

defined('ABSPATH') || exit;

final class Migrations
{
    private const VERSION = '1.0.0';

    public static function run(): void
    {
        if (version_compare((string) get_option('dizzy_reservations_db_version', '0'), self::VERSION, '>=')) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'dizzy_event_reservations';
        $capacities = $wpdb->prefix . 'dizzy_reservation_capacities';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            occurrence_id bigint(20) unsigned NOT NULL,
            name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            phone varchar(64) NULL,
            guests int(11) unsigned NOT NULL DEFAULT 1,
            status varchar(32) NOT NULL DEFAULT 'pending',
            checked_in_at datetime NULL,
            checked_in_by bigint(20) unsigned NULL,
            notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY occurrence_id (occurrence_id),
            KEY status (status),
            KEY checked_in_at (checked_in_at),
            KEY email (email)
        ) {$charset};");
        dbDelta("CREATE TABLE {$capacities} (
            occurrence_id bigint(20) unsigned NOT NULL,
            capacity int(11) unsigned NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (occurrence_id)
        ) {$charset};");
        update_option('dizzy_reservations_db_version', self::VERSION);
    }
}
