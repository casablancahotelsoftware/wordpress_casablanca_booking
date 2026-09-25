<?php

declare(strict_types=1);

namespace Casablanca\Booking\Cli;

use Casablanca\Booking\Infrastructure\SiteIdentifier;
use Casablanca\Booking\Sync\SyncService;

final class SyncCommand
{
    public static function register(): void
    {
        \WP_CLI::add_command('casablanca-booking sync', [self::class, 'sync']);
    }

    /**
     * Run CASABLANCA availability sync.
     *
     * ## OPTIONS
     *
     * [--site=<id>]
     * : WordPress site identifier (blog ID). Defaults to current site.
     *
     * [--force]
     * : Mark all room types for cache flush threshold.
     *
     * [--days=<n>]
     * : Override sync window length in days.
     */
    public function sync(array $args, array $assocArgs): void
    {
        $site = isset($assocArgs['site']) ? (string) $assocArgs['site'] : SiteIdentifier::current();
        $force = isset($assocArgs['force']);
        $days = isset($assocArgs['days']) ? (int) $assocArgs['days'] : null;

        $exitCode = (new SyncService())->sync($site, $force, $days);

        if ($exitCode === 0) {
            \WP_CLI::success('CASABLANCA sync completed.');
        } else {
            \WP_CLI::error('CASABLANCA sync failed.');
        }
    }
}
