<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Domain\Repository\AvailabilityRepository;
use Casablanca\Booking\Domain\Repository\RateRepository;
use Casablanca\Booking\Domain\Repository\RoomTypeRepository;
use Casablanca\Booking\Domain\Repository\SyncLogRepository;

final class SyncCatalogFlusher
{
    public function __construct(
        private readonly AvailabilityRepository $availabilityRepository = new AvailabilityRepository(),
        private readonly RoomTypeRepository $roomTypeRepository = new RoomTypeRepository(),
        private readonly RateRepository $rateRepository = new RateRepository(),
        private readonly SyncLogRepository $syncLogRepository = new SyncLogRepository()
    ) {}

    /**
     * @return array{roomTypesDeleted: int, ratesDeleted: int, availabilityDeleted: int, cacheTagsFlushed: int, message: string}
     */
    public function flushSite(string $siteIdentifier): array
    {
        $roomTypesDeleted = $this->roomTypeRepository->deleteBySiteIdentifier($siteIdentifier);
        $ratesDeleted = $this->rateRepository->deleteBySiteIdentifier($siteIdentifier);
        $availabilityDeleted = $this->availabilityRepository->deleteBySiteIdentifier($siteIdentifier);

        wp_cache_flush();

        $message = sprintf(
            'Flushed synced data: %d room types, %d rates, %d availability rows.',
            $roomTypesDeleted,
            $ratesDeleted,
            $availabilityDeleted
        );

        $logId = $this->syncLogRepository->open($siteIdentifier);
        $this->syncLogRepository->complete(
            $logId,
            SyncLogRepository::STATUS_SUCCESS,
            0,
            $roomTypesDeleted + $ratesDeleted + $availabilityDeleted,
            1,
            $message
        );

        return [
            'roomTypesDeleted' => $roomTypesDeleted,
            'ratesDeleted' => $ratesDeleted,
            'availabilityDeleted' => $availabilityDeleted,
            'cacheTagsFlushed' => 1,
            'message' => $message,
        ];
    }
}
