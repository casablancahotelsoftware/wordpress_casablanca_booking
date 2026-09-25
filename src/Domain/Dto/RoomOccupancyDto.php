<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Dto;

final class RoomOccupancyDto
{
    /** @param int[] $ageOfChildren */
    public function __construct(
        public int $numberOfAdults,
        public array $ageOfChildren = []
    ) {}

    /**
     * @return array{numberOfAdults: int, ageOfChildren: int[]}
     */
    public function toArray(): array
    {
        return [
            'numberOfAdults' => $this->numberOfAdults,
            'ageOfChildren' => array_values(array_map('intval', $this->ageOfChildren)),
        ];
    }

    public function getNumberOfChildren(): int
    {
        return count($this->ageOfChildren);
    }
}
