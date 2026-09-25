<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Domain\Dto\RateDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\Repository\RateRepository;
use Casablanca\Booking\Domain\Utility\RoomSlugGenerator;

final class RateWriter
{
    public function __construct(
        private readonly RateRepository $rateRepository = new RateRepository()
    ) {}

    /**
     * @param RateDto[] $dtos
     */
    public function write(SiteConfigurationDto $config, array $dtos): int
    {
        global $wpdb;

        $count = 0;
        $table = $this->rateRepository->table();

        foreach ($dtos as $dto) {
            $existing = $this->rateRepository->findByRateId($config->siteIdentifier, $dto->id);
            $slug = $dto->isPackage ? $this->resolveSlug($config->siteIdentifier, $dto->name, $existing) : '';
            $imagesJson = json_encode($dto->images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

            $data = [
                'site_identifier' => $config->siteIdentifier,
                'tenant_id' => $config->tenantId,
                'ibe_context_id' => $config->urlFriendlyIbeContextId,
                'rate_id' => $dto->id,
                'name' => $dto->name,
                'slug' => $slug,
                'description' => $dto->description,
                'short_description' => $dto->shortDescription,
                'image_url' => $dto->imageUrl,
                'images' => $imagesJson,
                'is_package' => $dto->isPackage ? 1 : 0,
                'catering_type' => $dto->cateringType,
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

    private function slugExists(string $siteIdentifier, string $slug, int $excludeId): bool
    {
        global $wpdb;

        $table = $this->rateRepository->table();

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
