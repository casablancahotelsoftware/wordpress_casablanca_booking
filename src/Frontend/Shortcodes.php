<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

final class Shortcodes
{
    public function register(): void
    {
        add_shortcode('casablanca_search_bar', [$this, 'searchBar']);
        add_shortcode('casablanca_calendar', [$this, 'calendar']);
        add_shortcode('casablanca_room_types', [$this, 'roomTypes']);
        add_shortcode('casablanca_packages', [$this, 'packages']);
        add_shortcode('casablanca_room_detail', [$this, 'roomDetail']);
        add_shortcode('casablanca_package_detail', [$this, 'packageDetail']);
        add_shortcode('casablanca_price_teaser', [$this, 'priceTeaser']);
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function searchBar($atts): string
    {
        return (new WidgetRenderer())->render('search_bar', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function calendar($atts): string
    {
        return (new WidgetRenderer())->render('calendar', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function roomTypes($atts): string
    {
        return (new WidgetRenderer())->render('room_types', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function packages($atts): string
    {
        return (new WidgetRenderer())->render('packages', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function roomDetail($atts): string
    {
        return (new WidgetRenderer())->render('room_detail', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function packageDetail($atts): string
    {
        return (new WidgetRenderer())->render('package_detail', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function priceTeaser($atts): string
    {
        return (new WidgetRenderer())->render('price_teaser', $this->parseAtts($atts));
    }

    /**
     * @param array<string, string>|string $atts
     * @return array<string, mixed>
     */
    private function parseAtts($atts): array
    {
        $parsed = shortcode_atts([], is_array($atts) ? $atts : [], '');
        $result = [];
        foreach (is_array($atts) ? $atts : (array) $atts as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (str_starts_with($key, 'labels_')) {
                $result['labels'][substr($key, 7)] = $value;
                continue;
            }
            $result[$key] = $this->castValue($value);
        }

        return array_merge($parsed, $result);
    }

    private function castValue(string $value): mixed
    {
        if ($value === 'true' || $value === '1') {
            return true;
        }
        if ($value === 'false' || $value === '0') {
            return false;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
