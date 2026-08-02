<?php

declare(strict_types=1);

namespace Dizzy\Events\Core;

defined('ABSPATH') || exit;

/**
 * Central configuration for Dizzy Events Manager.
 *
 * This class contains plugin-wide constants that should never be duplicated
 * throughout the codebase.
 *
 * @package Dizzy\Events\Core
 
 */
final class Config
{
    /**
     * Plugin text domain.
     */
    public const TEXT_DOMAIN = 'dizzy-events-manager';

    /**
     * REST API namespace.
     */
    public const REST_NAMESPACE = 'dizzy-events/v1';

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    public const OPTION_VERSION      = 'dizzy_events_version';
    public const OPTION_DB_VERSION   = 'dizzy_events_db_version';
    public const OPTION_SETTINGS     = 'dizzy_events_settings';
    public const OPTION_INSTALLED_AT = 'dizzy_events_installed_at';

    /*
    |--------------------------------------------------------------------------
    | Post Types
    |--------------------------------------------------------------------------
    */

    public const POST_TYPE_EVENT = 'dizzy_event';

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    */

    public const TAX_CATEGORY = 'dizzy_event_category';
    public const TAX_ARTIST   = 'dizzy_event_artist';
    public const TAX_VENUE    = 'dizzy_event_venue';
    public const TAX_TAG      = 'dizzy_event_tag';

    /*
    |--------------------------------------------------------------------------
    | Meta Keys
    |--------------------------------------------------------------------------
    */

    public const META_PRICE        = '_dizzy_price';
    public const META_CURRENCY     = '_dizzy_currency';
    public const META_TICKET_URL   = '_dizzy_ticket_url';
    public const META_SOURCE       = '_dizzy_source';
    public const META_SOURCE_ID    = '_dizzy_source_id';
    public const META_EXTERNAL_URL = '_dizzy_external_url';
    public const META_FEATURED     = '_dizzy_featured';
    public const META_TIMEZONE     = '_dizzy_timezone';
    public const META_CAPACITY     = '_dizzy_capacity';
    public const META_AGE_LIMIT    = '_dizzy_age_limit';
    public const META_STATUS       = '_dizzy_status';

    /*
    |--------------------------------------------------------------------------
    | Event Sources
    |--------------------------------------------------------------------------
    */

    public const SOURCE_MANUAL       = 'manual';
    public const SOURCE_FACEBOOK     = 'facebook';
    public const SOURCE_TICKETMASTER = 'ticketmaster';
    public const SOURCE_AI           = 'ai';
    public const SOURCE_ICS          = 'ics';
    public const SOURCE_CSV          = 'csv';

    /*
    |--------------------------------------------------------------------------
    | Cron Hooks
    |--------------------------------------------------------------------------
    */

    public const CRON_IMPORT_EVENTS = 'dizzy_events_import';
    public const CRON_DAILY         = 'dizzy_events_daily';
    public const CRON_CLEANUP       = 'dizzy_events_cleanup';

    /*
    |--------------------------------------------------------------------------
    | Capabilities
    |--------------------------------------------------------------------------
    */

    public const CAP_MANAGE_EVENTS = 'manage_dizzy_events';
    public const CAP_IMPORT_EVENTS = 'import_dizzy_events';

    /*
    |--------------------------------------------------------------------------
    | Table Keys
    |--------------------------------------------------------------------------
    |
    | These are logical identifiers only.
    | Database::tables() maps these keys to real table names.
    |
    */

    public const TABLE_OCCURRENCES = 'occurrences';
    public const TABLE_ARTISTS     = 'artists';
    public const TABLE_EVENT_ARTIST = 'event_artist';
    public const TABLE_LOGS        = 'logs';

    /*
    |--------------------------------------------------------------------------
    | Log Levels
    |--------------------------------------------------------------------------
    */

    public const LOG_DEBUG   = 'debug';
    public const LOG_INFO    = 'info';
    public const LOG_WARNING = 'warning';
    public const LOG_ERROR   = 'error';

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    public const DEFAULT_TIMEZONE = 'Europe/Amsterdam';

    public const DEFAULT_PAGE_SIZE = 20;

    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Returns all supported event sources.
     *
     * @return string[]
     */
    public static function sources(): array
    {
        return [
            self::SOURCE_MANUAL,
            self::SOURCE_FACEBOOK,
            self::SOURCE_TICKETMASTER,
            self::SOURCE_AI,
            self::SOURCE_ICS,
            self::SOURCE_CSV,
        ];
    }

    /**
     * Returns all supported log levels.
     *
     * @return string[]
     */
    public static function logLevels(): array
    {
        return [
            self::LOG_DEBUG,
            self::LOG_INFO,
            self::LOG_WARNING,
            self::LOG_ERROR,
        ];
    }

    /**
     * Returns all logical table keys.
     *
     * @return string[]
     */
    public static function tables(): array
    {
        return [
            self::TABLE_OCCURRENCES,
            self::TABLE_ARTISTS,
            self::TABLE_EVENT_ARTIST,
            self::TABLE_LOGS,
        ];
    }
}
