<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Autoloader.php';

Casablanca\Booking\Autoloader::register(dirname(__DIR__) . '/src');

if (! function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/[^a-z0-9\-]+/', '-', $title) ?? '';

        return trim($title, '-');
    }
}
