<?php

declare(strict_types=1);

namespace Casablanca\Booking\Api\Client;

use Casablanca\Booking\Api\CasablancaHttpClient;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use DateTimeInterface;

final class BookingOffersClient
{
    public function __construct(
        private readonly CasablancaHttpClient $http,
        private readonly SiteConfigurationDto $config
    ) {}

    /**
     * @param array<string, mixed> $selectionCriteria
     * @param array<string, mixed>|null $stayFilter
     * @return array<string, mixed>
     */
    public function create(
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        array $selectionCriteria,
        RoomOccupancyDto $occupancy,
        ?array $stayFilter = null,
        ?string $culture = null
    ): array {
        $body = [
            'selectionCriteria' => $selectionCriteria,
            'roomOccupancy' => $occupancy->toArray(),
        ];

        if ($stayFilter !== null) {
            $body['stayFilter'] = $stayFilter;
        }

        $path = sprintf(
            '/booking-offers/%s/%s',
            $arrival->format('Y-m-d'),
            $departure->format('Y-m-d')
        );

        $response = $this->http->postJson($path, $body, [
            'culture' => $culture ?? $this->config->defaultCulture,
        ]);

        return is_array($response) ? $response : [];
    }
}
