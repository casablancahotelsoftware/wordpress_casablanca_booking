<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

use Casablanca\Booking\Api\ApiClientFactory;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Repository\AvailabilityRepository;
use Casablanca\Booking\Domain\Repository\RateRepository;
use Casablanca\Booking\Domain\Repository\RoomTypeRepository;
use Casablanca\Booking\Domain\Utility\DescriptionPresenter;
use Casablanca\Booking\Domain\Utility\ThemeCssSanitizer;
use Casablanca\Booking\Domain\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;

final class WidgetRenderer
{
    public function __construct(
        private readonly ApiClientFactory $apiClientFactory = new ApiClientFactory(),
        private readonly LabelResolver $labelResolver = new LabelResolver(),
        private readonly IbeUrlBuilder $ibeUrlBuilder = new IbeUrlBuilder(),
        private readonly AvailabilityRepository $availabilityRepository = new AvailabilityRepository(),
        private readonly RoomTypeRepository $roomTypeRepository = new RoomTypeRepository(),
        private readonly RateRepository $rateRepository = new RateRepository(),
        private readonly AssetManager $assetManager = new AssetManager(),
        private readonly DetailRewrite $detailRewrite = new DetailRewrite()
    ) {}

    /**
     * @param array<string, mixed> $attrs
     */
    public function render(string $widget, array $attrs = []): string
    {
        $config = $this->apiClientFactory->resolveCurrent();
        $attrs = $this->normalizeAttributes($attrs);
        $language = WidgetLanguage::resolve((string) $attrs['language']);
        $this->labelResolver->setLanguage($language);

        if ($config === null) {
            return $this->renderError(
                $this->labelResolver->resolve(
                    'widget.error.notConfigured',
                    'The CASABLANCA booking widget is not configured for this site.'
                )
            );
        }

        $includeCss = ! isset($attrs['includeCss']) || (bool) $attrs['includeCss'];
        $this->assetManager->enqueueWidgetAssets($includeCss);

        $context = $this->buildBaseContext($config, $attrs, $language);

        return match ($widget) {
            'search_bar' => $this->renderSearchBar($context, $attrs),
            'calendar' => $this->renderCalendar($context, $attrs),
            'room_types' => $this->renderRoomTypes($context, $attrs),
            'packages' => $this->renderPackages($context, $attrs),
            'room_detail' => $this->renderRoomDetail($context, $attrs),
            'package_detail' => $this->renderPackageDetail($context, $attrs),
            'price_teaser' => $this->renderPriceTeaser($context, $attrs),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attrs): array
    {
        $defaults = [
            'appearance' => 'default',
            'language' => '',
            'defaultRooms' => 1,
            'defaultAdults' => 2,
            'defaultChildren' => 0,
            'defaultChildrenAges' => '',
            'compactOccupancy' => true,
            'ibeLinkTarget' => '_self',
            'windowDays' => 90,
            'calendarInitialMonths' => 1,
            'calendarOfferMode' => 'none',
            'showEnquiryButton' => false,
            'enquiryUrl' => '',
            'enquiryLinkTarget' => '_self',
            'preselectRoomCategory' => '',
            'filterRoomType' => '',
            'filterPackage' => '',
            'showName' => true,
            'showDescription' => true,
            'overviewDescriptionMode' => 'teaser',
            'overviewDescriptionLimit' => 250,
            'overviewLayout' => 'grid',
            'showPrice' => true,
            'showImage' => true,
            'cardLinkType' => 'book',
            'detailPageId' => 0,
            'showDetailCalendar' => false,
            'detailCalendarPosition' => 'below',
            'detailCalendarInitialMonths' => 1,
            'detailCalendarOfferMode' => 'rates_only',
            'stayNights' => 7,
            'filterByStayNights' => true,
            'fallbackRoomType' => '',
            'fallbackPackage' => '',
            'roomType' => '',
            'themeAccent' => '',
            'themeRadius' => '',
            'includeCss' => true,
            'labels' => [],
        ];

        $merged = array_merge($defaults, $attrs);

        $merged['windowDays'] = max(7, min(180, (int) $merged['windowDays']));
        $merged['stayNights'] = max(1, min(30, (int) $merged['stayNights']));
        $merged['defaultRooms'] = max(1, min(5, (int) $merged['defaultRooms']));
        $merged['defaultAdults'] = max(1, min(10, (int) $merged['defaultAdults']));
        $merged['defaultChildren'] = max(0, min(10, (int) $merged['defaultChildren']));
        $merged['defaultChildrenAges'] = trim((string) $merged['defaultChildrenAges']);
        $merged['overviewDescriptionLimit'] = max(50, min(2000, (int) $merged['overviewDescriptionLimit']));
        $merged['detailPageId'] = max(0, (int) $merged['detailPageId']);
        $merged['detailCalendarInitialMonths'] = in_array((int) $merged['detailCalendarInitialMonths'], [1, 2], true)
            ? (int) $merged['detailCalendarInitialMonths']
            : 1;
        $merged['calendarInitialMonths'] = in_array((int) $merged['calendarInitialMonths'], [1, 2], true)
            ? (int) $merged['calendarInitialMonths']
            : 1;

        if (! in_array($merged['overviewLayout'], ['grid', 'list'], true)) {
            $merged['overviewLayout'] = 'grid';
        }
        if (! in_array($merged['overviewDescriptionMode'], ['teaser', 'full'], true)) {
            $merged['overviewDescriptionMode'] = 'teaser';
        }
        if (! in_array($merged['cardLinkType'], ['book', 'details'], true)) {
            $merged['cardLinkType'] = 'book';
        }
        if (! in_array($merged['ibeLinkTarget'], ['_self', '_blank'], true)) {
            $merged['ibeLinkTarget'] = '_self';
        }
        if (! in_array($merged['enquiryLinkTarget'], ['_self', '_blank'], true)) {
            $merged['enquiryLinkTarget'] = '_self';
        }
        if (! in_array($merged['detailCalendarPosition'], ['above', 'below'], true)) {
            $merged['detailCalendarPosition'] = 'below';
        }
        $offerModes = ['none', 'rates_only', 'packages_only', 'packages_and_rates'];
        if (! in_array($merged['detailCalendarOfferMode'], $offerModes, true)) {
            $merged['detailCalendarOfferMode'] = 'rates_only';
        }
        if (! in_array($merged['calendarOfferMode'], $offerModes, true)) {
            $merged['calendarOfferMode'] = 'none';
        }
        if (! in_array($merged['appearance'], ['default', 'inherit', 'compact'], true)) {
            $merged['appearance'] = 'default';
        }
        $language = strtolower(trim((string) $merged['language']));
        if ($language === 'auto') {
            $language = '';
        }
        if (! in_array($language, ['', 'de', 'en', 'it', 'fr'], true)) {
            $language = '';
        }
        $merged['language'] = $language;

        foreach ([
            'showName',
            'showDescription',
            'showPrice',
            'showImage',
            'showDetailCalendar',
            'filterByStayNights',
            'compactOccupancy',
            'showEnquiryButton',
        ] as $boolKey) {
            $merged[$boolKey] = (bool) $merged[$boolKey];
        }

        $merged['enquiryUrl'] = trim((string) $merged['enquiryUrl']);
        $merged['preselectRoomCategory'] = trim((string) $merged['preselectRoomCategory']);

        return $merged;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function buildBaseContext(
        \Casablanca\Booking\Domain\Dto\SiteConfigurationDto $config,
        array $attrs,
        string $language
    ): array {
        $appearance = $attrs['appearance'];
        $themeCss = ThemeCssSanitizer::sanitize(
            (string) apply_filters('casablanca_booking_theme_css', $config->themeCss)
        );

        $themeStyle = '';
        if ($attrs['themeAccent'] !== '') {
            $themeStyle .= '--cb-accent:' . esc_attr((string) $attrs['themeAccent']) . ';';
        }
        if ($attrs['themeRadius'] !== '') {
            $themeStyle .= '--cb-radius:' . esc_attr((string) $attrs['themeRadius']) . ';';
        }

        $labels = is_array($attrs['labels']) ? $attrs['labels'] : [];
        $cbLabels = $this->labelResolver->buildJavaScriptLabels($labels);

        return [
            'config' => $config,
            'appearance' => $appearance,
            'language' => $language,
            'culture' => $language,
            'lr' => $this->labelResolver,
            'themeCss' => $themeCss,
            'themeStyle' => $themeStyle,
            'labels' => $labels,
            'cbLabelsJson' => wp_json_encode($cbLabels),
            'widgetClasses' => 'cb-widget cb-widget--' . $appearance,
            'restCalendar' => esc_url_raw(rest_url('casablanca-booking/v1/calendar')),
            'restOffers' => esc_url_raw(rest_url('casablanca-booking/v1/offers')),
            'redirectUrl' => esc_url_raw(add_query_arg('casablanca_booking_redirect', '1', home_url('/'))),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderSearchBar(array $context, array $attrs): string
    {
        $arrival = (new DateTimeImmutable('today'))->modify('+2 days');
        $departure = $arrival->modify('+7 days');
        $rooms = $this->buildDefaultRooms($attrs);

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/search-bar.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderCalendar(array $context, array $attrs): string
    {
        $rooms = $this->buildDefaultRooms($attrs);

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/calendar.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderRoomTypes(array $context, array $attrs): string
    {
        $config = $context['config'];
        $rooms = $this->roomTypeRepository->findForSite($config->siteIdentifier, $attrs['filterRoomType'] ?: null);
        $from = new DateTimeImmutable('today');
        $until = $from->modify('+' . ((int) $attrs['windowDays'] - 1) . ' days');
        $stayNights = (int) $attrs['stayNights'];
        $arrival = $from;
        $departure = $from->modify('+' . $stayNights . ' days');
        $occupancy = $this->buildOccupancy($attrs, $config->defaultOccupancy);

        if ((int) $attrs['detailPageId'] > 0) {
            $this->detailRewrite->ensurePageRegistered((int) $attrs['detailPageId'], 'room');
        }

        $cards = [];
        foreach ($rooms as $room) {
            $cards[] = $this->buildRoomCard($config, $context, $attrs, $room, $from, $until, $arrival, $departure, $occupancy);
        }

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/room-types.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderPackages(array $context, array $attrs): string
    {
        $config = $context['config'];
        $packages = $this->rateRepository->findPackagesForSite($config->siteIdentifier, $attrs['filterPackage'] ?: null);
        $from = new DateTimeImmutable('today');
        $until = $from->modify('+' . ((int) $attrs['windowDays'] - 1) . ' days');
        $stayNights = (int) $attrs['stayNights'];
        $arrival = $from;
        $departure = $from->modify('+' . $stayNights . ' days');
        $occupancy = $this->buildOccupancy($attrs, $config->defaultOccupancy, false);

        if ($attrs['filterByStayNights']) {
            $packages = array_values(array_filter(
                $packages,
                function (array $package) use ($config, $from, $until, $stayNights): bool {
                    $bookable = $this->availabilityRepository->findBookablePackageNights(
                        $config->siteIdentifier,
                        $from,
                        $until,
                        (string) $package['rate_id']
                    );

                    return $this->packageSupportsStayLength($bookable, $stayNights);
                }
            ));
        }

        if ((int) $attrs['detailPageId'] > 0) {
            $this->detailRewrite->ensurePageRegistered((int) $attrs['detailPageId'], 'package');
        }

        $cards = [];
        foreach ($packages as $package) {
            $cards[] = $this->buildPackageCard($config, $context, $attrs, $package, $from, $until, $arrival, $departure, $occupancy);
        }

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/packages.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderRoomDetail(array $context, array $attrs): string
    {
        $config = $context['config'];

        $pageId = (int) get_queried_object_id();
        if ($pageId > 0) {
            $this->detailRewrite->ensurePageRegistered($pageId, 'room');
        }

        $room = $this->resolveRoomForDetail($config->siteIdentifier, $attrs);
        $from = new DateTimeImmutable('today');
        $until = $from->modify('+' . ((int) $attrs['windowDays'] - 1) . ' days');
        $stayNights = (int) $attrs['stayNights'];
        $arrival = $from;
        $departure = $from->modify('+' . $stayNights . ' days');
        $occupancy = $this->buildOccupancy($attrs, $config->defaultOccupancy);

        $cards = [];
        $error = null;
        if ($room === null) {
            $error = 'room_not_found';
        } else {
            $cards[] = $this->buildRoomCard($config, $context, $attrs, $room, $from, $until, $arrival, $departure, $occupancy);
        }

        $detailCalendar = $this->buildDetailCalendarContext($context, $attrs, $cards, false);

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/room-detail.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderPackageDetail(array $context, array $attrs): string
    {
        $config = $context['config'];

        $pageId = (int) get_queried_object_id();
        if ($pageId > 0) {
            $this->detailRewrite->ensurePageRegistered($pageId, 'package');
        }

        $package = $this->resolvePackageForDetail($config->siteIdentifier, $attrs);
        $from = new DateTimeImmutable('today');
        $until = $from->modify('+' . ((int) $attrs['windowDays'] - 1) . ' days');
        $stayNights = (int) $attrs['stayNights'];
        $arrival = $from;
        $departure = $from->modify('+' . $stayNights . ' days');
        $occupancy = $this->buildOccupancy($attrs, $config->defaultOccupancy, false);

        $cards = [];
        $error = null;
        if ($package === null) {
            $error = 'package_not_found';
        } else {
            $cards[] = $this->buildPackageCard($config, $context, $attrs, $package, $from, $until, $arrival, $departure, $occupancy);
        }

        $detailCalendar = $this->buildDetailCalendarContext($context, $attrs, $cards, true);

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/package-detail.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     */
    private function renderPriceTeaser(array $context, array $attrs): string
    {
        $config = $context['config'];
        $from = new DateTimeImmutable('today');
        $until = $from->modify('+' . max(7, (int) $attrs['windowDays']) . ' days');
        $roomTypeId = (string) $attrs['roomType'];
        $price = $this->availabilityRepository->findCheapestPrice(
            $config->siteIdentifier,
            $from,
            $until,
            $roomTypeId !== '' ? $roomTypeId : null
        );

        $roomName = '';
        if ($roomTypeId !== '') {
            $rows = $this->roomTypeRepository->findForSite($config->siteIdentifier, $roomTypeId);
            $roomName = isset($rows[0]['name']) ? (string) $rows[0]['name'] : '';
        }

        $jsonLd = null;
        if ($price !== null) {
            $jsonLd = wp_json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'Hotel', 'name' => get_bloginfo('name')],
                    [
                        '@type' => 'Offer',
                        'price' => $price['from_price'],
                        'priceCurrency' => $price['currency'],
                        'availability' => 'https://schema.org/InStock',
                    ],
                ],
            ]);
        }

        ob_start();
        include CASABLANCA_BOOKING_PATH . 'templates/price-teaser.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>|null
     */
    private function resolveRoomForDetail(string $siteIdentifier, array $attrs): ?array
    {
        // Prefer URL slug (/detail-page/{slug}/) from query var or path — no fallback id required.
        $slug = $this->detailRewrite->resolveRoomSlug();
        if ($slug !== '') {
            return $this->roomTypeRepository->findOneBySiteAndSlug($siteIdentifier, $slug);
        }

        $fallbackId = trim((string) ($attrs['fallbackRoomType'] ?: $attrs['filterRoomType']));
        if ($fallbackId === '') {
            return null;
        }

        $rows = $this->roomTypeRepository->findForSite($siteIdentifier, $fallbackId);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>|null
     */
    private function resolvePackageForDetail(string $siteIdentifier, array $attrs): ?array
    {
        // Prefer URL slug (/detail-page/{slug}/) from query var or path — no fallback id required.
        $slug = $this->detailRewrite->resolvePackageSlug();
        if ($slug !== '') {
            return $this->rateRepository->findOneBySiteAndSlug($siteIdentifier, $slug);
        }

        $fallbackId = trim((string) ($attrs['fallbackPackage'] ?: $attrs['filterPackage']));
        if ($fallbackId === '') {
            return null;
        }

        $rows = $this->rateRepository->findPackagesForSite($siteIdentifier, $fallbackId);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     * @param array<string, mixed> $room
     * @return array<string, mixed>
     */
    private function buildRoomCard(
        $config,
        array $context,
        array $attrs,
        array $room,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        DateTimeImmutable $arrival,
        DateTimeImmutable $departure,
        RoomOccupancyDto $occupancy
    ): array {
        $price = $this->availabilityRepository->findCheapestPrice(
            $config->siteIdentifier,
            $from,
            $until,
            (string) $room['room_type_id']
        );

        $ibeUrl = $this->ibeUrlBuilder->build(
            $config,
            $arrival,
            $departure,
            $occupancy,
            (string) $room['room_type_id'],
            (string) $context['culture']
        );

        $detailUrl = '';
        if (
            $attrs['cardLinkType'] === 'details'
            && (int) $attrs['detailPageId'] > 0
            && ($room['slug'] ?? '') !== ''
        ) {
            $detailUrl = $this->detailRewrite->buildDetailUrl((int) $attrs['detailPageId'], (string) $room['slug']);
        }

        $images = $this->decodeImages($room['images'] ?? null);

        return [
            'item' => $room,
            'fromPrice' => $price['from_price'] ?? null,
            'currency' => $price['currency'] ?? 'EUR',
            'ibeUrl' => $ibeUrl,
            'detailUrl' => $detailUrl,
            'descriptionOverview' => DescriptionPresenter::buildOverviewPresentation(
                (string) ($room['short_description'] ?? ''),
                (string) ($room['description'] ?? ''),
                (string) $attrs['overviewDescriptionMode'],
                (int) $attrs['overviewDescriptionLimit']
            ),
            'carouselImages' => DescriptionPresenter::normalizeCarouselImages(
                $images,
                (string) ($room['image_url'] ?? '')
            ),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function buildPackageCard(
        $config,
        array $context,
        array $attrs,
        array $package,
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        DateTimeImmutable $arrival,
        DateTimeImmutable $departure,
        RoomOccupancyDto $occupancy
    ): array {
        $price = $this->availabilityRepository->findCheapestPrice(
            $config->siteIdentifier,
            $from,
            $until,
            null,
            (string) $package['rate_id']
        );

        $ibeUrl = $this->ibeUrlBuilder->build(
            $config,
            $arrival,
            $departure,
            $occupancy,
            null,
            (string) $context['culture'],
            (string) $package['rate_id']
        );

        $detailUrl = '';
        if (
            $attrs['cardLinkType'] === 'details'
            && (int) $attrs['detailPageId'] > 0
            && ($package['slug'] ?? '') !== ''
        ) {
            $detailUrl = $this->detailRewrite->buildDetailUrl((int) $attrs['detailPageId'], (string) $package['slug']);
        }

        $images = $this->decodeImages($package['images'] ?? null);

        return [
            'item' => $package,
            'fromPrice' => $price['from_price'] ?? null,
            'currency' => $price['currency'] ?? 'EUR',
            'ibeUrl' => $ibeUrl,
            'detailUrl' => $detailUrl,
            'descriptionOverview' => DescriptionPresenter::buildOverviewPresentation(
                (string) ($package['short_description'] ?? ''),
                (string) ($package['description'] ?? ''),
                (string) $attrs['overviewDescriptionMode'],
                (int) $attrs['overviewDescriptionLimit']
            ),
            'carouselImages' => DescriptionPresenter::normalizeCarouselImages(
                $images,
                (string) ($package['image_url'] ?? '')
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attrs
     * @param array<int, array<string, mixed>> $cards
     * @return array<string, mixed>
     */
    private function buildDetailCalendarContext(array $context, array $attrs, array $cards, bool $isPackage): array
    {
        $show = (bool) $attrs['showDetailCalendar'] && $cards !== [];
        $calendarAttrs = $attrs;
        $calendarAttrs['calendarInitialMonths'] = (int) $attrs['detailCalendarInitialMonths'];
        $calendarAttrs['calendarOfferMode'] = (string) $attrs['detailCalendarOfferMode'];
        $calendarAttrs['preselectRoomCategory'] = '';
        $calendarAttrs['filterRateIds'] = '';
        $calendarAttrs['preselectedRateId'] = '';
        $calendarAttrs['roomMaxOccupancy'] = 0;
        $calendarAttrs['roomMinOccupancy'] = 0;
        $calendarAttrs['showCalendarHeading'] = false;
        $calendarAttrs['calendarLayoutClass'] = 'cb-calendar-layout--detail';

        if ($show && ! $isPackage) {
            $room = $cards[0]['item'];
            $calendarAttrs['preselectRoomCategory'] = (string) ($room['room_type_id'] ?? '');
            $calendarAttrs['roomMaxOccupancy'] = (int) ($room['max_occupancy'] ?? 0);
            $calendarAttrs['roomMinOccupancy'] = (int) ($room['min_occupancy'] ?? 0);
        }
        if ($show && $isPackage) {
            $package = $cards[0]['item'];
            $rateId = (string) ($package['rate_id'] ?? '');
            $calendarAttrs['filterRateIds'] = $rateId;
            $calendarAttrs['preselectedRateId'] = $rateId;
        }

        return [
            'show' => $show,
            'position' => (string) $attrs['detailCalendarPosition'],
            'attrs' => $calendarAttrs,
            'context' => $context,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<int, array{adults: int, children: int, childrenAges: int[], childrenAgesString: string}>
     */
    private function buildDefaultRooms(array $attrs): array
    {
        $roomCount = max(1, min(5, (int) $attrs['defaultRooms']));
        $adults = max(1, min(10, (int) $attrs['defaultAdults']));
        $children = max(0, min(10, (int) $attrs['defaultChildren']));
        $ages = [];
        $rawAges = trim((string) $attrs['defaultChildrenAges']);
        if ($rawAges !== '') {
            $ages = array_values(array_filter(
                array_map('intval', preg_split('/\s*,\s*/', $rawAges) ?: []),
                static fn (int $age): bool => $age >= 0
            ));
        }
        while (count($ages) < $children) {
            $ages[] = 12;
        }
        $ages = array_slice($ages, 0, $children);
        $agesString = implode(', ', $ages);

        $rooms = [];
        for ($i = 0; $i < $roomCount; $i++) {
            $rooms[] = [
                'adults' => $adults,
                'children' => $children,
                'childrenAges' => $ages,
                'childrenAgesString' => $agesString,
            ];
        }

        return $rooms;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function buildOccupancy(array $attrs, RoomOccupancyDto $fallback, bool $useChildrenAttr = true): RoomOccupancyDto
    {
        $adults = max(1, (int) $attrs['defaultAdults']);
        $children = $useChildrenAttr
            ? max(0, (int) $attrs['defaultChildren'])
            : $fallback->getNumberOfChildren();
        $ages = [];
        $rawAges = trim((string) $attrs['defaultChildrenAges']);
        if ($rawAges !== '') {
            $ages = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $rawAges) ?: [])));
        } elseif (! $useChildrenAttr) {
            $ages = $fallback->ageOfChildren;
        }
        while (count($ages) < $children) {
            $ages[] = 12;
        }
        $ages = array_slice($ages, 0, $children);

        return new RoomOccupancyDto($adults, $ages);
    }

    /**
     * @param int[] $bookableNights
     */
    private function packageSupportsStayLength(array $bookableNights, int $stayNights): bool
    {
        if ($bookableNights === []) {
            return true;
        }

        return in_array($stayNights, $bookableNights, true);
    }

    /**
     * @param mixed $raw
     * @return array<int, array<string, mixed>>
     */
    private function decodeImages($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function renderError(string $message): string
    {
        return '<div class="cb-widget__error" role="alert">' . esc_html($message) . '</div>';
    }

    public function getIbeUrlBuilder(): IbeUrlBuilder
    {
        return $this->ibeUrlBuilder;
    }

    public function getDescriptionPresenter(): DescriptionPresenter
    {
        return new DescriptionPresenter();
    }

    public function getDetailRewrite(): DetailRewrite
    {
        return $this->detailRewrite;
    }
}
