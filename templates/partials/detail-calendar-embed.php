<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Detail-page availability calendar embed (reuses calendar widget markup/JS).
 *
 * @var array<string, mixed> $calendarEmbedContext
 * @var array<string, mixed> $calendarEmbedAttrs
 */
$calContext = $calendarEmbedContext;
$calAttrs = $calendarEmbedAttrs;
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
$lr = $calContext['lr'];
$labels = $calContext['labels'];
$calConfig = $calContext['config'];
$layoutExtra = trim((string) ($calAttrs['calendarLayoutClass'] ?? 'cb-calendar-layout--detail'));
$preselectRoom = (string) ($calAttrs['preselectRoomCategory'] ?? '');
$filterRateIds = (string) ($calAttrs['filterRateIds'] ?? '');
$preselectedRateId = (string) ($calAttrs['preselectedRateId'] ?? '');
$roomMaxOccupancy = (int) ($calAttrs['roomMaxOccupancy'] ?? 0);
$roomMinOccupancy = (int) ($calAttrs['roomMinOccupancy'] ?? 0);
$defaultRooms = max(1, min(5, (int) ($calAttrs['defaultRooms'] ?? 1)));
$rooms = [];
$adults = max(1, min(10, (int) ($calAttrs['defaultAdults'] ?? 2)));
$children = max(0, min(10, (int) ($calAttrs['defaultChildren'] ?? 0)));
$ages = [];
$rawAges = trim((string) ($calAttrs['defaultChildrenAges'] ?? ''));
if ($rawAges !== '') {
    $ages = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $rawAges) ?: [])));
}
while (count($ages) < $children) {
    $ages[] = 12;
}
$ages = array_slice($ages, 0, $children);
$agesString = implode(', ', $ages);
for ($i = 0; $i < $defaultRooms; $i++) {
    $rooms[] = [
        'adults' => $adults,
        'children' => $children,
        'childrenAges' => $ages,
        'childrenAgesString' => $agesString,
    ];
}
$calendarOfferMode = (string) ($calAttrs['calendarOfferMode'] ?? 'none');
$calendarOffersEnabled = $calendarOfferMode !== 'none';
?>
<div class="cb-calendar-layout <?php echo esc_attr($layoutExtra); ?>">
    <div class="cb-calendar-layout__main">
        <div class="cb-calendar__nav" data-cb-calendar-nav hidden="hidden">
            <button type="button" class="cb-calendar__nav-btn" data-cb-month-prev aria-label="<?php echo esc_attr($lr->resolve('widget.calendar.prevMonth', 'Previous month')); ?>">‹</button>
            <h3 class="cb-calendar__nav-title" data-cb-month-title></h3>
            <button type="button" class="cb-calendar__nav-btn" data-cb-month-next aria-label="<?php echo esc_attr($lr->resolve('widget.calendar.nextMonth', 'Next month')); ?>">›</button>
        </div>
        <div class="cb-calendar cb-calendar--empty" data-cb-calendar-host aria-live="polite"></div>
    </div>
    <aside class="cb-calendar-layout__aside">
        <form class="cb-widget__form cb-widget__form--calendar"
              method="get"
              data-cb-mode="calendar"
              data-cb-fetch-url="<?php echo esc_url($calContext['restCalendar']); ?>"
              data-cb-offers-url="<?php echo esc_url($calContext['restOffers']); ?>"
              data-cb-ibe-base="<?php echo esc_attr($calConfig->ibeBaseUrl); ?>"
              data-cb-tenant="<?php echo esc_attr($calConfig->tenantId); ?>"
              data-cb-ibe-context="<?php echo esc_attr($calConfig->urlFriendlyIbeContextId); ?>"
              data-cb-link-style="<?php echo esc_attr($calConfig->ibeLinkStyle); ?>"
              data-cb-culture="<?php echo esc_attr((string) $calContext['culture']); ?>"
              data-cb-link-target="<?php echo esc_attr((string) $calAttrs['ibeLinkTarget']); ?>"
              data-cb-window-days="<?php echo esc_attr((string) $calAttrs['windowDays']); ?>"
              data-cb-initial-months="<?php echo esc_attr((string) $calAttrs['calendarInitialMonths']); ?>"
              data-cb-offer-mode="<?php echo esc_attr($calendarOfferMode); ?>"
              data-cb-preselected-room="<?php echo esc_attr($preselectRoom); ?>"
              data-cb-filter-rate-ids="<?php echo esc_attr($filterRateIds); ?>"
              data-cb-rate-ids="<?php echo esc_attr($preselectedRateId); ?>"
              data-cb-max-occupancy="<?php echo esc_attr((string) $roomMaxOccupancy); ?>"
              data-cb-min-occupancy="<?php echo esc_attr((string) $roomMinOccupancy); ?>"
              data-cb-default-adults="<?php echo esc_attr((string) $calAttrs['defaultAdults']); ?>"
              data-cb-default-children="<?php echo esc_attr((string) ($calAttrs['defaultChildren'] ?? 0)); ?>"
              data-cb-default-children-ages="<?php echo esc_attr((string) ($calAttrs['defaultChildrenAges'] ?? '')); ?>">
            <?php if ($preselectRoom !== '') : ?>
                <input type="hidden" name="room" value="<?php echo esc_attr($preselectRoom); ?>" />
            <?php endif; ?>
            <?php if ($filterRateIds !== '') : ?>
                <input type="hidden" name="rateIds" value="<?php echo esc_attr($filterRateIds); ?>" />
            <?php endif; ?>
            <input type="hidden" name="arrival" value="" data-cb-field="arrival" />
            <input type="hidden" name="departure" value="" data-cb-field="departure" />
            <?php include CASABLANCA_BOOKING_PATH . 'templates/partials/calendar-occupancy.php'; ?>
            <?php if ($calendarOffersEnabled) : ?>
                <div class="cb-calendar__offers" data-cb-offers hidden="hidden">
                    <p class="cb-calendar__offers-loading" data-cb-offers-loading hidden="hidden"><?php echo esc_html($lr->resolve('widget.calendar.offersLoading', 'Loading offers…')); ?></p>
                    <p class="cb-calendar__offers-error" data-cb-offers-error hidden="hidden" role="alert"></p>
                    <div class="cb-calendar__offers-list" data-cb-offers-list role="radiogroup"></div>
                </div>
            <?php endif; ?>
            <button type="button" class="cb-widget__submit" data-cb-book-now>
                <?php echo esc_html($lr->resolve('widget.calendar.bookNow', 'Book now', (string) (($labels['bookNow'] ?? $labels['book'] ?? '')))); ?>
            </button>
        </form>
    </aside>
</div>
