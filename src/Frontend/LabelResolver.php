<?php

declare(strict_types=1);

namespace Casablanca\Booking\Frontend;

final class LabelResolver
{
    public function __construct(
        private readonly WidgetStringCatalog $catalog = new WidgetStringCatalog(),
        private string $language = 'en',
    ) {}

    public function setLanguage(string $language): void
    {
        $this->language = WidgetLanguage::isSupported($language)
            ? strtolower($language)
            : 'en';
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @param array<int, string|int|float> $arguments
     */
    public function resolve(string $key, string $default = '', string $override = '', array $arguments = []): string
    {
        $override = trim($override);
        if ($override !== '') {
            return $override;
        }

        $translated = $this->catalog->get($key, $this->language, $default);
        if ($translated === '' || $translated === $key) {
            $translated = $default !== '' ? $default : $key;
        }

        if ($arguments !== []) {
            return vsprintf($translated, array_values($arguments));
        }

        return $translated;
    }

    /**
     * @param array<string, mixed> $flexLabels
     * @return array<string, string>
     */
    public function buildJavaScriptLabels(array $flexLabels = []): array
    {
        return [
            'adults' => $this->resolve('widget.adults', 'Adults'),
            'children' => $this->resolve('widget.children', 'Children'),
            'childrenAges' => $this->resolve('widget.children.ages', 'Children ages'),
            'adultsHint' => $this->resolve('widget.calendar.adultsHint', 'aged 15 and over'),
            'childrenHint' => $this->resolve('widget.calendar.childrenHint', 'aged 0 to 14'),
            'childN' => $this->resolve('widget.children.ageN', 'Child %s'),
            'roomN' => $this->resolve('widget.rooms.roomN', 'Room %s'),
            'calendarLoading' => $this->resolve('widget.calendar.loading', 'Loading availability…'),
            'calendarError' => $this->resolve('widget.calendar.error', 'Could not load availability.'),
            'calendarFrom' => $this->resolve('widget.calendar.from', 'From'),
            'calendarTo' => $this->resolve('widget.calendar.to', 'To'),
            'calendarBookNow' => $this->resolve('widget.calendar.bookNow', 'Book now', (string) ($flexLabels['bookNow'] ?? '')),
            'calendarEnquiry' => $this->resolve('widget.calendar.enquiry', 'Enquiry', (string) ($flexLabels['enquiry'] ?? '')),
            'calendarFromPrice' => $this->resolve('widget.calendar.fromPrice', 'from'),
            'calendarNights' => $this->resolve('widget.calendar.nights', '%s nights'),
            'calendarClearSelection' => $this->resolve('widget.calendar.clearSelection', 'Clear date selection'),
            'calendarOffersLoading' => $this->resolve('widget.calendar.offersLoading', 'Loading offers…'),
            'calendarOffersError' => $this->resolve('widget.calendar.offersError', 'Could not load offers.'),
            'calendarPackagesHeading' => $this->resolve('widget.calendar.packagesHeading', 'Packages'),
            'calendarRatesHeading' => $this->resolve('widget.calendar.ratesHeading', 'Rates'),
            'calendarDetails' => $this->resolve('widget.calendar.details', 'Details'),
            'calendarAddRoom' => $this->resolve('widget.calendar.addRoom', 'Add another room'),
            'calendarRemoveRoom' => $this->resolve('widget.calendar.removeRoom', 'Remove room'),
            'restrictions' => $this->resolve('widget.restrictions', 'Restrictions apply'),
            'legendLabel' => $this->resolve('widget.legend.label', 'Calendar legend'),
            'legendAvailable' => $this->resolve('widget.legend.available', 'Available'),
            'legendRestricted' => $this->resolve('widget.legend.restricted', 'Restrictions apply'),
            'legendNoArrival' => $this->resolve('widget.legend.noArrival', 'No arrival'),
            'legendUnavailable' => $this->resolve('widget.legend.unavailable', 'Not available'),
            'more' => $this->resolve('common.more', 'More', (string) ($flexLabels['more'] ?? '')),
            'less' => $this->resolve('common.less', 'Less', (string) ($flexLabels['less'] ?? '')),
        ];
    }
}
