<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Domain\Dto\RoomTypeDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\Repository\RoomTypeRepository;
use Casablanca\Booking\Domain\Utility\RoomSlugGenerator;

final class RoomTypeWriter
{
    public function __construct(
        private readonly RoomTypeRepository $roomTypeRepository = new RoomTypeRepository()
    ) {}

    /**
     * @param RoomTypeDto[] $dtos
     */
    public function write(SiteConfigurationDto $config, array $dtos): int
    {
        global $wpdb;

        $count = 0;
        $table = $this->roomTypeRepository->table();

        foreach ($dtos as $dto) {
            $existing = $this->findByRoomTypeId($config->siteIdentifier, $dto->id);
            $imagesJson = json_encode($dto->images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            $slug = $this->resolveSlug($config->siteIdentifier, $dto->name, $existing);

            $data = [
                'site_identifier' => $config->siteIdentifier,
                'tenant_id' => $config->tenantId,
                'ibe_context_id' => $config->urlFriendlyIbeContextId,
                'room_type_id' => $dto->id,
                'company_id' => $dto->companyId,
                'name' => $dto->name,
                'slug' => $slug,
                'description' => $dto->description,
                'short_description' => $dto->shortDescription,
                'image_url' => $dto->imageUrl,
                'images' => $imagesJson,
                'standard_occupancy' => $dto->standardOccupancy,
                'min_occupancy' => $dto->minOccupancy,
                'max_occupancy' => $dto->maxOccupancy,
                'sort_order' => $dto->sort,
            ];

            if ($existing === null) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table write.
                $wpdb->insert($table, $data);
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
                $wpdb->update($table, $data, ['id' => (int) $existing['id']]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, mixed>|null $existing
     */
    private function resolveSlug(string $siteIdentifier, string $name, ?array $existing): string
    {
        $excludeId = is_array($existing) ? (int) $existing['id'] : 0;

        if (is_array($existing) && ($existing['name'] ?? '') === $name && ($existing['slug'] ?? '') !== '') {
            return (string) $existing['slug'];
        }

        $baseSlug = RoomSlugGenerator::fromName($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($siteIdentifier, $slug, $excludeId)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByRoomTypeId(string $siteIdentifier, string $roomTypeId): ?array
    {
        global $wpdb;

        $table = $this->roomTypeRepository->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE site_identifier = %s AND room_type_id = %s LIMIT 1',
                $table,
                $siteIdentifier,
                $roomTypeId
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private function slugExists(string $siteIdentifier, string $slug, int $excludeId): bool
    {
        global $wpdb;

        $table = $this->roomTypeRepository->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $row = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE site_identifier = %s AND slug = %s AND id != %d LIMIT 1',
                $table,
                $siteIdentifier,
                $slug,
                $excludeId
            )
        );

        return $row !== null;
    }
}
