<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

final class WriterResult
{
    /**
     * @param string[] $changedRoomIds
     */
    public function __construct(
        public int $rowsWritten,
        public int $rowsChanged,
        public array $changedRoomIds
    ) {}
}
