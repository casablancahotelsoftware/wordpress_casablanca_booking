<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Utility;

final class ThemeCssSanitizer
{
    public static function sanitize(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        return str_replace(['</style', '<script'], '', $css);
    }
}
