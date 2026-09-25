<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Utility;

use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;

final class OccupancyParser
{
    /**
     * @param array<string, mixed> $input
     * @return RoomOccupancyDto[]
     */
    public static function parseRooms(array $input): array
    {
        $rooms = [];
        $rawRooms = $input['rooms'] ?? [];

        if (! is_array($rawRooms)) {
            return [new RoomOccupancyDto(2)];
        }

        foreach ($rawRooms as $room) {
            if (! is_array($room)) {
                continue;
            }

            $adults = max(1, (int) ($room['adults'] ?? 2));
            $ages = [];

            if (isset($room['childAge']) && is_array($room['childAge'])) {
                foreach ($room['childAge'] as $age) {
                    $ages[] = max(0, min(17, (int) $age));
                }
            } elseif (isset($room['childrenAges']) && is_string($room['childrenAges'])) {
                foreach (array_filter(array_map('trim', explode(',', $room['childrenAges']))) as $age) {
                    $ages[] = max(0, min(17, (int) $age));
                }
            } elseif (isset($room['children'])) {
                $count = max(0, (int) $room['children']);
                $ages = array_fill(0, $count, 0);
            }

            $rooms[] = new RoomOccupancyDto($adults, $ages);
        }

        return $rooms !== [] ? $rooms : [new RoomOccupancyDto(2)];
    }
}
