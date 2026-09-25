<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

/**
 * Loads the shipped en/de/it/fr widget string catalog.
 */
final class WidgetStringCatalog
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $strings = null;

    public function get(string $key, string $language, string $fallback = ''): string
    {
        $catalog = self::all();
        $language = WidgetLanguage::isSupported($language) ? strtolower($language) : 'en';

        if (isset($catalog[$language][$key]) && $catalog[$language][$key] !== '') {
            return $catalog[$language][$key];
        }

        if (isset($catalog['en'][$key]) && $catalog['en'][$key] !== '') {
            return $catalog['en'][$key];
        }

        return $fallback !== '' ? $fallback : $key;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        if (self::$strings !== null) {
            return self::$strings;
        }

        $path = CASABLANCA_BOOKING_PATH . 'resources/i18n/widget-strings.php';
        /** @var array<string, array<string, string>> $loaded */
        $loaded = is_readable($path) ? (require $path) : [];
        self::$strings = is_array($loaded) ? $loaded : [];

        return self::$strings;
    }
}
