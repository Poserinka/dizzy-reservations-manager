<?php

declare(strict_types=1);

namespace Dizzy\Events\Database;

defined('ABSPATH') || exit;

/**
 * Database schema definitions.
 *
 * @package Dizzy\Events\Database
 */
final class Schema
{
    public static function occurrences(): string
    {
        return "
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        start_datetime datetime NOT NULL,
        end_datetime datetime NULL,
        capacity int(11) unsigned NULL,
        all_day tinyint(1) NOT NULL DEFAULT 0,
        timezone varchar(64) NOT NULL DEFAULT 'Europe/Amsterdam',
        sort_order int(11) NOT NULL DEFAULT 0,
        status varchar(32) NOT NULL DEFAULT 'publish',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id),
        KEY start_datetime (start_datetime),
        KEY sort_order (sort_order),
        KEY status (status),
        KEY event_status_start (event_id, status, start_datetime),
        KEY event_status_end (event_id, status, end_datetime),
        KEY status_start_event (status, start_datetime, event_id),
        KEY status_end_event (status, end_datetime, event_id)
        ";
    }

    public static function reservations(): string
    {
        return "
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        occurrence_id bigint(20) unsigned NULL,
        name varchar(190) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(64) NULL,
        guests int(11) NOT NULL DEFAULT 1,
        status varchar(32) NOT NULL DEFAULT 'pending',
        checked_in_at datetime NULL,
        checked_in_by bigint(20) unsigned NULL,
        notes text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY occurrence_id (occurrence_id),
        KEY status (status),
        KEY checked_in_at (checked_in_at),
        KEY email (email)
        ";
    }

    public static function posters(): string
    {
        return "
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        attachment_id bigint(20) unsigned NULL,
        prompt text NULL,
        image_url text NULL,
        provider varchar(64) NOT NULL DEFAULT 'ai',
        status varchar(32) NOT NULL DEFAULT 'draft',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY attachment_id (attachment_id),
        KEY status (status)
        ";
    }
}

