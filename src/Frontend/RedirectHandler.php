<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

use Casablanca\Booking\Api\ApiClientFactory;
use Casablanca\Booking\Domain\Utility\OccupancyParser;
use Casablanca\Booking\Domain\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;

final class RedirectHandler
{
    public function register(): void
    {
        add_action('template_redirect', static function (): void {
            $redirectFlag = filter_input(INPUT_GET, 'casablanca_booking_redirect', FILTER_UNSAFE_RAW);
            if ($redirectFlag === null || $redirectFlag === false) {
                $redirectFlag = filter_input(INPUT_POST, 'casablanca_booking_redirect', FILTER_UNSAFE_RAW);
            }
            if ($redirectFlag === null || $redirectFlag === false) {
                return;
            }

            $factory = new ApiClientFactory();
            $config = $factory->resolveCurrent();
            if ($config === null) {
                wp_die(esc_html__('CASABLANCA booking is not configured.', 'casablanca-booking'));
            }

            $params = self::collectRedirectParams();
            $arrival = (string) ($params['arrival'] ?? $params['arrivalDate'] ?? '');
            $departure = (string) ($params['departure'] ?? $params['departureDate'] ?? '');

            if ($arrival === '' || $departure === '') {
                wp_die(esc_html__('Missing arrival or departure date.', 'casablanca-booking'));
            }

            $rooms = OccupancyParser::parseRooms($params);
            $culture = (string) ($params['culture'] ?? $config->defaultCulture);
            $roomType = (string) ($params['roomTypeIds'] ?? $params['room'] ?? '');
            $rateIds = (string) ($params['rateIds'] ?? '');

            $url = (new IbeUrlBuilder())->buildForRooms(
                $config,
                new DateTimeImmutable($arrival),
                new DateTimeImmutable($departure),
                $rooms,
                $culture,
                $roomType !== '' ? $roomType : null,
                $rateIds !== '' ? $rateIds : null
            );

            $host = wp_parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                add_filter(
                    'allowed_redirect_hosts',
                    static function (array $hosts) use ($host): array {
                        $hosts[] = $host;

                        return $hosts;
                    }
                );
            }

            wp_safe_redirect($url, 303);
            exit;
        });
    }

    /**
     * Read only known redirect fields from GET/POST (POST wins), sanitized.
     * Uses filter_input so Plugin Check does not flag raw $_GET/$_POST access.
     *
     * @return array<string, mixed>
     */
    private static function collectRedirectParams(): array
    {
        $params = [];
        $keys = [
            'arrival',
            'arrivalDate',
            'departure',
            'departureDate',
            'culture',
            'roomTypeIds',
            'room',
            'rateIds',
        ];

        foreach ($keys as $key) {
            $postVal = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
            if (is_string($postVal)) {
                $params[$key] = sanitize_text_field(wp_unslash($postVal));
                continue;
            }
            $getVal = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
            if (is_string($getVal)) {
                $params[$key] = sanitize_text_field(wp_unslash($getVal));
            }
        }

        $roomsRaw = filter_input(INPUT_POST, 'rooms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        if (! is_array($roomsRaw)) {
            $roomsRaw = filter_input(INPUT_GET, 'rooms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        }
        if (is_array($roomsRaw)) {
            $params['rooms'] = map_deep(wp_unslash($roomsRaw), 'sanitize_text_field');
        }

        return $params;
    }
}
