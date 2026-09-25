<?php

declare(strict_types=1);

namespace Casablanca\Booking\Api\Client;

use Casablanca\Booking\Api\CasablancaHttpClient;
use Casablanca\Booking\Domain\Dto\RoomTypeDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;

final class RoomTypesClient
{
    public function __construct(
        private readonly CasablancaHttpClient $http,
        private readonly SiteConfigurationDto $config
    ) {}

    /**
     * @return RoomTypeDto[]
     */
    public function fetchAll(): array
    {
        $result = [];
        foreach ($this->http->paginate('/room-types', ['culture' => $this->config->defaultCulture]) as $page) {
            foreach ($page as $item) {
                $result[] = RoomTypeDto::fromArray((array) $item);
            }
        }

        return $result;
    }
}
