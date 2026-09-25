<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals from include scope, not plugin globals.
/**
 * @var bool $showDescription
 * @var array{mode: string, html: string, preview: string, needsExpand: bool} $descriptionOverview
 */
/** @var \Casablanca\Booking\Frontend\LabelResolver $lr */
if (! isset($lr) || ! $lr instanceof \Casablanca\Booking\Frontend\LabelResolver) {
    if (isset($context) && ($context['lr'] ?? null) instanceof \Casablanca\Booking\Frontend\LabelResolver) {
        $lr = $context['lr'];
    } elseif (isset($calContext) && ($calContext['lr'] ?? null) instanceof \Casablanca\Booking\Frontend\LabelResolver) {
        $lr = $calContext['lr'];
    } else {
        $lr = new \Casablanca\Booking\Frontend\LabelResolver();
    }
}
if (! $showDescription || ($descriptionOverview['html'] ?? '') === '') {
    return;
}
?>
<div class="cb-overview-description">
    <?php if (($descriptionOverview['mode'] ?? '') === 'full' && ! empty($descriptionOverview['needsExpand'])) : ?>
        <p class="cb-overview-description__preview"><?php echo esc_html((string) $descriptionOverview['preview']); ?></p>
        <div class="cb-overview-description__full" hidden="hidden">
            <?php echo wp_kses_post((string) $descriptionOverview['html']); ?>
        </div>
        <button type="button"
                class="cb-overview-description__toggle cb-button cb-button--secondary"
                data-cb-description-toggle
                aria-expanded="false">
            <?php echo esc_html($lr->resolve('common.more', 'More')); ?>
        </button>
    <?php else : ?>
        <div class="cb-overview-description__text">
            <?php echo wp_kses_post((string) $descriptionOverview['html']); ?>
        </div>
    <?php endif; ?>
</div>
