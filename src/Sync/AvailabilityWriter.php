<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\Repository\AvailabilityRepository;

final class AvailabilityWriter
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly AvailabilityRepository $availabilityRepository = new AvailabilityRepository()
    ) {}

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function write(SiteConfigurationDto $config, array $rows): WriterResult
    {
        global $wpdb;

        if ($rows === []) {
            return new WriterResult(0, 0, []);
        }

        $existingHashes = $this->loadExistingHashes($config, $rows);
        $changedRoomIds = [];
        $rowsChanged = 0;
        $table = $this->availabilityRepository->table();

        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $row) {
                $rateId = (string) ($row['rate_id'] ?? '');
                $key = $row['site_identifier'] . '|' . $row['room_type_id'] . '|' . $rateId . '|' . $row['effective_date'];
                $oldHash = $existingHashes[$key] ?? null;
                if ($oldHash !== $row['data_hash']) {
                    $rowsChanged++;
                    if ($row['room_type_id'] !== '') {
                        $changedRoomIds[$row['room_type_id']] = true;
                    }
                }
            }

            foreach ($batch as $row) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
                $wpdb->replace($table, $row);
            }
        }

        return new WriterResult(count($rows), $rowsChanged, array_keys($changedRoomIds));
    }

    public function purgePastDates(SiteConfigurationDto $config): int
    {
        return $this->availabilityRepository->purgePastDates($config->siteIdentifier);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function loadExistingHashes(SiteConfigurationDto $config, array $rows): array
    {
        global $wpdb;

        $dates = array_unique(array_column($rows, 'effective_date'));
        if ($dates === []) {
            return [];
        }

        $table = $this->availabilityRepository->table();
        $dateList = array_values($dates);
        $minDate = min($dateList);
        $maxDate = max($dateList);
        $wantedDates = array_fill_keys($dateList, true);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT room_type_id, rate_id, effective_date, data_hash FROM %i'
                . ' WHERE site_identifier = %s AND effective_date >= %s AND effective_date <= %s',
                $table,
                $config->siteIdentifier,
                $minDate,
                $maxDate
            ),
            ARRAY_A
        );

        $existing = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                $effectiveDate = (string) $row['effective_date'];
                if (! isset($wantedDates[$effectiveDate])) {
                    continue;
                }
                $key = $config->siteIdentifier . '|'
                    . $row['room_type_id'] . '|'
                    . ($row['rate_id'] ?? '') . '|'
                    . $effectiveDate;
                $existing[$key] = (string) $row['data_hash'];
            }
        }

        return $existing;
    }
}
