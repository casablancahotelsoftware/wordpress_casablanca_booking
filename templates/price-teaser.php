<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/** @var array<string, mixed> $context @var array<string, mixed> $attrs @var array{from_price: float, currency: string}|null $price @var string $roomName @var string|null $jsonLd */
$labels = $context['labels'];
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
$lr = $context['lr'];
include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-open.php';
?>
<div class="cb-price-teaser">
    <?php if (! empty($labels['heading'])) : ?>
        <h2 class="cb-price-teaser__heading"><?php echo esc_html((string) $labels['heading']); ?></h2>
    <?php endif; ?>
    <?php if ($roomName !== '') : ?>
        <p class="cb-price-teaser__room"><?php echo esc_html($roomName); ?></p>
    <?php endif; ?>
    <?php if ($price !== null) : ?>
        <p class="cb-price-teaser__price" aria-live="polite">
            <span class="cb-price-teaser__label"><?php echo esc_html($lr->resolve('priceteaser.from', 'from')); ?></span>
            <span class="cb-price-teaser__value"><?php echo esc_html(number_format($price['from_price'], 2) . ' ' . $price['currency']); ?></span>
            <span class="cb-price-teaser__window"><?php echo esc_html($lr->resolve('priceteaser.window', 'in the next %s days', '', [(string) (int) $attrs['windowDays']])); ?></span>
        </p>
    <?php else : ?>
        <p class="cb-price-teaser__empty"><?php echo esc_html($lr->resolve('priceteaser.empty', 'No prices available in the selected window.')); ?></p>
    <?php endif; ?>
</div>
<?php if ($jsonLd !== null) : ?>
<script type="application/ld+json"><?php echo esc_html($jsonLd); ?></script>
<?php endif; ?>
<?php include CASABLANCA_BOOKING_PATH . 'templates/partials/widget-shell-close.php'; ?>
