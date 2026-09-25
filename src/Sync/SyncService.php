<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Api\ApiClientFactory;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\Repository\ConfigurationRepository;
use Casablanca\Booking\Domain\Repository\SyncLogRepository;
use Casablanca\Booking\Infrastructure\SiteIdentifier;
use DateTimeImmutable;
use Throwable;

final class SyncService
{
    public function __construct(
        private readonly ApiClientFactory $apiClientFactory = new ApiClientFactory(),
        private readonly PriceNormaliser $priceNormaliser = new PriceNormaliser(),
        private readonly AvailabilityWriter $availabilityWriter = new AvailabilityWriter(),
        private readonly RoomTypeWriter $roomTypeWriter = new RoomTypeWriter(),
        private readonly RateWriter $rateWriter = new RateWriter(),
        private readonly SyncLogRepository $syncLogRepository = new SyncLogRepository(),
        private readonly ConfigurationRepository $configurationRepository = new ConfigurationRepository(),
        private readonly SyncCatalogFlusher $syncCatalogFlusher = new SyncCatalogFlusher()
    ) {}

    /**
     * @return array<string, SiteConfigurationDto>
     */
    public function getConfiguredSites(?string $onlySiteIdentifier = null): array
    {
        $sites = $this->apiClientFactory->getAllConfiguredSites();
        if ($onlySiteIdentifier === null || $onlySiteIdentifier === '') {
            return $sites;
        }

        return isset($sites[$onlySiteIdentifier]) ? [$onlySiteIdentifier => $sites[$onlySiteIdentifier]] : [];
    }

    public function syncSite(
        SiteConfigurationDto $config,
        bool $force = false,
        ?int $overrideDays = null
    ): SyncSiteResult {
        $logId = $this->syncLogRepository->open($config->siteIdentifier);

        try {
            $rangeDays = $overrideDays ?? $config->syncRangeDays;
            $from = new DateTimeImmutable('today');
            $until = $from->modify('+' . (max(1, $rangeDays) - 1) . ' days');

            $roomTypes = $this->apiClientFactory->createRoomTypesClient($config)->fetchAll();
            $roomTypesWritten = $this->roomTypeWriter->write($config, $roomTypes);

            $rates = $this->apiClientFactory->createRatesClient($config)->fetchAll();
            $ratesWritten = $this->rateWriter->write($config, $rates);

            if ($roomTypes === []) {
                $message = 'No room types returned from API.';
                $this->syncLogRepository->complete($logId, SyncLogRepository::STATUS_PARTIAL, 0, 0, 0, $message);

                return new SyncSiteResult(true, 0, 0, 0, $roomTypesWritten, $ratesWritten, $message);
            }

            $calendarClient = $this->apiClientFactory->createCalendarDatesClient($config);
            $monthCursor = new DateTimeImmutable($from->format('Y-m-01'));
            $monthEnd = new DateTimeImmutable($until->format('Y-m-01'));
            $occupancy = [$config->defaultOccupancy];
            $emptyStayFilter = [
                'companyIdentifiers' => [],
                'roomTypeIds' => [],
                'rateIds' => [],
            ];

            $totalWritten = 0;
            $totalChanged = 0;
            $allChangedRoomIds = [];

            while ($monthCursor <= $monthEnd) {
                foreach ($roomTypes as $roomType) {
                    $roomFilter = $emptyStayFilter;
                    $roomFilter['roomTypeIds'] = [$roomType->id];

                    $calendarDates = $calendarClient->fetchMonth($monthCursor, $occupancy, $roomFilter);
                    $rows = $this->priceNormaliser->normaliseRoomPrices($config, $calendarDates, $roomType->id);

                    if ($rows === []) {
                        continue;
                    }

                    if ($force) {
                        $allChangedRoomIds[$roomType->id] = true;
                    }

                    $result = $this->availabilityWriter->write($config, $rows);
                    $totalWritten += $result->rowsWritten;
                    $totalChanged += $result->rowsChanged;
                    foreach ($result->changedRoomIds as $rid) {
                        $allChangedRoomIds[$rid] = true;
                    }
                }

                foreach ($rates as $rate) {
                    if (! $rate->isPackage) {
                        continue;
                    }

                    $packageFilter = $emptyStayFilter;
                    $packageFilter['rateIds'] = [$rate->id];
                    $calendarDates = $calendarClient->fetchMonth($monthCursor, $occupancy, $packageFilter);
                    $rows = $this->priceNormaliser->normalisePackagePrices($config, $calendarDates, $rate->id);

                    if ($rows === []) {
                        continue;
                    }

                    $result = $this->availabilityWriter->write($config, $rows);
                    $totalWritten += $result->rowsWritten;
                    $totalChanged += $result->rowsChanged;
                }

                $monthCursor = $monthCursor->modify('+1 month');
            }

            $this->availabilityWriter->purgePastDates($config);

            $tagsFlushed = count($allChangedRoomIds) >= 10 ? 1 : count($allChangedRoomIds);
            if ($tagsFlushed > 0) {
                wp_cache_flush();
            }

            $message = sprintf('Window %s–%s', $from->format('Y-m-d'), $until->format('Y-m-d'));
            $this->syncLogRepository->complete(
                $logId,
                SyncLogRepository::STATUS_SUCCESS,
                $totalWritten,
                $totalChanged,
                $tagsFlushed,
                $message
            );

            return new SyncSiteResult(true, $totalWritten, $totalChanged, $tagsFlushed, $roomTypesWritten, $ratesWritten, $message);
        } catch (Throwable $e) {
            $this->syncLogRepository->complete(
                $logId,
                SyncLogRepository::STATUS_FAILURE,
                0,
                0,
                0,
                $e->getMessage()
            );

            return new SyncSiteResult(false, 0, 0, 0, 0, 0, $e->getMessage());
        }
    }

