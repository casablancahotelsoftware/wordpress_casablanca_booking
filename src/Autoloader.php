<?php

declare(strict_types=1);

namespace Casablanca\Booking;

final class Autoloader
{
    private static string $baseDir = '';

    public static function register(string $baseDir): void
    {
        self::$baseDir = rtrim($baseDir, '/') . '/';
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (! str_starts_with($class, 'Casablanca\\Booking\\')) {
            return;
        }

        $relative = substr($class, strlen('Casablanca\\Booking\\'));
        $path = self::$baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($path)) {
            require_once $path;
        }
    }
}
