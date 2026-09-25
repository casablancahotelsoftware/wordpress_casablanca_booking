<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Domain\Dto\CalendarDateDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;

final class PriceNormaliser
{
    /**
     * @param CalendarDateDto[] $calendarDates
     * @return array<int, array<string, mixed>>
     */
    public function normaliseRoomPrices(
        SiteConfigurationDto $config,
        array $calendarDates,
        string $roomTypeId
    ): array {
        return $this->normalise($config, $calendarDates, $roomTypeId, '');
    }

    /**
     * @param CalendarDateDto[] $calendarDates
     * @return array<int, array<string, mixed>>
     */
    public function normalisePackagePrices(
        SiteConfigurationDto $config,
        array $calendarDates,
        string $rateId
    ): array {
        return $this->normalise($config, $calendarDates, '', $rateId);
    }

    /**
     * @param CalendarDateDto[] $calendarDates
     * @return array<int, array<string, mixed>>
     */
    private function normalise(
        SiteConfigurationDto $config,
        array $calendarDates,
        string $roomTypeId,
        string $rateId
    ): array {
        $rows = [];

        foreach ($calendarDates as $cal) {
            $restrictions = [];
            if ($cal->minLengthOfStay > 1) {
                $restrictions[] = 'Restriktionen';
            }
            if ($cal->isAvailable && $cal->bookableNights === []) {
                $restrictions[] = 'KeineBuchbaren';
            }
            if (! $cal->isAvailable) {
                $restrictions[] = 'NichtVerfügbar';
            }

            $row = [
                'site_identifier' => $config->siteIdentifier,
                'tenant_id' => $config->tenantId,
                'ibe_context_id' => $config->urlFriendlyIbeContextId,
                'room_type_id' => $roomTypeId,
                'rate_id' => $rateId,
                'effective_date' => $cal->effectiveDate->format('Y-m-d'),
                'from_price' => $cal->fromPrice,
                'currency' => 'EUR',
                'is_available' => $cal->isAvailable ? 1 : 0,
                'is_arrival_allowed' => $cal->isArrivalAllowed ? 1 : 0,
                'is_departure_allowed' => $cal->isDepartureAllowed ? 1 : 0,
                'min_length_of_stay' => $cal->minLengthOfStay,
                'max_length_of_stay' => $cal->maxLengthOfStay,
                'bookable_nights' => implode(',', $cal->bookableNights),
                'bookable_nights_with_packages' => implode(',', $cal->bookableNightsWithPackages),
                'restrictions' => implode(',', $restrictions),
                'previous_day_blocked' => $cal->isPreviousDayBlocked ? 1 : 0,
                'next_day_blocked' => $cal->isNextDayBlocked ? 1 : 0,
            ];

            $row['data_hash'] = $this->hashRow($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hashRow(array $row): string
    {
        return hash('sha256', implode('|', [
            $row['from_price'] ?? 'null',
            $row['is_available'],
            $row['is_arrival_allowed'],
            $row['is_departure_allowed'],
            $row['min_length_of_stay'],
            $row['max_length_of_stay'],
            $row['bookable_nights'],
            $row['bookable_nights_with_packages'],
            $row['restrictions'],
            $row['previous_day_blocked'],
            $row['next_day_blocked'],
        ]));
    }
}
