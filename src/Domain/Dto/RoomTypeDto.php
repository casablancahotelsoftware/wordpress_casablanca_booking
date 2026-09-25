<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Dto;

use Casablanca\Booking\Domain\Utility\DescriptionPresenter;

final class RoomTypeDto
{
    /** @param array<int, array<string, mixed>> $images */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $shortDescription,
        public string $imageUrl,
        public array $images,
        public string $companyId,
        public int $sort,
        public int $standardOccupancy,
        public int $minOccupancy,
        public int $maxOccupancy
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $occupancy = isset($data['occupancy']) && is_array($data['occupancy']) ? $data['occupancy'] : [];
        $images = isset($data['images']) && is_array($data['images']) ? $data['images'] : [];

        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['description'] ?? ''),
            DescriptionPresenter::extractShortDescriptionFromApi($data),
            (string) ($data['imageUrl'] ?? ''),
            $images,
            (string) ($data['companyId'] ?? ''),
            (int) ($data['sort'] ?? 0),
            (int) ($occupancy['standardOccupancy'] ?? 2),
            (int) ($occupancy['minOccupancy'] ?? 1),
            (int) ($occupancy['maxOccupancy'] ?? 4)
        );
    }
}
