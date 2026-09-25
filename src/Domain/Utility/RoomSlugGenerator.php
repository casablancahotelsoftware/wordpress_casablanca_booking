<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Utility;

final class RoomSlugGenerator
{
    public static function fromName(string $name): string
    {
        $slug = sanitize_title($name);

        return $slug !== '' ? $slug : 'item';
    }
}
