<?php

declare(strict_types=1);

namespace Casablanca\Booking\Api\Client;

use Casablanca\Booking\Api\CasablancaHttpClient;
use Casablanca\Booking\Domain\Dto\RateDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;

final class RatesClient
{
    public function __construct(
        private readonly CasablancaHttpClient $http,
        private readonly SiteConfigurationDto $config
    ) {}

    /**
     * @param string[] $rateIds
     * @return RateDto[]
     */
    public function fetchAll(array $rateIds = []): array
    {
        $query = ['culture' => $this->config->defaultCulture];
        if ($rateIds !== []) {
            $query['rateIds'] = implode(',', $rateIds);
        }

        $result = [];
        foreach ($this->http->paginate('/rates', $query) as $page) {
            foreach ($page as $item) {
                $result[] = RateDto::fromArray((array) $item);
            }
        }

        return $result;
    }
}
