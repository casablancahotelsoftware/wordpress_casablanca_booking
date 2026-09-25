<?php

declare(strict_types=1);

namespace Casablanca\Booking\Sync;

use Casablanca\Booking\Infrastructure\SiteIdentifier;

final class CronScheduler
{
    public const HOOK = 'casablanca_booking_daily_sync';

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);
    }

    public function ensureDailySyncScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK)) {
            return;
        }

        $timestamp = $this->nextDailyTimestamp();
        wp_schedule_event($timestamp, 'daily', self::HOOK);
    }

    /**
     * @return array{
     *     scheduled: bool,
     *     hook: string,
     *     recurrence: string,
     *     next_run: int|false,
     *     next_run_formatted: string
     * }
     */
    public function getStatus(): array
    {
        $next = wp_next_scheduled(self::HOOK);

        return [
            'scheduled' => $next !== false,
            'hook' => self::HOOK,
            'recurrence' => 'daily',
            'next_run' => $next,
            'next_run_formatted' => $next
                ? (string) wp_date('Y-m-d H:i:s', (int) $next)
                : '',
        ];
    }

    public function run(): void
    {
        (new SyncService())->sync(null, false, null);
    }

    private function nextDailyTimestamp(): int
    {
        $now = current_time('timestamp');
        $today = strtotime('today', $now) + (int) gmdate('G', $now) * HOUR_IN_SECONDS + (int) gmdate('i', $now) * MINUTE_IN_SECONDS;

        return $today >= $now ? $today : $today + DAY_IN_SECONDS;
    }

    public static function siteScope(): string
    {
        return SiteIdentifier::current();
    }
}
