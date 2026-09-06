<?php

declare(strict_types=1);

namespace Dizzy\Reservations\Database;

defined('ABSPATH') || exit;

final class Migrations
{
    private const VERSION = '3.9.0';

    public static function run(): void
    {
        if (version_compare((string) get_option('dizzy_reservations_db_version', '0'), self::VERSION, '>=')) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $reservations = $wpdb->prefix . 'dizzy_event_reservations';
        $tables = $wpdb->prefix . 'dizzy_reservation_tables';
        $holds = $wpdb->prefix . 'dizzy_reservation_table_holds';

        dbDelta("CREATE TABLE {$reservations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL DEFAULT 0,
            occurrence_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            phone varchar(64) NULL,
            reservation_date date NULL,
            reservation_time time NULL,
            guests int(11) unsigned NOT NULL DEFAULT 1,
            table_id bigint(20) unsigned NOT NULL DEFAULT 0,
            duration_minutes int(11) unsigned NOT NULL DEFAULT 120,
            status varchar(32) NOT NULL DEFAULT 'pending',
            notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY reservation_slot (reservation_date,reservation_time),
            KEY table_slot (table_id,reservation_date,reservation_time),
            KEY status (status),
            KEY email (email)
        ) {$charset};");

        dbDelta("CREATE TABLE {$tables} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(32) NOT NULL,
            label varchar(100) NOT NULL,
            capacity int(11) unsigned NOT NULL DEFAULT 2,
            shape varchar(20) NOT NULL DEFAULT 'square',
            pos_x decimal(7,3) NOT NULL DEFAULT 10,
            pos_y decimal(7,3) NOT NULL DEFAULT 10,
            width decimal(7,3) NOT NULL DEFAULT 7,
            height decimal(7,3) NOT NULL DEFAULT 7,
            rotation decimal(7,2) NOT NULL DEFAULT 0,
            active tinyint(1) unsigned NOT NULL DEFAULT 1,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active (active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$holds} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            table_id bigint(20) unsigned NOT NULL,
            session_token varchar(64) NOT NULL,
            start_at datetime NOT NULL,
            end_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY table_slot (table_id,start_at,end_at),
            KEY session_token (session_token),
            KEY expires_at (expires_at)
        ) {$charset};");

        self::seedTables($tables);

        update_option('dizzy_reservations_db_version', self::VERSION);
    }

    private static function seedTables(string $table): void
    {
        global $wpdb;
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") > 0) return;

        $items = [
            ['A8',4,'square',8,8,7,7],['A7',4,'square',8,17,7,7],['A6',4,'square',8,26,7,7],['A5',4,'square',8,35,7,7],
            ['A4',4,'square',8,44,7,7],['A3',4,'square',8,53,7,7],['A2',4,'square',8,62,7,7],['A1',4,'square',8,71,7,7],['A0',2,'square',4,86,6,9],
            ['D1',2,'round',31,24,6,6],['D2',2,'round',39,24,6,6],['D3',2,'round',47,24,6,6],['D4',2,'round',55,24,6,6],['D5',2,'round',63,24,6,6],
            ['B3',6,'round',27,38,10,10],['B2',6,'round',27,54,10,10],['B1',6,'round',27,70,10,10],['B0',8,'rectangle',21,90,16,7],
            ['C2',4,'square',44,43,9,7],['C3',4,'square',59,43,9,7],['C1',4,'square',44,65,9,7],['C4',4,'square',59,65,9,7],
            ['E1',2,'square',42,86,7,6],['E2',2,'square',51,86,7,6],['F1',2,'square',75,89,6,8],['F2',6,'rectangle',85,89,10,8],
        ];
        $now = current_time('mysql', true);
        foreach ($items as $index => $item) {
            [$code,$capacity,$shape,$x,$y,$width,$height] = $item;
            $wpdb->insert($table, [
                'code'=>$code,'label'=>$code,'capacity'=>$capacity,'shape'=>$shape,
                'pos_x'=>$x,'pos_y'=>$y,'width'=>$width,'height'=>$height,'rotation'=>0,
                'active'=>1,'sort_order'=>$index,'created_at'=>$now,'updated_at'=>$now,
            ]);
        }
    }
}
