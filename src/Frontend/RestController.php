<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

use Casablanca\Booking\Api\ApiClientFactory;
use Casablanca\Booking\Domain\Repository\RateRepository;
use Casablanca\Booking\Domain\Repository\RoomTypeRepository;
use Casablanca\Booking\Domain\Utility\OccupancyParser;
use Casablanca\Booking\Domain\UrlBuilder\IbeUrlBuilder;
use Casablanca\Booking\Service\BookingOffer\BookingOffersFetchService;
use Casablanca\Booking\Service\Calendar\CalendarFetchService;
use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

final class RestController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('casablanca-booking/v1', '/calendar', [
            'methods' => 'GET',
            'callback' => [$this, 'calendar'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('casablanca-booking/v1', '/offers', [
            'methods' => 'GET',
            'callback' => [$this, 'offers'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('casablanca-booking/v1', '/catalog', [
            'methods' => 'GET',
            'callback' => [$this, 'catalog'],
            'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
        ]);

        register_rest_route('casablanca-booking/v1', '/redirect', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'redirect'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function calendar(WP_REST_Request $request): WP_REST_Response
    {
        $factory = new ApiClientFactory();
        $config = $factory->resolveCurrent();
        if ($config === null) {
            return new WP_REST_Response(['error' => 'not_configured'], 503);
        }

        $windowDays = max(7, min(180, (int) $request->get_param('windowDays') ?: 90));
        $from = new DateTimeImmutable('today');
        $until = $from->modify('+' . $windowDays . ' days');
        $rooms = OccupancyParser::parseRooms((array) $request->get_params());
        $culture = (string) ($request->get_param('culture') ?: $config->defaultCulture);

        $stayFilter = [
            'companyIdentifiers' => [],
            'roomTypeIds' => [],
            'rateIds' => [],
        ];
        $room = (string) $request->get_param('room');
        if ($room !== '') {
            $stayFilter['roomTypeIds'] = [$room];
        }

        $client = $factory->createCalendarDatesClient($config);
        $rateIds = array_filter(array_map('trim', explode(',', (string) $request->get_param('rateIds'))));
        $payload = (new CalendarFetchService())->fetchRange(
            $client,
            $config,
            $from,
            $until,
            $rooms,
            $room !== '' ? $room : null,
            $rateIds,
            $culture
        );

        return new WP_REST_Response($payload);
    }

    public function offers(WP_REST_Request $request): WP_REST_Response
    {
        $factory = new ApiClientFactory();
        $config = $factory->resolveCurrent();
        if ($config === null) {
            return new WP_REST_Response(['error' => 'not_configured'], 503);
        }

        $offerMode = BookingOffersFetchService::normaliseOfferMode(
            (string) ($request->get_param('offerMode') ?: '')
        );
        if ($offerMode === BookingOffersFetchService::MODE_NONE) {
            return new WP_REST_Response([
                'offerMode' => BookingOffersFetchService::MODE_NONE,
                'offers' => [],
                'lowestTotalPrice' => null,
                'currency' => 'EUR',
            ]);
        }

        $arrival = trim((string) $request->get_param('arrival'));
        $departure = trim((string) $request->get_param('departure'));
        if ($arrival === '' || $departure === '' || $departure <= $arrival) {
            return new WP_REST_Response([
                'error' => 'missing_dates',
                'message' => 'Arrival and departure dates are required.',
            ], 400);
        }

        $rooms = OccupancyParser::parseRooms((array) $request->get_params());
        $occupancy = $rooms[0];
        $culture = (string) ($request->get_param('culture') ?: $config->defaultCulture);
        $roomTypeId = trim((string) ($request->get_param('room') ?: ''));
        $rateIds = array_values(array_filter(
            array_map('trim', explode(',', (string) ($request->get_param('rateIds') ?: ''))),
            static fn (string $id): bool => $id !== ''
        ));

        try {
            $payload = (new BookingOffersFetchService())->fetch(
                $factory->createBookingOffersClient($config),
                $config,
                new DateTimeImmutable($arrival),
                new DateTimeImmutable($departure),
                $occupancy,
                $offerMode,
                $roomTypeId !== '' ? $roomTypeId : null,
                $rateIds,
                $culture
            );

            return new WP_REST_Response($payload);
        } catch (\Throwable $e) {
            $message = trim($e->getMessage());
            if ($message === '') {
                $message = 'Offers fetch failed.';
            }

            return new WP_REST_Response([
                'error' => 'fetch_failed',
                'message' => $message,
            ], 502);
        }
    }

    public function catalog(): WP_REST_Response
    {
        $factory = new ApiClientFactory();
        $config = $factory->resolveCurrent();
        if ($config === null) {
            return new WP_REST_Response(['roomTypes' => [], 'packages' => []]);
        }

        return new WP_REST_Response([
            'roomTypes' => (new RoomTypeRepository())->getOptionsForSite($config->siteIdentifier),
            'packages' => (new RateRepository())->getPackageOptionsForSite($config->siteIdentifier),
        ]);
    }

    public function redirect(WP_REST_Request $request): WP_REST_Response
    {
        $factory = new ApiClientFactory();
        $config = $factory->resolveCurrent();
        if ($config === null) {
            return new WP_REST_Response(['error' => 'not_configured'], 503);
        }

        $arrival = (string) ($request->get_param('arrival') ?: $request->get_param('arrivalDate'));
        $departure = (string) ($request->get_param('departure') ?: $request->get_param('departureDate'));
        if ($arrival === '' || $departure === '') {
            return new WP_REST_Response(['error' => 'missing_dates'], 400);
        }

        $rooms = OccupancyParser::parseRooms((array) $request->get_params());
        $culture = (string) ($request->get_param('culture') ?: $config->defaultCulture);
        $roomType = (string) $request->get_param('roomTypeIds');
        $rateIds = (string) $request->get_param('rateIds');

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
    }
}