    /**
     * @return array{success: bool, message: string, roomTypeCount?: int}
     */
    public function testConnection(string $siteIdentifier): array
    {
        if ($siteIdentifier === '') {
            return ['success' => false, 'message' => 'Site identifier is required.'];
        }

        try {
            $config = $this->apiClientFactory->resolveBySiteIdentifier($siteIdentifier);
            if ($config === null) {
                $message = 'No configuration found for this site.';
                $this->configurationRepository->updateConnectionStatus($siteIdentifier, 'error', $message);

                return ['success' => false, 'message' => $message];
            }

            $roomTypes = $this->apiClientFactory->createRoomTypesClient($config)->fetchAll();
            $count = count($roomTypes);

            if ($count === 0) {
                $message = 'Connection OK, but the API returned no room types for this space.';
                $this->configurationRepository->updateConnectionStatus($siteIdentifier, 'ok', $message);

                return ['success' => true, 'message' => $message, 'roomTypeCount' => 0];
            }

            $message = sprintf(
                'Connection OK — %d room type(s) found for space "%s".',
                $count,
                $config->urlFriendlyIbeContextId
            );
            $this->configurationRepository->updateConnectionStatus($siteIdentifier, 'ok', $message);

            return ['success' => true, 'message' => $message, 'roomTypeCount' => $count];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->configurationRepository->updateConnectionStatus($siteIdentifier, 'error', $message);

            return ['success' => false, 'message' => $message];
        }
    }

    public function sync(?string $siteIdentifier, bool $force, ?int $overrideDays): int
    {
        $sites = $this->getConfiguredSites($siteIdentifier);
        if ($sites === []) {
            return 1;
        }

        $hadFailure = false;
        foreach ($sites as $config) {
            $result = $this->syncSite($config, $force, $overrideDays);
            if (! $result->success) {
                $hadFailure = true;
            }
        }

        return $hadFailure ? 1 : 0;
    }

    /**
     * @return array{flush: array<string, mixed>, exitCode: int}
     */
    public function flushSyncedDataAndSync(string $siteIdentifier): array
    {
        if ($siteIdentifier === '') {
            return [
                'flush' => [
                    'roomTypesDeleted' => 0,
                    'ratesDeleted' => 0,
                    'availabilityDeleted' => 0,
                    'cacheTagsFlushed' => 0,
                    'message' => 'Site identifier is required.',
                ],
                'exitCode' => 1,
            ];
        }

        $flush = $this->syncCatalogFlusher->flushSite($siteIdentifier);

        return [
            'flush' => $flush,
            'exitCode' => $this->sync($siteIdentifier, false, null),
        ];
    }

    public function syncCurrentSite(bool $force = false, ?int $overrideDays = null): int
    {
        return $this->sync(SiteIdentifier::current(), $force, $overrideDays);
    }
}
