<?php

declare(strict_types=1);

namespace Casablanca\Booking\Service\Calendar;

use Casablanca\Booking\Domain\Dto\CalendarDateDto;

final class CalendarDayPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(CalendarDateDto $cal, string $roomTypeId = '', string $currency = 'EUR'): array
    {
        $restrictions = $this->deriveRestrictions($cal);

        return [
            'date' => $cal->effectiveDate->format('Y-m-d'),
            'fromPrice' => $cal->fromPrice,
            'currency' => $currency,
            'isAvailable' => $cal->isAvailable,
            'isArrivalAllowed' => $cal->isArrivalAllowed,
            'minLengthOfStay' => $cal->minLengthOfStay,
            'roomTypeId' => $roomTypeId,
            'hasRestrictions' => $restrictions !== [],
            'calendarStateClass' => $this->getCalendarStateClass($cal, $restrictions),
        ];
    }

    /**
     * @return string[]
     */
    private function deriveRestrictions(CalendarDateDto $cal): array
    {
        $restrictions = [];
        if ($cal->minLengthOfStay > 1) {
            $restrictions[] = 'Restriktionen';
        }
        if ($cal->isAvailable && $cal->bookableNights === []) {
            $restrictions[] = 'KeineBuchbaren';
        }
        if (! $cal->isAvailable) {
            $restrictions[] = 'NichtVerfügbar';
        }

        return $restrictions;
    }

    /**
     * @param string[] $restrictions
     */
    private function getCalendarStateClass(CalendarDateDto $cal, array $restrictions): string
    {
        if (! $cal->isAvailable) {
            return 'cb-state-unavailable';
        }
        if ($restrictions !== []) {
            return 'cb-state-restricted';
        }
        if (! $cal->isArrivalAllowed) {
            return 'cb-state-no-arrival';
        }

        return 'cb-state-available';
    }
}
