<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/** @var array<string, mixed> $context @var array<string, mixed> $attrs */
$config = $context['config'];
$labels = $context['labels'];
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
$lr = $context['lr'];
$compactOccupancy = ! empty($attrs['compactOccupancy']);
$defaultRooms = max(1, min(5, (int) $attrs['defaultRooms']));
$rooms = is_array($rooms ?? null) ? $rooms : [];
$occupancyPanelId = 'cb-search-occupancy-panel-' . wp_unique_id();
$minDeparture = $arrival->modify('+1 day');
include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-open.php';
?>
<div class="cb-search-bar">
    <h2 id="cb-widget-heading" class="cb-search-bar__heading"><?php echo esc_html($lr->resolve('searchbar.heading', 'Search availability', (string) ($labels['heading'] ?? ''))); ?></h2>
    <form class="cb-widget__form cb-search-bar__form<?php echo $compactOccupancy ? ' cb-search-bar__form--compact-occupancy' : ''; ?>"
          method="get"
          action="<?php echo esc_url($context['redirectUrl']); ?>"
          data-cb-mode="search"
          data-cb-ibe-base="<?php echo esc_attr($config->ibeBaseUrl); ?>"
          data-cb-tenant="<?php echo esc_attr($config->tenantId); ?>"
          data-cb-ibe-context="<?php echo esc_attr($config->urlFriendlyIbeContextId); ?>"
          data-cb-link-style="<?php echo esc_attr($config->ibeLinkStyle); ?>"
          data-cb-culture="<?php echo esc_attr((string) $context['culture']); ?>"
          data-cb-link-target="<?php echo esc_attr((string) $attrs['ibeLinkTarget']); ?>"
          data-cb-default-adults="<?php echo esc_attr((string) $attrs['defaultAdults']); ?>"
          data-cb-default-children="<?php echo esc_attr((string) $attrs['defaultChildren']); ?>"
          data-cb-default-children-ages="<?php echo esc_attr((string) $attrs['defaultChildrenAges']); ?>">
        <?php if ($compactOccupancy) : ?>
            <div class="cb-search-bar__toolbar">
                <label class="cb-field">
                    <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.arrival', 'Arrival')); ?></span>
                    <input type="date" name="arrival" required value="<?php echo esc_attr($arrival->format('Y-m-d')); ?>" data-cb-field="arrival" />
                </label>
                <label class="cb-field">
                    <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.departure', 'Departure')); ?></span>
                    <input type="date" name="departure" required min="<?php echo esc_attr($minDeparture->format('Y-m-d')); ?>" value="<?php echo esc_attr($departure->format('Y-m-d')); ?>" data-cb-field="departure" />
                </label>
                <button type="button"
                        class="cb-search-bar__occupancy-toggle"
                        data-cb-occupancy-toggle
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($occupancyPanelId); ?>"
                        title="<?php echo esc_attr($lr->resolve('searchbar.occupancyToggle', 'Guests and rooms')); ?>">
                    <svg class="cb-search-bar__occupancy-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span class="cb-sr-only"><?php echo esc_html($lr->resolve('searchbar.occupancyToggle', 'Guests and rooms')); ?></span>
                </button>
                <button type="submit" class="cb-widget__submit cb-search-bar__submit"><?php echo esc_html($lr->resolve('searchbar.submit', 'Search & book', (string) ($labels['submit'] ?? ''))); ?></button>
            </div>
            <div id="<?php echo esc_attr($occupancyPanelId); ?>"
                 class="cb-search-bar__occupancy-panel"
                 data-cb-occupancy-panel
                 hidden="hidden">
                <?php include CASABLANCA_BOOKING_PATH . 'templates/partials/occupancy-rooms.php'; ?>
            </div>
        <?php else : ?>
            <div class="cb-widget__fields cb-search-bar__fields">
                <label class="cb-field">
                    <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.arrival', 'Arrival')); ?></span>
                    <input type="date" name="arrival" required value="<?php echo esc_attr($arrival->format('Y-m-d')); ?>" data-cb-field="arrival" />
                </label>
                <label class="cb-field">
                    <span class="cb-field__label"><?php echo esc_html($lr->resolve('widget.departure', 'Departure')); ?></span>
                    <input type="date" name="departure" required min="<?php echo esc_attr($minDeparture->format('Y-m-d')); ?>" value="<?php echo esc_attr($departure->format('Y-m-d')); ?>" data-cb-field="departure" />
                </label>
                <?php include CASABLANCA_BOOKING_PATH . 'templates/partials/occupancy-rooms.php'; ?>
            </div>
            <button type="submit" class="cb-widget__submit cb-search-bar__submit"><?php echo esc_html($lr->resolve('searchbar.submit', 'Search & book', (string) ($labels['submit'] ?? ''))); ?></button>
        <?php endif; ?>
    </form>
</div>
<?php include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-close.php'; ?>
