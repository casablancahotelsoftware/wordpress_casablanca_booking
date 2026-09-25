<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Repository;

use Casablanca\Booking\Domain\IbeLinkStyle;
use Casablanca\Booking\Domain\Utility\ThemeCssSanitizer;
use Casablanca\Booking\Infrastructure\Encryption;
use Casablanca\Booking\Infrastructure\SiteIdentifier;
use RuntimeException;

final class ConfigurationRepository
{
    public const TABLE = 'casablanca_configuration';

    private const DEFAULT_SYNC_RANGE_DAYS = 365;
    private const DEFAULT_SYNC_CHUNK_DAYS = 31;
    private const DEFAULT_PAGINATION_TOP = 100;

    public function __construct(
        private readonly Encryption $encryption = new Encryption()
    ) {}

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySiteIdentifier(string $siteIdentifier): ?array
    {
        global $wpdb;

        if ($siteIdentifier === '') {
            return null;
        }

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE site_identifier = %s LIMIT 1', $table, $siteIdentifier),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function findCurrent(): ?array
    {
        return $this->findBySiteIdentifier(SiteIdentifier::current());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        global $wpdb;

        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; no object-cache group.
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i ORDER BY site_identifier ASC', $table),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function decryptApiKey(string $encrypted): string
    {
        return $this->encryption->decrypt($encrypted);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): int
    {
        global $wpdb;

        $siteIdentifier = trim((string) ($data['siteIdentifier'] ?? SiteIdentifier::current()));
        $tenantId = trim((string) ($data['tenantId'] ?? ''));

        if ($siteIdentifier === '' || $tenantId === '') {
            throw new RuntimeException('Site identifier and Tenant ID are required.');
        }

        $existing = $this->findBySiteIdentifier($siteIdentifier);
        $apiKeyPlain = trim((string) ($data['apiKey'] ?? ''));
        $apiKeyEncrypted = is_array($existing) ? (string) ($existing['api_key_encrypted'] ?? '') : '';

        if ($apiKeyPlain !== '') {
            $apiKeyEncrypted = $this->encryption->encrypt($apiKeyPlain);
        }

        if ($apiKeyEncrypted === '') {
            throw new RuntimeException('API key is required.');
        }

        $useCustomIbe = ! empty($data['useCustomIbeDomain']);
        $ibeBaseUrl = $useCustomIbe
            ? rtrim(trim((string) ($data['ibeBaseUrl'] ?? '')), '/')
            : 'https://bookingengine.casablanca.at';

        if ($ibeBaseUrl === '') {
            throw new RuntimeException('IBE base URL is required when using a custom domain.');
        }

        $spaceName = trim((string) ($data['urlFriendlyIbeContextId'] ?? 'bookingengine'));
        $ibeLinkStyle = IbeLinkStyle::normalize((string) ($data['ibeLinkStyle'] ?? IbeLinkStyle::FULL_PATH));

        if ($ibeLinkStyle === IbeLinkStyle::FULL_PATH && $spaceName === '') {
            throw new RuntimeException('Space name is required when using the full path link style.');
        }

        $row = [
            'site_identifier' => $siteIdentifier,
            'tenant_id' => $tenantId,
            'url_friendly_ibe_context_id' => $spaceName,
            'api_key_encrypted' => $apiKeyEncrypted,
            'api_base_url' => rtrim(trim((string) ($data['apiBaseUrl'] ?? 'https://api.casablanca.at')), '/'),
            'ibe_base_url' => $ibeBaseUrl,
            'ibe_link_style' => $ibeLinkStyle,
            'use_custom_ibe_domain' => $useCustomIbe ? 1 : 0,
            'service_path' => trim((string) ($data['servicePath'] ?? 'ibe')),
            'default_culture' => trim((string) ($data['defaultCulture'] ?? 'de')),
            'sync_range_days' => self::DEFAULT_SYNC_RANGE_DAYS,
            'sync_chunk_days' => self::DEFAULT_SYNC_CHUNK_DAYS,
            'pagination_top' => self::DEFAULT_PAGINATION_TOP,
            'default_adults' => max(1, (int) ($data['defaultAdults'] ?? 2)),
            'default_children_ages' => trim((string) ($data['defaultChildrenAges'] ?? '')),
            'theme_css' => ThemeCssSanitizer::sanitize(trim((string) ($data['themeCss'] ?? ''))),
            'updated_at' => time(),
        ];

        if (is_array($existing)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
            $wpdb->update($this->table(), $row, ['id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        $row['created_at'] = time();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table write.
        $wpdb->insert($this->table(), $row);

        return (int) $wpdb->insert_id;
    }

    public function updateConnectionStatus(string $siteIdentifier, string $status, string $message): void
    {
        global $wpdb;

        if ($siteIdentifier === '') {
            return;
        }

        $allowed = ['unknown', 'ok', 'error'];
        if (! in_array($status, $allowed, true)) {
            $status = 'unknown';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table write.
        $wpdb->update(
            $this->table(),
            [
                'connection_status' => $status,
                'connection_checked_at' => time(),
                'connection_message' => $message,
                'updated_at' => time(),
            ],
            ['site_identifier' => $siteIdentifier]
        );
    }
}
