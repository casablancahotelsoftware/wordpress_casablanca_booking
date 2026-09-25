<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Dto;

final class SiteConfigurationDto
{
    public function __construct(
        public string $siteIdentifier,
        public string $tenantId,
        public string $urlFriendlyIbeContextId,
        public string $apiKey,
        public string $apiBaseUrl,
        public string $ibeBaseUrl,
        public string $defaultCulture,
        public int $syncRangeDays,
        public int $syncChunkDays,
        public int $paginationTop,
        public RoomOccupancyDto $defaultOccupancy,
        public string $servicePath = 'ibe',
        public string $ibeLinkStyle = 'full_path',
        public string $themeCss = ''
    ) {}

    public function isComplete(): bool
    {
        return $this->tenantId !== ''
            && $this->urlFriendlyIbeContextId !== ''
            && $this->apiKey !== '';
    }

    public function getCacheTag(): string
    {
        return 'casablanca_ari_' . $this->siteIdentifier;
    }
}
