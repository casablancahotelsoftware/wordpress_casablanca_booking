<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Repository;

use Casablanca\Booking\Infrastructure\SiteIdentifier;
use DateTimeImmutable;

final class AvailabilityRepository
{
    public const TABLE = 'casablanca_availability';

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * @return array{from_price: float, currency: string}|null
     */
    public function findCheapestPrice(
        string $siteIdentifier,
        DateTimeImmutable $from,
        ?DateTimeImmutable $until,
        ?string $roomTypeId = null,
        ?string $rateId = null
    ): ?array {
        global $wpdb;

        $table = $this->table();
        $fromDate = $from->format('Y-m-d');
        // Open-ended upper bound when $until is null (avoids dynamic SQL that PHPCS cannot verify).
        $untilDate = $until !== null ? $until->format('Y-m-d') : '9999-12-31';

        if ($roomTypeId !== null && $roomTypeId !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT from_price, currency FROM %i'
                    . ' WHERE site_identifier = %s AND effective_date >= %s AND effective_date <= %s'
                    . ' AND is_available = 1 AND from_price > 0 AND room_type_id = %s AND rate_id = %s'
                    . ' ORDER BY from_price ASC LIMIT 1',
                    $table,
                    $siteIdentifier,
                    $fromDate,
                    $untilDate,
                    $roomTypeId,
                    ''
                ),
                ARRAY_A
            );
        } elseif ($rateId !== null && $rateId !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT from_price, currency FROM %i'
                    . ' WHERE site_identifier = %s AND effective_date >= %s AND effective_date <= %s'
                    . ' AND is_available = 1 AND from_price > 0 AND rate_id = %s'
                    . ' ORDER BY from_price ASC LIMIT 1',
                    $table,
                    $siteIdentifier,
                    $fromDate,
                    $untilDate,
                    $rateId
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT from_price, currency FROM %i'
                    . ' WHERE site_identifier = %s AND effective_date >= %s AND effective_date <= %s'
                    . " AND is_available = 1 AND from_price > 0 AND rate_id = ''"
                    . ' ORDER BY from_price ASC LIMIT 1',
                    $table,
                    $siteIdentifier,
                    $fromDate,
                    $untilDate
                ),
                ARRAY_A
            );
        }

        if (! is_array($row)) {
            if ($until !== null) {
                return $this->findCheapestPrice($siteIdentifier, $from, null, $roomTypeId, $rateId);
            }

            return null;
        }

        return ['from_price' => (float) $row['from_price'], 'currency' => (string) $row['currency']];
    }

    /**
     * Distinct package stay lengths from bookable_nights_with_packages in the window.
     *
     * @return int[]
     */
    public function findBookablePackageNights(
        string $siteIdentifier,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        string $rateId
    ): array {
        if ($rateId === '') {
            return [];
        }

        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT bookable_nights_with_packages FROM %i'
                . ' WHERE site_identifier = %s AND rate_id = %s'
                . ' AND effective_date >= %s AND effective_date <= %s',
                $table,
                $siteIdentifier,
                $rateId,
                $from->format('Y-m-d'),
                $until->format('Y-m-d')
            )
        );

        $nights = [];
        foreach (is_array($rows) ? $rows : [] as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            foreach (array_map('intval', explode(',', $raw)) as $nightCount) {
                if ($nightCount > 0) {
                    $nights[$nightCount] = true;
                }
            }
        }

        return array_map('intval', array_keys($nights));
    }

    public function deleteBySiteIdentifier(string $siteIdentifier): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
        return (int) $wpdb->delete($this->table(), ['site_identifier' => $siteIdentifier]);
    }

    public function purgePastDates(string $siteIdentifier): int
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
        return (int) $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE site_identifier = %s AND effective_date < %s',
                $table,
                $siteIdentifier,
                (new DateTimeImmutable('today'))->format('Y-m-d')
            )
        );
    }

    public function currentSite(): string
    {
        return SiteIdentifier::current();
    }
}
