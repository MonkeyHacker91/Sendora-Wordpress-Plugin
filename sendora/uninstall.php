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
delete_option('sendora_onboarding_finished');

$sendora_transients = [
    'sendora_flows_cache',
    'sendora_templates_cache',
    'sendora_connections_cache',
    'sendora_waba_cache',
    'sendora_funnels_cache',
    'sendora_stages_cache',
    'sendora_meta_tpl_default',
];

foreach ($sendora_transients as $sendora_transient) {
    delete_transient($sendora_transient);
}

global $wpdb;

// Clear any meta-template caches keyed by WABA id.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall only.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_sendora_meta_tpl_') . '%',
        $wpdb->esc_like('_transient_timeout_sendora_meta_tpl_') . '%'
    )
);

$sendora_table = $wpdb->prefix . 'sendora_logs';
if (preg_match('/^[A-Za-z0-9_]+$/', $sendora_table)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup only.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $sendora_table));
}
