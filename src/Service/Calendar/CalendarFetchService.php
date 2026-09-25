<?php

declare(strict_types=1);

namespace Casablanca\Booking\Service\Calendar;

use Casablanca\Booking\Api\Client\CalendarDatesClient;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use DateTimeImmutable;
use DateTimeInterface;

final class CalendarFetchService
{
    public function __construct(
        private readonly CalendarDayPresenter $presenter = new CalendarDayPresenter()
    ) {}

    /**
     * @param RoomOccupancyDto[] $roomOccupancies
     * @param string[] $rateIds
     * @return array{months: array<string, array<int, array<string, mixed>>>}
     */
    public function fetchRange(
        CalendarDatesClient $client,
        SiteConfigurationDto $config,
        DateTimeInterface $from,
        DateTimeInterface $until,
        array $roomOccupancies,
        ?string $preselectedRoomTypeId = null,
        array $rateIds = [],
        ?string $culture = null
    ): array {
        if ($roomOccupancies === []) {
            $roomOccupancies = [$config->defaultOccupancy];
        }

        $stayFilter = $this->buildStayFilter($preselectedRoomTypeId, $rateIds);
        $months = [];
        $monthCursor = new DateTimeImmutable($from->format('Y-m-01'));
        $monthEnd = new DateTimeImmutable($until->format('Y-m-01'));
        $untilDate = $until->format('Y-m-d');

        while ($monthCursor <= $monthEnd) {
            $calendarDates = $client->fetchMonth($monthCursor, $roomOccupancies, $stayFilter, $culture);
            $monthKey = $monthCursor->format('Y-m');

            foreach ($calendarDates as $cal) {
                $dateStr = $cal->effectiveDate->format('Y-m-d');
                if ($dateStr < $from->format('Y-m-d') || $dateStr > $untilDate) {
                    continue;
                }
                $months[$monthKey][] = $this->presenter->present(
                    $cal,
                    $preselectedRoomTypeId ?? ''
                );
            }

            $monthCursor = $monthCursor->modify('+1 month');
        }

        ksort($months);

        return ['months' => $months];
    }

    /**
     * @param string[] $rateIds
     * @return array<string, mixed>|null
     */
    private function buildStayFilter(?string $roomTypeId, array $rateIds): ?array
    {
        $roomTypeId = $roomTypeId !== null ? trim($roomTypeId) : '';
        $rateIds = array_values(array_filter(array_map('trim', $rateIds), static fn (string $id): bool => $id !== ''));

        if ($roomTypeId === '' && $rateIds === []) {
            return null;
        }

        return [
            'companyIdentifiers' => [],
            'roomTypeIds' => $roomTypeId !== '' ? [$roomTypeId] : [],
            'rateIds' => $rateIds,
        ];
    }
}
