<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Repository;

final class SyncLogRepository
{
    public const TABLE = 'casablanca_synclog';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';
    public const STATUS_PARTIAL = 'partial';

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    public function open(string $siteIdentifier): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table write.
        $wpdb->insert($this->table(), [
            'site_identifier' => $siteIdentifier,
            'started_at' => time(),
            'finished_at' => 0,
            'status' => self::STATUS_RUNNING,
            'rows_written' => 0,
            'rows_changed' => 0,
            'cache_tags_flushed' => 0,
            'message' => '',
        ]);

        return (int) $wpdb->insert_id;
    }

    public function complete(
        int $logId,
        string $status,
        int $rowsWritten,
        int $rowsChanged,
        int $cacheTagsFlushed,
        string $message
    ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
        $wpdb->update(
            $this->table(),
            [
                'finished_at' => time(),
                'status' => $status,
                'rows_written' => $rowsWritten,
                'rows_changed' => $rowsChanged,
                'cache_tags_flushed' => $cacheTagsFlushed,
                'message' => $message,
            ],
            ['id' => $logId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(string $siteIdentifier, int $limit = 50): array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE site_identifier = %s ORDER BY started_at DESC LIMIT %d',
                $table,
                $siteIdentifier,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }
}
