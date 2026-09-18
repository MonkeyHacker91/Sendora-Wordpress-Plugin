<?php
/**
 * Uninstall cleanup for Sendora.
 *
 * @package Sendora
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('sendora_settings');
delete_option('sendora_connection_status');
delete_transient('sendora_flows_cache');

global $wpdb;

$sendora_table = $wpdb->prefix . 'sendora_logs';
if (preg_match('/^[A-Za-z0-9_]+$/', $sendora_table)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup only.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $sendora_table));
}
