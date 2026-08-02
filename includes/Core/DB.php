<?php

declare(strict_types=1);

namespace Dizzy\Events\Core;

use wpdb;

defined('ABSPATH') || exit;

/**
 * Lightweight wrapper around the global wpdb instance.
 *
 * All direct database access should go through this class.
 *
 * @package Dizzy\Events\Core
 */
final class DB
{
    /**
     * Prevent instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Return the global wpdb instance.
     */
    public static function instance(): wpdb
    {
        global $wpdb;

        return $wpdb;
    }

    /**
     * Prepare a SQL statement.
     *
     * @param string            $query SQL query.
     * @param array<int, mixed> $args  Query arguments.
     */
    public static function prepare(string $query, array $args = []): string
    {
        if ($args === []) {
            return $query;
        }

        return self::instance()->prepare($query, $args);
    }

    /**
     * Insert a record.
     *
     * @param array<string, mixed> $data   Record data.
     * @param array<int, string>   $format Data formats.
     */
    public static function insert(
        string $table,
        array $data,
        array $format = []
    ): bool {
        return self::instance()->insert(
            $table,
            $data,
            $format
        ) !== false;
    }

    /**
     * Update records.
     *
     * @param array<string, mixed> $data        Record data.
     * @param array<string, mixed> $where       Update conditions.
     * @param array<int, string>   $format      Data formats.
     * @param array<int, string>   $whereFormat Condition formats.
     */
    public static function update(
        string $table,
        array $data,
        array $where,
        array $format = [],
        array $whereFormat = []
    ): bool {
        return self::instance()->update(
            $table,
            $data,
            $where,
            $format,
            $whereFormat
        ) !== false;
    }

    /**
     * Delete records.
     *
     * @param array<string, mixed> $where       Delete conditions.
     * @param array<int, string>   $whereFormat Condition formats.
     */
    public static function delete(
        string $table,
        array $where,
        array $whereFormat = []
    ): bool {
        return self::instance()->delete(
            $table,
            $where,
            $whereFormat
        ) !== false;
    }

    /**
     * Get a single row.
     */
    public static function getRow(
        string $query,
        array $args = []
    ): ?object {
        return self::instance()->get_row(
            self::prepare($query, $args)
        );
    }

    /**
     * Get multiple rows.
     *
     * An empty array is returned when WordPress reports a query failure.
     * The original database error remains available through lastError().
     *
     * @return array<object>
     */
    public static function getResults(
        string $query,
        array $args = []
    ): array {
        $results = self::instance()->get_results(
            self::prepare($query, $args)
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Get a single value.
     */
    public static function getVar(
        string $query,
        array $args = []
    ): mixed {
        return self::instance()->get_var(
            self::prepare($query, $args)
        );
    }

    /**
     * Get first column as an array.
     *
     * @return array<int, mixed>
     */
    public static function getColumn(
        string $query,
        array $args = []
    ): array {
        return self::instance()->get_col(
            self::prepare($query, $args)
        );
    }

    /**
     * Return whether at least one row exists.
     */
    public static function exists(
        string $query,
        array $args = []
    ): bool {
        return self::getVar($query, $args) !== null;
    }

    /**
     * Execute SQL.
     */
    public static function query(
        string $query,
        array $args = []
    ): int|false {
        return self::instance()->query(
            self::prepare($query, $args)
        );
    }

    /**
     * Get the last insert ID.
     */
    public static function insertId(): int
    {
        return (int) self::instance()->insert_id;
    }

    /**
     * Get the last database error.
     */
    public static function lastError(): string
    {
        return self::instance()->last_error;
    }

    /**
     * Get the number of affected rows.
     */
    public static function rowsAffected(): int
    {
        return (int) self::instance()->rows_affected;
    }
}
