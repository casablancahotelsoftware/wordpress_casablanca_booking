<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Dto;

use DateTimeImmutable;

final class CalendarDateDto
{
    /** @param int[] $bookableNights @param int[] $bookableNightsWithPackages */
    public function __construct(
        public DateTimeImmutable $effectiveDate,
        public ?float $fromPrice,
        public bool $isAvailable,
        public bool $isArrivalAllowed,
        public bool $isDepartureAllowed,
        public bool $isPreviousDayBlocked,
        public bool $isNextDayBlocked,
        public int $minLengthOfStay,
        public int $maxLengthOfStay,
        public array $bookableNights,
        public array $bookableNightsWithPackages
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $adjacent = isset($data['adjacentDays']) && is_array($data['adjacentDays'])
            ? $data['adjacentDays']
            : [];

        return new self(
            new DateTimeImmutable((string) ($data['effectiveDate'] ?? 'now')),
            isset($data['fromPrice']) ? (float) $data['fromPrice'] : null,
            (bool) ($data['isAvailable'] ?? false),
            (bool) ($data['isArrivalAllowed'] ?? false),
            (bool) ($data['isDepartureAllowed'] ?? false),
            (bool) ($adjacent['isPreviousDayBlocked'] ?? false),
            (bool) ($adjacent['isNextDayBlocked'] ?? false),
            (int) ($data['minLengthOfStay'] ?? 0),
            (int) ($data['maxLengthOfStay'] ?? 0),
            array_map('intval', (array) ($data['bookableNights'] ?? [])),
            array_map('intval', (array) ($data['bookableNightsWithPackages'] ?? []))
        );
    }
}
