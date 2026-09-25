<?php

declare(strict_types=1);

namespace Casablanca\Booking\Infrastructure;

final class Activator
{
    public static function activate(): void
    {
        self::createTables();
        update_option('casablanca_booking_db_version', CASABLANCA_BOOKING_VERSION);
        (new \Casablanca\Booking\Sync\CronScheduler())->ensureDailySyncScheduled();
        (new \Casablanca\Booking\Frontend\DetailRewrite())->flush();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(\Casablanca\Booking\Sync\CronScheduler::HOOK);
        flush_rewrite_rules(false);
    }

    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}casablanca_configuration (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_identifier varchar(100) NOT NULL DEFAULT '',
            tenant_id varchar(64) NOT NULL DEFAULT '',
            url_friendly_ibe_context_id varchar(100) NOT NULL DEFAULT 'bookingengine',
            api_key_encrypted longtext NULL,
            api_base_url varchar(255) NOT NULL DEFAULT 'https://api.casablanca.at',
            ibe_base_url varchar(255) NOT NULL DEFAULT 'https://bookingengine.casablanca.at',
            ibe_link_style varchar(30) NOT NULL DEFAULT 'full_path',
            use_custom_ibe_domain tinyint(1) NOT NULL DEFAULT 0,
            service_path varchar(50) NOT NULL DEFAULT 'ibe',
            default_culture varchar(10) NOT NULL DEFAULT 'de',
            sync_range_days int(11) NOT NULL DEFAULT 365,
            sync_chunk_days int(11) NOT NULL DEFAULT 31,
            pagination_top int(11) NOT NULL DEFAULT 100,
            default_adults int(4) NOT NULL DEFAULT 2,
            default_children_ages text NULL,
            connection_status varchar(20) NOT NULL DEFAULT 'unknown',
            connection_checked_at bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            connection_message text NULL,
            theme_css longtext NULL,
            created_at bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            updated_at bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY site_identity (site_identifier)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}casablanca_availability (
            site_identifier varchar(100) NOT NULL DEFAULT '',
            tenant_id varchar(64) NOT NULL DEFAULT '',
            ibe_context_id varchar(100) NOT NULL DEFAULT '',
            room_type_id varchar(100) NOT NULL DEFAULT '',
            rate_id varchar(100) NOT NULL DEFAULT '',
            effective_date date NOT NULL DEFAULT '1970-01-01',
            from_price decimal(10,2) DEFAULT NULL,
            currency varchar(3) NOT NULL DEFAULT 'EUR',
            is_available tinyint(1) NOT NULL DEFAULT 0,
            is_arrival_allowed tinyint(1) NOT NULL DEFAULT 0,
            is_departure_allowed tinyint(1) NOT NULL DEFAULT 0,
            min_length_of_stay int(4) NOT NULL DEFAULT 0,
            max_length_of_stay int(4) NOT NULL DEFAULT 0,
            bookable_nights text NULL,
            bookable_nights_with_packages text NULL,
            restrictions text NULL,
            previous_day_blocked tinyint(1) NOT NULL DEFAULT 0,
            next_day_blocked tinyint(1) NOT NULL DEFAULT 0,
            data_hash varchar(64) NOT NULL DEFAULT '',
            PRIMARY KEY (site_identifier, room_type_id, rate_id, effective_date),
            KEY ari_lookup (site_identifier, room_type_id, effective_date),
            KEY ari_date (effective_date)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}casablanca_roomtype (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_identifier varchar(100) NOT NULL DEFAULT '',
            tenant_id varchar(64) NOT NULL DEFAULT '',
            ibe_context_id varchar(100) NOT NULL DEFAULT '',
            room_type_id varchar(100) NOT NULL DEFAULT '',
            company_id varchar(64) NOT NULL DEFAULT '',
            name varchar(255) NOT NULL DEFAULT '',
            slug varchar(255) NOT NULL DEFAULT '',
            description longtext NULL,
            short_description longtext NULL,
            image_url varchar(255) NOT NULL DEFAULT '',
            images longtext NULL,
            standard_occupancy int(4) NOT NULL DEFAULT 2,
            min_occupancy int(4) NOT NULL DEFAULT 1,
            max_occupancy int(4) NOT NULL DEFAULT 4,
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY room_type_identity (site_identifier, room_type_id),
            KEY site_lookup (site_identifier),
            KEY site_slug_lookup (site_identifier, slug)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}casablanca_rate (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_identifier varchar(100) NOT NULL DEFAULT '',
            tenant_id varchar(64) NOT NULL DEFAULT '',
            ibe_context_id varchar(100) NOT NULL DEFAULT '',
            rate_id varchar(100) NOT NULL DEFAULT '',
            name varchar(255) NOT NULL DEFAULT '',
            slug varchar(255) NOT NULL DEFAULT '',
            description longtext NULL,
            short_description longtext NULL,
            image_url varchar(255) NOT NULL DEFAULT '',
            images longtext NULL,
            is_package tinyint(1) NOT NULL DEFAULT 0,
            catering_type varchar(50) NOT NULL DEFAULT '',
            sort_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY rate_identity (site_identifier, rate_id),
            KEY site_package (site_identifier, is_package),
            KEY package_slug (site_identifier, slug)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}casablanca_synclog (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_identifier varchar(100) NOT NULL DEFAULT '',
            started_at bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            finished_at bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT '',
            rows_written int(11) NOT NULL DEFAULT 0,
            rows_changed int(11) NOT NULL DEFAULT 0,
            cache_tags_flushed int(11) NOT NULL DEFAULT 0,
            message text NULL,
            PRIMARY KEY (id),
            KEY site_started (site_identifier, started_at)
        ) {$charset};");
    }
}
