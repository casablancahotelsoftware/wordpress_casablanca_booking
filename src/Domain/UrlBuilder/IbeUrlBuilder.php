<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\UrlBuilder;

use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\IbeLinkStyle;
use DateTimeInterface;

final class IbeUrlBuilder
{
    public function build(
        SiteConfigurationDto $config,
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        RoomOccupancyDto $occupancy,
        ?string $preselectedRoomTypeId = null,
        ?string $culture = null,
        ?string $rateIds = null
    ): string {
        return $this->buildForRooms(
            $config,
            $arrival,
            $departure,
            [$occupancy],
            $culture,
            $preselectedRoomTypeId,
            $rateIds
        );
    }

    /**
     * @param RoomOccupancyDto[] $rooms
     */
    public function buildForRooms(
        SiteConfigurationDto $config,
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        array $rooms,
        ?string $culture = null,
        ?string $preselectedRoomTypeId = null,
        ?string $rateIds = null
    ): string {
        if ($rooms === []) {
            $rooms = [new RoomOccupancyDto(1)];
        }

        $params = $this->buildQueryParams(
            $arrival,
            $departure,
            $rooms,
            $preselectedRoomTypeId,
            $rateIds
        );

        return $this->buildPath($config, $culture) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param RoomOccupancyDto[] $rooms
     * @return array<string, int|string>
     */
    public function buildQueryParams(
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        array $rooms,
        ?string $preselectedRoomTypeId = null,
        ?string $rateIds = null
    ): array {
        if ($rooms === []) {
            $rooms = [new RoomOccupancyDto(1)];
        }

        $params = [
            'arrivalDate' => $arrival->format('Y-m-d'),
            'departureDate' => $departure->format('Y-m-d'),
        ];

        if (count($rooms) > 1) {
            $params['numberOfRooms'] = count($rooms);
        }

        foreach ($rooms as $index => $room) {
            $params['rooms_' . $index . '__adults'] = $room->numberOfAdults;

            $children = $room->getNumberOfChildren();
            if ($children > 0) {
                $params['rooms_' . $index . '__children'] = $children;

                foreach ($room->ageOfChildren as $childIndex => $age) {
                    $params['rooms_' . $index . '__children_' . $childIndex . '__age'] = max(0, min(17, (int) $age));
                }
            }
        }

        if ($rateIds !== null && $rateIds !== '') {
            $params['rateIds'] = $rateIds;
        }

        if ($preselectedRoomTypeId !== null && $preselectedRoomTypeId !== '') {
            $params['roomTypeIds'] = $preselectedRoomTypeId;
        }

        return $params;
    }

    public function buildPath(SiteConfigurationDto $config, ?string $culture = null): string
    {
        $cultureSegment = $culture !== null && $culture !== '' ? $culture : $config->defaultCulture;
        $base = rtrim($config->ibeBaseUrl, '/');
        $style = IbeLinkStyle::normalize($config->ibeLinkStyle);
        $segments = [$base, rawurlencode($cultureSegment)];

        if ($style === IbeLinkStyle::CULTURE_ONLY) {
            return implode('/', $segments);
        }

        if ($style === IbeLinkStyle::CULTURE_SPACE) {
            $segments[] = rawurlencode($config->urlFriendlyIbeContextId);

            return implode('/', $segments);
        }

        $segments[] = rawurlencode($config->tenantId);
        $segments[] = rawurlencode($config->urlFriendlyIbeContextId);

        return implode('/', $segments);
    }
}
