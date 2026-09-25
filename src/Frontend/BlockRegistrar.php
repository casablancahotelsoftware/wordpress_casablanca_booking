<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

final class BlockRegistrar
{
    /** @var array<string, string> */
    private const BLOCKS = [
        'search-bar' => 'search_bar',
        'calendar' => 'calendar',
        'room-types' => 'room_types',
        'packages' => 'packages',
        'room-detail' => 'room_detail',
        'package-detail' => 'package_detail',
        'price-teaser' => 'price_teaser',
    ];

    public const EDITOR_SCRIPT_HANDLE = 'casablanca-booking-blocks';

    public function register(): void
    {
        add_filter('block_categories_all', [$this, 'registerCategory'], 10, 1);
        add_action('init', [$this, 'registerBlocks']);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public function registerCategory(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === 'casablanca') {
                return $categories;
            }
        }

        array_unshift($categories, [
            'slug' => 'casablanca',
            'title' => 'CASABLANCA',
            'icon' => 'calendar-alt',
        ]);

        return $categories;
    }

    public function registerBlocks(): void
    {
        wp_register_script(
            self::EDITOR_SCRIPT_HANDLE,
            CASABLANCA_BOOKING_URL . 'assets/js/blocks-editor.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-server-side-render',
                'wp-i18n',
                'wp-api-fetch',
                'wp-data',
                'wp-core-data',
            ],
            CASABLANCA_BOOKING_VERSION,
            true
        );

        foreach (self::BLOCKS as $slug => $widget) {
            $dir = CASABLANCA_BOOKING_PATH . 'blocks/' . $slug;
            if (! is_dir($dir)) {
                continue;
            }

            register_block_type($dir, [
                'render_callback' => function (array $attributes) use ($widget): string {
                    return (new WidgetRenderer())->render($widget, $attributes);
                },
            ]);
        }
    }
}
