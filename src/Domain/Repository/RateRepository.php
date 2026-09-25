<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Repository;

final class RateRepository
{
    public const TABLE = 'casablanca_rate';

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findForSite(string $siteIdentifier): array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE site_identifier = %s ORDER BY sort_order ASC, name ASC',
                $table,
                $siteIdentifier
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Non-package rates for mapping / overview.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findNonPackagesForSite(string $siteIdentifier): array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE site_identifier = %s AND is_package = 0 ORDER BY sort_order ASC, name ASC',
                $table,
                $siteIdentifier
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findPackagesForSite(string $siteIdentifier, ?string $filterRateId = null): array
    {
        global $wpdb;

        $table = $this->table();

        if ($filterRateId !== null && $filterRateId !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE site_identifier = %s AND is_package = 1 AND rate_id = %s ORDER BY sort_order ASC, name ASC',
                    $table,
                    $siteIdentifier,
                    $filterRateId
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE site_identifier = %s AND is_package = 1 ORDER BY sort_order ASC, name ASC',
                    $table,
                    $siteIdentifier
                ),
                ARRAY_A
            );
        }

        return is_array($rows) ? $rows : [];
    }

    public function findOneBySiteAndSlug(string $siteIdentifier, string $slug): ?array
    {
        global $wpdb;

        $slug = sanitize_title(rawurldecode(trim($slug)));
        if ($slug === '') {
            return null;
        }

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE site_identifier = %s AND slug = %s AND is_package = 1 LIMIT 1',
                $table,
                $siteIdentifier,
                $slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function findByRateId(string $siteIdentifier, string $rateId): ?array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE site_identifier = %s AND rate_id = %s LIMIT 1',
                $table,
                $siteIdentifier,
                $rateId
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function deleteBySiteIdentifier(string $siteIdentifier): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
        return (int) $wpdb->delete($this->table(), ['site_identifier' => $siteIdentifier]);
    }

    /**
     * @return array<string, string>
     */
    public function getPackageOptionsForSite(string $siteIdentifier): array
    {
        $options = ['' => __('All packages', 'casablanca-booking')];
        foreach ($this->findPackagesForSite($siteIdentifier) as $row) {
            $options[(string) $row['rate_id']] = (string) $row['name'];
        }

        return $options;
    }
}
