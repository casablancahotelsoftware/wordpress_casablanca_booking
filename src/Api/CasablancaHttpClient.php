<?php

declare(strict_types=1);

namespace Casablanca\Booking\Api;

use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use RuntimeException;

final class CasablancaHttpClient
{
    public function __construct(
        private readonly SiteConfigurationDto $config
    ) {}

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function getJson(string $path, array $query = []): array
    {
        return $this->sendJson('GET', $path, $query, null);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function postJson(string $path, array $body, array $query = []): array
    {
        return $this->sendJson('POST', $path, $query, $body);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return iterable<int, array<int, array<string, mixed>>>
     */
    public function paginate(string $path, array $query = []): iterable
    {
        $skip = 0;
        $top = $this->config->paginationTop;

        do {
            $pageQuery = $query;
            $pageQuery['$skip'] = $skip;
            $pageQuery['$top'] = $top;
            $page = $this->getJson($path, $pageQuery);

            if (! self::isList($page) && array_key_exists('values', $page)) {
                $values = (array) $page['values'];
                if ($values === []) {
                    break;
                }
                yield $values;

                if (! isset($page['@odata.nextLink']) || $page['@odata.nextLink'] === '') {
                    break;
                }
                $skip += count($values);
                continue;
            }

            yield is_array($page) ? $page : [];
            break;
        } while (true);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    private function sendJson(string $method, string $path, array $query, ?array $body): array
    {
        $url = $this->buildUrl($path, $query);
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'CASABLANCA-WP-Booking/1.0',
            ],
        ];

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Failed to encode CASABLANCA request body.');
            }
            $args['body'] = $json;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $transport_error = $response->get_error_message();
            throw new RuntimeException(
                'CASABLANCA API transport failure: ' . esc_html($transport_error)
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);

        if ($status >= 400) {
            throw new RuntimeException(
                esc_html(
                    sprintf(
                        'CASABLANCA API error %d on %s %s',
                        $status,
                        $method,
                        $path
                    )
                )
            );
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            throw new RuntimeException(
                'CASABLANCA API returned invalid JSON: ' . esc_html($json_error)
            );
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('CASABLANCA API returned a non-array JSON root.');
        }

        return $decoded;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $normalisedPath = '/' . ltrim($path, '/');
        $servicePrefix = $this->config->servicePath !== ''
            ? '/' . rawurlencode($this->config->servicePath)
            : '';

        $url = sprintf(
            '%s%s/%s/%s%s',
            $this->config->apiBaseUrl,
            $servicePrefix,
            rawurlencode($this->config->tenantId),
            rawurlencode($this->config->urlFriendlyIbeContextId),
            $normalisedPath
        );

        $filtered = array_filter(
            $query,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }

        return $url;
    }

    /**
     * @param mixed $value
     */
    public static function isList($value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
