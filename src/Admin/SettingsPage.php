<?php

declare(strict_types=1);

namespace Casablanca\Booking\Admin;

use Casablanca\Booking\Domain\IbeLinkStyle;
use Casablanca\Booking\Domain\Repository\ConfigurationRepository;
use Casablanca\Booking\Domain\Repository\RateRepository;
use Casablanca\Booking\Domain\Repository\RoomTypeRepository;
use Casablanca\Booking\Domain\Repository\SyncLogRepository;
use Casablanca\Booking\Domain\Utility\RateDisplayType;
use Casablanca\Booking\Domain\Utility\ThemeCssSanitizer;
use Casablanca\Booking\Frontend\ThemeTokenRegistry;
use Casablanca\Booking\Infrastructure\SiteIdentifier;
use Casablanca\Booking\Infrastructure\StagingHostResolver;
use Casablanca\Booking\Sync\CronScheduler;
use Casablanca\Booking\Sync\SyncService;

final class SettingsPage
{
    private ConfigurationRepository $configurationRepository;
    private SyncLogRepository $syncLogRepository;
    private StagingHostResolver $stagingHostResolver;

    public function __construct()
    {
        $this->configurationRepository = new ConfigurationRepository();
        $this->syncLogRepository = new SyncLogRepository();
        $this->stagingHostResolver = new StagingHostResolver();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_casablanca_booking_save', [$this, 'handleSave']);
        add_action('admin_post_casablanca_booking_test', [$this, 'handleTestConnection']);
        add_action('admin_post_casablanca_booking_sync', [$this, 'handleSync']);
        add_action('admin_post_casablanca_booking_flush_sync', [$this, 'handleFlushSync']);
        add_action('admin_post_casablanca_booking_reschedule_cron', [$this, 'handleRescheduleCron']);
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('CASABLANCA Booking', 'casablanca-booking'),
            __('CASABLANCA Booking', 'casablanca-booking'),
            'manage_options',
            'casablanca-booking',
            [$this, 'renderPage'],
            'dashicons-calendar-alt',
            58
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $siteId = SiteIdentifier::current();
        $config = $this->configurationRepository->findBySiteIdentifier($siteId);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only admin tab query arg; capability checked above.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'config';
        $connectionOk = is_array($config) && ($config['connection_status'] ?? '') === 'ok';
        $envMode = $this->stagingHostResolver->getEnvironmentMode();
        $themeTokens = ThemeTokenRegistry::getDefaultTokens();
        $schedulerStatus = (new CronScheduler())->getStatus();
        $mappingData = $connectionOk ? $this->getMappingCodes($siteId, is_array($config) ? $config : []) : null;

        include CASABLANCA_BOOKING_PATH . 'templates/admin/settings-page.php';
    }

