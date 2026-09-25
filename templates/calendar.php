<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/** @var array<string, mixed> $context @var array<string, mixed> $attrs */
$config = $context['config'];
$labels = $context['labels'];
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
$lr = $context['lr'];
$defaultRooms = max(1, min(5, (int) $attrs['defaultRooms']));
$rooms = is_array($rooms ?? null) ? $rooms : [];
$roomMaxOccupancy = (int) ($attrs['roomMaxOccupancy'] ?? 0);
$roomMinOccupancy = (int) ($attrs['roomMinOccupancy'] ?? 0);
$preselectRoom = (string) ($attrs['preselectRoomCategory'] ?? '');
$filterRateIds = (string) ($attrs['filterRateIds'] ?? '');
$preselectedRateId = (string) ($attrs['preselectedRateId'] ?? '');
$calendarOfferMode = (string) ($attrs['calendarOfferMode'] ?? 'none');
$calendarOffersEnabled = $calendarOfferMode !== 'none';
$showEnquiryButton = ! empty($attrs['showEnquiryButton']);
$enquiryUrl = (string) ($attrs['enquiryUrl'] ?? '');
$enquiryLinkTarget = (string) ($attrs['enquiryLinkTarget'] ?? '_self');
$showHeading = ! isset($attrs['showCalendarHeading']) || ! empty($attrs['showCalendarHeading']);
$layoutExtra = trim((string) ($attrs['calendarLayoutClass'] ?? ''));
include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-open.php';
?>
<div class="cb-calendar-layout<?php echo $layoutExtra !== '' ? ' ' . esc_attr($layoutExtra) : ''; ?>">
    <div class="cb-calendar-layout__main">
        <?php if ($showHeading) : ?>
            <h2 id="cb-widget-heading" class="cb-widget__heading"><?php echo esc_html($lr->resolve('widget.heading.default', 'Check availability and book', (string) ($labels['heading'] ?? ''))); ?></h2>
        <?php endif; ?>
        <div class="cb-calendar__nav" data-cb-calendar-nav hidden="hidden">
            <button type="button" class="cb-calendar__nav-btn" data-cb-month-prev aria-label="<?php echo esc_attr($lr->resolve('widget.calendar.prevMonth', 'Previous month')); ?>">‹</button>
            <h3 class="cb-calendar__nav-title" data-cb-month-title></h3>
            <button type="button" class="cb-calendar__nav-btn" data-cb-month-next aria-label="<?php echo esc_attr($lr->resolve('widget.calendar.nextMonth', 'Next month')); ?>">›</button>
        </div>
        <div class="cb-calendar cb-calendar--empty" data-cb-calendar-host aria-live="polite" aria-label="<?php echo esc_attr($lr->resolve('widget.calendar.label', 'Availability calendar')); ?>"></div>
        <p class="cb-calendar__footnote"><?php echo esc_html($lr->resolve('widget.calendar.footnote', '* Lowest prices per person at standard occupancy')); ?></p>
        <?php include CASABLANCA_BOOKING_PATH . 'templates/partials/calendar-legend.php'; ?>
    </div>
    <aside class="cb-calendar-layout__aside">
        <form class="cb-widget__form cb-widget__form--calendar"
              method="get"
              data-cb-mode="calendar"
              data-cb-fetch-url="<?php echo esc_url($context['restCalendar']); ?>"
              data-cb-offers-url="<?php echo esc_url($context['restOffers']); ?>"
              data-cb-ibe-base="<?php echo esc_attr($config->ibeBaseUrl); ?>"
              data-cb-tenant="<?php echo esc_attr($config->tenantId); ?>"
              data-cb-ibe-context="<?php echo esc_attr($config->urlFriendlyIbeContextId); ?>"
              data-cb-link-style="<?php echo esc_attr($config->ibeLinkStyle); ?>"
              data-cb-culture="<?php echo esc_attr((string) $context['culture']); ?>"
              data-cb-link-target="<?php echo esc_attr((string) $attrs['ibeLinkTarget']); ?>"
              data-cb-window-days="<?php echo esc_attr((string) $attrs['windowDays']); ?>"
              data-cb-initial-months="<?php echo esc_attr((string) $attrs['calendarInitialMonths']); ?>"
              data-cb-offer-mode="<?php echo esc_attr($calendarOfferMode); ?>"
              data-cb-preselected-room="<?php echo esc_attr($preselectRoom); ?>"
              data-cb-filter-rate-ids="<?php echo esc_attr($filterRateIds); ?>"
              data-cb-rate-ids="<?php echo esc_attr($preselectedRateId); ?>"
              data-cb-max-occupancy="<?php echo esc_attr((string) $roomMaxOccupancy); ?>"
              data-cb-min-occupancy="<?php echo esc_attr((string) $roomMinOccupancy); ?>"
              data-cb-default-adults="<?php echo esc_attr((string) $attrs['defaultAdults']); ?>"
              data-cb-default-children="<?php echo esc_attr((string) $attrs['defaultChildren']); ?>"
              data-cb-default-children-ages="<?php echo esc_attr((string) ($attrs['defaultChildrenAges'] ?? '')); ?>">
            <?php if ($preselectRoom !== '') : ?>
                <input type="hidden" name="room" value="<?php echo esc_attr($preselectRoom); ?>" />
            <?php endif; ?>
            <?php if ($filterRateIds !== '') : ?>
                <input type="hidden" name="rateIds" value="<?php echo esc_attr($filterRateIds); ?>" />
            <?php endif; ?>
            <input type="hidden" name="arrival" value="" data-cb-field="arrival" />
            <input type="hidden" name="departure" value="" data-cb-field="departure" />

            <?php include CASABLANCA_BOOKING_PATH . 'templates/partials/calendar-occupancy.php'; ?>

            <p class="cb-calendar__selection-range" data-cb-selection-summary hidden="hidden">
                <span class="cb-calendar__selection-label"><?php echo esc_html($lr->resolve('widget.calendar.from', 'From')); ?>:</span>
                <strong data-cb-selection-from>—</strong>
                <span class="cb-calendar__selection-label"><?php echo esc_html($lr->resolve('widget.calendar.to', 'To')); ?>:</span>
                <strong data-cb-selection-to>—</strong>
            </p>

            <?php if ($calendarOffersEnabled) : ?>
                <div class="cb-calendar__offers" data-cb-offers hidden="hidden">
                    <p class="cb-calendar__offers-loading" data-cb-offers-loading hidden="hidden"><?php echo esc_html($lr->resolve('widget.calendar.offersLoading', 'Loading offers…')); ?></p>
                    <p class="cb-calendar__offers-error" data-cb-offers-error hidden="hidden" role="alert"></p>
                    <div class="cb-calendar__offers-list" data-cb-offers-list role="radiogroup"></div>
                </div>
            <?php endif; ?>

            <p class="cb-calendar__price" data-cb-price-summary>
                <span class="cb-calendar__price-prefix"><?php echo esc_html($lr->resolve('widget.calendar.fromPrice', 'from')); ?></span>
                <span class="cb-calendar__price-value" data-cb-price-value>—</span>
            </p>

            <div class="cb-calendar__actions">
                <?php if ($showEnquiryButton && $enquiryUrl !== '') : ?>
                    <a class="cb-button cb-calendar__enquiry"
                       data-cb-enquiry
                       href="<?php echo esc_url($enquiryUrl); ?>"
                       target="<?php echo esc_attr($enquiryLinkTarget); ?>"
                       <?php echo $enquiryLinkTarget === '_blank' ? 'rel="noopener noreferrer"' : ''; ?>>
                        <?php echo esc_html($lr->resolve('widget.calendar.enquiry', 'Enquiry', (string) ($labels['enquiry'] ?? ''))); ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="cb-widget__submit cb-calendar__book" data-cb-book-now disabled>
                    <?php echo esc_html($lr->resolve('widget.calendar.bookNow', 'Book now', (string) ($labels['bookNow'] ?? ''))); ?>
                </button>
            </div>
        </form>
    </aside>
</div>
<?php include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-close.php'; ?>
