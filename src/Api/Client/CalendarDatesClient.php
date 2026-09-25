<?php

declare(strict_types=1);

namespace Casablanca\Booking\Api\Client;

use Casablanca\Booking\Api\CasablancaHttpClient;
use Casablanca\Booking\Domain\Dto\CalendarDateDto;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use DateTimeImmutable;
use DateTimeInterface;

final class CalendarDatesClient
{
    public function __construct(
        private readonly CasablancaHttpClient $http,
        private readonly SiteConfigurationDto $config
    ) {}

    /**
     * @param RoomOccupancyDto[] $roomOccupancies
     * @param array<string, array<int, string>>|null $stayFilter
     * @return CalendarDateDto[]
     */
    public function fetchMonth(
        DateTimeInterface $monthAnchor,
        array $roomOccupancies,
        ?array $stayFilter = null,
        ?string $culture = null
    ): array {
        $occupancies = [];
        foreach ($roomOccupancies as $occupancy) {
            $occupancies[] = $occupancy->toArray();
        }

        $body = ['roomOccupancies' => $occupancies];
        if ($stayFilter !== null) {
            $body['stayFilter'] = $stayFilter;
        }

        $response = $this->http->postJson('/calendar-dates', $body, [
            'month' => $monthAnchor->format('Y-m-01'),
            'culture' => $culture ?? $this->config->defaultCulture,
        ]);

        $items = CasablancaHttpClient::isList($response) ? $response : (array) ($response['values'] ?? []);
        $result = [];
        foreach ($items as $item) {
            $result[] = CalendarDateDto::fromArray((array) $item);
        }

        return $result;
    }

    /**
     * @param RoomOccupancyDto[] $roomOccupancies
     * @return iterable<int, CalendarDateDto>
     */
    public function streamRange(
        DateTimeInterface $from,
        DateTimeInterface $until,
        array $roomOccupancies,
        ?array $stayFilter = null,
        ?string $culture = null
    ): iterable {
        $cursor = new DateTimeImmutable($from->format('Y-m-01'));
        $end = new DateTimeImmutable($until->format('Y-m-01'));

        while ($cursor <= $end) {
            foreach ($this->fetchMonth($cursor, $roomOccupancies, $stayFilter, $culture) as $dto) {
                yield $dto;
            }
            $cursor = $cursor->modify('+1 month');
        }
    }
}
