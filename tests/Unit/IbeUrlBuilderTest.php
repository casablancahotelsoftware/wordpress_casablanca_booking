<?php

declare(strict_types=1);

namespace Casablanca\Booking\Tests\Unit;

use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\IbeLinkStyle;
use Casablanca\Booking\Domain\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IbeUrlBuilderTest extends TestCase
{
    private IbeUrlBuilder $subject;

    protected function setUp(): void
    {
        $this->subject = new IbeUrlBuilder();
    }

    public function testBuildPathFullPath(): void
    {
        $config = $this->createConfig(IbeLinkStyle::FULL_PATH);
        self::assertSame(
            'https://booking.example.com/de/tenant-uuid/wellness-days',
            $this->subject->buildPath($config)
        );
    }

    public function testBuildPathCultureSpace(): void
    {
        $config = $this->createConfig(IbeLinkStyle::CULTURE_SPACE);
        self::assertSame(
            'https://booking.example.com/de/wellness-days',
            $this->subject->buildPath($config)
        );
    }

    public function testBuildQueryParamsMultiRoomWithChildren(): void
    {
        $params = $this->subject->buildQueryParams(
            new DateTimeImmutable('2026-10-05'),
            new DateTimeImmutable('2026-10-09'),
            [new RoomOccupancyDto(2, [5]), new RoomOccupancyDto(1)]
        );

        self::assertSame(2, $params['numberOfRooms']);
        self::assertSame(5, $params['rooms_0__children_0__age']);
    }

    private function createConfig(string $ibeLinkStyle): SiteConfigurationDto
    {
        return new SiteConfigurationDto(
            '1',
            'tenant-uuid',
            'wellness-days',
            'key',
            'https://api.casablanca.at',
            'https://booking.example.com',
            'de',
            365,
            31,
            100,
            new RoomOccupancyDto(2),
            'ibe',
            $ibeLinkStyle
        );
    }
}
