<?php

declare(strict_types=1);

namespace Casablanca\Booking\Service\BookingOffer;

use Casablanca\Booking\Api\Client\BookingOffersClient;
use Casablanca\Booking\Domain\Dto\RoomOccupancyDto;
use Casablanca\Booking\Domain\Dto\SiteConfigurationDto;
use Casablanca\Booking\Domain\Repository\RateRepository;
use Casablanca\Booking\Domain\Utility\RateDisplayType;
use DateTimeInterface;

/**
 * Fetches and normalises booking offers for the availability calendar sidebar.
 */
final class BookingOffersFetchService
{
    public const MODE_NONE = 'none';
    public const MODE_RATES_ONLY = 'rates_only';
    public const MODE_PACKAGES_ONLY = 'packages_only';
    public const MODE_PACKAGES_AND_RATES = 'packages_and_rates';

    public function __construct(
        private readonly RateRepository $rateRepository = new RateRepository()
    ) {}

    /**
     * @param string[] $rateIds
     * @return array<string, mixed>
     */
    public function fetch(
        BookingOffersClient $client,
        SiteConfigurationDto $config,
        DateTimeInterface $arrival,
        DateTimeInterface $departure,
        RoomOccupancyDto $roomOccupancy,
        string $offerMode,
        ?string $preselectedRoomTypeId = null,
        array $rateIds = [],
        ?string $culture = null
    ): array {
        $offerMode = self::normaliseOfferMode($offerMode);
        if ($offerMode === self::MODE_NONE) {
            return [
                'offerMode' => self::MODE_NONE,
                'offers' => [],
                'lowestTotalPrice' => null,
                'currency' => 'EUR',
            ];
        }

        $selectionCriteria = $this->buildSelectionCriteria($offerMode);
        $stayFilter = $this->buildStayFilter($preselectedRoomTypeId, $rateIds);

        $response = $client->create(
            $arrival,
            $departure,
            $selectionCriteria,
            $roomOccupancy,
            $stayFilter,
            $culture
        );

        $offers = $this->parseOffers($config, $offerMode, $response);
        $rateIds = array_values(array_filter(array_map('trim', $rateIds), static fn (string $id): bool => $id !== ''));
        if ($rateIds !== []) {
            $allowed = array_fill_keys($rateIds, true);
            $offers = array_values(array_filter(
                $offers,
                static fn (array $offer): bool => isset($allowed[(string) ($offer['rateId'] ?? '')])
            ));
        }

        $lowest = null;
        $currency = 'EUR';
        foreach ($offers as $offer) {
            $price = $offer['totalPrice'] ?? null;
            if ($price === null) {
                continue;
            }
            if ($lowest === null || $price < $lowest) {
                $lowest = $price;
            }
            if (($offer['currency'] ?? '') !== '') {
                $currency = (string) $offer['currency'];
            }
        }

        return [
            'offerMode' => $offerMode,
            'offers' => $offers,
            'lowestTotalPrice' => $lowest,
            'currency' => $currency,
        ];
    }

    public static function normaliseOfferMode(string $offerMode): string
    {
        $allowed = [
            self::MODE_NONE,
            self::MODE_RATES_ONLY,
            self::MODE_PACKAGES_ONLY,
            self::MODE_PACKAGES_AND_RATES,
        ];

        return in_array($offerMode, $allowed, true) ? $offerMode : self::MODE_NONE;
    }

    /**
     * @return string[]
     */
    private function buildSelectionCriteria(string $offerMode): array
    {
        return match ($offerMode) {
            self::MODE_RATES_ONLY => ['includeRates'],
            self::MODE_PACKAGES_ONLY => ['includePackages'],
            self::MODE_PACKAGES_AND_RATES => ['includePackages', 'includeRates'],
            default => [],
        };
    }

