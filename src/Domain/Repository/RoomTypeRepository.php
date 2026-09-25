<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Repository;

final class RoomTypeRepository
{
    public const TABLE = 'casablanca_roomtype';

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findForSite(string $siteIdentifier, ?string $filterRoomTypeId = null): array
    {
        global $wpdb;

        $table = $this->table();

        if ($filterRoomTypeId !== null && $filterRoomTypeId !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE site_identifier = %s AND room_type_id = %s ORDER BY sort_order ASC, name ASC',
                    $table,
                    $siteIdentifier,
                    $filterRoomTypeId
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE site_identifier = %s ORDER BY sort_order ASC, name ASC',
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
                'SELECT * FROM %i WHERE site_identifier = %s AND slug = %s LIMIT 1',
                $table,
                $siteIdentifier,
                $slug
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
    public function getOptionsForSite(string $siteIdentifier): array
    {
        $options = ['' => __('All room types', 'casablanca-booking')];
        foreach ($this->findForSite($siteIdentifier) as $row) {
            $options[(string) $row['room_type_id']] = (string) $row['name'];
        }

        return $options;
    }
}
