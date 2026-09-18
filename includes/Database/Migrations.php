<?php

declare(strict_types=1);

namespace Dizzy\Reservations\Database;

defined('ABSPATH') || exit;

final class Migrations
{
    private const VERSION = '3.11.2';

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
            reservation_type varchar(32) NOT NULL DEFAULT 'standard',
            ticket_status varchar(32) NOT NULL DEFAULT 'none',
            ticket_quantity int(11) unsigned NOT NULL DEFAULT 0,
            ticket_price decimal(10,2) NOT NULL DEFAULT 0,
            event_title varchar(255) NULL,
            concert_start datetime NULL,
            concert_end datetime NULL,
            ticket_url text NULL,
            ticket_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            payment_expires_at datetime NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY reservation_slot (reservation_date,reservation_time),
            KEY table_slot (table_id,reservation_date,reservation_time),
            KEY status (status),
            KEY email (email),
            KEY ticket_order_id (ticket_order_id)
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
            ['A8',4,'square',6.167,5,5.167,6.333],['A7',4,'square',6.167,13.5,5.167,6.167],['A6',4,'square',6.167,21.833,5.167,6.333],['A5',4,'square',6.167,30.333,5.167,6.333],
            ['A4',4,'square',6.167,38.833,5.167,6.333],['A3',4,'square',6.167,47.333,5.167,6.333],['A2',4,'square',6.167,55.833,5.167,6.167],['A1',4,'square',6.167,64.167,5.167,6.333],['A0',2,'square',2,81.167,5,7.833],
            ['D1',2,'round',30.167,20.667,5.167,5.167],['D2',2,'round',37.833,20.667,5.167,5.167],['D3',2,'round',45.833,20.667,5.167,5.167],['D4',2,'round',53.667,20.667,5.167,5.167],['D5',2,'round',61.5,20.667,5.167,5.167],
            ['B3',6,'round',26,36.667,7.833,7.833],['B2',6,'round',26,52.167,7.833,7.667],['B1',6,'round',26,67.5,7.833,7.833],['B0',8,'rectangle',20.333,90,15.167,5.5],
            ['C2',4,'square',42.667,42.333,8.333,5.667],['C3',4,'square',57.667,42.333,8.167,5.667],['C1',4,'square',42.667,63.333,8.333,5.667],['C4',4,'square',57.667,63.333,8.167,5.667],
            ['E1',2,'square',40.5,82.5,5.167,5.333],['E2',2,'square',50,82.5,5.167,5.333],['F1',2,'square',73.667,86.167,5.667,7.333],['F2',6,'rectangle',83.167,86.167,10.333,7.333],
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