    /**
     * @param string[] $rateIds
     * @return array<string, mixed>|null
     */
    private function buildStayFilter(?string $preselectedRoomTypeId, array $rateIds): ?array
    {
        $roomTypeId = $preselectedRoomTypeId !== null ? trim($preselectedRoomTypeId) : '';
        $rateIds = array_values(array_filter(array_map('trim', $rateIds), static fn (string $id): bool => $id !== ''));

        if ($roomTypeId === '' && $rateIds === []) {
            return null;
        }

        return [
            'companyIdentifiers' => [],
            'roomTypeIds' => $roomTypeId !== '' ? [$roomTypeId] : [],
            'rateIds' => $rateIds,
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function parseOffers(SiteConfigurationDto $config, string $offerMode, array $response): array
    {
        $configurations = isset($response['configurations']) && is_array($response['configurations'])
            ? $response['configurations']
            : [];
        if ($configurations === []) {
            return [];
        }

        $configuration = $configurations[0];
        if (! is_array($configuration)) {
            return [];
        }

        $offers = [];

        if ($offerMode === self::MODE_PACKAGES_ONLY || $offerMode === self::MODE_PACKAGES_AND_RATES) {
            $packageGroups = isset($configuration['packageGroups']) && is_array($configuration['packageGroups'])
                ? $configuration['packageGroups']
                : [];
            foreach ($packageGroups as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $offer = $this->cheapestOfferFromGroup($group, 'packages');
                if ($offer === null) {
                    continue;
                }
                $offers[] = $this->enrichOffer($config, $offer, (string) ($group['packageId'] ?? ''), true);
            }
        }

        if ($offerMode === self::MODE_RATES_ONLY || $offerMode === self::MODE_PACKAGES_AND_RATES) {
            $rateGroups = isset($configuration['rateGroups']) && is_array($configuration['rateGroups'])
                ? $configuration['rateGroups']
                : [];
            $cheapestByRateId = [];
            foreach ($rateGroups as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $roomStayOffers = isset($group['roomStayOffers']) && is_array($group['roomStayOffers'])
                    ? $group['roomStayOffers']
                    : [];
                foreach ($roomStayOffers as $roomStayOffer) {
                    if (! is_array($roomStayOffer)) {
                        continue;
                    }
                    $rateId = (string) ($roomStayOffer['rateId'] ?? '');
                    if ($rateId === '') {
                        continue;
                    }
                    $totalPrice = $this->parsePrice($roomStayOffer['totalPrice'] ?? null);
                    if ($totalPrice === null) {
                        continue;
                    }
                    $existing = $cheapestByRateId[$rateId] ?? null;
                    if ($existing !== null && $existing['totalPrice'] <= $totalPrice) {
                        continue;
                    }
                    $cheapestByRateId[$rateId] = [
                        'rateId' => $rateId,
                        'totalPrice' => $totalPrice,
                        'currency' => 'EUR',
                        'section' => 'rates',
                        'isPackage' => false,
                    ];
                }
            }
            foreach ($cheapestByRateId as $candidate) {
                $offers[] = $this->enrichOffer(
                    $config,
                    $candidate,
                    (string) $candidate['rateId'],
                    false
                );
            }
        }

        usort($offers, static function (array $a, array $b): int {
            $priceA = $a['totalPrice'] ?? PHP_FLOAT_MAX;
            $priceB = $b['totalPrice'] ?? PHP_FLOAT_MAX;
            if ($priceA === $priceB) {
                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }

            return $priceA <=> $priceB;
        });

        if ($offerMode === self::MODE_PACKAGES_AND_RATES) {
            usort($offers, static function (array $a, array $b): int {
                $sectionOrder = ['packages' => 0, 'rates' => 1];
                $orderA = $sectionOrder[$a['section'] ?? 'rates'] ?? 1;
                $orderB = $sectionOrder[$b['section'] ?? 'rates'] ?? 1;
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
                $priceA = $a['totalPrice'] ?? PHP_FLOAT_MAX;
                $priceB = $b['totalPrice'] ?? PHP_FLOAT_MAX;
                if ($priceA === $priceB) {
                    return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                }

                return $priceA <=> $priceB;
            });
        }

        return $offers;
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>|null
     */
    private function cheapestOfferFromGroup(array $group, string $section): ?array
    {
        $roomStayOffers = isset($group['roomStayOffers']) && is_array($group['roomStayOffers'])
            ? $group['roomStayOffers']
            : [];
        $cheapest = null;
        $rateId = '';

        foreach ($roomStayOffers as $roomStayOffer) {
            if (! is_array($roomStayOffer)) {
                continue;
            }
            $totalPrice = $this->parsePrice($roomStayOffer['totalPrice'] ?? null);
            if ($totalPrice === null) {
                continue;
            }
            if ($cheapest === null || $totalPrice < $cheapest) {
                $cheapest = $totalPrice;
                $rateId = (string) ($roomStayOffer['rateId'] ?? $group['packageId'] ?? '');
            }
        }

        if ($cheapest === null) {
            return null;
        }

        $packageId = (string) ($group['packageId'] ?? $rateId);

        return [
            'rateId' => $rateId !== '' ? $rateId : $packageId,
            'totalPrice' => $cheapest,
            'currency' => 'EUR',
            'section' => $section,
            'isPackage' => true,
        ];
    }

    /**
     * @param array<string, mixed> $offer
     * @return array<string, mixed>
     */
    private function enrichOffer(
        SiteConfigurationDto $config,
        array $offer,
        string $lookupId,
        bool $isPackage
    ): array {
        $rateId = (string) ($offer['rateId'] ?? $lookupId);
        $rate = $this->rateRepository->findByRateId($config->siteIdentifier, $rateId);
        if ($rate === null && $lookupId !== '' && $lookupId !== $rateId) {
            $rate = $this->rateRepository->findByRateId($config->siteIdentifier, $lookupId);
        }

        $name = $rateId;
        $subtitle = '';
        if ($rate !== null) {
            $name = ($rate['name'] ?? '') !== '' ? (string) $rate['name'] : $rateId;
            $subtitle = RateDisplayType::fromRow($rate);
            if ($subtitle === 'Package' || $subtitle === 'Day Rate') {
                $subtitle = '';
            }
            $isPackage = ! empty($rate['is_package']);
        }

        return [
            'rateId' => $rateId,
            'name' => $name,
            'subtitle' => $subtitle,
            'totalPrice' => $offer['totalPrice'] ?? null,
            'currency' => $offer['currency'] ?? 'EUR',
            'isPackage' => $isPackage,
            'section' => $offer['section'] ?? ($isPackage ? 'packages' : 'rates'),
            'detailUrl' => '',
        ];
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
