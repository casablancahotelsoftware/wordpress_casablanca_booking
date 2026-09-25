<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * Colour-key legend for the availability calendar.
 *
 * @var \Casablanca\Booking\Frontend\LabelResolver $lr
 */
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
if (! isset($lr) || ! $lr instanceof \Casablanca\Booking\Frontend\LabelResolver) {
    $lr = (isset($context) && ($context['lr'] ?? null) instanceof \Casablanca\Booking\Frontend\LabelResolver)
        ? $context['lr']
        : new \Casablanca\Booking\Frontend\LabelResolver();
}
?>
<ul class="cb-legend" role="list" aria-label="<?php echo esc_attr($lr->resolve('widget.legend.label', 'Calendar legend')); ?>">
    <li class="cb-legend__item">
        <span class="cb-legend__swatch cb-state-available" aria-hidden="true"></span>
        <?php echo esc_html($lr->resolve('widget.legend.available', 'Available')); ?>
    </li>
    <li class="cb-legend__item">
        <span class="cb-legend__swatch cb-state-restricted" aria-hidden="true"></span>
        <?php echo esc_html($lr->resolve('widget.legend.restricted', 'Restrictions apply')); ?>
    </li>
    <li class="cb-legend__item">
        <span class="cb-legend__swatch cb-state-no-arrival" aria-hidden="true"></span>
        <?php echo esc_html($lr->resolve('widget.legend.noArrival', 'No arrival')); ?>
    </li>
    <li class="cb-legend__item">
        <span class="cb-legend__swatch cb-state-unavailable" aria-hidden="true"></span>
        <?php echo esc_html($lr->resolve('widget.legend.unavailable', 'Not available')); ?>
    </li>
</ul>
