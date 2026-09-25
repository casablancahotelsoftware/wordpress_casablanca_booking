<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

final class SyncSiteResult
{
    public function __construct(
        public bool $success,
        public int $rowsWritten,
        public int $rowsChanged,
        public int $cacheTagsFlushed,
        public int $roomTypesWritten,
        public int $ratesWritten,
        public string $message
    ) {}
}
