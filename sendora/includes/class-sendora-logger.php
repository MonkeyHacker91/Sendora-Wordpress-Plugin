<?php
/**
 * Local sync log storage.
 *
 * @package Sendora
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Sendora_Logger
{
    private const CONTEXT_MAX_BYTES = 4096;
    private const ROW_CAP = 1000;
    private const TABLE_SUFFIX = 'sendora_logs';

    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function install_table(): void
    {
        global $wpdb;

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            source varchar(32) NOT NULL,
            level varchar(16) NOT NULL,
            message text NOT NULL,
            context longtext NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log(string $source, string $level, string $message, array $context = []): void
    {
        global $wpdb;

        $source = sanitize_key(substr($source, 0, 32));
        $level = sanitize_key(substr($level, 0, 16));
        $message = wp_strip_all_tags($message);
        $context_json = self::encode_context($context);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional local log write.
        $wpdb->insert(
            self::table_name(),
            [
                'created_at' => current_time('mysql', true),
                'source' => $source,
                'level' => $level,
                'message' => $message,
                'context' => $context_json,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        self::prune_old_rows();
    }

    /**
     * @return array<int, array{id: int, created_at: string, source: string, level: string, message: string, context: array<string, mixed>}>
     */
    public static function list(int $limit = 100): array
    {
        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = self::safe_table_name();

        if ($table === '') {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i escapes the validated table identifier (WP 6.2+).
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, created_at, source, level, message, context FROM %i ORDER BY id DESC LIMIT %d',
                $table,
                $limit
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $context = [];
            if (!empty($row['context']) && is_string($row['context'])) {
                $decoded = json_decode($row['context'], true);
                $context = is_array($decoded) ? $decoded : [];
            }

            $entries[] = [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'source' => (string) ($row['source'] ?? ''),
                'level' => (string) ($row['level'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'context' => $context,
            ];
        }

        return $entries;
    }

    public static function clear(): void
    {
        global $wpdb;

        $table = self::safe_table_name();
        if ($table === '') {
            return;
        }

        // Prefer DELETE over TRUNCATE (TRUNCATE needs elevated privileges and is flagged by Plugin Check).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i escapes the validated table identifier (WP 6.2+).
        $wpdb->query($wpdb->prepare('DELETE FROM %i', $table));
    }

    public static function drop_table(): void
    {
        global $wpdb;

        $table = self::safe_table_name();
        if ($table === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup only.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function encode_context(array $context): string
    {
        $encoded = wp_json_encode($context);

        if (!is_string($encoded)) {
            return '{}';
        }

        if (strlen($encoded) <= self::CONTEXT_MAX_BYTES) {
            return $encoded;
        }

        return substr($encoded, 0, self::CONTEXT_MAX_BYTES) . '…';
    }

    private static function prune_old_rows(): void
    {
        global $wpdb;

        $table = self::safe_table_name();
        if ($table === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i escapes the validated table identifier (WP 6.2+).
        $count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(id) FROM %i', $table));

        if ($count <= self::ROW_CAP) {
            return;
        }

        $excess = $count - self::ROW_CAP;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- %i escapes the validated table identifier (WP 6.2+).
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i ORDER BY id ASC LIMIT %d',
                $table,
                $excess
            )
        );
    }

    /**
     * Return a safe table identifier (wpdb prefix + fixed suffix) or empty string.
     */
    private static function safe_table_name(): string
    {
        $table = self::table_name();

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return '';
        }

        return $table;
    }
}
