<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Dto;

use Casablanca\Booking\Domain\Utility\DescriptionPresenter;

final class RateDto
{
    /** @param array<int, array<string, mixed>> $images */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $shortDescription,
        public string $imageUrl,
        public array $images,
        public bool $isPackage,
        public string $cateringType,
        public int $sort
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $images = isset($data['images']) && is_array($data['images']) ? $data['images'] : [];

        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['description'] ?? ''),
            DescriptionPresenter::extractShortDescriptionFromApi($data),
            (string) ($data['imageUrl'] ?? ''),
            $images,
            (bool) ($data['isPackage'] ?? false),
            (string) ($data['cateringType'] ?? ''),
            (int) ($data['sort'] ?? 0)
        );
    }
}
