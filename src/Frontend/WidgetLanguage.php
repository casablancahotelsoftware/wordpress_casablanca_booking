<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

/**
 * Resolves block/shortcode language to a supported widget culture (en|de|it|fr).
 */
final class WidgetLanguage
{
    public const SUPPORTED = ['en', 'de', 'it', 'fr'];

    /**
     * @param string $setting Block attribute: '' / 'auto' / 'de' / 'en' / 'it' / 'fr'
     */
    public static function resolve(string $setting): string
    {
        $setting = strtolower(trim($setting));
        if (in_array($setting, self::SUPPORTED, true)) {
            return $setting;
        }

        $locale = function_exists('determine_locale')
            ? (string) determine_locale()
            : (function_exists('get_locale') ? (string) get_locale() : 'en_US');

        return self::fromLocale($locale);
    }

    public static function fromLocale(string $locale): string
    {
        $locale = strtolower(str_replace('-', '_', trim($locale)));
        $primary = explode('_', $locale, 2)[0] ?? 'en';

        return match ($primary) {
            'de' => 'de',
            'it' => 'it',
            'fr' => 'fr',
            default => 'en',
        };
    }

    public static function isSupported(string $code): bool
    {
        return in_array(strtolower(trim($code)), self::SUPPORTED, true);
    }
}
