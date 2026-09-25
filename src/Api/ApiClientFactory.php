<?php

declare(strict_types=1);

namespace Casablanca\Booking\Api;

use Casablanca\Booking\Api\Client\BookingOffersClient;
use Casablanca\Booking\Api\Client\CalendarDatesClient;
use Casablanca\Booking\Api\Client\RatesClient;
use Casablanca\Booking\Api\Client\RoomTypesClient;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\Repository\ConfigurationRepository;
use Casablanca\Booking\Infrastructure\SiteIdentifier;
use Casablanca\Booking\Infrastructure\StagingHostResolver;
use RuntimeException;

final class ApiClientFactory
{
    public const DEFAULT_API_BASE_URL = 'https://api.casablanca.at';
    public const DEFAULT_IBE_BASE_URL = 'https://bookingengine.casablanca.at';

    public function __construct(
        private readonly ConfigurationRepository $configurationRepository = new ConfigurationRepository(),
        private readonly StagingHostResolver $stagingHostResolver = new StagingHostResolver()
    ) {}

    /**
     * @return array<string, SiteConfigurationDto>
     */
    public function getAllConfiguredSites(): array
    {
        $sites = [];
        foreach ($this->configurationRepository->findAll() as $row) {
            $config = $this->resolveFromRow($row);
            if ($config !== null) {
                $sites[$config->siteIdentifier] = $config;
            }
        }

        return $sites;
    }

    public function resolveCurrent(): ?SiteConfigurationDto
    {
        return $this->resolveBySiteIdentifier(SiteIdentifier::current());
    }

    public function resolveBySiteIdentifier(string $siteIdentifier): ?SiteConfigurationDto
    {
        $row = $this->configurationRepository->findBySiteIdentifier($siteIdentifier);

        return $row !== null ? $this->resolveFromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function resolveFromRow(array $row): ?SiteConfigurationDto
    {
        try {
            $settings = [
                'siteIdentifier' => (string) ($row['site_identifier'] ?? ''),
                'tenantId' => (string) ($row['tenant_id'] ?? ''),
                'urlFriendlyIbeContextId' => (string) ($row['url_friendly_ibe_context_id'] ?? 'bookingengine'),
                'apiKey' => $this->configurationRepository->decryptApiKey((string) ($row['api_key_encrypted'] ?? '')),
                'apiBaseUrl' => (string) ($row['api_base_url'] ?? self::DEFAULT_API_BASE_URL),
                'ibeBaseUrl' => (string) ($row['ibe_base_url'] ?? self::DEFAULT_IBE_BASE_URL),
                'defaultCulture' => (string) ($row['default_culture'] ?? 'de'),
                'syncRangeDays' => (int) ($row['sync_range_days'] ?? 365),
                'syncChunkDays' => (int) ($row['sync_chunk_days'] ?? 31),
                'paginationTop' => (int) ($row['pagination_top'] ?? 100),
                'servicePath' => (string) ($row['service_path'] ?? 'ibe'),
                'ibeLinkStyle' => (string) ($row['ibe_link_style'] ?? 'full_path'),
                'themeCss' => (string) ($row['theme_css'] ?? ''),
                'defaultAdults' => (int) ($row['default_adults'] ?? 2),
                'defaultChildrenAges' => (string) ($row['default_children_ages'] ?? ''),
            ];

            $settings = $this->stagingHostResolver->applyToSettings($settings);

            $childrenAges = [];
            if ($settings['defaultChildrenAges'] !== '') {
                $childrenAges = array_map(
                    'intval',
                    array_filter(array_map('trim', explode(',', $settings['defaultChildrenAges'])))
                );
            }

            $config = new SiteConfigurationDto(
                $settings['siteIdentifier'],
                $settings['tenantId'],
                $settings['urlFriendlyIbeContextId'],
                $settings['apiKey'],
                $settings['apiBaseUrl'],
                $settings['ibeBaseUrl'],
                $settings['defaultCulture'],
                $settings['syncRangeDays'],
                $settings['syncChunkDays'],
                $settings['paginationTop'],
                new RoomOccupancyDto(max(1, $settings['defaultAdults']), $childrenAges),
                $settings['servicePath'],
                $settings['ibeLinkStyle'],
                $settings['themeCss']
            );

            return $config->isComplete() ? $config : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function createHttpClient(SiteConfigurationDto $config): CasablancaHttpClient
    {
        return new CasablancaHttpClient($config);
    }

    public function createRoomTypesClient(SiteConfigurationDto $config): RoomTypesClient
    {
        return new RoomTypesClient($this->createHttpClient($config), $config);
    }

    public function createRatesClient(SiteConfigurationDto $config): RatesClient
    {
        return new RatesClient($this->createHttpClient($config), $config);
    }

    public function createCalendarDatesClient(SiteConfigurationDto $config): CalendarDatesClient
    {
        return new CalendarDatesClient($this->createHttpClient($config), $config);
    }

    public function createBookingOffersClient(SiteConfigurationDto $config): BookingOffersClient
    {
        return new BookingOffersClient($this->createHttpClient($config), $config);
    }
}