    public function handleSave(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'casablanca-booking'));
        }
        check_admin_referer('casablanca_booking_save');

        $siteId = SiteIdentifier::current();
        $existing = $this->configurationRepository->findBySiteIdentifier($siteId);
        $isNew = $existing === null;

        try {
            $this->configurationRepository->save([
                'siteIdentifier' => $siteId,
                'tenantId' => sanitize_text_field(wp_unslash((string) ($_POST['tenant_id'] ?? ''))),
                'apiKey' => sanitize_text_field(wp_unslash((string) ($_POST['api_key'] ?? ''))),
                'urlFriendlyIbeContextId' => sanitize_text_field(wp_unslash((string) ($_POST['url_friendly_ibe_context_id'] ?? 'bookingengine'))),
                'defaultCulture' => sanitize_text_field(wp_unslash((string) ($_POST['default_culture'] ?? 'de'))),
                'useCustomIbeDomain' => ! empty($_POST['use_custom_ibe_domain']),
                'ibeBaseUrl' => esc_url_raw(wp_unslash((string) ($_POST['ibe_base_url'] ?? ''))),
                'ibeLinkStyle' => IbeLinkStyle::normalize(sanitize_text_field(wp_unslash((string) ($_POST['ibe_link_style'] ?? IbeLinkStyle::FULL_PATH)))),
                'themeCss' => ThemeCssSanitizer::sanitize(
                    sanitize_textarea_field(wp_unslash((string) ($_POST['theme_css'] ?? '')))
                ),
            ]);
        } catch (\Throwable $e) {
            wp_safe_redirect(add_query_arg(['page' => 'casablanca-booking', 'error' => rawurlencode($e->getMessage())], admin_url('admin.php')));
            exit;
        }

        (new CronScheduler())->ensureDailySyncScheduled();
        (new \Casablanca\Booking\Frontend\DetailRewrite())->flush();

        $test = (new SyncService())->testConnection($siteId);
        if ($isNew && ($test['success'] ?? false)) {
            (new SyncService())->sync($siteId, false, null);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'casablanca-booking',
            'saved' => '1',
            'connection' => ($test['success'] ?? false) ? 'ok' : 'error',
            'message' => rawurlencode((string) ($test['message'] ?? '')),
        ], admin_url('admin.php')));
        exit;
    }

    public function handleTestConnection(): void
    {
        $this->assertAdminPost('casablanca_booking_test');
        $siteId = SiteIdentifier::current();
        $test = (new SyncService())->testConnection($siteId);

        wp_safe_redirect(add_query_arg([
            'page' => 'casablanca-booking',
            'connection' => ($test['success'] ?? false) ? 'ok' : 'error',
            'message' => rawurlencode((string) ($test['message'] ?? '')),
        ], admin_url('admin.php')));
        exit;
    }

    public function handleSync(): void
    {
        $this->assertAdminPost('casablanca_booking_sync');
        $siteId = SiteIdentifier::current();
        $exitCode = (new SyncService())->sync($siteId, false, null);

        wp_safe_redirect(add_query_arg([
            'page' => 'casablanca-booking',
            'tab' => 'logs',
            'sync' => $exitCode === 0 ? 'ok' : 'error',
        ], admin_url('admin.php')));
        exit;
    }

    public function handleFlushSync(): void
    {
        $this->assertAdminPost('casablanca_booking_flush_sync');
        $siteId = SiteIdentifier::current();
        $result = (new SyncService())->flushSyncedDataAndSync($siteId);

        wp_safe_redirect(add_query_arg([
            'page' => 'casablanca-booking',
            'tab' => 'logs',
            'sync' => ($result['exitCode'] ?? 1) === 0 ? 'ok' : 'error',
            'flush' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function handleRescheduleCron(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'casablanca-booking'));
        }
        check_admin_referer('casablanca_booking_reschedule_cron');

        $scheduler = new CronScheduler();
        // Clear stale entries then ensure a fresh daily schedule.
        wp_clear_scheduled_hook(CronScheduler::HOOK);
        $scheduler->ensureDailySyncScheduled();

        $tab = sanitize_key(wp_unslash((string) ($_POST['redirect_tab'] ?? 'logs')));
        if (! in_array($tab, ['config', 'mapping', 'logs'], true)) {
            $tab = 'logs';
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'casablanca-booking',
            'tab' => $tab,
            'cron' => 'scheduled',
        ], admin_url('admin.php')));
        exit;
    }

    private function assertAdminPost(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'casablanca-booking'));
        }

        check_admin_referer($action);
    }

    /**
     * Mapping payload matching TYPO3 ConfigurationController::assignMappingCodes.
     *
     * Rates are not linked to rooms in local tables; the nested rates list under
     * each room type mirrors TYPO3 (same non-package rates repeated for UX).
     *
     * @param array<string, mixed> $config
     * @return array{
     *     roomTypes: array<int, array<string, mixed>>,
     *     rates: array<int, array<string, mixed>>,
     *     packages: array<int, array<string, mixed>>,
     *     companyId: string,
     *     spaceId: string,
     *     tenantId: string,
     *     empty: bool
     * }
     */
    public function getMappingCodes(string $siteId, array $config = []): array
    {
        $roomTypes = (new RoomTypeRepository())->findForSite($siteId);
        $rates = [];
        foreach ((new RateRepository())->findNonPackagesForSite($siteId) as $rate) {
            $rate['display_type'] = RateDisplayType::fromRow($rate);
            $rates[] = $rate;
        }
        $packages = [];
        foreach ((new RateRepository())->findPackagesForSite($siteId) as $package) {
            $package['display_type'] = RateDisplayType::fromRow($package);
            $packages[] = $package;
        }

        $companyId = '';
        if ($roomTypes !== []) {
            $companyId = (string) ($roomTypes[0]['company_id'] ?? '');
        }

        return [
            'roomTypes' => $roomTypes,
            'rates' => $rates,
            'packages' => $packages,
            'companyId' => $companyId,
            'spaceId' => (string) ($config['url_friendly_ibe_context_id'] ?? ''),
            'tenantId' => (string) ($config['tenant_id'] ?? ''),
            'empty' => $roomTypes === [] && $rates === [] && $packages === [],
        ];
    }
}
