<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$casablanca_booking_tables = [
    $wpdb->prefix . 'casablanca_configuration',
    $wpdb->prefix . 'casablanca_availability',
    $wpdb->prefix . 'casablanca_roomtype',
    $wpdb->prefix . 'casablanca_rate',
    $wpdb->prefix . 'casablanca_synclog',
];

foreach ($casablanca_booking_tables as $casablanca_booking_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall drops trusted plugin tables built from $wpdb->prefix + fixed names.
    $wpdb->query("DROP TABLE IF EXISTS {$casablanca_booking_table}");
}

delete_option('casablanca_booking_db_version');
wp_clear_scheduled_hook(\Casablanca\Booking\Sync\CronScheduler::HOOK);
