<?php

declare(strict_types=1);

namespace Casablanca\Booking\Infrastructure;

final class SiteIdentifier
{
    public static function current(): string
    {
        return (string) get_current_blog_id();
    }
}
