<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

final class AssetManager
{
    public const STYLE_HANDLE = 'casablanca-booking-widget';
    public const SCRIPT_HANDLE = 'casablanca-booking-widget';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('admin_enqueue_scripts', [$this, 'registerAdminAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }

    public function enqueueBlockEditorAssets(): void
    {
        $this->registerAssets();
        $this->enqueueWidgetAssets(true);
    }

    public function registerAssets(): void
    {
        wp_register_style(
            self::STYLE_HANDLE,
            CASABLANCA_BOOKING_URL . 'assets/css/widget.css',
            [],
            CASABLANCA_BOOKING_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            CASABLANCA_BOOKING_URL . 'assets/js/widget.js',
            [],
            CASABLANCA_BOOKING_VERSION,
            true
        );

        $themeCss = get_theme_file_path('casablanca-booking.css');
        if (file_exists($themeCss)) {
            wp_register_style(
                'casablanca-booking-theme',
                get_theme_file_uri('casablanca-booking.css'),
                [self::STYLE_HANDLE],
                (string) filemtime($themeCss)
            );
        }
    }

    public function enqueueWidgetAssets(bool $includeCss = true): void
    {
        if ($includeCss) {
            wp_enqueue_style(self::STYLE_HANDLE);
            if (wp_style_is('casablanca-booking-theme', 'registered')) {
                wp_enqueue_style('casablanca-booking-theme');
            }
        }

        wp_enqueue_script(self::SCRIPT_HANDLE);
    }

    public function registerAdminAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_casablanca-booking') {
            return;
        }

        wp_enqueue_style(
            'casablanca-booking-admin',
            CASABLANCA_BOOKING_URL . 'assets/css/backend.css',
            [],
            CASABLANCA_BOOKING_VERSION
        );

        wp_enqueue_script(
            'casablanca-booking-admin',
            CASABLANCA_BOOKING_URL . 'assets/js/backend-config.js',
            [],
            CASABLANCA_BOOKING_VERSION,
            true
        );
    }
}
