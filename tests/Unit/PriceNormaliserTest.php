<?php

declare(strict_types=1);

namespace Casablanca\Booking\Tests\Unit;

use Casablanca\Booking\Domain\Dto\CalendarDateDto;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Sync\PriceNormaliser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PriceNormaliserTest extends TestCase
{
    public function testRoomRowsUseEmptyRateId(): void
    {
        $config = new SiteConfigurationDto('1', 'tenant', 'space', 'key', 'https://api.casablanca.at', 'https://ibe.example.com', 'de', 365, 31, 100, new RoomOccupancyDto(2));
        $dto = new CalendarDateDto(
            new DateTimeImmutable('2026-10-01'),
            120.0,
            true,
            true,
            true,
            false,
            false,
            1,
            0,
            [1, 2],
            []
        );

        $rows = (new PriceNormaliser())->normaliseRoomPrices($config, [$dto], 'room-1');
        self::assertSame('', $rows[0]['rate_id']);
        self::assertSame('room-1', $rows[0]['room_type_id']);
    }
}
