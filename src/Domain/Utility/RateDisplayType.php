<?php

declare(strict_types=1);

namespace Casablanca\Booking\Domain\Utility;

/**
 * Human-readable mapping type for rates (TYPO3 Rate::getDisplayType parity).
 */
final class RateDisplayType
{
    /**
     * @param array<string, mixed> $rate
     */
    public static function fromRow(array $rate): string
    {
        if (! empty($rate['is_package'])) {
            return 'Package';
        }

        $formatted = self::formatCateringType(trim((string) ($rate['catering_type'] ?? '')));
        if ($formatted === '') {
            return 'Day Rate';
        }

        return $formatted;
    }

    private static function formatCateringType(string $cateringType): string
    {
        if ($cateringType === '' || strcasecmp($cateringType, 'Undefined') === 0) {
            return '';
        }

        static $labels = [
            'AllInclusive' => 'All Inclusive',
            'American' => 'American',
            'BedAndBreakfast' => 'Bed and Breakfast',
            'BuffetBreakfast' => 'Buffet Breakfast',
            'CaribbeanBreakfast' => 'Caribbean Breakfast',
            'ContinentalBreakfast' => 'Continental Breakfast',
            'EnglishBreakfast' => 'English Breakfast',
            'EuropeanPlan' => 'European Plan',
            'FamilyPlan' => 'Family Plan',
            'FullBoard' => 'Full Board',
            'FullBreakfast' => 'Full Breakfast',
            'Halfboard_modifiedAmericanPlan' => 'Halfboard',
            'AsBrochured' => 'As Brochured',
            'RoomOnly' => 'Room only',
            'SelfCatering' => 'Self Catering',
            'Bermuda' => 'Bermuda',
            'DinnerBedAndBreakfastPlan' => 'Dinner, Bed and Breakfast',
            'FamilyAmerican' => 'Family American',
            'Breakfast' => 'Breakfast',
            'Modified' => 'Modified',
            'Lunch' => 'Lunch',
            'Dinner' => 'Dinner',
            'BreakfastLunch' => 'Breakfast and Lunch',
        ];

        if (isset($labels[$cateringType])) {
            return $labels[$cateringType];
        }

        return preg_replace('/(?<!^)([A-Z])/', ' $1', $cateringType) ?? $cateringType;
    }
}
