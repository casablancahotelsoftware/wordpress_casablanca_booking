<?php

declare(strict_types=1);

namespace Casablanca\Booking\Infrastructure;

final class StagingHostResolver
{
    public const MODE_PRODUCTION = 'production';
    public const MODE_STAGING = 'staging';
    public const MODE_DEVELOPMENT = 'development';

    public const PRODUCTION_API_HOST = 'api.casablanca.at';
    public const STAGING_API_HOST = 'staging-api.casablanca.at';
    public const DEV_API_HOST = 'dev-api.casablanca.at';
    public const PRODUCTION_IBE_HOST = 'bookingengine.casablanca.at';
    public const STAGING_IBE_HOST = 'staging-bookingengine.casablanca.at';
    public const DEV_IBE_HOST = 'dev-bookingengine.casablanca.at';

    public function getEnvironmentMode(): string
    {
        if (! defined('CASABLANCA_BOOKING_ENV')) {
            return self::MODE_PRODUCTION;
        }

        $mode = strtolower(trim((string) CASABLANCA_BOOKING_ENV));
        if (in_array($mode, [self::MODE_STAGING, self::MODE_DEVELOPMENT], true)) {
            return $mode;
        }

        return self::MODE_PRODUCTION;
    }

    public function isAlternateEnvironmentEnabled(): bool
    {
        return $this->getEnvironmentMode() !== self::MODE_PRODUCTION;
    }

    public function applyToApiUrl(string $url): string
    {
        $targetHost = $this->resolveApiTargetHost();

        return $targetHost === null ? $url : $this->replaceHost($url, self::PRODUCTION_API_HOST, $targetHost);
    }

    public function applyToIbeUrl(string $url): string
    {
        $targetHost = $this->resolveIbeTargetHost();

        return $targetHost === null ? $url : $this->replaceHost($url, self::PRODUCTION_IBE_HOST, $targetHost);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function applyToSettings(array $settings): array
    {
        if (! $this->isAlternateEnvironmentEnabled()) {
            return $settings;
        }

        if (isset($settings['apiBaseUrl']) && is_string($settings['apiBaseUrl'])) {
            $settings['apiBaseUrl'] = $this->applyToApiUrl($settings['apiBaseUrl']);
        }
        if (isset($settings['ibeBaseUrl']) && is_string($settings['ibeBaseUrl'])) {
            $settings['ibeBaseUrl'] = $this->applyToIbeUrl($settings['ibeBaseUrl']);
        }

        return $settings;
    }

    private function resolveApiTargetHost(): ?string
    {
        return match ($this->getEnvironmentMode()) {
            self::MODE_STAGING => self::STAGING_API_HOST,
            self::MODE_DEVELOPMENT => self::DEV_API_HOST,
            default => null,
        };
    }

    private function resolveIbeTargetHost(): ?string
    {
        return match ($this->getEnvironmentMode()) {
            self::MODE_STAGING => self::STAGING_IBE_HOST,
            self::MODE_DEVELOPMENT => self::DEV_IBE_HOST,
            default => null,
        };
    }

    private function replaceHost(string $url, string $fromHost, string $toHost): string
    {
        $parts = wp_parse_url($url);
        if (! is_array($parts) || ! isset($parts['host']) || $parts['host'] !== $fromHost) {
            return $url;
        }

        $parts['host'] = $toHost;

        return $this->buildUrl($parts);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }
}
